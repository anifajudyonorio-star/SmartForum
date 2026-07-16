package com.smartforum.dao;

import com.smartforum.database.DatabaseConnection;
import com.smartforum.model.QuizCategory;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class QuizCategoryDAO {

    public boolean saveCategory(QuizCategory category) {
        String sql = "INSERT INTO quiz_categories(category_name, description, created_by) VALUES(?,?,?)";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, category.getCategoryName());
            ps.setString(2, category.getDescription());
            ps.setString(3, category.getCreatedBy());
            return ps.executeUpdate() > 0;
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }

    public List<QuizCategory> getAllCategories() {
        List<QuizCategory> list = new ArrayList<>();
        String sql = "SELECT * FROM quiz_categories ORDER BY id DESC";
        try (Connection conn = DatabaseConnection.getConnection();
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                QuizCategory category = new QuizCategory();
                category.setId(rs.getInt("id"));
                category.setCategoryName(rs.getString("category_name"));
                category.setDescription(rs.getString("description"));
                category.setCreatedBy(rs.getString("created_by"));
                list.add(category);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return list;
    }

    public boolean updateCategory(QuizCategory category) {
        String sql = "UPDATE quiz_categories SET category_name=?, description=?, created_by=? WHERE id=?";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, category.getCategoryName());
            ps.setString(2, category.getDescription());
            ps.setString(3, category.getCreatedBy());
            ps.setInt(4, category.getId());
            return ps.executeUpdate() > 0;
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return false;
    }

    public boolean deleteCategory(int id) {
        String sql = "DELETE FROM quiz_categories WHERE id=?";
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
