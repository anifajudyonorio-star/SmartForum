package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.Group;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizCategory;

import javafx.application.Platform;
import javafx.beans.property.SimpleIntegerProperty;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;

public class QuizController {

    private static final DateTimeFormatter LOCAL_INPUT =
            DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm");

    @FXML private VBox listPane;
    @FXML private VBox createPane;
    @FXML private VBox reviewPane;
    @FXML private Label quizCountLabel;
    @FXML private Button btnCreateQuiz;
    @FXML private TableView<Quiz> tblQuizzes;
    @FXML private TableColumn<Quiz, String> colTitle;
    @FXML private TableColumn<Quiz, String> colCategory;
    @FXML private TableColumn<Quiz, String> colGroup;
    @FXML private TableColumn<Quiz, Integer> colQuestions;
    @FXML private TableColumn<Quiz, Integer> colMaximumMarks;
    @FXML private TableColumn<Quiz, Integer> colDuration;
    @FXML private TableColumn<Quiz, String> colStatus;
    @FXML private TableColumn<Quiz, Void> colActions;

    @FXML private ComboBox<QuizCategory> cmbCategory;
    @FXML private ComboBox<Group> cmbGroup;
    @FXML private TextField txtTitle;
    @FXML private TextArea txtDescription;
    @FXML private Spinner<Integer> spnDuration;
    @FXML private Spinner<Integer> spnParticipation;
    @FXML private TextField txtStartTime;
    @FXML private TextField txtEndTime;
    @FXML private Label lblCreateFeedback;
    @FXML private Button btnSaveQuiz;
    @FXML private Label lblFormTitle;
    @FXML private Label lblFormSubtitle;

    @FXML private Label lblReviewTitle;
    @FXML private Label lblReviewDescription;
    @FXML private Label lblReviewCategory;
    @FXML private Label lblReviewGroup;
    @FXML private Label lblReviewStatus;
    @FXML private Label lblReviewDuration;
    @FXML private Label lblReviewMarks;
    @FXML private Button btnReviewPublish;
    @FXML private Button btnReviewEdit;
    @FXML private TableView<Question> tblReviewQuestions;
    @FXML private TableColumn<Question, Number> colReviewNumber;
    @FXML private TableColumn<Question, String> colReviewQuestion;
    @FXML private TableColumn<Question, String> colReviewOptions;
    @FXML private TableColumn<Question, String> colReviewCorrect;
    @FXML private TableColumn<Question, Integer> colReviewMarks;

    private final List<QuizCategory> formCategories = new ArrayList<>();
    private final List<Group> formGroups = new ArrayList<>();
    private Quiz reviewingQuiz;
    private Quiz editingQuiz;

    @FXML
    public void initialize() {
        colTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colCategory.setCellValueFactory(new PropertyValueFactory<>("categoryName"));
        colGroup.setCellValueFactory(new PropertyValueFactory<>("groupName"));
        colQuestions.setCellValueFactory(new PropertyValueFactory<>("questionsCount"));
        colMaximumMarks.setCellValueFactory(new PropertyValueFactory<>("maximumMarks"));
        colDuration.setCellValueFactory(new PropertyValueFactory<>("duration"));
        colStatus.setCellValueFactory(new PropertyValueFactory<>("lifecycleStatus"));
        colActions.setCellValueFactory(param -> null);
        colActions.setCellFactory(column -> actionsCell());
        tblQuizzes.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);

        configureQuestionTable(colReviewNumber, colReviewQuestion, colReviewOptions, colReviewCorrect,
                colReviewMarks, tblReviewQuestions);

        spnDuration.setValueFactory(new SpinnerValueFactory.IntegerSpinnerValueFactory(1, 600, 15));
        spnParticipation.setValueFactory(new SpinnerValueFactory.IntegerSpinnerValueFactory(0, 100, 2));
        spnDuration.setMaxWidth(Double.MAX_VALUE);
        spnParticipation.setMaxWidth(Double.MAX_VALUE);

