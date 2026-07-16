package com.smartforum.dao;

import com.smartforum.database.DatabaseConnection;
import com.smartforum.model.Quiz;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class QuizDAO {

    public boolean saveQuiz(Quiz quiz) {
        String sql = "INSERT INTO quizzes(category_id,title,description,duration,total_marks,start_date,end_date) VALUES(?,?,?,?,?,?,?)";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, quiz.getCategoryId());
            ps.setString(2, quiz.getTitle());
            ps.setString(3, quiz.getDescription());
            ps.setInt(4, quiz.getDuration());
            ps.setInt(5, quiz.getTotalMarks());
            ps.setString(6, quiz.getStartDate());
            ps.setString(7, quiz.getEndDate());
            return ps.executeUpdate() > 0;
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }

    public List<Quiz> getAllQuizzes() {
        List<Quiz> quizzes = new ArrayList<>();
        String sql = "SELECT q.*, c.category_name FROM quizzes q JOIN quiz_categories c ON q.category_id = c.id";
        try (Connection conn = DatabaseConnection.getConnection();
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                Quiz quiz = new Quiz();
                quiz.setId(rs.getInt("id"));
                quiz.setCategoryId(rs.getInt("category_id"));
                quiz.setCategoryName(rs.getString("category_name"));
                quiz.setTitle(rs.getString("title"));
                quiz.setDescription(rs.getString("description"));
                quiz.setDuration(rs.getInt("duration"));
                quiz.setTotalMarks(rs.getInt("total_marks"));
                quiz.setStartDate(rs.getString("start_date"));
                quiz.setEndDate(rs.getString("end_date"));
                quizzes.add(quiz);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return quizzes;
    }

    public boolean updateQuiz(Quiz quiz) {
        String sql = "UPDATE quizzes SET category_id=?, title=?, description=?, duration=?, total_marks=?, start_date=?, end_date=? WHERE id=?";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, quiz.getCategoryId());
            ps.setString(2, quiz.getTitle());
            ps.setString(3, quiz.getDescription());
            ps.setInt(4, quiz.getDuration());
            ps.setInt(5, quiz.getTotalMarks());
            ps.setString(6, quiz.getStartDate());
            ps.setString(7, quiz.getEndDate());
            ps.setInt(8, quiz.getId());
            return ps.executeUpdate() > 0;
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }

    public boolean deleteQuiz(int id) {
        String sql = "DELETE FROM quizzes WHERE id=?";
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
