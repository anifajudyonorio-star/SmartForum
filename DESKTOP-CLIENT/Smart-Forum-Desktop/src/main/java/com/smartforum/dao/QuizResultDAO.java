package com.smartforum.dao;

import com.smartforum.database.DatabaseConnection;
import com.smartforum.model.QuizPerformanceRow;
import com.smartforum.model.QuizResult;
import com.smartforum.util.QuizSchedule;

import java.sql.*;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

public class QuizResultDAO {

    public List<QuizResult> getAllResults() {
        List<QuizResult> results = new ArrayList<>();
        String sql =
            "SELECT r.id AS result_id, r.quiz_id AS result_quiz_id, q.title AS quiz_title, " +
            "r.student_id AS result_student_id, r.student_name AS result_student_name, " +
            "r.category_id AS result_category_id, r.score AS result_score, " +
            "r.total_marks AS authored_total_marks, r.participation_marks AS participation_marks, " +
            "r.total_score AS final_score, r.final_possible_marks AS final_possible_marks, " +
            "r.submitted_at AS result_submitted_at " +
            "FROM quiz_results r JOIN quizzes q ON r.quiz_id = q.id " +
            "ORDER BY r.submitted_at DESC";
        try (Connection conn = DatabaseConnection.getConnection();
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                QuizResult result = new QuizResult();
                result.setId(rs.getInt("result_id"));
                result.setQuizId(rs.getInt("result_quiz_id"));
                result.setQuizTitle(rs.getString("quiz_title"));
                int studentId = rs.getInt("result_student_id");
                result.setStudentId(rs.wasNull() ? null : studentId);
                result.setStudentName(rs.getString("result_student_name"));
                result.setCategoryId(rs.getInt("result_category_id"));
                result.setScore(rs.getInt("result_score"));
                result.setTotalMarks(rs.getInt("authored_total_marks"));
                result.setParticipationMarks(rs.getInt("participation_marks"));
                result.setTotalScore(rs.getInt("final_score"));
                result.setFinalPossibleMarks(rs.getInt("final_possible_marks"));
                result.setSubmittedAt(rs.getString("result_submitted_at"));
                results.add(result);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return results;
    }

    public List<QuizResult> getStudentProgress(int studentId, String studentName) throws SQLException {
        List<QuizResult> results = new ArrayList<>();
        String sql =
            "SELECT r.id AS result_id, r.quiz_id AS result_quiz_id, q.title AS quiz_title, " +
            "r.student_id AS result_student_id, r.student_name AS result_student_name, " +
            "r.category_id AS result_category_id, r.score AS result_score, " +
            "r.total_marks AS authored_total_marks, r.participation_marks AS participation_marks, " +
            "r.total_score AS final_score, r.final_possible_marks AS final_possible_marks, " +
            "r.submitted_at AS result_submitted_at " +
            "FROM quiz_results r JOIN quizzes q ON r.quiz_id = q.id " +
            "WHERE r.student_id = ? OR (r.student_id IS NULL AND r.student_name = ?) " +
            "ORDER BY CASE WHEN datetime(r.submitted_at) IS NULL THEN 1 ELSE 0 END, " +
            "datetime(r.submitted_at), r.id";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = require(conn).prepareStatement(sql)) {
            ps.setInt(1, studentId);
            ps.setString(2, studentName);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    QuizResult result = new QuizResult();
                    result.setId(rs.getInt("result_id"));
                    result.setQuizId(rs.getInt("result_quiz_id"));
                    result.setQuizTitle(rs.getString("quiz_title"));
                    int storedStudentId = rs.getInt("result_student_id");
                    result.setStudentId(rs.wasNull() ? null : storedStudentId);
                    result.setStudentName(rs.getString("result_student_name"));
                    result.setCategoryId(rs.getInt("result_category_id"));
                    result.setScore(rs.getInt("result_score"));
                    result.setTotalMarks(rs.getInt("authored_total_marks"));
                    result.setParticipationMarks(rs.getInt("participation_marks"));
                    result.setTotalScore(rs.getInt("final_score"));
                    result.setFinalPossibleMarks(rs.getInt("final_possible_marks"));
                    result.setSubmittedAt(rs.getString("result_submitted_at"));
                    results.add(result);
                }
            }
        }
        return results;
    }

    public SubmissionReceipt submitResult(QuizResult result, int attemptId) throws SQLException {
        String check = "SELECT a.status,a.deadline_at,q.end_date FROM quiz_attempts a " +
            "JOIN quizzes q ON q.id=a.quiz_id WHERE a.id=? AND a.quiz_id=? AND a.student_id=? " +
            "AND a.category_id=? AND q.category_id=?";
        String insert = "INSERT INTO quiz_results(quiz_id,student_id,student_name,category_id,score," +
            "total_marks,participation_marks,total_score,final_possible_marks,submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?)";
        try (Connection conn = DatabaseConnection.getConnection()) {
            if (conn == null) throw new SQLException("Database connection unavailable");
            conn.setAutoCommit(false);
            try {
                QuizResult existing = findStudentResult(
                    conn, result.getQuizId(), result.getStudentId(), result.getStudentName());
                if (existing != null) {
                    conn.commit();
                    return new SubmissionReceipt(existing, true, false);
                }

                String status;
                LocalDateTime deadline;
                try (PreparedStatement ps = conn.prepareStatement(check)) {
                    ps.setInt(1, attemptId);
                    ps.setInt(2, result.getQuizId());
                    ps.setInt(3, result.getStudentId());
                    ps.setInt(4, result.getCategoryId());
                    ps.setInt(5, result.getCategoryId());
                    try (ResultSet rs = ps.executeQuery()) {
                        if (!rs.next()) {
                            throw new SQLException("This attempt does not belong to the current student enrollment.");
                        }
                        status = rs.getString("status");
                        if (!"IN_PROGRESS".equals(status)) {
                            throw new SQLException("This attempt is no longer available for submission.");
                        }
                        deadline = LocalDateTime.parse(rs.getString("deadline_at"));
                        LocalDateTime globalEnd = QuizSchedule.parseEnd(rs.getString("end_date"));
                        if (globalEnd != null && globalEnd.isBefore(deadline)) deadline = globalEnd;
                    }
                }
                LocalDateTime completedAt = LocalDateTime.now();
                boolean timedOut = !completedAt.isBefore(deadline);
                result.setSubmittedAt(completedAt.toString());
                try (PreparedStatement ps = conn.prepareStatement(insert)) {
                    ps.setInt(1, result.getQuizId());
                    ps.setInt(2, result.getStudentId());
                    ps.setString(3, result.getStudentName());
                    ps.setInt(4, result.getCategoryId());
                    ps.setInt(5, result.getScore());
                    ps.setInt(6, result.getTotalMarks());
                    ps.setInt(7, result.getParticipationMarks());
                    ps.setInt(8, result.getTotalScore());
                    ps.setInt(9, result.getFinalPossibleMarks());
                    ps.setString(10, result.getSubmittedAt());
                    ps.executeUpdate();
                }
                try (PreparedStatement ps = conn.prepareStatement(
                        "UPDATE quiz_attempts SET status=?,completed_at=? " +
                        "WHERE id=? AND status='IN_PROGRESS'")) {
                    ps.setString(1, timedOut ? "TIMED_OUT" : "COMPLETED");
                    ps.setString(2, result.getSubmittedAt());
                    ps.setInt(3, attemptId);
                    if (ps.executeUpdate() != 1) throw new SQLException("Attempt could not be completed.");
                }
                conn.commit();
                return new SubmissionReceipt(result, false, timedOut);
            } catch (SQLException e) {
                conn.rollback();
                QuizResult concurrentlySaved = findStudentResult(
                    conn, result.getQuizId(), result.getStudentId(), result.getStudentName());
                if (concurrentlySaved != null) {
                    return new SubmissionReceipt(concurrentlySaved, true, false);
                }
                throw e;
            } catch (RuntimeException e) {
                conn.rollback();
                throw e;
            }
        }
    }

    public QuizResult getStudentResult(int quizId, int studentId, String studentName) throws SQLException {
        try (Connection conn = DatabaseConnection.getConnection()) {
            if (conn == null) throw new SQLException("Database connection unavailable");
            return findStudentResult(conn, quizId, studentId, studentName);
        }
    }

    public List<QuizPerformanceRow> getCategoryPerformanceReport(int quizId, int categoryId)
            throws SQLException {
        List<QuizPerformanceRow> rows = new ArrayList<>();
        String sql = "SELECT cs.student_name, r.total_score, " +
            "COALESCE(r.final_possible_marks, r.total_marks + MAX(r.participation_marks,0), " +
            "(SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id=q.id) + MAX(q.participation_marks,0)) " +
            "AS possible_marks, r.id AS result_id FROM quizzes q " +
            "JOIN category_students cs ON cs.category_id=q.category_id " +
            "LEFT JOIN quiz_results r ON r.id=(" +
            "SELECT rr.id FROM quiz_results rr WHERE rr.quiz_id=q.id AND " +
            "((cs.student_id IS NOT NULL AND rr.student_id=cs.student_id) OR " +
            "(rr.student_id IS NULL AND rr.student_name=cs.student_name)) " +
            "ORDER BY CASE WHEN rr.student_id IS NOT NULL THEN 0 ELSE 1 END, rr.id LIMIT 1) " +
            "WHERE q.id=? AND q.category_id=? ORDER BY lower(cs.student_name), cs.id";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = require(conn).prepareStatement(sql)) {
            ps.setInt(1, quizId);
            ps.setInt(2, categoryId);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    boolean submitted = rs.getObject("result_id") != null;
                    rows.add(new QuizPerformanceRow(
                        rs.getString("student_name"),
                        submitted ? rs.getInt("total_score") : 0,
                        rs.getInt("possible_marks"),
                        submitted));
                }
            }
        }
        return rows;
    }

    private QuizResult findStudentResult(Connection conn, int quizId, int studentId, String studentName)
            throws SQLException {
        String sql = "SELECT r.*, q.title AS quiz_title FROM quiz_results r JOIN quizzes q ON q.id=r.quiz_id " +
            "WHERE r.quiz_id=? AND (r.student_id=? OR (r.student_id IS NULL AND r.student_name=?)) " +
            "ORDER BY CASE WHEN r.student_id=? THEN 0 ELSE 1 END, r.id LIMIT 1";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, quizId);
            ps.setInt(2, studentId);
            ps.setString(3, studentName);
            ps.setInt(4, studentId);
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) return null;
                QuizResult result = new QuizResult();
                result.setId(rs.getInt("id"));
                result.setQuizId(rs.getInt("quiz_id"));
                result.setQuizTitle(rs.getString("quiz_title"));
                int storedStudentId = rs.getInt("student_id");
                result.setStudentId(rs.wasNull() ? null : storedStudentId);
                result.setStudentName(rs.getString("student_name"));
                result.setCategoryId(rs.getInt("category_id"));
                result.setScore(rs.getInt("score"));
                result.setTotalMarks(rs.getInt("total_marks"));
                result.setParticipationMarks(rs.getInt("participation_marks"));
                result.setTotalScore(rs.getInt("total_score"));
                result.setFinalPossibleMarks(rs.getInt("final_possible_marks"));
                result.setSubmittedAt(rs.getString("submitted_at"));
                return result;
            }
        }
    }

    private Connection require(Connection conn) throws SQLException {
        if (conn == null) throw new SQLException("Database connection unavailable");
        return conn;
    }

    public static final class SubmissionReceipt {
        private final QuizResult result;
        private final boolean alreadySubmitted;
        private final boolean timedOut;

        public SubmissionReceipt(QuizResult result, boolean alreadySubmitted, boolean timedOut) {
            this.result = result;
            this.alreadySubmitted = alreadySubmitted;
            this.timedOut = timedOut;
        }

        public QuizResult getResult() { return result; }
        public boolean isAlreadySubmitted() { return alreadySubmitted; }
        public boolean isTimedOut() { return timedOut; }
    }
}
