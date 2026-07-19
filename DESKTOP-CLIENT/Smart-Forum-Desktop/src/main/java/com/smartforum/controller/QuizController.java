package com.smartforum.controller;

import com.smartforum.dao.QuizCategoryDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizCategory;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;

public class QuizController {

    @FXML
    private ComboBox<QuizCategory> cmbCategory;

    @FXML
    private TextField txtTitle;

    @FXML
    private TextArea txtDescription;

    @FXML
    private Spinner<Integer> spDuration;

    @FXML
    private Spinner<Integer> spMarks;
    @FXML
    private Spinner<Integer> spParticipation;

    @FXML
    private DatePicker dpStart;

    @FXML
    private DatePicker dpEnd;

    @FXML
    private TableView<Quiz> tblQuizzes;

    @FXML
    private TableColumn<Quiz, Integer> colId;

    @FXML
    private TableColumn<Quiz, String> colCategory;

    @FXML
    private TableColumn<Quiz, String> colTitle;

    @FXML
    private TableColumn<Quiz, Integer> colDuration;

    @FXML
    private TableColumn<Quiz, Integer> colMarks;

    @FXML
    private TableColumn<Quiz, String> colStart;

    @FXML
    private TableColumn<Quiz, String> colEnd;

    @FXML
    private Button btnSave;

    @FXML
    private Button btnUpdate;

    @FXML
    private Button btnDelete;

    @FXML
    private Button btnClear;

    private Quiz selectedQuiz;

    @FXML
    public void initialize() {

        spDuration.setValueFactory(
            new SpinnerValueFactory.IntegerSpinnerValueFactory(1, 300, 30));

        spMarks.setValueFactory(
            new SpinnerValueFactory.IntegerSpinnerValueFactory(1, 1000, 100));
        spParticipation.setValueFactory(
            new SpinnerValueFactory.IntegerSpinnerValueFactory(0, 1000, 0));

        loadCategories();

        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colCategory.setCellValueFactory(new PropertyValueFactory<>("categoryName"));
        colTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colDuration.setCellValueFactory(new PropertyValueFactory<>("duration"));
        colMarks.setCellValueFactory(new PropertyValueFactory<>("totalMarks"));
        colStart.setCellValueFactory(new PropertyValueFactory<>("startDate"));
        colEnd.setCellValueFactory(new PropertyValueFactory<>("endDate"));

        loadQuizzes();

        tblQuizzes.getSelectionModel().selectedItemProperty().addListener(
            (observable, oldValue, newValue) -> {

                if (newValue != null) {

                    selectedQuiz = newValue;

                    txtTitle.setText(newValue.getTitle());
                    txtDescription.setText(newValue.getDescription());

                    spDuration.getValueFactory().setValue(newValue.getDuration());
                    spMarks.getValueFactory().setValue(newValue.getTotalMarks());
                    spParticipation.getValueFactory().setValue(Math.max(0, newValue.getParticipationMarks()));

                    try {
                        dpStart.setValue(newValue.getStartDate() == null ? null :
                            com.smartforum.util.QuizSchedule.parseStart(newValue.getStartDate()).toLocalDate());
                        dpEnd.setValue(newValue.getEndDate() == null ? null :
                            com.smartforum.util.QuizSchedule.parseEnd(newValue.getEndDate()).toLocalDate());
                    } catch (RuntimeException invalidHistoricSchedule) {
                        dpStart.setValue(null);
                        dpEnd.setValue(null);
                    }

                    for (QuizCategory category : cmbCategory.getItems()) {

                        if (category.getId() == newValue.getCategoryId()) {

                            cmbCategory.setValue(category);

                            break;
                        }
                    }

                }

            });

    }

    @FXML
    private void saveQuiz() {

        QuizCategory category = cmbCategory.getValue();

        if (category == null) {

            showAlert("Validation", "Please select a category.");

            return;

        }
        if (!validateQuiz()) return;

        Quiz quiz = new Quiz();

        quiz.setCategoryId(category.getId());
        quiz.setTitle(txtTitle.getText());
        quiz.setDescription(txtDescription.getText());
        quiz.setDuration(spDuration.getValue());
        quiz.setTotalMarks(spMarks.getValue());
        quiz.setParticipationMarks(spParticipation.getValue());

        if (dpStart.getValue() != null)
            quiz.setStartDate(dpStart.getValue().toString());

        if (dpEnd.getValue() != null)
            quiz.setEndDate(dpEnd.getValue().toString());

        QuizDAO dao = new QuizDAO();

        if (dao.saveQuiz(quiz)) {

            showAlert("Success", "Quiz saved successfully.");

            loadQuizzes();

            clearFields();

        } else {

            showAlert("Error", "Failed to save quiz.");

        }

    }

