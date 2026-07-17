package com.smartforum.dao;

import com.smartforum.database.DatabaseConnection;
import com.smartforum.model.Question;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class QuestionDAO {

    public boolean saveQuestion(Question question) {
        String sql = "INSERT INTO questions(quiz_id,question,option_a,option_b,option_c,option_d,correct_answer,marks) VALUES(?,?,?,?,?,?,?,?)";
        try (Connection conn = DatabaseConnection.getConnection()) {
            if (conn == null) return false;
            conn.setAutoCommit(false);
            try (PreparedStatement ps = conn.prepareStatement(sql)) {
                bindQuestion(ps, question);
                boolean saved = ps.executeUpdate() > 0;
                synchronizeQuizMarks(conn, question.getQuizId());
                conn.commit();
                return saved;
            } catch (SQLException e) {
                conn.rollback();
                throw e;
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }

    public List<Question> getAllQuestions() {
        List<Question> questions = new ArrayList<>();
        String sql = "SELECT q.*, qu.title FROM questions q JOIN quizzes qu ON q.quiz_id = qu.id";
        try (Connection conn = DatabaseConnection.getConnection();
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                Question question = new Question();
                question.setId(rs.getInt("id"));
                question.setQuizId(rs.getInt("quiz_id"));
                question.setQuizTitle(rs.getString("title"));
                question.setQuestion(rs.getString("question"));
                question.setOptionA(rs.getString("option_a"));
                question.setOptionB(rs.getString("option_b"));
                question.setOptionC(rs.getString("option_c"));
                question.setOptionD(rs.getString("option_d"));
                question.setCorrectAnswer(rs.getString("correct_answer"));
                question.setMarks(rs.getInt("marks"));
                questions.add(question);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return questions;
    }

    public List<Question> getQuestionsByQuizId(int quizId) {
        List<Question> questions = new ArrayList<>();
        String sql = "SELECT q.*, qu.title FROM questions q JOIN quizzes qu ON q.quiz_id = qu.id WHERE q.quiz_id = ?";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, quizId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                Question question = new Question();
                question.setId(rs.getInt("id"));
                question.setQuizId(rs.getInt("quiz_id"));
                question.setQuizTitle(rs.getString("title"));
                question.setQuestion(rs.getString("question"));
                question.setOptionA(rs.getString("option_a"));
                question.setOptionB(rs.getString("option_b"));
                question.setOptionC(rs.getString("option_c"));
                question.setOptionD(rs.getString("option_d"));
                question.setCorrectAnswer(rs.getString("correct_answer"));
                question.setMarks(rs.getInt("marks"));
                questions.add(question);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return questions;
    }

    public boolean updateQuestion(Question question) {
        String sql = "UPDATE questions SET quiz_id=?, question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, marks=? WHERE id=?";
        try (Connection conn = DatabaseConnection.getConnection()) {
            if (conn == null) return false;
            conn.setAutoCommit(false);
            int oldQuizId = question.getQuizId();
            try (PreparedStatement lookup = conn.prepareStatement("SELECT quiz_id FROM questions WHERE id=?")) {
                lookup.setInt(1, question.getId());
                try (ResultSet rs = lookup.executeQuery()) {
                    if (rs.next()) oldQuizId = rs.getInt(1);
                }
            }
            try (PreparedStatement ps = conn.prepareStatement(sql)) {
                bindQuestion(ps, question);
                ps.setInt(9, question.getId());
                boolean updated = ps.executeUpdate() > 0;
                synchronizeQuizMarks(conn, oldQuizId);
                if (oldQuizId != question.getQuizId()) synchronizeQuizMarks(conn, question.getQuizId());
                conn.commit();
                return updated;
            } catch (SQLException e) {
                conn.rollback();
                throw e;
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }

    public boolean deleteQuestion(int id) {
        String sql = "DELETE FROM questions WHERE id=?";
        try (Connection conn = DatabaseConnection.getConnection()) {
            if (conn == null) return false;
            conn.setAutoCommit(false);
            int quizId = -1;
            try (PreparedStatement lookup = conn.prepareStatement("SELECT quiz_id FROM questions WHERE id=?")) {
                lookup.setInt(1, id);
                try (ResultSet rs = lookup.executeQuery()) {
                    if (rs.next()) quizId = rs.getInt(1);
                }
            }
            try (PreparedStatement ps = conn.prepareStatement(sql)) {
                ps.setInt(1, id);
                boolean deleted = ps.executeUpdate() > 0;
                if (quizId >= 0) synchronizeQuizMarks(conn, quizId);
                conn.commit();
                return deleted;
            } catch (SQLException e) {
                conn.rollback();
                throw e;
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }

    private void bindQuestion(PreparedStatement ps, Question question) throws SQLException {
        ps.setInt(1, question.getQuizId());
        ps.setString(2, question.getQuestion());
        ps.setString(3, question.getOptionA());
        ps.setString(4, question.getOptionB());
        ps.setString(5, question.getOptionC());
        ps.setString(6, question.getOptionD());
        ps.setString(7, question.getCorrectAnswer());
        ps.setInt(8, question.getMarks());
    }

    private void synchronizeQuizMarks(Connection conn, int quizId) throws SQLException {
        try (PreparedStatement ps = conn.prepareStatement(
                "UPDATE quizzes SET total_marks=(SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id=?) WHERE id=?")) {
            ps.setInt(1, quizId);
            ps.setInt(2, quizId);
            ps.executeUpdate();
        }
    }
}
