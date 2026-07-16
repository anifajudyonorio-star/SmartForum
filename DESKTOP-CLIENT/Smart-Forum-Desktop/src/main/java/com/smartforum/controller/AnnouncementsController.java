package com.smartforum.controller;

import com.smartforum.dao.AnnouncementDAO;
import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuizCategoryDAO;
import com.smartforum.model.Announcement;
import com.smartforum.model.QuizCategory;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.List;

public class AnnouncementsController {

    @FXML private ComboBox<QuizCategory> cmbCategory;
    @FXML private TextField txtTitle, txtPostedBy, txtStudentFilter;
    @FXML private TextArea txtMessage;
    @FXML private TableView<Announcement> tblAnnouncements;
    @FXML private TableColumn<Announcement, String> colCategory, colTitle, colMessage, colBy, colAt;

    private final AnnouncementDAO announcementDAO = new AnnouncementDAO();
    private final CategoryStudentDAO categoryStudentDAO = new CategoryStudentDAO();

    @FXML
    public void initialize() {
        colCategory.setCellValueFactory(new PropertyValueFactory<>("categoryName"));
        colTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colMessage.setCellValueFactory(new PropertyValueFactory<>("message"));
        colBy.setCellValueFactory(new PropertyValueFactory<>("createdBy"));
        colAt.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        cmbCategory.setItems(FXCollections.observableArrayList(new QuizCategoryDAO().getAllCategories()));
        loadAll();
    }

    @FXML
    private void postAnnouncement() {
        QuizCategory cat = cmbCategory.getValue();
        String title = txtTitle.getText().trim();
        String msg = txtMessage.getText().trim();
        String by = txtPostedBy.getText().trim();

        if (cat == null || title.isEmpty() || msg.isEmpty()) {
            alert("Validation", "Category, title, and message are required.");
            return;
        }

        Announcement a = new Announcement();
        a.setCategoryId(cat.getId());
        a.setTitle(title);
        a.setMessage(msg);
        a.setCreatedBy(by.isEmpty() ? "Lecturer" : by);
        a.setCreatedAt(LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm")));

        if (announcementDAO.save(a)) {
            txtTitle.clear();
            txtMessage.clear();
            loadAll();
            alert("Success", "Announcement posted to \"" + cat.getCategoryName() + "\".");
        }
    }

    @FXML
    private void loadStudentAnnouncements() {
        String name = txtStudentFilter.getText().trim();
        if (name.isEmpty()) { alert("Validation", "Enter a student name."); return; }

        int catId = categoryStudentDAO.getCategoryForStudent(name);
        if (catId == -1) {
            alert("Not Enrolled", "\"" + name + "\" is not enrolled in any category.");
            tblAnnouncements.setItems(FXCollections.observableArrayList());
            return;
        }

        List<Announcement> list = announcementDAO.getByCategory(catId);
        // Populate categoryName for display (getByCategory doesn't join)
        QuizCategoryDAO catDAO = new QuizCategoryDAO();
        catDAO.getAllCategories().stream()
              .filter(c -> c.getId() == catId).findFirst()
              .ifPresent(c -> list.forEach(a -> a.setCategoryName(c.getCategoryName())));

        tblAnnouncements.setItems(FXCollections.observableArrayList(list));
    }

    @FXML
    private void loadAll() {
        tblAnnouncements.setItems(FXCollections.observableArrayList(announcementDAO.getAll()));
    }

    @FXML
    private void deleteSelected() {
        Announcement selected = tblAnnouncements.getSelectionModel().getSelectedItem();
        if (selected == null) { alert("Selection", "Select an announcement to delete."); return; }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION, "Delete this announcement?", ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText(null);
        if (confirm.showAndWait().orElse(ButtonType.NO) == ButtonType.YES) {
            announcementDAO.delete(selected.getId());
            loadAll();
        }
    }

    private void alert(String title, String msg) {
        Alert a = new Alert(Alert.AlertType.INFORMATION);
        a.setTitle(title); a.setHeaderText(null); a.setContentText(msg);
        a.showAndWait();
    }
}
