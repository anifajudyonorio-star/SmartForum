package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.ForumUser;
import com.smartforum.model.GroupAdminSummaryRow;
import com.smartforum.service.AppSession;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.GroupAdminDashboardSupport;
import javafx.application.Platform;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

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

    private ShellNavigator navigator;

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
    private void onAnnouncements() {
        if (navigator != null) {
            navigator.showAnnouncements();
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

        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadEmptyState();
        }
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