        loadQuizzes();
    }

    private void configureQuestionTable(TableColumn<Question, Number> numberCol,
                                        TableColumn<Question, String> textCol,
                                        TableColumn<Question, String> optionsCol,
                                        TableColumn<Question, String> correctCol,
                                        TableColumn<Question, Integer> marksCol,
                                        TableView<Question> table) {
        numberCol.setCellValueFactory(cell ->
                new SimpleIntegerProperty(table.getItems().indexOf(cell.getValue()) + 1));
        textCol.setCellValueFactory(new PropertyValueFactory<>("question"));
        optionsCol.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue() == null ? "" : cell.getValue().getOptionsDisplay()));
        correctCol.setCellValueFactory(new PropertyValueFactory<>("correctAnswer"));
        marksCol.setCellValueFactory(new PropertyValueFactory<>("marks"));
        table.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);
    }

    /** Called when the Quizzes tab is selected so newly created titles appear. */
    public void reload() {
        if (createPane != null && createPane.isVisible()) {
            refreshFormOptions(() -> Platform.runLater(this::populateCreateForm));
            return;
        }
        if (reviewPane != null && reviewPane.isVisible() && reviewingQuiz != null) {
            openReview(reviewingQuiz);
            return;
        }
        loadQuizzes();
    }

    @FXML
    private void openCreateQuiz() {
        editingQuiz = null;
        lblFormTitle.setText("Create New Quiz");
        lblFormSubtitle.setText("Draft a quiz, add questions next, then publish when ready.");
        btnSaveQuiz.setText("Create Quiz");
        clearCreateForm();
        setAssignmentFieldsEnabled(true);
        lblCreateFeedback.setText("Loading form…");
        showCreatePane(true);
        refreshFormOptions(() -> Platform.runLater(() -> {
            populateCreateForm();
            if (formCategories.isEmpty()) {
                lblCreateFeedback.setText(
                    "No quiz title templates yet. Open the Quiz Categories tab, create one "
                        + "(it syncs to the server when online), then come back here.");
                btnSaveQuiz.setDisable(true);
            } else if (formGroups.isEmpty()) {
                lblCreateFeedback.setText(
                    "You need at least one teachable group before creating a quiz.");
                btnSaveQuiz.setDisable(true);
            } else {
                lblCreateFeedback.setText("");
                btnSaveQuiz.setDisable(false);
            }
        }));
    }

    private void openEditQuiz(Quiz quiz) {
        if (quiz == null) {
            return;
        }
        editingQuiz = quiz;
        lblFormTitle.setText("Edit Quiz Schedule");
        lblFormSubtitle.setText("Update start/end time and duration. Assignment fields stay locked after publish.");
        btnSaveQuiz.setText("Update Quiz");
        lblCreateFeedback.setText("Loading quiz…");
        showCreatePane(true);
        refreshFormOptions(() -> Platform.runLater(() -> {
            populateCreateForm();
            fillFormFromQuiz(quiz);
            setAssignmentFieldsEnabled(quiz.isCanEditAssignment());
            lblCreateFeedback.setText(quiz.isCanEditAssignment()
                    ? "Editing draft quiz details and schedule."
                    : "You can change title, description, duration, and start/end times.");
            btnSaveQuiz.setDisable(false);
        }));
    }

    private void setAssignmentFieldsEnabled(boolean enabled) {
        cmbCategory.setDisable(!enabled);
        cmbGroup.setDisable(!enabled);
        spnParticipation.setDisable(!enabled);
    }

    private void fillFormFromQuiz(Quiz quiz) {
        txtTitle.setText(quiz.getTitle() == null ? "" : quiz.getTitle());
        txtDescription.setText(quiz.getDescription() == null ? "" : quiz.getDescription());
        spnDuration.getValueFactory().setValue(Math.max(1, quiz.getDuration()));
        spnParticipation.getValueFactory().setValue(Math.max(0, quiz.getParticipationMarks()));

        if (quiz.getStartDate() != null && !quiz.getStartDate().isBlank()) {
            txtStartTime.setText(normalizeDateTimeInput(quiz.getStartDate()));
        }
        if (quiz.getEndDate() != null && !quiz.getEndDate().isBlank()) {
            txtEndTime.setText(normalizeDateTimeInput(quiz.getEndDate()));
        }

        formCategories.stream()
                .filter(category -> category.getId() == quiz.getCategoryId())
                .findFirst()
                .ifPresentOrElse(cmbCategory::setValue, () -> {
                    if (quiz.getCategoryId() > 0) {
                        QuizCategory placeholder = new QuizCategory();
                        placeholder.setId(quiz.getCategoryId());
                        placeholder.setCategoryName(safe(quiz.getCategoryName()));
                        cmbCategory.getItems().add(placeholder);
                        cmbCategory.setValue(placeholder);
                    }
                });

                formGroups.stream()
                .filter(group -> group.getId() == quiz.getGroupId())
                .findFirst()
                .ifPresentOrElse(cmbGroup::setValue, () -> {
                    if (quiz.getGroupId() > 0) {
                        Group placeholder = new Group(
                                quiz.getGroupId(),
                                safe(quiz.getGroupName()),
                                "",
                                "Active",
                                0,
                                "",
                                0,
                                0,
                                "");
                        cmbGroup.getItems().add(placeholder);
                        cmbGroup.setValue(placeholder);
                    }
                });
    }

    private String normalizeDateTimeInput(String value) {
        String trimmed = value.trim();
        if (trimmed.length() >= 16 && trimmed.charAt(10) == 'T') {
            return trimmed.substring(0, 16);
        }
        if (trimmed.length() >= 16 && trimmed.charAt(10) == ' ') {
            return trimmed.substring(0, 10) + "T" + trimmed.substring(11, 16);
        }
        try {
            return LocalDateTime.parse(trimmed.replace(' ', 'T').substring(0, Math.min(16, trimmed.length())),
                    LOCAL_INPUT).format(LOCAL_INPUT);
        } catch (Exception ignored) {
            return trimmed;
        }
    }

    @FXML
    private void cancelCreateQuiz() {
        showCreatePane(false);
        clearCreateForm();
        editingQuiz = null;
    }

    @FXML
    private void saveCreateQuiz() {
        lblCreateFeedback.setText("");
        QuizCategory category = cmbCategory.getValue();
        Group group = cmbGroup.getValue();
        String title = txtTitle.getText() == null ? "" : txtTitle.getText().trim();
        String description = txtDescription.getText() == null ? "" : txtDescription.getText().trim();
        boolean editing = editingQuiz != null;
        boolean canEditAssignment = !editing || editingQuiz.isCanEditAssignment();

        if (canEditAssignment && category == null) {
            lblCreateFeedback.setText("Select a quiz title template.");
            return;
        }
        if (canEditAssignment && group == null) {
            lblCreateFeedback.setText("Select a group.");
            return;
        }
        if (title.isBlank() || description.isBlank()) {
            lblCreateFeedback.setText("Title and description are required.");
            return;
        }

        LocalDateTime start;
        LocalDateTime end;
        try {
            start = LocalDateTime.parse(txtStartTime.getText().trim(), LOCAL_INPUT);
            end = LocalDateTime.parse(txtEndTime.getText().trim(), LOCAL_INPUT);
        } catch (Exception ex) {
            lblCreateFeedback.setText("Use start/end times like 2026-07-24T20:00");
            return;
        }
        if (!end.isAfter(start)) {
            lblCreateFeedback.setText("End time must be after start time.");
            return;
        }

        int duration = spnDuration.getValue();
        int participation = spnParticipation.getValue();
        btnSaveQuiz.setDisable(true);
        lblCreateFeedback.setText(editing ? "Saving changes…" : "Creating quiz…");


        JsonObject body = new JsonObject();
        body.addProperty("title", title);
        body.addProperty("description", description);
        body.addProperty("duration", duration);
        body.addProperty("start_time", start.format(LOCAL_INPUT));
        body.addProperty("end_time", end.format(LOCAL_INPUT));
        if (canEditAssignment) {
            body.addProperty("category_id", category.getId());
            body.addProperty("group_id", group.getId());
            body.addProperty("participation_marks", participation);
        }

        final int quizId = editing ? editingQuiz.getId() : 0;
        new Thread(() -> {
            ApiClient.MutationResult result = editing
                    ? ApiClient.updateQuiz(quizId, body)
                    : ApiClient.createQuiz(body);
            Platform.runLater(() -> {
                btnSaveQuiz.setDisable(false);
                if (result.success()) {
                    Quiz saved = null;
                    if (result.body() != null && result.body().has("quiz")) {
                        saved = parseQuiz(result.body().getAsJsonObject("quiz"));
                    }
                    showCreatePane(false);
                    clearCreateForm();
                    editingQuiz = null;
                    loadQuizzes();
                    if (editing) {
                        alert("Quiz Updated", result.message().isBlank()
                                ? "Quiz schedule and details saved."
                                : result.message());
                    } else if (saved != null) {
                        openReview(saved);
                    } else {
                        alert("Quiz Created", result.message().isBlank()
                                ? "Quiz created. Add questions in the Questions tab, then publish it."
                                : result.message());
                    }
                } else {
                    lblCreateFeedback.setText(result.message().isBlank()
                            ? (editing ? "Could not update this quiz." : "Could not create this quiz.")
                            : result.message());
                }
            });
        }, editing ? "update-quiz" : "create-quiz").start();
    }

    private void showCreatePane(boolean showCreate) {
        createPane.setVisible(showCreate);
        createPane.setManaged(showCreate);
        if (showCreate) {
            reviewPane.setVisible(false);
            reviewPane.setManaged(false);
            listPane.setVisible(false);
            listPane.setManaged(false);
        } else if (!reviewPane.isVisible()) {
            listPane.setVisible(true);
            listPane.setManaged(true);
        }
    }

    private void showReviewPane(boolean showReview) {
        reviewPane.setVisible(showReview);
        reviewPane.setManaged(showReview);
        if (showReview) {
            createPane.setVisible(false);
            createPane.setManaged(false);
            listPane.setVisible(false);
            listPane.setManaged(false);
        } else {
            listPane.setVisible(true);
            listPane.setManaged(true);
        }
    }

    @FXML
    private void closeReview() {
        reviewingQuiz = null;
        showReviewPane(false);
        loadQuizzes();
    }

    @FXML
    private void publishFromReview() {
        if (reviewingQuiz != null) {
            publishQuiz(reviewingQuiz);
        }
    }

    @FXML
    private void editFromReview() {
        if (reviewingQuiz != null) {
            openEditQuiz(reviewingQuiz);
        }
    }

    private void populateCreateForm() {
        cmbCategory.setItems(FXCollections.observableArrayList(formCategories));
        cmbGroup.setItems(FXCollections.observableArrayList(formGroups));
        if (!formCategories.isEmpty()) {
            cmbCategory.getSelectionModel().selectFirst();
        }
        if (!formGroups.isEmpty()) {
            cmbGroup.getSelectionModel().selectFirst();
        }
        cmbGroup.setDisable(false);
        cmbGroup.setPromptText("Select group");

        LocalDateTime now = LocalDateTime.now().withSecond(0).withNano(0);
        if (txtStartTime.getText() == null || txtStartTime.getText().isBlank()) {
            txtStartTime.setText(now.plusHours(1).format(LOCAL_INPUT));
        }
        if (txtEndTime.getText() == null || txtEndTime.getText().isBlank()) {
            txtEndTime.setText(now.plusHours(2).format(LOCAL_INPUT));
        }
        if (txtTitle.getText() == null) {
            txtTitle.setText("");
        }
    }

    private void clearCreateForm() {
        editingQuiz = null;
        txtTitle.clear();
        txtDescription.clear();
        lblCreateFeedback.setText("");
        spnDuration.getValueFactory().setValue(15);
        spnParticipation.getValueFactory().setValue(2);
        LocalDateTime now = LocalDateTime.now().withSecond(0).withNano(0);
        txtStartTime.setText(now.plusHours(1).format(LOCAL_INPUT));
        txtEndTime.setText(now.plusHours(2).format(LOCAL_INPUT));
        btnSaveQuiz.setDisable(false);
        btnSaveQuiz.setText("Create Quiz");
        if (lblFormTitle != null) {
            lblFormTitle.setText("Create New Quiz");
        }
        if (lblFormSubtitle != null) {
            lblFormSubtitle.setText("Draft a quiz, add questions next, then publish when ready.");
        }
        setAssignmentFieldsEnabled(true);
    }

    private void refreshFormOptions(Runnable then) {

        new Thread(() -> {
            formCategories.clear();
            formGroups.clear();

            ApiClient.getQuizCategories().ifPresent(categoriesJson -> {
                JsonArray array = categoriesJson.getAsJsonArray("categories");
                if (array != null) {
                    for (JsonElement element : array) {
                        JsonObject item = element.getAsJsonObject();
                        QuizCategory category = new QuizCategory();
                        category.setId(item.get("id").getAsInt());
                        category.setCategoryName(item.get("name").getAsString());
                        formCategories.add(category);
                    }
                }
            });

            ApiClient.getManagedQuizzes().ifPresent(json -> {
                if (json.has("groups") && json.get("groups").isJsonArray()) {
                    for (JsonElement element : json.getAsJsonArray("groups")) {
                        JsonObject item = element.getAsJsonObject();
                        formGroups.add(new Group(
                                item.get("id").getAsInt(),
                                item.get("name").getAsString(),
                                "",
                                "Active",
                                0,
                                "",
                                0,
                                0,
                                ""
                        ));
                    }
                }
                // Fallback categories from quizzes payload if dedicated endpoint failed.
                if (formCategories.isEmpty() && json.has("categories") && json.get("categories").isJsonArray()) {
                    for (JsonElement element : json.getAsJsonArray("categories")) {
                        JsonObject item = element.getAsJsonObject();
                        QuizCategory category = new QuizCategory();
                        category.setId(item.get("id").getAsInt());
                        category.setCategoryName(item.get("name").getAsString());
                        formCategories.add(category);
                    }
                }
            });

            then.run();
        }, "quiz-form-options").start();
    }

    private void loadQuizzes() {

        new Thread(() -> ApiClient.getManagedQuizzes().ifPresentOrElse(json -> Platform.runLater(() -> {
            List<Quiz> quizzes = parseQuizzes(json.getAsJsonArray("quizzes"));
            tblQuizzes.setItems(FXCollections.observableArrayList(quizzes));
            quizCountLabel.setText(String.valueOf(json.get("count").getAsInt()));
            cacheFormOptions(json);
        }), () -> Platform.runLater(() -> {
            tblQuizzes.setItems(FXCollections.observableArrayList());
            quizCountLabel.setText("0");
        }))).start();
    }

    private void cacheFormOptions(JsonObject json) {
        formCategories.clear();
        formGroups.clear();
        if (json.has("categories") && json.get("categories").isJsonArray()) {
            for (JsonElement element : json.getAsJsonArray("categories")) {
                JsonObject item = element.getAsJsonObject();
                QuizCategory category = new QuizCategory();
                category.setId(item.get("id").getAsInt());
                category.setCategoryName(item.get("name").getAsString());
                formCategories.add(category);
            }
        }
        if (json.has("groups") && json.get("groups").isJsonArray()) {
            for (JsonElement element : json.getAsJsonArray("groups")) {
                JsonObject item = element.getAsJsonObject();
                formGroups.add(new Group(
                        item.get("id").getAsInt(),
                        item.get("name").getAsString(),
                        "",
                        "Active",
                        0,
                        "",
                        0,
                        0,
                        ""
                ));
            }
        }
    }

    private List<Quiz> parseQuizzes(JsonArray array) {
        List<Quiz> quizzes = new ArrayList<>();
        for (JsonElement element : array) {
            quizzes.add(parseQuiz(element.getAsJsonObject()));
        }
        return quizzes;
    }

    private Quiz parseQuiz(JsonObject item) {
        Quiz quiz = new Quiz();
        quiz.setId(item.get("id").getAsInt());
        quiz.setTitle(item.get("title").getAsString());
        if (item.has("category_id") && !item.get("category_id").isJsonNull()) {
            quiz.setCategoryId(item.get("category_id").getAsInt());
        }
        if (item.has("category_name") && !item.get("category_name").isJsonNull()) {
            quiz.setCategoryName(item.get("category_name").getAsString());
        }
        if (item.has("group_id") && !item.get("group_id").isJsonNull()) {
            quiz.setGroupId(item.get("group_id").getAsInt());
        }
        if (item.has("group_name") && !item.get("group_name").isJsonNull()) {
            quiz.setGroupName(item.get("group_name").getAsString());
        }
        if (item.has("questions_count")) {
            quiz.setQuestionsCount(item.get("questions_count").getAsInt());
        }
        if (item.has("maximum_marks")) {
            quiz.setMaximumMarks(item.get("maximum_marks").getAsInt());
        }
        if (item.has("duration")) {
            quiz.setDuration(item.get("duration").getAsInt());
        }
        if (item.has("participation_marks")) {
            quiz.setParticipationMarks(item.get("participation_marks").getAsInt());
        }
        if (item.has("start_time_iso") && !item.get("start_time_iso").isJsonNull()) {
            quiz.setStartDate(item.get("start_time_iso").getAsString());
        } else if (item.has("start_time") && !item.get("start_time").isJsonNull()) {
            quiz.setStartDate(item.get("start_time").getAsString());
        }
        if (item.has("end_time_iso") && !item.get("end_time_iso").isJsonNull()) {
            quiz.setEndDate(item.get("end_time_iso").getAsString());
        } else if (item.has("end_time") && !item.get("end_time").isJsonNull()) {
            quiz.setEndDate(item.get("end_time").getAsString());
        }
        if (item.has("lifecycle_status")) {
            quiz.setLifecycleStatus(item.get("lifecycle_status").getAsString());
        }
        if (item.has("is_published")) {
            quiz.setPublished(item.get("is_published").getAsBoolean());
        }
        if (item.has("can_publish")) {
            quiz.setCanPublish(item.get("can_publish").getAsBoolean());
        }
        if (item.has("can_delete")) {
            quiz.setCanDelete(item.get("can_delete").getAsBoolean());
        }
        if (item.has("can_edit_schedule")) {
            quiz.setCanEditSchedule(item.get("can_edit_schedule").getAsBoolean());
        } else {
            quiz.setCanEditSchedule(true);
        }
        if (item.has("can_edit_assignment")) {
            quiz.setCanEditAssignment(item.get("can_edit_assignment").getAsBoolean());
        } else {
            quiz.setCanEditAssignment(!quiz.isPublished());
        }
        if (item.has("description") && !item.get("description").isJsonNull()) {
            quiz.setDescription(item.get("description").getAsString());
        }
        return quiz;
    }

    private List<Question> parseReviewQuestions(JsonArray array) {
        List<Question> questions = new ArrayList<>();
        if (array == null) {
            return questions;
        }
        for (JsonElement element : array) {
            JsonObject item = element.getAsJsonObject();
            Question question = new Question();
            question.setId(item.get("id").getAsInt());
            question.setQuizId(item.get("quiz_id").getAsInt());
            question.setQuizTitle(item.has("quiz_title") ? item.get("quiz_title").getAsString() : "");
            question.setQuestion(item.get("question").getAsString());
            question.setMarks(item.get("marks").getAsInt());
            question.setCorrectAnswer(item.get("correct_answer").getAsString());

            JsonArray options = item.getAsJsonArray("options");
            if (options != null) {
                if (options.size() > 0) {
                    question.setOptionA(options.get(0).getAsJsonObject().get("text").getAsString());
                }
                if (options.size() > 1) {
                    question.setOptionB(options.get(1).getAsJsonObject().get("text").getAsString());
                }
                if (options.size() > 2) {
                    question.setOptionC(options.get(2).getAsJsonObject().get("text").getAsString());
                }
                if (options.size() > 3) {
                    question.setOptionD(options.get(3).getAsJsonObject().get("text").getAsString());
                }
            }
            questions.add(question);
        }
        return questions;
    }

    private TableCell<Quiz, Void> actionsCell() {
        return new TableCell<>() {
            private final HBox box = new HBox(6);
            private final Button editButton = new Button("Edit");
            private final Button reviewButton = new Button("Review");
            private final Button publishButton = new Button("Publish");
            private final Button deleteButton = new Button("Delete");
            private final Label disabledPublish = new Label("Add questions first");

            {
                editButton.getStyleClass().addAll("btn-outline", "btn-sm");
                reviewButton.getStyleClass().addAll("btn-outline", "btn-sm");
                publishButton.getStyleClass().addAll("btn-primary", "btn-sm");
                deleteButton.getStyleClass().addAll("btn-danger", "btn-sm");
                disabledPublish.getStyleClass().add("dashboard-subtitle");
                box.setAlignment(Pos.CENTER_LEFT);

                editButton.setOnAction(event -> {
                    Quiz quiz = getTableView().getItems().get(getIndex());
                    if (quiz != null) {
                        openEditQuiz(quiz);
                    }
                });
                reviewButton.setOnAction(event -> {
                    Quiz quiz = getTableView().getItems().get(getIndex());
                    if (quiz != null) {
                        showReview(quiz);
                    }
                });
                publishButton.setOnAction(event -> {
                    Quiz quiz = getTableView().getItems().get(getIndex());
                    if (quiz != null) {
                        publishQuiz(quiz);
                    }
                });
                deleteButton.setOnAction(event -> {
                    Quiz quiz = getTableView().getItems().get(getIndex());
                    if (quiz != null) {
                        deleteQuiz(quiz);
                    }
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                box.getChildren().clear();
                if (empty) {
                    setGraphic(null);
                    return;
                }

                Quiz quiz = getTableView().getItems().get(getIndex());
                if (quiz.isCanEditSchedule()) {
                    box.getChildren().add(editButton);
                }
                box.getChildren().add(reviewButton);
                if (!quiz.isPublished()) {
                    if (quiz.isCanPublish()) {
                        box.getChildren().add(publishButton);
                    } else if (quiz.getQuestionsCount() <= 0) {
                        box.getChildren().add(disabledPublish);
                    }
                }
                if (quiz.isCanDelete()) {
                    box.getChildren().add(deleteButton);
                }
                setGraphic(box);
            }
        };
    }

    private void showReview(Quiz quiz) {
        openReview(quiz);
    }

    private void openReview(Quiz quiz) {
        reviewingQuiz = quiz;
        showReviewPane(true);
        applyReviewHeader(quiz);
        tblReviewQuestions.getItems().clear();


        new Thread(() -> ApiClient.getManagedQuiz(quiz.getId()).ifPresentOrElse(json -> {
            Quiz fresh = parseQuiz(json.getAsJsonObject("quiz"));
            List<Question> questions = parseReviewQuestions(json.getAsJsonArray("questions"));
            Platform.runLater(() -> {
                if (reviewingQuiz == null || reviewingQuiz.getId() != quiz.getId()) {
                    return;
                }
                reviewingQuiz = fresh;
                applyReviewHeader(fresh);
                tblReviewQuestions.setItems(FXCollections.observableArrayList(questions));
            });
        }, () -> Platform.runLater(() ->
                tblReviewQuestions.setItems(FXCollections.observableArrayList()))), "quiz-review").start();
    }

    private void applyReviewHeader(Quiz quiz) {
        lblReviewTitle.setText(quiz.getTitle());
        lblReviewDescription.setText(
                quiz.getDescription() == null || quiz.getDescription().isBlank()
                        ? "No description provided."
                        : quiz.getDescription());
        lblReviewCategory.setText("Title: " + safe(quiz.getCategoryName()));
        lblReviewGroup.setText("Group: " + safe(quiz.getGroupName()));
        lblReviewStatus.setText("Status: " + safe(quiz.getLifecycleStatus()));
        lblReviewDuration.setText(quiz.getDuration() + " min");
        lblReviewMarks.setText("Max: " + quiz.getMaximumMarks());
        btnReviewPublish.setDisable(quiz.isPublished() || !quiz.isCanPublish());
        btnReviewPublish.setText(quiz.isPublished() ? "Published" : "Publish Quiz");
        if (btnReviewEdit != null) {
            btnReviewEdit.setDisable(!quiz.isCanEditSchedule());
        }
    }

    private void publishQuiz(Quiz quiz) {

        new Thread(() -> {
            ApiClient.MutationResult result = ApiClient.publishQuiz(quiz.getId());
            Platform.runLater(() -> {
                if (result.success()) {
                    alert("Published", result.message());
                    if (reviewPane.isVisible()) {
                        openReview(quiz);
                    }
                    loadQuizzes();
                } else {
                    alert("Publish Failed", result.message().isBlank()
                            ? "Could not publish this quiz."
                            : result.message());
                }
            });
        }).start();
    }

    private void deleteQuiz(Quiz quiz) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                "Delete this draft quiz?", ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText(null);
        if (confirm.showAndWait().orElse(ButtonType.NO) != ButtonType.YES) {
            return;
        }


        new Thread(() -> {
            ApiClient.MutationResult result = ApiClient.deleteQuiz(quiz.getId());
            Platform.runLater(() -> {
                if (result.success()) {
                    loadQuizzes();
                } else {
                    alert("Delete Failed", result.message().isBlank()
                            ? "Could not delete this quiz."
                            : result.message());
                }
            });
        }).start();
    }

    private static String safe(String value) {
        return value == null || value.isBlank() ? "—" : value;
    }

    private void alert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}
