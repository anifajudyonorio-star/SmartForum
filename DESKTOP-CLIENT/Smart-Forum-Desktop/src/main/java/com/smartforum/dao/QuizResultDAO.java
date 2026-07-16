package com.smartforum.dao;

import com.smartforum.database.DatabaseConnection;
import com.smartforum.model.QuizResult;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class QuizResultDAO {

    public List<QuizResult> getAllResults() {
        List<QuizResult> results = new ArrayList<>();
        String sql =
            "SELECT r.*, q.title, q.total_marks, r.student_name, r.submitted_at " +
            "FROM quiz_results r JOIN quizzes q ON r.quiz_id = q.id " +
            "ORDER BY r.submitted_at DESC";
        try (Connection conn = DatabaseConnection.getConnection();
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                QuizResult result = new QuizResult();
                result.setId(rs.getInt("id"));
                result.setQuizId(rs.getInt("quiz_id"));
                result.setQuizTitle(rs.getString("title"));
                result.setStudentName(rs.getString("student_name"));
                result.setScore(rs.getInt("score"));
                result.setTotalMarks(rs.getInt("total_marks"));
                result.setParticipationMarks(rs.getInt("participation_marks"));
                result.setTotalScore(rs.getInt("total_score"));
                result.setSubmittedAt(rs.getString("submitted_at"));
                results.add(result);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return results;
    }

    public boolean saveResult(QuizResult result) {
        String sql = "INSERT INTO quiz_results(quiz_id, student_name, category_id, score, total_marks, participation_marks, total_score, submitted_at) VALUES(?,?,?,?,?,?,?,?)";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, result.getQuizId());
            ps.setString(2, result.getStudentName());
            ps.setInt(3, result.getCategoryId());
            ps.setInt(4, result.getScore());
            ps.setInt(5, result.getTotalMarks());
            ps.setInt(6, result.getParticipationMarks());
            ps.setInt(7, result.getTotalScore());
            ps.setString(8, result.getSubmittedAt());
            return ps.executeUpdate() > 0;
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }

    public boolean deleteResult(int id) {
        String sql = "DELETE FROM quiz_results WHERE id=?";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            return ps.executeUpdate() > 0;
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }
}
