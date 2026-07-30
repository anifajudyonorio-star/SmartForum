package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;

import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.ArrayList;
import java.util.Comparator;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

public class QuestionController {

    @FXML private ComboBox<Quiz> cmbQuiz;
    @FXML private ComboBox<String> cmbCorrect;
    @FXML private TextArea txtQuestion;
    @FXML private TextField txtOptionA;
    @FXML private TextField txtOptionB;
    @FXML private TextField txtOptionC;
    @FXML private TextField txtOptionD;
    @FXML private Spinner<Integer> spQuestionMarks;
    @FXML private VBox quizOutlineBox;
    @FXML private Label lblOutlineHint;

    private Question selectedQuestion;
    private final List<Quiz> allQuizzes = new ArrayList<>();
    private final List<Question> allQuestions = new ArrayList<>();

    @FXML
    public void initialize() {
        cmbCorrect.setItems(FXCollections.observableArrayList("A", "B", "C", "D"));
        spQuestionMarks.setValueFactory(new SpinnerValueFactory.IntegerSpinnerValueFactory(1, 1000, 1));
        reload();
    }

    /** Called when the Questions tab is selected so newly created quizzes appear. */
    public void reload() {

        new Thread(() -> ApiClient.getManagedQuestions().ifPresentOrElse(json -> {
            List<Quiz> quizzes = parseQuizzes(json.getAsJsonArray("quizzes"));
            List<Question> questions = parseQuestions(json.getAsJsonArray("questions"));
            Platform.runLater(() -> applyData(quizzes, questions));
        }, () -> Platform.runLater(() -> applyData(List.of(), List.of()))), "questions-reload").start();
    }

    private void applyData(List<Quiz> quizzes, List<Question> questions) {
        allQuizzes.clear();
        allQuizzes.addAll(quizzes);
        allQuestions.clear();
        allQuestions.addAll(questions);

        List<Quiz> editable = quizzes.stream()
                .filter(this::canAuthorQuestions)
                .toList();

        Quiz previous = cmbQuiz.getValue();
        cmbQuiz.setItems(FXCollections.observableArrayList(editable));
        cmbQuiz.setConverter(new javafx.util.StringConverter<>() {
            @Override
            public String toString(Quiz quiz) {
                return quiz == null ? "" : quiz.getTitle();
            }

            @Override
            public Quiz fromString(String string) {
                return null;
            }
        });
        if (previous != null) {
            editable.stream()
                    .filter(quiz -> quiz.getId() == previous.getId())
                    .findFirst()
                    .ifPresentOrElse(cmbQuiz::setValue, () -> cmbQuiz.getSelectionModel().clearSelection());
        }

        renderOutline();
    }

    /** Draft quizzes (and any the API marks editable) can receive new questions. */
    private boolean canAuthorQuestions(Quiz quiz) {
        if (quiz == null) {
            return false;
        }
        if (quiz.isCanEditQuestions()) {
            return true;
        }
        String status = quiz.getLifecycleStatus();
        return status != null && status.equalsIgnoreCase("Draft");
    }

    private void renderOutline() {
        quizOutlineBox.getChildren().clear();

        Map<Integer, List<Question>> byQuiz = allQuestions.stream()
                .collect(Collectors.groupingBy(Question::getQuizId, LinkedHashMap::new, Collectors.toList()));

        List<Quiz> ordered = new ArrayList<>(allQuizzes);
        ordered.sort(Comparator.comparing(quiz -> quiz.getTitle() == null ? "" : quiz.getTitle(),
                String.CASE_INSENSITIVE_ORDER));

        // Include any quiz that only appears via questions (edge case).
        for (Integer quizId : byQuiz.keySet()) {
            boolean known = ordered.stream().anyMatch(quiz -> quiz.getId() == quizId);
            if (!known) {
                Question sample = byQuiz.get(quizId).get(0);
                Quiz orphan = new Quiz();
                orphan.setId(quizId);
                orphan.setTitle(sample.getQuizTitle() == null ? "Quiz #" + quizId : sample.getQuizTitle());
                orphan.setCanEditQuestions(false);
                ordered.add(orphan);
            }
        }

        if (ordered.isEmpty()) {
            lblOutlineHint.setText("No quizzes yet. Create a draft quiz first, then add questions here.");
            return;
        }

        lblOutlineHint.setText(ordered.size() + " quiz(zes) · " + allQuestions.size() + " question(s)");

        for (Quiz quiz : ordered) {
            List<Question> questions = byQuiz.getOrDefault(quiz.getId(), List.of());
            quizOutlineBox.getChildren().add(buildQuizSection(quiz, questions));
        }
    }

