package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuestionDAO;
import com.smartforum.dao.QuizCategoryDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.dao.QuizAttemptDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizAttempt;
import com.smartforum.model.QuizCategory;
import com.smartforum.service.AppSession;
import com.smartforum.service.QuizSubmissionService;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.QuizSchedule;

import javafx.application.Platform;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.Modality;
import javafx.stage.Stage;

import java.net.URL;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

public class TakeQuizController {

    @FXML private Button btnAnnouncements;
    @FXML private Button btnQuizProgress;
    @FXML private Label lblFeedback;
    @FXML private VBox enrolledPane;
    @FXML private VBox enrollPane;
    @FXML private Label lblEnrolledInfo;
    @FXML private Button btnUnenroll;
    @FXML private ComboBox<QuizCategory> cmbEnrollCategory;
    @FXML private Button btnEnroll;
    @FXML private TableView<Quiz> tblAvailableQuizzes;
    @FXML private TableColumn<Quiz, String> colQuiz;
    @FXML private TableColumn<Quiz, Integer> colQuestions;
    @FXML private TableColumn<Quiz, Integer> colMaximumMarks;
    @FXML private TableColumn<Quiz, String> colScheduled;
    @FXML private TableColumn<Quiz, String> colDuration;
    @FXML private TableColumn<Quiz, String> colEndsAt;
    @FXML private TableColumn<Quiz, String> colStatus;
    @FXML private TableColumn<Quiz, Void> colAction;
    @FXML private Label tblEmptyLabel;

    private Runnable openAnnouncementsHandler;
    private Runnable openQuizProgressHandler;

