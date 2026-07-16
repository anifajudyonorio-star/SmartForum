package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.service.AppSession;
import com.smartforum.util.ApiSupport;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.scene.layout.VBox;

public class StudentDashboardController {

    @FXML private Label welcomeTitleLabel;
    @FXML private Label myPostsLabel;
    @FXML private Label myTopicsLabel;
    @FXML private Label myRepliesLabel;
    @FXML private Label groupsLabel;
    @FXML private VBox recentTopicsBox;
    @FXML private VBox latestPostsBox;

    private ShellNavigator navigator;

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    @FXML
    private void onTakeQuiz() {
        if (navigator != null) navigator.showQuizzes();
    }

    @FXML
    private void initialize() {
        if (welcomeTitleLabel != null) {
            welcomeTitleLabel.setText("Welcome back, " + AppSession.getInstance().getCurrentUser().getName() + "!");
        }

        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadPreviewData();
        }
    }

    private void loadFromApi() {
        new Thread(() -> {
            ApiClient.getDashboard().ifPresentOrElse(json -> {
                if (!"student".equals(json.get("role").getAsString())) {
                    Platform.runLater(this::loadPreviewData);
                    return;
                }
                JsonObject stats = json.getAsJsonObject("stats");
                Platform.runLater(() -> {
                    myPostsLabel.setText(String.valueOf(stats.get("my_posts").getAsInt()));
                    myTopicsLabel.setText(String.valueOf(stats.get("my_topics").getAsInt()));
                    myRepliesLabel.setText(String.valueOf(stats.get("my_replies").getAsInt()));
                    groupsLabel.setText(String.valueOf(stats.get("groups").getAsInt()));
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
            }, () -> Platform.runLater(this::loadPreviewData));
        }).start();
    }

    private void loadPreviewData() {
        myPostsLabel.setText("12");
        myTopicsLabel.setText("3");
        myRepliesLabel.setText("8");
        groupsLabel.setText("2");

        addTopicRow("Introduction to Algorithms", "CS Year 2", "2 hours ago");
        addTopicRow("Database Normalization Help", "CS Year 2", "Yesterday");
        addTopicRow("Project Team Formation", "Software Engineering", "3 days ago");

        addPostRow("Thanks for the explanation!", "1 hour ago");
        addPostRow("Can we meet tomorrow?", "4 hours ago");
        addPostRow("I uploaded my notes.", "Yesterday");
    }

    private void addTopicRow(String title, String group, String time) {
        Label titleLabel = new Label(title);
        titleLabel.getStyleClass().add("list-item-title");

        Label metaLabel = new Label(group + " ΓÇó " + time);
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
