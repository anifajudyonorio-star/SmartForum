package com.smartforum.controller;

import com.smartforum.dao.QuestionDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;

public class QuestionController {

    @FXML private ComboBox<Quiz> cmbQuiz;
    @FXML private ComboBox<String> cmbCorrect;
    @FXML private TextArea txtQuestion;
    @FXML private TextField txtOptionA;
    @FXML private TextField txtOptionB;
    @FXML private TextField txtOptionC;
    @FXML private TextField txtOptionD;
    @FXML private Spinner<Integer> spQuestionMarks;
    @FXML private TableView<Question> tblQuestions;
    @FXML private TableColumn<Question, Integer> colId;
    @FXML private TableColumn<Question, String> colQuiz;
    @FXML private TableColumn<Question, String> colQuestion;
    @FXML private TableColumn<Question, String> colCorrect;
    @FXML private TableColumn<Question, Integer> colMarks;

    private Question selectedQuestion;

    @FXML
    public void initialize() {
        cmbCorrect.setItems(FXCollections.observableArrayList("A", "B", "C", "D"));
        spQuestionMarks.setValueFactory(new SpinnerValueFactory.IntegerSpinnerValueFactory(1, 1000, 1));

        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colQuiz.setCellValueFactory(new PropertyValueFactory<>("quizTitle"));
        colQuestion.setCellValueFactory(new PropertyValueFactory<>("question"));
        colCorrect.setCellValueFactory(new PropertyValueFactory<>("correctAnswer"));
        colMarks.setCellValueFactory(new PropertyValueFactory<>("marks"));

        loadQuizzes();
        loadQuestions();

        tblQuestions.getSelectionModel().selectedItemProperty().addListener(
            (obs, oldVal, newVal) -> {
                if (newVal != null) {
                    selectedQuestion = newVal;
                    txtQuestion.setText(newVal.getQuestion());
                    txtOptionA.setText(newVal.getOptionA());
                    txtOptionB.setText(newVal.getOptionB());
                    txtOptionC.setText(newVal.getOptionC());
                    txtOptionD.setText(newVal.getOptionD());
                    cmbCorrect.setValue(newVal.getCorrectAnswer());
                    spQuestionMarks.getValueFactory().setValue(Math.max(1, newVal.getMarks()));
                    for (Quiz quiz : cmbQuiz.getItems()) {
                        if (quiz.getId() == newVal.getQuizId()) {
                            cmbQuiz.setValue(quiz);
                            break;
                        }
                    }
                }
            });
    }

    private void loadQuizzes() {
        cmbQuiz.setItems(FXCollections.observableArrayList(new QuizDAO().getAllQuizzes()));
    }

    private void loadQuestions() {
        tblQuestions.setItems(FXCollections.observableArrayList(new QuestionDAO().getAllQuestions()));
    }

    @FXML
    private void saveQuestion() {
        Quiz quiz = cmbQuiz.getValue();
        if (quiz == null) { showAlert("Validation", "Please select a quiz."); return; }
        if (!validateFields()) return;

        Question question = new Question();
        question.setQuizId(quiz.getId());
        question.setQuestion(txtQuestion.getText());
        question.setOptionA(txtOptionA.getText());
        question.setOptionB(txtOptionB.getText());
        question.setOptionC(txtOptionC.getText());
        question.setOptionD(txtOptionD.getText());
        question.setCorrectAnswer(cmbCorrect.getValue());
        question.setMarks(spQuestionMarks.getValue());

        if (new QuestionDAO().saveQuestion(question)) {
            showAlert("Success", "Question saved successfully.");
            loadQuestions();
            clearFields();
        } else {
            showAlert("Error", "Failed to save question.");
        }
    }

    @FXML
    private void updateQuestion() {
        if (selectedQuestion == null) { showAlert("Update", "Please select a question."); return; }
        Quiz quiz = cmbQuiz.getValue();
        if (quiz == null) { showAlert("Validation", "Please select a quiz."); return; }
        if (!validateFields()) return;

        selectedQuestion.setQuizId(quiz.getId());
        selectedQuestion.setQuestion(txtQuestion.getText());
        selectedQuestion.setOptionA(txtOptionA.getText());
        selectedQuestion.setOptionB(txtOptionB.getText());
        selectedQuestion.setOptionC(txtOptionC.getText());
        selectedQuestion.setOptionD(txtOptionD.getText());
        selectedQuestion.setCorrectAnswer(cmbCorrect.getValue());
        selectedQuestion.setMarks(spQuestionMarks.getValue());

        if (new QuestionDAO().updateQuestion(selectedQuestion)) {
            showAlert("Success", "Question updated successfully.");
            loadQuestions();
            clearFields();
            selectedQuestion = null;
        } else {
            showAlert("Error", "Failed to update question.");
        }
    }

    @FXML
    private void deleteQuestion() {
        if (selectedQuestion == null) { showAlert("Delete", "Please select a question."); return; }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Delete Question");
        confirm.setHeaderText(null);
        confirm.setContentText("Are you sure you want to delete this question?");

        if (confirm.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK) {
            if (new QuestionDAO().deleteQuestion(selectedQuestion.getId())) {
                showAlert("Success", "Question deleted successfully.");
                loadQuestions();
                clearFields();
                selectedQuestion = null;
            } else {
                showAlert("Error", "Failed to delete question.");
            }
        }
    }

    @FXML
    private void clearFields() {
        cmbQuiz.getSelectionModel().clearSelection();
        cmbCorrect.getSelectionModel().clearSelection();
        txtQuestion.clear();
        txtOptionA.clear();
        txtOptionB.clear();
        txtOptionC.clear();
        txtOptionD.clear();
        spQuestionMarks.getValueFactory().setValue(1);
        selectedQuestion = null;
        tblQuestions.getSelectionModel().clearSelection();
    }

    private boolean validateFields() {
        if (txtQuestion.getText() == null || txtQuestion.getText().isBlank()) {
            showAlert("Validation", "Question text is required.");
            return false;
        }
        if (txtOptionA.getText().isBlank() || txtOptionB.getText().isBlank()
                || txtOptionC.getText().isBlank() || txtOptionD.getText().isBlank()) {
            showAlert("Validation", "All four answer options are required.");
            return false;
        }
        if (cmbCorrect.getValue() == null || !"ABCD".contains(cmbCorrect.getValue())) {
            showAlert("Validation", "Select a valid correct answer (A–D).");
            return false;
        }
        if (spQuestionMarks.getValue() == null || spQuestionMarks.getValue() <= 0) {
            showAlert("Validation", "Question marks must be positive.");
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
}
