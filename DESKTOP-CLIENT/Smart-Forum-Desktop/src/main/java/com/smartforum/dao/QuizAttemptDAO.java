package com.smartforum.dao;

import com.smartforum.database.DatabaseConnection;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizAttempt;
import com.smartforum.util.QuizSchedule;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

public class QuizAttemptDAO {

    public QuizAttempt startOrResume(Quiz quiz, int studentId, String studentName, int categoryId)
            throws SQLException {
        try (Connection conn = DatabaseConnection.getConnection()) {
            if (conn == null) throw new SQLException("Database connection unavailable");
            conn.setAutoCommit(false);
            try {
                if (hasResult(conn, quiz.getId(), studentId, studentName)) {
                    throw new SQLException("This quiz has already been completed.");
                }
                QuizAttempt existing = find(conn, quiz.getId(), studentId);
                if (existing != null) {
                    if (!"IN_PROGRESS".equals(existing.getStatus())) {
                        throw new SQLException("This quiz has already been completed.");
                    }
                    LocalDateTime recalculated = QuizSchedule.deadline(quiz, existing.getStartedAt());
                    try (PreparedStatement ps = conn.prepareStatement(
                            "UPDATE quiz_attempts SET deadline_at=? WHERE id=?")) {
                        ps.setString(1, recalculated.toString());
                        ps.setInt(2, existing.getId());
                        ps.executeUpdate();
                    }
                    existing.setDeadlineAt(recalculated);
                    conn.commit();
                    return existing;
                }
                LocalDateTime started = LocalDateTime.now();
                LocalDateTime deadline = QuizSchedule.deadline(quiz, started);
                String sql = "INSERT INTO quiz_attempts(quiz_id,student_id,student_name,category_id," +
                    "started_at,deadline_at,status,answers) VALUES(?,?,?,?,?,?, 'IN_PROGRESS','')";
                try (PreparedStatement ps = conn.prepareStatement(sql)) {
                    ps.setInt(1, quiz.getId());
                    ps.setInt(2, studentId);
                    ps.setString(3, studentName);
                    ps.setInt(4, categoryId);
                    ps.setString(5, started.toString());
                    ps.setString(6, deadline.toString());
                    ps.executeUpdate();
                }
                QuizAttempt created = find(conn, quiz.getId(), studentId);
                conn.commit();
                return created;
            } catch (SQLException | RuntimeException e) {
                conn.rollback();
                throw e;
            }
        }
    }

    public boolean saveAnswers(int attemptId, int studentId, String answers) throws SQLException {
        String sql = "UPDATE quiz_attempts SET answers=? WHERE id=? AND student_id=? " +
            "AND status='IN_PROGRESS' AND datetime(deadline_at)>datetime(?) " +
            "AND NOT EXISTS (SELECT 1 FROM quizzes q WHERE q.id=quiz_attempts.quiz_id " +
            "AND q.end_date IS NOT NULL AND datetime(CASE WHEN length(trim(q.end_date))=10 " +
            "THEN trim(q.end_date)||' 23:59:59' ELSE q.end_date END)<=datetime(?))";
        String now = LocalDateTime.now().toString();
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = require(conn).prepareStatement(sql)) {
            ps.setString(1, answers == null ? "" : answers);
            ps.setInt(2, attemptId);
            ps.setInt(3, studentId);
            ps.setString(4, now);
            ps.setString(5, now);
            return ps.executeUpdate() == 1;
        }
    }

    public QuizAttempt getForStudent(int attemptId, int studentId, String studentName) throws SQLException {
        String sql = "SELECT id,quiz_id,student_id,student_name,category_id,started_at,deadline_at,status,answers " +
            "FROM quiz_attempts WHERE id=? AND (student_id=? OR (student_id IS NULL AND student_name=?))";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = require(conn).prepareStatement(sql)) {
            ps.setInt(1, attemptId);
            ps.setInt(2, studentId);
            ps.setString(3, studentName);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? mapAttempt(rs) : null;
            }
        }
    }

    public List<QuizAttempt> getExpiredInProgressAttempts(
            int studentId, String studentName, int categoryId, LocalDateTime now) throws SQLException {
        List<QuizAttempt> attempts = new ArrayList<>();
        String sql = "SELECT a.id,a.quiz_id,a.student_id,a.student_name,a.category_id,a.started_at," +
            "a.deadline_at,a.status,a.answers FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id " +
            "WHERE a.status='IN_PROGRESS' AND a.category_id=? AND q.category_id=? " +
            "AND (a.student_id=? OR (a.student_id IS NULL AND a.student_name=?)) " +
            "AND (datetime(a.deadline_at)<=datetime(?) OR " +
            "(q.end_date IS NOT NULL AND datetime(CASE WHEN length(trim(q.end_date))=10 " +
            "THEN trim(q.end_date)||' 23:59:59' ELSE q.end_date END)<=datetime(?))) ORDER BY a.id";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = require(conn).prepareStatement(sql)) {
            ps.setInt(1, categoryId);
            ps.setInt(2, categoryId);
            ps.setInt(3, studentId);
            ps.setString(4, studentName);
            ps.setString(5, now.toString());
            ps.setString(6, now.toString());
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) attempts.add(mapAttempt(rs));
            }
        }
        return attempts;
    }

    public boolean hasCompletedResult(int quizId, int studentId, String studentName) {
        try (Connection conn = DatabaseConnection.getConnection()) {
            return conn != null && hasResult(conn, quizId, studentId, studentName);
        } catch (SQLException e) {
            return true;
        }
    }

    private boolean hasResult(Connection conn, int quizId, int studentId, String studentName) throws SQLException {
        try (PreparedStatement ps = conn.prepareStatement(
                "SELECT 1 FROM quiz_results WHERE quiz_id=? AND " +
                "(student_id=? OR (student_id IS NULL AND student_name=?)) LIMIT 1")) {
            ps.setInt(1, quizId);
            ps.setInt(2, studentId);
            ps.setString(3, studentName);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next();
            }
        }
    }

    private QuizAttempt find(Connection conn, int quizId, int studentId) throws SQLException {
        try (PreparedStatement ps = conn.prepareStatement(
                "SELECT id,quiz_id,student_id,student_name,category_id,started_at,deadline_at,status,answers " +
                "FROM quiz_attempts WHERE quiz_id=? AND student_id=?")) {
            ps.setInt(1, quizId);
            ps.setInt(2, studentId);
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) return null;
                return mapAttempt(rs);
            }
        }
    }

    private QuizAttempt mapAttempt(ResultSet rs) throws SQLException {
        QuizAttempt attempt = new QuizAttempt();
        attempt.setId(rs.getInt("id"));
        attempt.setQuizId(rs.getInt("quiz_id"));
        attempt.setStudentId(rs.getInt("student_id"));
        attempt.setStudentName(rs.getString("student_name"));
        attempt.setCategoryId(rs.getInt("category_id"));
        attempt.setStartedAt(LocalDateTime.parse(rs.getString("started_at")));
        attempt.setDeadlineAt(LocalDateTime.parse(rs.getString("deadline_at")));
        attempt.setStatus(rs.getString("status"));
        attempt.setAnswers(rs.getString("answers"));
        return attempt;
    }

    private Connection require(Connection conn) throws SQLException {
        if (conn == null) throw new SQLException("Database connection unavailable");
        return conn;
    }
}
