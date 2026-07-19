package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.util.ApiSupport;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;

public class AdminDashboardController {

    @FXML private Label totalUsersLabel;
    @FXML private Label totalGroupsLabel;
    @FXML private Label totalTopicsLabel;
    @FXML private Label totalPostsLabel;
    @FXML private javafx.scene.layout.VBox topGroupsBox;
    @FXML private javafx.scene.layout.VBox topTopicsBox;

    private ShellNavigator navigator;

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    @FXML
    private void onManageGroups() {
        if (navigator != null) {
            navigator.showGroups();
        }
    }

    @FXML
    private void onViewStatistics() {
        if (navigator != null) {
            navigator.showStatistics();
        }
    }

    @FXML
    private void initialize() {
        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadEmptyState();
        }
    }

    private void loadFromApi() {
        new Thread(() -> ApiClient.getDashboard().ifPresentOrElse(json -> {
            if (!"admin".equals(json.get("role").getAsString())) {
                Platform.runLater(this::loadEmptyState);
                return;
            }
            JsonObject stats = json.getAsJsonObject("stats");
            Platform.runLater(() -> {
                totalUsersLabel.setText(String.valueOf(stats.get("total_users").getAsInt()));
                totalGroupsLabel.setText(String.valueOf(stats.get("total_groups").getAsInt()));
                totalTopicsLabel.setText(String.valueOf(stats.get("total_topics").getAsInt()));
                totalPostsLabel.setText(String.valueOf(stats.get("total_posts").getAsInt()));
                topGroupsBox.getChildren().clear();
                topTopicsBox.getChildren().clear();

                JsonArray topGroups = stats.getAsJsonArray("top_groups");
                for (JsonElement element : topGroups) {
                    JsonObject group = element.getAsJsonObject();
                    addRankedRow(topGroupsBox, group.get("name").getAsString(),
                            String.valueOf(group.get("topics_count").getAsInt()), true);
                }

                JsonArray topTopics = stats.getAsJsonArray("top_topics");
                for (JsonElement element : topTopics) {
                    JsonObject topic = element.getAsJsonObject();
                    addRankedRow(topTopicsBox, topic.get("title").getAsString(),
                            String.valueOf(topic.get("posts_count").getAsInt()), false);
                }
            });
        }, () -> Platform.runLater(this::loadEmptyState))).start();
    }

    private void loadEmptyState() {
        totalUsersLabel.setText("0");
        totalGroupsLabel.setText("0");
        totalTopicsLabel.setText("0");
        totalPostsLabel.setText("0");
        topGroupsBox.getChildren().clear();
        topTopicsBox.getChildren().clear();
    }

    private void addRankedRow(javafx.scene.layout.VBox container, String title, String count, boolean primaryBadge) {
        Label titleLabel = new Label(title);
        titleLabel.getStyleClass().add("list-item-title");
        HBox.setHgrow(titleLabel, Priority.ALWAYS);

        Label badge = new Label(count);
        badge.getStyleClass().add(primaryBadge ? "badge-primary" : "badge-muted");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        HBox row = new HBox(8, titleLabel, spacer, badge);
        row.setAlignment(Pos.CENTER_LEFT);
        row.getStyleClass().add("list-item-row");
        container.getChildren().add(row);
    }
}