    private VBox buildQuizSection(Quiz quiz, List<Question> questions) {
        VBox section = new VBox(10);
        section.getStyleClass().add("quiz-card");
        section.setPadding(new Insets(14, 16, 14, 16));

        Label title = new Label(quiz.getTitle());
        title.getStyleClass().add("dashboard-card-title");
        title.setWrapText(true);

        Label meta = new Label(
                (quiz.getLifecycleStatus() == null || quiz.getLifecycleStatus().isBlank()
                        ? (canAuthorQuestions(quiz) ? "Draft" : "Quiz")
                        : quiz.getLifecycleStatus())
                        + " · " + questions.size() + " question"
                        + (questions.size() == 1 ? "" : "s")
                        + (canAuthorQuestions(quiz) ? " · editable" : " · locked"));
        meta.getStyleClass().add("dashboard-subtitle");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button addHere = new Button("+ Add question");
        addHere.getStyleClass().addAll("btn-primary", "btn-sm");
        addHere.setOnAction(e -> startAddQuestion(quiz));

        HBox header = new HBox(10, title, spacer, addHere);
        header.setAlignment(Pos.CENTER_LEFT);

        section.getChildren().addAll(header, meta);

        if (questions.isEmpty()) {
            Label empty = new Label(canAuthorQuestions(quiz)
                    ? "No questions yet. Click “+ Add question” or use the form above."
                    : "No questions on this quiz (locked — only Draft quizzes can be edited).");
            empty.getStyleClass().add("empty-label");
            section.getChildren().add(empty);
            return section;
        }

        TableView<Question> table = new TableView<>();
        table.getStyleClass().add("dashboard-table");
        table.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);
        table.setPrefHeight(Math.min(56 + questions.size() * 36.0, 280));
        table.setItems(FXCollections.observableArrayList(questions));

        TableColumn<Question, Number> colNum = new TableColumn<>("#");
        colNum.setPrefWidth(45);
        colNum.setCellValueFactory(cell ->
                new javafx.beans.property.SimpleIntegerProperty(
                        table.getItems().indexOf(cell.getValue()) + 1));

        TableColumn<Question, String> colText = new TableColumn<>("Question");
        colText.setPrefWidth(280);
        colText.setCellValueFactory(new PropertyValueFactory<>("question"));

        TableColumn<Question, String> colOptions = new TableColumn<>("Options");
        colOptions.setPrefWidth(320);
        colOptions.setCellValueFactory(cell ->
                new javafx.beans.property.SimpleStringProperty(
                        cell.getValue() == null ? "" : cell.getValue().getOptionsDisplay()));

        TableColumn<Question, String> colCorrect = new TableColumn<>("Correct");
        colCorrect.setPrefWidth(70);
        colCorrect.setCellValueFactory(new PropertyValueFactory<>("correctAnswer"));

        TableColumn<Question, Integer> colMarks = new TableColumn<>("Marks");
        colMarks.setPrefWidth(70);
        colMarks.setCellValueFactory(new PropertyValueFactory<>("marks"));

        table.getColumns().addAll(List.of(colNum, colText, colOptions, colCorrect, colMarks));
        table.getSelectionModel().selectedItemProperty().addListener((obs, oldVal, selected) -> {
            if (selected != null) {
                fillFormFromQuestion(selected);
            }
        });

