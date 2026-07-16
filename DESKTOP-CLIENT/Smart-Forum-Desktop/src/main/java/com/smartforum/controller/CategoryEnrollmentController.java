package com.smartforum.controller;

import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuizCategoryDAO;
import com.smartforum.model.QuizCategory;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;

public class CategoryEnrollmentController {

    @FXML private ComboBox<QuizCategory> cmbCategory;
    @FXML private TextField txtStudentName, txtCheckName;
    @FXML private ListView<String> lstStudents;
    @FXML private Label lblFoundCategory;

    private final CategoryStudentDAO dao = new CategoryStudentDAO();

    @FXML
    public void initialize() {
        cmbCategory.setItems(FXCollections.observableArrayList(new QuizCategoryDAO().getAllCategories()));
    }

    @FXML
    private void enroll() {
        QuizCategory cat = cmbCategory.getValue();
        String name = txtStudentName.getText().trim();
        if (cat == null || name.isEmpty()) { alert("Validation", "Select a category and enter a student name."); return; }
        if (dao.enroll(cat.getId(), name)) {
            loadEnrolled();
            txtStudentName.clear();
        } else {
            alert("Info", "Student may already be enrolled in this category.");
        }
    }

    @FXML
    private void unenroll() {
        QuizCategory cat = cmbCategory.getValue();
        String name = txtStudentName.getText().trim();
        if (cat == null || name.isEmpty()) { alert("Validation", "Select a category and enter a student name."); return; }
        dao.unenroll(cat.getId(), name);
        loadEnrolled();
        txtStudentName.clear();
    }

    @FXML
    private void loadEnrolled() {
        QuizCategory cat = cmbCategory.getValue();
        if (cat == null) return;
        lstStudents.setItems(FXCollections.observableArrayList(dao.getStudentsInCategory(cat.getId())));
    }

    @FXML
    private void findCategory() {
        String name = txtCheckName.getText().trim();
        if (name.isEmpty()) { alert("Validation", "Enter a student name."); return; }
        int catId = dao.getCategoryForStudent(name);
        if (catId == -1) {
            lblFoundCategory.setText("\"" + name + "\" is not enrolled in any category.");
        } else {
            new QuizCategoryDAO().getAllCategories().stream()
                .filter(c -> c.getId() == catId).findFirst()
                .ifPresentOrElse(
                    c -> lblFoundCategory.setText("Category: " + c.getCategoryName()),
                    () -> lblFoundCategory.setText("Category ID " + catId + " (name not found)")
                );
        }
    }

    private void alert(String title, String msg) {
        Alert a = new Alert(Alert.AlertType.INFORMATION);
        a.setTitle(title); a.setHeaderText(null); a.setContentText(msg);
        a.showAndWait();
    }
}
