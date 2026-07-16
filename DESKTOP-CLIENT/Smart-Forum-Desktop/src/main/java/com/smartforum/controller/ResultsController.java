package com.smartforum.controller;

import com.smartforum.dao.QuizResultDAO;
import com.smartforum.model.QuizResult;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;

public class ResultsController {

    @FXML private TableView<QuizResult> tblResults;
    @FXML private TableColumn<QuizResult, Integer> colId;
    @FXML private TableColumn<QuizResult, String> colStudent;
    @FXML private TableColumn<QuizResult, String> colQuiz;
    @FXML private TableColumn<QuizResult, Integer> colScore;
    @FXML private TableColumn<QuizResult, Integer> colTotalMarks;
    @FXML private TableColumn<QuizResult, String> colPercentage;
    @FXML private TableColumn<QuizResult, Integer> colParticipation;
    @FXML private TableColumn<QuizResult, Integer> colTotalScore;
    @FXML private TableColumn<QuizResult, String> colDate;

    private final QuizResultDAO dao = new QuizResultDAO();
    private QuizResult selectedResult;

    @FXML
    public void initialize() {
        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colStudent.setCellValueFactory(new PropertyValueFactory<>("studentName"));
        colQuiz.setCellValueFactory(new PropertyValueFactory<>("quizTitle"));
        colScore.setCellValueFactory(new PropertyValueFactory<>("score"));
        colTotalMarks.setCellValueFactory(new PropertyValueFactory<>("totalMarks"));
        colPercentage.setCellValueFactory(new PropertyValueFactory<>("percentage"));
        colParticipation.setCellValueFactory(new PropertyValueFactory<>("participationMarks"));
        colTotalScore.setCellValueFactory(new PropertyValueFactory<>("totalScore"));
        colDate.setCellValueFactory(new PropertyValueFactory<>("submittedAt"));

        tblResults.getSelectionModel().selectedItemProperty().addListener(
            (obs, oldVal, newVal) -> selectedResult = newVal);

        loadResults();
    }

    @FXML
    private void deleteResult() {
        if (selectedResult == null) {
            showAlert("Delete", "Please select a result to delete.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Delete Result");
        confirm.setHeaderText(null);
        confirm.setContentText("Are you sure you want to delete this result?");

        if (confirm.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK) {
            if (dao.deleteResult(selectedResult.getId())) {
                showAlert("Success", "Result deleted successfully.");
                selectedResult = null;
                loadResults();
            } else {
                showAlert("Error", "Failed to delete result.");
            }
        }
    }

    @FXML
    private void refreshResults() {
        loadResults();
    }

    private void loadResults() {
        tblResults.setItems(FXCollections.observableArrayList(dao.getAllResults()));
    }

    private void showAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}