    @FXML
    public void initialize() {
        colQuiz.setCellValueFactory(data -> new SimpleStringProperty(formatQuizCell(data.getValue())));
        colQuestions.setCellValueFactory(new javafx.scene.control.cell.PropertyValueFactory<>("questionsCount"));
        colMaximumMarks.setCellValueFactory(new javafx.scene.control.cell.PropertyValueFactory<>("maximumMarks"));
        colScheduled.setCellValueFactory(new javafx.scene.control.cell.PropertyValueFactory<>("startDate"));
        colDuration.setCellValueFactory(data -> new SimpleStringProperty(
                data.getValue() == null ? "" : data.getValue().getDuration() + " min"));
        colEndsAt.setCellValueFactory(new javafx.scene.control.cell.PropertyValueFactory<>("endDate"));
        colStatus.setCellFactory(column -> statusCell());
        colStatus.setCellValueFactory(data -> new SimpleStringProperty(
                data.getValue() == null ? "" : data.getValue().getStatusLabel()));
        colAction.setCellValueFactory(param -> null);
        colAction.setCellFactory(column -> actionCell());
        tblAvailableQuizzes.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);
        loadForCurrentStudent();
    }

    public void setOpenAnnouncementsHandler(Runnable handler) {
        this.openAnnouncementsHandler = handler;
    }

    public void setOpenQuizProgressHandler(Runnable handler) {
        this.openQuizProgressHandler = handler;
    }

    public void loadForStudent(String ignored) {
        loadForCurrentStudent();
    }

    public void loadForCurrentStudent() {
        if (ApiSupport.useApi()) {
            new Thread(() -> ApiClient.getStudentQuizzes().ifPresentOrElse(json -> Platform.runLater(() -> {
                populateEnrollment(json);
                List<Quiz> quizzes = parseQuizzes(json.getAsJsonArray("quizzes"));
                tblAvailableQuizzes.setItems(FXCollections.observableArrayList(quizzes));
                tblEmptyLabel.setText(json.has("enrolled_category") && !json.get("enrolled_category").isJsonNull()
                        ? "No quizzes are available right now."
                        : "Enroll in a quiz title to see available quizzes.");
            }), () -> Platform.runLater(this::loadOffline))).start();
            return;
        }

        loadOffline();
    }

    @FXML
    private void openAnnouncements() {
        if (openAnnouncementsHandler != null) {
            openAnnouncementsHandler.run();
        }
    }

    @FXML
    private void openQuizProgress() {
        if (openQuizProgressHandler != null) {
            openQuizProgressHandler.run();
        }
    }

    @FXML
    private void enroll() {
        QuizCategory category = cmbEnrollCategory.getValue();
        if (category == null) {
            alert("Validation", "Choose a quiz title to enroll.");
            return;
        }

        if (ApiSupport.useApi()) {
            new Thread(() -> {
                ApiClient.MutationResult result = ApiClient.enrollInQuizCategory(category.getId());
                Platform.runLater(() -> {
                    if (result.success()) {
                        showFeedback(result.message(), false);
                        loadForCurrentStudent();
                    } else {
                        alert("Enrollment Failed", result.message().isBlank()
                                ? "Could not enroll in the selected quiz title."
                                : result.message());
                    }
                });
            }).start();
            return;
        }

        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null) {
            alert("Access Denied", "Sign in as a student to enroll.");
            return;
        }
        if (new CategoryStudentDAO().enroll(category.getId(), user.getId(), user.getName())) {
            showFeedback("You are now enrolled in " + category.getCategoryName() + ".", false);
            loadForCurrentStudent();
        } else {
            alert("Enrollment Failed", "You could not be enrolled. You may already belong to another quiz title.");
        }
    }

    @FXML
    private void unenroll() {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                "Unenroll from this quiz title?", ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText(null);
        if (confirm.showAndWait().orElse(ButtonType.NO) != ButtonType.YES) {
            return;
        }

        if (ApiSupport.useApi()) {
            new Thread(() -> {
                ApiClient.MutationResult result = ApiClient.unenrollFromQuizCategory();
                Platform.runLater(() -> {
                    if (result.success()) {
                        showFeedback(result.message(), false);
                        loadForCurrentStudent();
                    } else {
                        alert("Unenroll Failed", result.message().isBlank()
                                ? "Could not unenroll from the quiz title."
                                : result.message());
                    }
                });
            }).start();
            return;
        }

        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user != null) {
            int categoryId = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
            if (categoryId >= 0 && new CategoryStudentDAO().unenroll(categoryId, user.getName())) {
                showFeedback("You have been unenrolled from the quiz title.", false);
                loadForCurrentStudent();
            }
        }
    }

    private void startQuiz(Quiz quiz) {
        if (quiz == null) {
            return;
        }

        if (ApiSupport.useApi()) {
            new Thread(() -> ApiClient.getStudentQuizSession(quiz.getId(), false).ifPresentOrElse(preview -> {
                Platform.runLater(() -> {
                    if (!confirmPreview(preview)) {
                        return;
                    }
                    new Thread(() -> ApiClient.getStudentQuizSession(quiz.getId(), true).ifPresentOrElse(session ->
                            Platform.runLater(() -> openApiQuizWindow(session)),
                            () -> Platform.runLater(() -> alert("Unable to Start",
                                    "Could not start this quiz session.")))).start();
                });
            }, () -> Platform.runLater(() -> alert("Quiz Unavailable",
                    "This quiz is not currently available.")))).start();
            return;
        }

        startOfflineQuiz(quiz);
    }

    private void loadOffline() {
        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null || !AppSession.getInstance().isStudent()) {
            tblAvailableQuizzes.setItems(FXCollections.observableArrayList());
            return;
        }

        QuizSubmissionService.FinalizationSummary finalization =
                new QuizSubmissionService().finalizeExpiredForCurrentStudent();
        if (finalization.getFinalized() > 0 || finalization.getFailed() > 0) {
            showFeedback(finalization.getFinalized() + " expired quiz attempt(s) were submitted from saved answers.", false);
        }

        cmbEnrollCategory.setItems(FXCollections.observableArrayList(new QuizCategoryDAO().getAllCategories()));
        int categoryId = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
        if (categoryId == -1) {
            enrolledPane.setVisible(false);
            enrolledPane.setManaged(false);
            enrollPane.setVisible(true);
            enrollPane.setManaged(true);
            tblAvailableQuizzes.setItems(FXCollections.observableArrayList());
            tblEmptyLabel.setText("Enroll in a quiz title to see available quizzes.");
            return;
        }

        QuizCategory enrolled = cmbEnrollCategory.getItems().stream()
                .filter(category -> category.getId() == categoryId)
                .findFirst()
                .orElse(null);
        if (enrolled != null) {
            lblEnrolledInfo.setText("You are enrolled in " + enrolled.getCategoryName()
                    + ". Only quizzes under this title are shown below.");
        }
        enrolledPane.setVisible(true);
        enrolledPane.setManaged(true);
        enrollPane.setVisible(false);
        enrollPane.setManaged(false);

        List<Quiz> quizzes = new QuizDAO().getQuizzesByCategory(categoryId).stream()
                .map(this::decorateOfflineQuiz)
                .toList();
        tblAvailableQuizzes.setItems(FXCollections.observableArrayList(quizzes));
        tblEmptyLabel.setText("No quizzes are available right now.");
    }

    private Quiz decorateOfflineQuiz(Quiz quiz) {
        ForumUser user = AppSession.getInstance().getCurrentUser();
        quiz.setQuestionsCount(new QuestionDAO().getQuestionsByQuizId(quiz.getId()).size());
        quiz.setMaximumMarks(quiz.getTotalMarks() + Math.max(0, quiz.getParticipationMarks()));
        boolean completed = user != null && new QuizAttemptDAO().hasCompletedResult(quiz.getId(), user.getId(), user.getName());
        quiz.setCompleted(completed);
        String availability = QuizSchedule.availability(quiz, LocalDateTime.now());
        quiz.setStatusLabel(completed ? "Completed" : switch (availability) {
            case "Available" -> "Available";
            case "Upcoming" -> "Upcoming";
            default -> availability;
        });
        quiz.setCanStart(!completed && "Available".equals(availability));
        return quiz;
    }

    private void populateEnrollment(JsonObject json) {
        cmbEnrollCategory.getItems().clear();
        JsonArray categories = json.getAsJsonArray("available_categories");
        for (JsonElement element : categories) {
            JsonObject category = element.getAsJsonObject();
            QuizCategory quizCategory = new QuizCategory();
            quizCategory.setId(category.get("id").getAsInt());
            quizCategory.setCategoryName(category.get("name").getAsString());
            cmbEnrollCategory.getItems().add(quizCategory);
        }

        if (json.has("enrolled_category") && !json.get("enrolled_category").isJsonNull()) {
            JsonObject enrolled = json.getAsJsonObject("enrolled_category");
            lblEnrolledInfo.setText("You are enrolled in " + enrolled.get("name").getAsString()
                    + ". Only quizzes under this title are shown below.");
            enrolledPane.setVisible(true);
            enrolledPane.setManaged(true);
            enrollPane.setVisible(false);
            enrollPane.setManaged(false);
        } else {
            enrolledPane.setVisible(false);
            enrolledPane.setManaged(false);
            enrollPane.setVisible(true);
            enrollPane.setManaged(true);
        }
    }

    private List<Quiz> parseQuizzes(JsonArray array) {
        List<Quiz> quizzes = new ArrayList<>();
        for (JsonElement element : array) {
            JsonObject item = element.getAsJsonObject();
            Quiz quiz = new Quiz();
            quiz.setId(item.get("id").getAsInt());
            quiz.setTitle(item.get("title").getAsString());
            quiz.setDescription(item.has("description") && !item.get("description").isJsonNull()
                    ? item.get("description").getAsString() : "");
            quiz.setQuestionsCount(item.get("questions_count").getAsInt());
            quiz.setMaximumMarks(item.get("maximum_marks").getAsInt());
            quiz.setStartDate(item.get("start_time").getAsString());
            quiz.setEndDate(item.get("end_time").getAsString());
            quiz.setDuration(item.get("duration").getAsInt());
            quiz.setCompleted(item.get("completed").getAsBoolean());
            quiz.setCanStart(item.get("can_start").getAsBoolean());
            quiz.setStatusLabel(item.get("status_label").getAsString());
            quizzes.add(quiz);
        }
        return quizzes;
    }

    private boolean confirmPreview(JsonObject preview) {
        JsonObject quiz = preview.getAsJsonObject("quiz");
        String message = quiz.get("title").getAsString() + "\n\n"
                + (quiz.has("description") ? quiz.get("description").getAsString() : "") + "\n\n"
                + "Scheduled: " + quiz.get("start_time").getAsString() + "\n"
                + "Ends: " + quiz.get("end_time").getAsString() + "\n"
                + "Duration: " + quiz.get("duration").getAsInt() + " min\n"
                + "Maximum score: " + quiz.get("question_marks").getAsInt()
                + " question marks + " + quiz.get("participation_marks").getAsInt()
                + " participation = " + quiz.get("maximum_marks").getAsInt();

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION, message, ButtonType.CANCEL, ButtonType.OK);
        confirm.setTitle("Start Quiz");
        confirm.setHeaderText("Ready to begin?");
        return confirm.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK;
    }

    private void openApiQuizWindow(JsonObject session) {
        try {
            JsonObject quizJson = session.getAsJsonObject("quiz");
            JsonObject attemptJson = session.getAsJsonObject("attempt");
            Quiz quiz = new Quiz();
            quiz.setId(quizJson.get("id").getAsInt());
            quiz.setTitle(quizJson.get("title").getAsString());
            quiz.setDuration(quizJson.get("duration").getAsInt());

            List<Question> questions = new ArrayList<>();
            for (JsonElement element : session.getAsJsonArray("questions")) {
                JsonObject questionJson = element.getAsJsonObject();
                Question question = new Question();
                question.setId(questionJson.get("id").getAsInt());
                question.setQuestion(questionJson.get("question").getAsString());
                question.setMarks(questionJson.get("marks").getAsInt());

                JsonArray options = questionJson.getAsJsonArray("options");
                if (options.size() > 0) {
                    question.setOptionA(options.get(0).getAsJsonObject().get("text").getAsString());
                    question.setOptionAId(options.get(0).getAsJsonObject().get("id").getAsInt());
                }
                if (options.size() > 1) {
                    question.setOptionB(options.get(1).getAsJsonObject().get("text").getAsString());
                    question.setOptionBId(options.get(1).getAsJsonObject().get("id").getAsInt());
                }
                if (options.size() > 2) {
                    question.setOptionC(options.get(2).getAsJsonObject().get("text").getAsString());
                    question.setOptionCId(options.get(2).getAsJsonObject().get("id").getAsInt());
                }
                if (options.size() > 3) {
                    question.setOptionD(options.get(3).getAsJsonObject().get("text").getAsString());
                    question.setOptionDId(options.get(3).getAsJsonObject().get("id").getAsInt());
                }
                questions.add(question);
            }

            QuizAttempt attempt = new QuizAttempt();
            attempt.setId(attemptJson.get("id").getAsInt());
            attempt.setQuizId(quiz.getId());
            attempt.setDeadlineAt(LocalDateTime.parse(attemptJson.get("deadline_at").getAsString()));

            ForumUser user = AppSession.getInstance().getCurrentUser();
            openQuizModal(quiz, questions, user, attempt, true);
            loadForCurrentStudent();
        } catch (Exception e) {
            alert("Error", "Failed to open quiz window: " + e.getMessage());
        }
    }

    private void startOfflineQuiz(Quiz selectedQuiz) {
        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null) {
            return;
        }

        Quiz freshQuiz = new QuizDAO().getById(selectedQuiz.getId());
        if (freshQuiz == null || !selectedQuiz.isCanStart()) {
            alert("Quiz Unavailable", "This quiz cannot be started right now.");
            loadForCurrentStudent();
            return;
        }

        List<Question> questions = new QuestionDAO().getQuestionsByQuizId(freshQuiz.getId());
        if (questions.isEmpty()) {
            alert("No Questions", "This quiz has no questions yet.");
            return;
        }

        int categoryId = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
        try {
            QuizAttempt attempt = new QuizAttemptDAO().startOrResume(freshQuiz, user.getId(), user.getName(), categoryId);
            if (attempt == null) {
                alert("Unable to Start", "Quiz attempt could not be created.");
                return;
            }
            openQuizModal(freshQuiz, questions, user, attempt, false);
            loadForCurrentStudent();
        } catch (Exception e) {
            alert("Unable to Start", e.getMessage());
        }
    }

    private void openQuizModal(Quiz quiz, List<Question> questions, ForumUser user, QuizAttempt attempt, boolean apiMode) {
        try {
            URL fxmlUrl = getClass().getResource("/fxml/QuizModal.fxml");
            FXMLLoader loader = new FXMLLoader(fxmlUrl);
            Parent root = loader.load();
            Scene scene = new Scene(root, 760, 580);
            URL cssUrl = getClass().getResource("/com/smartforum/css/app.css");
            if (cssUrl != null) {
                scene.getStylesheets().add(cssUrl.toExternalForm());
            }

            QuizModalController modal = loader.getController();
            modal.setup(quiz, questions, user, attempt, apiMode);

            Stage stage = new Stage();
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.setTitle(quiz.getTitle());
            stage.setMinWidth(720);
            stage.setMinHeight(560);
            if (tblAvailableQuizzes.getScene() != null && tblAvailableQuizzes.getScene().getWindow() != null) {
                stage.initOwner(tblAvailableQuizzes.getScene().getWindow());
            }
            stage.setScene(scene);
            stage.setOnCloseRequest(event -> {
                event.consume();
                alert("Quiz In Progress", "Use Submit Quiz to finish this attempt.");
            });
            stage.showAndWait();
        } catch (Exception e) {
            alert("Error", "Failed to open quiz window: " + e.getMessage());
        }
    }

    private TableCell<Quiz, String> statusCell() {
        return new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                    setGraphic(null);
                    getStyleClass().removeAll("badge-primary", "badge-muted", "badge-rank-gold");
                    return;
                }
                Label badge = new Label(item);
                badge.getStyleClass().add(switch (item) {
                    case "Completed" -> "badge-primary";
                    case "Upcoming" -> "badge-rank-gold";
                    default -> "badge-muted";
                });
                setGraphic(badge);
                setText(null);
                setAlignment(Pos.CENTER_LEFT);
            }
        };
    }

    private TableCell<Quiz, Void> actionCell() {
        return new TableCell<>() {
            private final Label takenLabel = new Label("Already taken");
            private final Label upcomingLabel = new Label("Not open yet");
            private final Button startButton = new Button("Start Quiz");

            {
                takenLabel.getStyleClass().add("dashboard-subtitle");
                upcomingLabel.getStyleClass().add("dashboard-subtitle");
                startButton.getStyleClass().addAll("btn-primary", "btn-sm");
                startButton.setOnAction(event -> {
                    Quiz quiz = getTableView().getItems().get(getIndex());
                    startQuiz(quiz);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                    return;
                }
                Quiz quiz = getTableView().getItems().get(getIndex());
                if (quiz.isCompleted()) {
                    setGraphic(takenLabel);
                } else if (!quiz.isCanStart()) {
                    setGraphic(upcomingLabel);
                } else {
                    setGraphic(startButton);
                }
                setAlignment(Pos.CENTER_LEFT);
            }
        };
    }

    private static String formatQuizCell(Quiz quiz) {
        if (quiz == null) {
            return "";
        }
        String description = quiz.getDescription();
        if (description == null || description.isBlank()) {
            return quiz.getTitle();
        }
        return quiz.getTitle() + "\n" + description;
    }

    private void showFeedback(String message, boolean error) {
        lblFeedback.setText(message);
        lblFeedback.getStyleClass().remove("quiz-recovery-notice");
        if (error) {
            lblFeedback.getStyleClass().add("announcement-alert-info");
        } else {
            lblFeedback.getStyleClass().add("quiz-recovery-notice");
        }
        lblFeedback.setManaged(true);
        lblFeedback.setVisible(true);
    }

    private void alert(String title, String msg) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(msg);
        alert.showAndWait();
    }
}
