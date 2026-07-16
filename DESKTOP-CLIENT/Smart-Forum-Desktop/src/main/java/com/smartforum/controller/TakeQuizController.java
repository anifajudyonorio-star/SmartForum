package com.smartforum.controller;

import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuestionDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;

import javafx.beans.property.SimpleIntegerProperty;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.stage.StageStyle;

import java.util.List;

public class TakeQuizController {

    @FXML private TextField txtStudentName;
    @FXML private Label lblCategoryInfo;
    @FXML private TableView<Quiz> tblAvailableQuizzes;
    @FXML private TableColumn<Quiz, String> colQTitle, colQCategory, colQStart, colQEnd, colQStatus;
    @FXML private TableColumn<Quiz, Integer> colQQuestions, colQDuration;

    private int studentCategoryId = -1;

    @FXML
    public void initialize() {
        colQTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colQCategory.setCellValueFactory(new PropertyValueFactory<>("categoryName"));
        colQDuration.setCellValueFactory(new PropertyValueFactory<>("duration"));
        colQStart.setCellValueFactory(new PropertyValueFactory<>("startDate"));
        colQEnd.setCellValueFactory(new PropertyValueFactory<>("endDate"));

        colQStatus.setCellValueFactory(cd -> {
            Quiz q = cd.getValue();
            String status = "Available";
            if (q.getStartDate() != null && !q.getStartDate().isEmpty()) {
                try {
                    java.time.LocalDate start = java.time.LocalDate.parse(q.getStartDate());
                    if (java.time.LocalDate.now().isBefore(start)) status = "Upcoming";
                } catch (Exception ignored) {}
            }
            return new SimpleStringProperty(status);
        });

        colQQuestions.setCellValueFactory(cd ->
            new SimpleIntegerProperty(
                new QuestionDAO().getQuestionsByQuizId(cd.getValue().getId()).size()
            ).asObject()
        );
    }

    public void loadForStudent(String name) {
        txtStudentName.setText(name);
        onNameEntered();
    }

    @FXML
    private void onNameEntered() {
        String name = txtStudentName.getText().trim();
        if (name.isEmpty()) return;

        studentCategoryId = new CategoryStudentDAO().getCategoryForStudent(name);
        List<Quiz> quizzes;

        if (studentCategoryId == -1) {
            lblCategoryInfo.setText("⚠ Not enrolled in any category — showing all quizzes.");
            quizzes = new QuizDAO().getAllQuizzes();
        } else {
            quizzes = new QuizDAO().getQuizzesByCategory(studentCategoryId);
            lblCategoryInfo.setText("✔ Showing quizzes for your enrolled category (" + quizzes.size() + " available).");
        }

        tblAvailableQuizzes.setItems(FXCollections.observableArrayList(quizzes));
    }

    @FXML
    private void startQuiz() {
        String studentName = txtStudentName.getText().trim();
        Quiz selectedQuiz  = tblAvailableQuizzes.getSelectionModel().getSelectedItem();

        if (studentName.isEmpty()) { alert("Validation", "Please enter your name."); return; }
        if (selectedQuiz == null)  { alert("Validation", "Select a quiz from the table."); return; }

        List<Question> questions = new QuestionDAO().getQuestionsByQuizId(selectedQuiz.getId());
        if (questions.isEmpty()) { alert("No Questions", "This quiz has no questions yet."); return; }

        openLockedQuizWindow(selectedQuiz, questions, studentName);
    }

    private void openLockedQuizWindow(Quiz quiz, List<Question> questions, String studentName) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/QuizModal.fxml"));
            Scene scene = new Scene(loader.load(), 760, 580);

            QuizModalController modal = loader.getController();
            modal.setup(quiz, questions, studentName, studentCategoryId);

            Stage stage = new Stage(StageStyle.UNDECORATED);
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.setAlwaysOnTop(true);
            stage.setScene(scene);
            stage.setOnCloseRequest(e -> e.consume());
            stage.showAndWait();

            // Reset after quiz closes
            txtStudentName.clear();
            tblAvailableQuizzes.setItems(FXCollections.observableArrayList());
            lblCategoryInfo.setText("");
            studentCategoryId = -1;

        } catch (Exception e) {
            e.printStackTrace();
            alert("Error", "Failed to open quiz window: " + e.getMessage());
        }
    }

    private void alert(String title, String msg) {
        Alert a = new Alert(Alert.AlertType.INFORMATION);
        a.setTitle(title); a.setHeaderText(null); a.setContentText(msg);
        a.showAndWait();
    }
}
