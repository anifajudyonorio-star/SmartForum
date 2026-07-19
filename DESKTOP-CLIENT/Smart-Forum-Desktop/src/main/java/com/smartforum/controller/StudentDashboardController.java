package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.dao.QuizResultDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.GroupAdminSummaryRow;
import com.smartforum.model.QuizResult;
import com.smartforum.service.AppSession;
import com.smartforum.service.SyncStatusService;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.GroupAdminDashboardSupport;
import javafx.application.Platform;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.chart.LineChart;
import javafx.scene.chart.XYChart;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

import java.sql.SQLException;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.OffsetDateTime;
import java.time.format.DateTimeFormatter;
import java.time.format.DateTimeParseException;
import java.util.List;
import java.util.Locale;
import java.util.Optional;

public class StudentDashboardController {

    @FXML private Label welcomeTitleLabel;
    @FXML private Label myPostsLabel;
    @FXML private Label myTopicsLabel;
    @FXML private Label myRepliesLabel;
    @FXML private Label groupsLabel;
    @FXML private VBox groupAdminCard;
    @FXML private HBox groupAdminTitleBox;
    @FXML private TableView<GroupAdminSummaryRow> groupAdminTable;
    @FXML private TableColumn<GroupAdminSummaryRow, String> groupAdminGroupColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminMembersColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminTopicsColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminPostsColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Void> groupAdminActionColumn;
    @FXML private Button viewStatisticsBtn;
    @FXML private VBox recentTopicsBox;
    @FXML private VBox latestPostsBox;
    @FXML private Label quizzesAttemptedLabel;
    @FXML private Label averagePercentageLabel;
    @FXML private Label bestPercentageLabel;
    @FXML private Label latestPercentageLabel;
    @FXML private Label quizProgressMessage;
    @FXML private LineChart<String, Number> quizProgressChart;
    @FXML private TableView<QuizResult> quizAttemptTable;
    @FXML private TableColumn<QuizResult, String> quizTitleColumn;
    @FXML private TableColumn<QuizResult, String> quizScoreColumn;
    @FXML private TableColumn<QuizResult, String> quizPercentageColumn;
    @FXML private TableColumn<QuizResult, String> quizSubmittedColumn;

    @FXML private Label syncStatusCardLabel;
    @FXML private Label pendingCountCardLabel;
    @FXML private Label lastSyncCardLabel;
    @FXML private Button syncNowCardBtn;

    private ShellNavigator navigator;
    private final QuizResultDAO quizResultDAO = new QuizResultDAO();
    private static final DateTimeFormatter DISPLAY_DATE =
            DateTimeFormatter.ofPattern("dd MMM yyyy, HH:mm", Locale.ENGLISH);

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    @FXML
    private void onTakeQuiz() {
        if (navigator != null) {
            navigator.showQuizzes();
        }
    }

    @FXML
    private void onExploreGroups() {
        if (navigator != null) {
            navigator.showExploreGroups();
        }
    }

    @FXML
    private void onViewStatistics() {
        if (navigator != null) {
            navigator.showStatisticsOverview();
        }
    }