    @FXML
    private void updateQuiz() {

        if (selectedQuiz == null) {

            showAlert("Update", "Please select a quiz.");

            return;

        }

        QuizCategory category = cmbCategory.getValue();
        if (category == null) { showAlert("Validation", "Please select a category."); return; }
        if (!validateQuiz()) return;

        selectedQuiz.setCategoryId(category.getId());
        selectedQuiz.setTitle(txtTitle.getText());
        selectedQuiz.setDescription(txtDescription.getText());
        selectedQuiz.setDuration(spDuration.getValue());
        selectedQuiz.setTotalMarks(spMarks.getValue());
        selectedQuiz.setParticipationMarks(spParticipation.getValue());

        if (dpStart.getValue() != null)
            selectedQuiz.setStartDate(dpStart.getValue().toString());

        if (dpEnd.getValue() != null)
            selectedQuiz.setEndDate(dpEnd.getValue().toString());

        QuizDAO dao = new QuizDAO();

        if (dao.updateQuiz(selectedQuiz)) {

            showAlert("Success", "Quiz updated successfully.");

            loadQuizzes();

            clearFields();

            selectedQuiz = null;

        } else {

            showAlert("Error", "Update failed.");

        }

    }
    @FXML
    private void deleteQuiz() {

        if (selectedQuiz == null) {

            showAlert("Delete", "Please select a quiz.");

            return;

        }

        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);

        alert.setTitle("Delete Quiz");

        alert.setHeaderText(null);

        alert.setContentText("Are you sure you want to delete this quiz?");

        if (alert.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK) {

            QuizDAO dao = new QuizDAO();

            if (dao.deleteQuiz(selectedQuiz.getId())) {

                showAlert("Success", "Quiz deleted successfully.");

                loadQuizzes();

                clearFields();

                selectedQuiz = null;

            } else {

                showAlert("Error", "Failed to delete quiz.");

            }

        }

    }

    @FXML
    private void clearFields() {

        cmbCategory.getSelectionModel().clearSelection();

        txtTitle.clear();

        txtDescription.clear();

        spDuration.getValueFactory().setValue(30);

        spMarks.getValueFactory().setValue(100);
        spParticipation.getValueFactory().setValue(0);

        dpStart.setValue(null);

        dpEnd.setValue(null);

        selectedQuiz = null;

        tblQuizzes.getSelectionModel().clearSelection();

    }

    private boolean validateQuiz() {
        if (txtTitle.getText() == null || txtTitle.getText().isBlank()) {
            showAlert("Validation", "Quiz title is required.");
            return false;
        }
        if (txtDescription.getText() == null || txtDescription.getText().isBlank()) {
            showAlert("Validation", "Quiz description is required.");
            return false;
        }
        if (spDuration.getValue() == null || spDuration.getValue() <= 0) {
            showAlert("Validation", "Duration must be positive.");
            return false;
        }
        if (spMarks.getValue() == null || spMarks.getValue() <= 0) {
            showAlert("Validation", "Planned total marks must be positive.");
            return false;
        }
        if (spParticipation.getValue() == null || spParticipation.getValue() < 0) {
            showAlert("Validation", "Participation marks cannot be negative.");
            return false;
        }
        if (dpStart.getValue() == null || dpEnd.getValue() == null) {
            showAlert("Validation", "Start and end dates are required.");
            return false;
        }
        if (dpEnd.getValue().isBefore(dpStart.getValue())) {
            showAlert("Validation", "End date must not be before the start date.");
            return false;
        }
        return true;
    }

    private void showAlert(String title, String message) {

        Alert alert = new Alert(Alert.AlertType.INFORMATION);

        alert.setTitle(title);

        alert.setHeaderText(null);

        alert.setContentText(message);

        alert.showAndWait();

    }

    private void loadCategories() {

        QuizCategoryDAO dao = new QuizCategoryDAO();

        cmbCategory.setItems(
            FXCollections.observableArrayList(
                dao.getAllCategories()
            )
        );

    }

    private void loadQuizzes() {

        QuizDAO dao = new QuizDAO();

        tblQuizzes.setItems(
            FXCollections.observableArrayList(
                dao.getAllQuizzes()
            )
        );

    }

}
