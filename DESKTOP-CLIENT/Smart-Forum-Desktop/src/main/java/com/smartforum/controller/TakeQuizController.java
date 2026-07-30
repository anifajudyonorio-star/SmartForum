package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizAttempt;
import com.smartforum.model.QuizCategory;
import com.smartforum.service.AppSession;
import com.smartforum.service.QuizLaunchMonitor;
import com.smartforum.util.ApiDateTimes;

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

        new Thread(() -> ApiClient.getStudentQuizzes().ifPresentOrElse(json -> Platform.runLater(() -> {
            populateEnrollment(json);
            List<Quiz> quizzes = parseQuizzes(json.getAsJsonArray("quizzes"));
            tblAvailableQuizzes.setItems(FXCollections.observableArrayList(quizzes));
            tblEmptyLabel.setText(json.has("enrolled_category") && !json.get("enrolled_category").isJsonNull()
                    ? "No quizzes are available right now."
                    : "Enroll in a quiz title to see available quizzes.");
        }), () -> Platform.runLater(() -> {
            tblAvailableQuizzes.setItems(FXCollections.observableArrayList());
            tblEmptyLabel.setText("Could not load quizzes from the server.");
        }))).start();
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
    }

    @FXML
    private void unenroll() {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                "Unenroll from this quiz title?", ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText(null);
        if (confirm.showAndWait().orElse(ButtonType.NO) != ButtonType.YES) {
            return;
        }


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
    }

    private void startQuiz(Quiz quiz) {
        if (quiz == null) {
            return;
        }


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
        String description = quiz.has("description") && !quiz.get("description").isJsonNull()
                ? quiz.get("description").getAsString()
                : "";
        String message = quiz.get("title").getAsString() + "\n\n"
                + description + "\n\n"
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
            attempt.setDeadlineAt(ApiDateTimes.parseLocal(attemptJson.get("deadline_at").getAsString()));
            if (attemptJson.has("remaining_seconds") && !attemptJson.get("remaining_seconds").isJsonNull()) {
                attempt.setRemainingSeconds(attemptJson.get("remaining_seconds").getAsLong());
            }

            ForumUser user = AppSession.getInstance().getCurrentUser();
            openQuizModal(quiz, questions, user, attempt);
            loadForCurrentStudent();
        } catch (Exception e) {
            alert("Error", "Failed to open quiz window: " + e.getMessage());
        }
    }

    private void openQuizModal(Quiz quiz, List<Question> questions, ForumUser user, QuizAttempt attempt) {
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
            QuizLaunchMonitor.setQuizWindowOpen(true);
            modal.setup(quiz, questions, user, attempt);

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
            stage.setOnHidden(event -> QuizLaunchMonitor.setQuizWindowOpen(false));
            stage.showAndWait();
        } catch (Exception e) {
            QuizLaunchMonitor.setQuizWindowOpen(false);
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
