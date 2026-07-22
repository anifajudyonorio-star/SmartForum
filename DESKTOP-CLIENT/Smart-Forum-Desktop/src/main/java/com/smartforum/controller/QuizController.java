package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.dao.QuizDAO;
import com.smartforum.model.Quiz;
import com.smartforum.util.ApiSupport;

import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;

import java.util.ArrayList;
import java.util.List;

public class QuizController {

    @FXML private Label quizCountLabel;
    @FXML private TableView<Quiz> tblQuizzes;
    @FXML private TableColumn<Quiz, String> colTitle;
    @FXML private TableColumn<Quiz, String> colCategory;
    @FXML private TableColumn<Quiz, String> colGroup;
    @FXML private TableColumn<Quiz, Integer> colQuestions;
    @FXML private TableColumn<Quiz, Integer> colMaximumMarks;
    @FXML private TableColumn<Quiz, Integer> colDuration;
    @FXML private TableColumn<Quiz, String> colStatus;
    @FXML private TableColumn<Quiz, Void> colActions;

    private final QuizDAO quizDAO = new QuizDAO();

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
        loadQuizzes();
    }

    private void loadQuizzes() {
        if (ApiSupport.useApi()) {
            new Thread(() -> ApiClient.getManagedQuizzes().ifPresentOrElse(json -> Platform.runLater(() -> {
                List<Quiz> quizzes = parseQuizzes(json.getAsJsonArray("quizzes"));
                tblQuizzes.setItems(FXCollections.observableArrayList(quizzes));
                quizCountLabel.setText(String.valueOf(json.get("count").getAsInt()));
            }), () -> Platform.runLater(this::loadOfflineQuizzes))).start();
            return;
        }

        loadOfflineQuizzes();
    }

    private void loadOfflineQuizzes() {
        List<Quiz> quizzes = quizDAO.getAllQuizzes().stream().map(quiz -> {
            quiz.setQuestionsCount(0);
            quiz.setMaximumMarks(quiz.getTotalMarks() + Math.max(0, quiz.getParticipationMarks()));
            quiz.setGroupName("Local");
            quiz.setLifecycleStatus("Draft");
            return quiz;
        }).toList();
        tblQuizzes.setItems(FXCollections.observableArrayList(quizzes));
        quizCountLabel.setText(String.valueOf(quizzes.size()));
    }

    private List<Quiz> parseQuizzes(JsonArray array) {
        List<Quiz> quizzes = new ArrayList<>();
        for (JsonElement element : array) {
            JsonObject item = element.getAsJsonObject();
            Quiz quiz = new Quiz();
            quiz.setId(item.get("id").getAsInt());
            quiz.setTitle(item.get("title").getAsString());
            if (item.has("category_name") && !item.get("category_name").isJsonNull()) {
                quiz.setCategoryName(item.get("category_name").getAsString());
            }
            if (item.has("group_name") && !item.get("group_name").isJsonNull()) {
                quiz.setGroupName(item.get("group_name").getAsString());
            }
            quiz.setQuestionsCount(item.get("questions_count").getAsInt());
            quiz.setMaximumMarks(item.get("maximum_marks").getAsInt());
            quiz.setDuration(item.get("duration").getAsInt());
            quiz.setLifecycleStatus(item.get("lifecycle_status").getAsString());
            quiz.setPublished(item.get("is_published").getAsBoolean());
            quiz.setCanPublish(item.get("can_publish").getAsBoolean());
            quiz.setCanDelete(item.get("can_delete").getAsBoolean());
            if (item.has("description") && !item.get("description").isJsonNull()) {
                quiz.setDescription(item.get("description").getAsString());
            }
            quizzes.add(quiz);
        }
        return quizzes;
    }

    private TableCell<Quiz, Void> actionsCell() {
        return new TableCell<>() {
            private final HBox box = new HBox(6);
            private final Button reviewButton = new Button("Review");
            private final Button publishButton = new Button("Publish");
            private final Button deleteButton = new Button("Delete");
            private final Label disabledPublish = new Label("Add questions first");

            {
                reviewButton.getStyleClass().addAll("btn-outline", "btn-sm");
                publishButton.getStyleClass().addAll("btn-primary", "btn-sm");
                deleteButton.getStyleClass().addAll("btn-danger", "btn-sm");
                disabledPublish.getStyleClass().add("dashboard-subtitle");
                box.setAlignment(Pos.CENTER_LEFT);

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
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Quiz Review");
        alert.setHeaderText(quiz.getTitle());
        alert.setContentText((quiz.getDescription() == null ? "" : quiz.getDescription() + "\n\n")
                + "Category: " + safe(quiz.getCategoryName()) + "\n"
                + "Group: " + safe(quiz.getGroupName()) + "\n"
                + "Questions: " + quiz.getQuestionsCount() + "\n"
                + "Maximum marks: " + quiz.getMaximumMarks() + "\n"
                + "Duration: " + quiz.getDuration() + " min\n"
                + "Status: " + safe(quiz.getLifecycleStatus()));
        alert.showAndWait();
    }

    private void publishQuiz(Quiz quiz) {
        if (ApiSupport.useApi()) {
            new Thread(() -> {
                ApiClient.MutationResult result = ApiClient.publishQuiz(quiz.getId());
                Platform.runLater(() -> {
                    if (result.success()) {
                        alert("Published", result.message());
                        loadQuizzes();
                    } else {
                        alert("Publish Failed", result.message().isBlank()
                                ? "Could not publish this quiz."
                                : result.message());
                    }
                });
            }).start();
            return;
        }

        alert("Offline Mode", "Publishing is available when connected to the SmartForum server.");
    }

    private void deleteQuiz(Quiz quiz) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                "Delete this draft quiz?", ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText(null);
        if (confirm.showAndWait().orElse(ButtonType.NO) != ButtonType.YES) {
            return;
        }

        if (ApiSupport.useApi()) {
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
            return;
        }

        if (quizDAO.deleteQuiz(quiz.getId())) {
            loadOfflineQuizzes();
        }
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
