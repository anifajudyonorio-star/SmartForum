package com.smartforum.dao;

import com.smartforum.database.DatabaseConnection;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class CategoryStudentDAO {

    public boolean enroll(int categoryId, String studentName) {
        String sql = "INSERT OR IGNORE INTO category_students(category_id, student_name) VALUES(?,?)";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, categoryId);
            ps.setString(2, studentName);
            return ps.executeUpdate() > 0;
        } catch (SQLException e) { e.printStackTrace(); }
        return false;
    }

    public boolean unenroll(int categoryId, String studentName) {
        String sql = "DELETE FROM category_students WHERE category_id=? AND student_name=?";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, categoryId);
            ps.setString(2, studentName);
            return ps.executeUpdate() > 0;
        } catch (SQLException e) { e.printStackTrace(); }
        return false;
    }

    /** Returns category id the student is enrolled in, or -1 if none. */
    public int getCategoryForStudent(String studentName) {
        String sql = "SELECT category_id FROM category_students WHERE student_name=? LIMIT 1";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, studentName);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return rs.getInt("category_id");
        } catch (SQLException e) { e.printStackTrace(); }
        return -1;
    }

    public List<String> getStudentsInCategory(int categoryId) {
        List<String> list = new ArrayList<>();
        String sql = "SELECT student_name FROM category_students WHERE category_id=?";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, categoryId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(rs.getString("student_name"));
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }
}
