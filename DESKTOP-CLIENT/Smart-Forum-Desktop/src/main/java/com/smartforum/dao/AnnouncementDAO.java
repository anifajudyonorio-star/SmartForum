package com.smartforum.dao;

import com.smartforum.database.DatabaseConnection;
import com.smartforum.model.Announcement;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class AnnouncementDAO {

    public boolean save(Announcement a) {
        String sql = "INSERT INTO announcements(category_id,title,message,created_by,created_at) VALUES(?,?,?,?,?)";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, a.getCategoryId());
            ps.setString(2, a.getTitle());
            ps.setString(3, a.getMessage());
            ps.setString(4, a.getCreatedBy());
            ps.setString(5, a.getCreatedAt());
            return ps.executeUpdate() > 0;
        } catch (SQLException e) { e.printStackTrace(); }
        return false;
    }

    public List<Announcement> getByCategory(int categoryId) {
        List<Announcement> list = new ArrayList<>();
        String sql = "SELECT * FROM announcements WHERE category_id=? ORDER BY id DESC";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, categoryId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(map(rs));
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }

    public List<Announcement> getAll() {
        List<Announcement> list = new ArrayList<>();
        String sql = "SELECT a.*, c.category_name FROM announcements a " +
                     "JOIN quiz_categories c ON a.category_id = c.id ORDER BY a.id DESC";
        try (Connection conn = DatabaseConnection.getConnection();
             Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                Announcement a = map(rs);
                a.setCategoryName(rs.getString("category_name"));
                list.add(a);
            }
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }

    public boolean delete(int id) {
        String sql = "DELETE FROM announcements WHERE id=?";
        try (Connection conn = DatabaseConnection.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            return ps.executeUpdate() > 0;
        } catch (SQLException e) { e.printStackTrace(); }
        return false;
    }

    private Announcement map(ResultSet rs) throws SQLException {
        Announcement a = new Announcement();
        a.setId(rs.getInt("id"));
        a.setCategoryId(rs.getInt("category_id"));
        a.setTitle(rs.getString("title"));
        a.setMessage(rs.getString("message"));
        a.setCreatedBy(rs.getString("created_by"));
        a.setCreatedAt(rs.getString("created_at"));
        return a;
    }
}