    @FXML
    private void initialize() {
        ForumUser currentUser = AppSession.getInstance().getCurrentUser();
        if (welcomeTitleLabel != null && currentUser != null) {
            welcomeTitleLabel.setText("Welcome back, " + currentUser.getName() + "!");
        }

        GroupAdminDashboardSupport.configureHeader(groupAdminTitleBox);
        GroupAdminDashboardSupport.configureViewStatisticsButton(viewStatisticsBtn);
        GroupAdminDashboardSupport.configureTable(
                groupAdminTable,
                groupAdminGroupColumn,
                groupAdminMembersColumn,
                groupAdminTopicsColumn,
                groupAdminPostsColumn,
                groupAdminActionColumn,
                groupId -> {
                    if (navigator != null) {
                        navigator.showGroupStatistics(groupId);
                    }
                }
        );

        SyncStatusService sync = SyncStatusService.getInstance();
        if (syncStatusCardLabel != null) syncStatusCardLabel.textProperty().bind(sync.statusTextProperty());
        if (pendingCountCardLabel != null) pendingCountCardLabel.textProperty().bind(sync.pendingCountProperty().asString());
        if (lastSyncCardLabel != null) lastSyncCardLabel.textProperty().bind(sync.lastSyncTextProperty());

        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadEmptyState();
        }
        configureQuizProgressTable();
        loadQuizProgress(currentUser);
    }

    private void configureQuizProgressTable() {
        quizTitleColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(safeTitle(cell.getValue())));
        quizScoreColumn.setCellValueFactory(cell -> {
            QuizResult result = cell.getValue();
            return new SimpleStringProperty(
                    result.getTotalScore() + " / " + result.getFinalPossibleMarks());
        });
        quizPercentageColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(formatPercentage(percentage(cell.getValue()))));
        quizSubmittedColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(formatSubmission(cell.getValue().getSubmittedAt())));
        quizProgressChart.setAnimated(false);
        quizProgressChart.setCreateSymbols(true);
        resetQuizProgress("Loading quiz progress...");
    }

    private void loadQuizProgress(ForumUser currentUser) {
        if (currentUser == null || currentUser.getId() <= 0) {
            resetQuizProgress("Quiz progress is unavailable for this session.");
            return;
        }

        Thread loader = new Thread(() -> {
            try {
                List<QuizResult> results =
                        quizResultDAO.getStudentProgress(currentUser.getId(), currentUser.getName());
                Platform.runLater(() -> showQuizProgress(results));
            } catch (SQLException | RuntimeException e) {
                Platform.runLater(() ->
                        resetQuizProgress("Quiz progress could not be loaded right now."));
            }
        }, "student-quiz-progress");
        loader.setDaemon(true);
        loader.start();
    }

    private void showQuizProgress(List<QuizResult> results) {
        quizAttemptTable.getItems().setAll(results);
        quizProgressChart.getData().clear();

        if (results.isEmpty()) {
            resetQuizProgress("No quiz attempts yet. Take a quiz to start tracking your progress.");
            return;
        }

        quizzesAttemptedLabel.setText(String.valueOf(results.size()));
        List<Double> validPercentages = results.stream()
                .map(this::percentage)
                .filter(value -> value != null)
                .toList();
        averagePercentageLabel.setText(validPercentages.isEmpty() ? "N/A" :
                formatPercentage(validPercentages.stream().mapToDouble(Double::doubleValue).average().orElse(0)));
        bestPercentageLabel.setText(validPercentages.isEmpty() ? "N/A" :
                formatPercentage(validPercentages.stream().mapToDouble(Double::doubleValue).max().orElse(0)));
        latestPercentageLabel.setText(formatPercentage(percentage(results.get(results.size() - 1))));

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Percentage");
        int attemptNumber = 1;
        for (QuizResult result : results) {
            Double value = percentage(result);
            if (value != null) {
                XYChart.Data<String, Number> point = new XYChart.Data<>(
                        attemptNumber + ". " + safeTitle(result), value);
                point.setExtraValue(result.getQuizId());
                series.getData().add(point);
            }
            attemptNumber++;
        }
        if (!series.getData().isEmpty()) {
            quizProgressChart.getData().add(series);
            quizProgressChart.setVisible(true);
            quizProgressChart.setManaged(true);
        } else {
            quizProgressChart.setVisible(false);
            quizProgressChart.setManaged(false);
        }
        quizProgressMessage.setText(series.getData().isEmpty()
                ? "Percentages are unavailable because these attempts have no possible marks."
                : "");
        quizProgressMessage.setVisible(!quizProgressMessage.getText().isEmpty());
        quizProgressMessage.setManaged(quizProgressMessage.isVisible());
    }

    private void resetQuizProgress(String message) {
        quizzesAttemptedLabel.setText("0");
        averagePercentageLabel.setText("N/A");
        bestPercentageLabel.setText("N/A");
        latestPercentageLabel.setText("N/A");
        quizAttemptTable.getItems().clear();
        quizProgressChart.getData().clear();
        quizProgressChart.setVisible(false);
        quizProgressChart.setManaged(false);
        quizProgressMessage.setText(message);
        quizProgressMessage.setVisible(true);
        quizProgressMessage.setManaged(true);
    }

    private Double percentage(QuizResult result) {
        int possibleMarks = result.getFinalPossibleMarks();
        if (possibleMarks <= 0) return null;
        return result.getTotalScore() * 100.0 / possibleMarks;
    }

    private String formatPercentage(Double percentage) {
        return percentage == null ? "N/A" : String.format(Locale.ENGLISH, "%.1f%%", percentage);
    }

    private String safeTitle(QuizResult result) {
        String title = result.getQuizTitle();
        return title == null || title.isBlank() ? "Quiz #" + result.getQuizId() : title;
    }

    private String formatSubmission(String submittedAt) {
        Optional<LocalDateTime> parsed = parseSubmissionDate(submittedAt);
        return parsed.map(value -> DISPLAY_DATE.format(value) + " • Submitted")
                .orElse("Date unavailable • Submitted");
    }

    private Optional<LocalDateTime> parseSubmissionDate(String value) {
        if (value == null || value.isBlank()) return Optional.empty();
        try {
            return Optional.of(LocalDateTime.parse(value, DateTimeFormatter.ISO_LOCAL_DATE_TIME));
        } catch (DateTimeParseException ignored) {
            try {
                return Optional.of(OffsetDateTime.parse(value).toLocalDateTime());
            } catch (DateTimeParseException ignoredOffset) {
                DateTimeFormatter[] legacyFormats = {
                    DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss"),
                    DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm")
                };
                for (DateTimeFormatter formatter : legacyFormats) {
                    try {
                        return Optional.of(LocalDateTime.parse(value, formatter));
                    } catch (DateTimeParseException ignoredLegacy) {
                        // Try the next supported legacy format.
                    }
                }
                try {
                    return Optional.of(LocalDate.parse(value, DateTimeFormatter.ISO_LOCAL_DATE).atStartOfDay());
                } catch (DateTimeParseException ignoredDate) {
                    return Optional.empty();
                }
            }
        }
    }

    @FXML
    private void onSyncNow() {
        if (syncNowCardBtn != null) {
            syncNowCardBtn.setDisable(true);
            syncNowCardBtn.setText("Syncing…");
        }
        SyncStatusService.getInstance().syncNow(() -> {
            if (syncNowCardBtn != null) {
                syncNowCardBtn.setDisable(false);
                syncNowCardBtn.setText("🔄  Sync Now");
            }
        });
    }

    private void loadFromApi() {
        new Thread(() -> {
            ApiClient.getDashboard().ifPresentOrElse(json -> {
                if (!"student".equals(json.get("role").getAsString())) {
                    Platform.runLater(this::loadEmptyState);
                    return;
                }
                JsonObject stats = json.getAsJsonObject("stats");
                ObservableList<GroupAdminSummaryRow> adminRows = GroupAdminDashboardSupport.rowsFromApi(json);
                Platform.runLater(() -> {
                    myPostsLabel.setText(String.valueOf(stats.get("my_posts").getAsInt()));
                    myTopicsLabel.setText(String.valueOf(stats.get("my_topics").getAsInt()));
                    myRepliesLabel.setText(String.valueOf(stats.get("my_replies").getAsInt()));
                    groupsLabel.setText(String.valueOf(stats.get("groups").getAsInt()));
                    populateGroupAdminCard(adminRows);
                    recentTopicsBox.getChildren().clear();
                    latestPostsBox.getChildren().clear();

                    JsonArray recentTopics = stats.getAsJsonArray("recent_topics");
                    for (JsonElement element : recentTopics) {
                        JsonObject topic = element.getAsJsonObject();
                        addTopicRow(
                                topic.get("title").getAsString(),
                                topic.get("group_name").getAsString(),
                                topic.get("created_at").getAsString()
                        );
                    }

                    JsonArray latestPosts = stats.getAsJsonArray("latest_posts");
                    for (JsonElement element : latestPosts) {
                        JsonObject post = element.getAsJsonObject();
                        addPostRow(
                                post.get("content").getAsString(),
                                post.get("created_at").getAsString()
                        );
                    }
                });
            }, () -> Platform.runLater(this::loadEmptyState));
        }).start();
    }

    private void loadEmptyState() {
        myPostsLabel.setText("0");
        myTopicsLabel.setText("0");
        myRepliesLabel.setText("0");
        groupsLabel.setText("0");
        populateGroupAdminCard(GroupAdminDashboardSupport.rowsFromApi(null));
        recentTopicsBox.getChildren().clear();
        latestPostsBox.getChildren().clear();
        recentTopicsBox.getChildren().add(emptyLabel("No recent topics."));
        latestPostsBox.getChildren().add(emptyLabel("No recent posts."));
    }

    private void populateGroupAdminCard(ObservableList<GroupAdminSummaryRow> rows) {
        GroupAdminDashboardSupport.populateTable(groupAdminTable, groupAdminCard, rows);
    }

    private Label emptyLabel(String text) {
        Label label = new Label(text);
        label.getStyleClass().add("list-item-meta");
        return label;
    }

    private void addTopicRow(String title, String group, String time) {
        Label titleLabel = new Label(title);
        titleLabel.getStyleClass().add("list-item-title");

        Label metaLabel = new Label(group + " • " + time);
        metaLabel.getStyleClass().add("list-item-meta");

        VBox row = new VBox(2, titleLabel, metaLabel);
        row.getStyleClass().add("list-item-row");
        recentTopicsBox.getChildren().add(row);
    }

    private void addPostRow(String content, String time) {
        Label contentLabel = new Label(content);
        contentLabel.getStyleClass().add("list-item-title");
        contentLabel.setWrapText(true);

        Label timeLabel = new Label(time);
        timeLabel.getStyleClass().add("list-item-meta");

        VBox row = new VBox(2, contentLabel, timeLabel);
        row.getStyleClass().add("list-item-row");
        latestPostsBox.getChildren().add(row);
    }
}