        section.getChildren().add(table);
        return section;
    }

    /** Prepare the top form to author a question for the given quiz. */
    private void startAddQuestion(Quiz quiz) {
        if (quiz == null) {
            return;
        }
        if (!canAuthorQuestions(quiz)) {
            showAlert("Cannot add questions",
                    "“" + quiz.getTitle() + "” is locked.\n\n"
                            + "Questions can only be added while the quiz is still a Draft "
                            + "(and has no student attempts yet).\n\n"
                            + "Open Quizzes → Review that quiz if you need to check its status.");
            return;
        }

        // Ensure this quiz is selectable in the form dropdown.
        Quiz editable = findEditableQuiz(quiz.getId());
        if (editable == null) {
            quiz.setCanEditQuestions(true);
            cmbQuiz.getItems().add(quiz);
            editable = quiz;
        }

        clearFieldsKeepQuiz();
        cmbQuiz.setValue(editable);
        if (cmbCorrect.getValue() == null) {
            cmbCorrect.setValue("A");
        }
        txtQuestion.requestFocus();

        if (lblOutlineHint != null) {
            lblOutlineHint.setText("Adding a question to: " + editable.getTitle()
                    + " — fill the form above, then click Save Question.");
        }
    }

    private Quiz findEditableQuiz(int quizId) {
        return cmbQuiz.getItems().stream()
                .filter(quiz -> quiz.getId() == quizId)
                .findFirst()
                .orElse(null);
    }

    private void fillFormFromQuestion(Question question) {
        selectedQuestion = question;
        txtQuestion.setText(question.getQuestion());
        txtOptionA.setText(question.getOptionA());
        txtOptionB.setText(question.getOptionB());
        txtOptionC.setText(question.getOptionC());
        txtOptionD.setText(question.getOptionD());
        cmbCorrect.setValue(question.getCorrectAnswer());
        spQuestionMarks.getValueFactory().setValue(Math.max(1, question.getMarks()));
        Quiz match = findEditableQuiz(question.getQuizId());
        if (match != null) {
            cmbQuiz.setValue(match);
        } else {
            // Show title even if not editable — save/update will validate.
            allQuizzes.stream()
                    .filter(quiz -> quiz.getId() == question.getQuizId())
                    .findFirst()
                    .ifPresent(quiz -> {
                        if (!cmbQuiz.getItems().contains(quiz)) {
                            // Don't add locked quiz to editable combo.
                        }
                    });
        }
    }

    private List<Quiz> parseQuizzes(JsonArray array) {
        List<Quiz> quizzes = new ArrayList<>();
        if (array == null) {
            return quizzes;
        }
        for (JsonElement element : array) {
            JsonObject item = element.getAsJsonObject();
            Quiz quiz = new Quiz();
            quiz.setId(item.get("id").getAsInt());
            quiz.setTitle(item.get("title").getAsString());
            if (item.has("lifecycle_status") && !item.get("lifecycle_status").isJsonNull()) {
                quiz.setLifecycleStatus(item.get("lifecycle_status").getAsString());
            } else if (item.has("status")) {
                quiz.setLifecycleStatus(item.get("status").getAsString());
            }
            if (item.has("questions_count")) {
                quiz.setQuestionsCount(item.get("questions_count").getAsInt());
            }
            quiz.setCanEditQuestions(item.has("can_edit_questions")
                    && !item.get("can_edit_questions").isJsonNull()
                    && item.get("can_edit_questions").getAsBoolean());
            // Prefer Draft lifecycle when the API omits/zeros the flag.
            if (!quiz.isCanEditQuestions()) {
                String life = quiz.getLifecycleStatus();
                if (life != null && life.equalsIgnoreCase("Draft")) {
                    quiz.setCanEditQuestions(true);
                }
            }
            quizzes.add(quiz);
        }
        return quizzes;
    }

    private List<Question> parseQuestions(JsonArray array) {
        List<Question> questions = new ArrayList<>();
        if (array == null) {
            return questions;
        }
        for (JsonElement element : array) {
            JsonObject item = element.getAsJsonObject();
            Question question = new Question();
            question.setId(item.get("id").getAsInt());
            question.setQuizId(item.get("quiz_id").getAsInt());
            question.setQuizTitle(item.get("quiz_title").getAsString());
            question.setQuestion(item.get("question").getAsString());
            question.setMarks(item.get("marks").getAsInt());
            question.setCorrectAnswer(item.get("correct_answer").getAsString());

            JsonArray options = item.getAsJsonArray("options");
            if (options != null) {
                if (options.size() > 0) {
                    JsonObject option = options.get(0).getAsJsonObject();
                    question.setOptionA(option.get("text").getAsString());
                    question.setOptionAId(option.get("id").getAsInt());
                }
                if (options.size() > 1) {
                    JsonObject option = options.get(1).getAsJsonObject();
                    question.setOptionB(option.get("text").getAsString());
                    question.setOptionBId(option.get("id").getAsInt());
                }
                if (options.size() > 2) {
                    JsonObject option = options.get(2).getAsJsonObject();
                    question.setOptionC(option.get("text").getAsString());
                    question.setOptionCId(option.get("id").getAsInt());
                }
                if (options.size() > 3) {
                    JsonObject option = options.get(3).getAsJsonObject();
                    question.setOptionD(option.get("text").getAsString());
                    question.setOptionDId(option.get("id").getAsInt());
                }
            }
            questions.add(question);
        }
        return questions;
    }

    @FXML
    private void saveQuestion() {
        Quiz quiz = cmbQuiz.getValue();
        if (quiz == null) {
            showAlert("Validation",
                    "Select a draft quiz first.\n\n"
                            + "Tip: open your quiz card below and click “+ Add question”, "
                            + "then fill the form and click Save Question.");
            return;
        }
        if (!canAuthorQuestions(quiz)) {
            showAlert("Cannot save",
                    "Questions can only be saved on Draft quizzes with no student attempts.");
            return;
        }
        if (!validateFields()) {
            return;
        }


        JsonObject body = buildApiPayload(quiz.getId());
        new Thread(() -> {
            ApiClient.MutationResult result = ApiClient.createQuestion(body);
            Platform.runLater(() -> {
                if (result.success()) {
                    showAlert("Success", result.message().isBlank()
                            ? "Question saved successfully."
                            : result.message());
                    clearFields();
                    reload();
                } else {
                    showAlert("Error", result.message().isBlank()
                            ? "Failed to save question."
                            : result.message());
                }
            });
        }, "question-create").start();
    }

    @FXML
    private void updateQuestion() {
        if (selectedQuestion == null) {
            showAlert("Update", "Select a question from a quiz section below first.");
            return;
        }
        Quiz quiz = cmbQuiz.getValue();
        if (quiz == null) {
            showAlert("Validation", "Please select a quiz.");
            return;
        }
        if (!validateFields()) {
            return;
        }


        JsonObject body = buildApiPayload(quiz.getId());
        new Thread(() -> {
            ApiClient.MutationResult result = ApiClient.updateQuestion(selectedQuestion.getId(), body);
            Platform.runLater(() -> {
                if (result.success()) {
                    showAlert("Success", result.message().isBlank()
                            ? "Question updated successfully."
                            : result.message());
                    selectedQuestion = null;
                    clearFields();
                    reload();
                } else {
                    showAlert("Error", result.message().isBlank()
                            ? "Failed to update question."
                            : result.message());
                }
            });
        }, "question-update").start();
    }

    @FXML
    private void deleteQuestion() {
        if (selectedQuestion == null) {
            showAlert("Delete", "Select a question from a quiz section below first.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Delete Question");
        confirm.setHeaderText(null);
        confirm.setContentText("Delete this question?");

        if (confirm.showAndWait().orElse(ButtonType.CANCEL) != ButtonType.OK) {
            return;
        }


        int questionId = selectedQuestion.getId();
        new Thread(() -> {
            ApiClient.MutationResult result = ApiClient.deleteQuestion(questionId);
            Platform.runLater(() -> {
                if (result.success()) {
                    showAlert("Success", result.message().isBlank()
                            ? "Question deleted successfully."
                            : result.message());
                    selectedQuestion = null;
                    clearFields();
                    reload();
                } else {
                    showAlert("Error", result.message().isBlank()
                            ? "Failed to delete question."
                            : result.message());
                }
            });
        }, "question-delete").start();
    }

    private JsonObject buildApiPayload(int quizId) {
        JsonObject body = new JsonObject();
        body.addProperty("quiz_id", quizId);
        body.addProperty("question", txtQuestion.getText().trim());
        body.addProperty("question_type", "Multiple Choice");
        body.addProperty("marks", spQuestionMarks.getValue());
        JsonArray options = new JsonArray();
        options.add(txtOptionA.getText().trim());
        options.add(txtOptionB.getText().trim());
        options.add(txtOptionC.getText().trim());
        options.add(txtOptionD.getText().trim());
        body.add("options", options);
        body.addProperty("correct_option", "ABCD".indexOf(cmbCorrect.getValue()));
        return body;
    }

    @FXML
    private void clearFields() {
        cmbQuiz.getSelectionModel().clearSelection();
        clearFieldsKeepQuiz();
    }

    private void clearFieldsKeepQuiz() {
        cmbCorrect.getSelectionModel().clearSelection();
        txtQuestion.clear();
        txtOptionA.clear();
        txtOptionB.clear();
        txtOptionC.clear();
        txtOptionD.clear();
        spQuestionMarks.getValueFactory().setValue(1);
        selectedQuestion = null;
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
