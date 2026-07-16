package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.StatChartFactory;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.ArrayList;
import java.util.List;

public class GroupStatisticsController {

    @FXML private Label groupTitleLabel;
    @FXML private Label groupSubtitleLabel;
    @FXML private Label groupStatusBadge;
    @FXML private Label membersCountLabel;
    @FXML private Label topicsCountLabel;
    @FXML private Label postsCountLabel;
    @FXML private Label postsTodayLabel;
    @FXML private Label postsWeekLabel;
    @FXML private Label postsMonthLabel;
    @FXML private Label avgPostsLabel;
    @FXML private Label mostActiveUserLabel;
    @FXML private Label mostActiveTopicLabel;
    @FXML private VBox topUsersBox;
    @FXML private VBox monthlyChartBox;
    @FXML private VBox topicsChartBox;
    @FXML private VBox topicsBox;

    private ShellNavigator navigator;
    private int groupId;

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    public void loadGroup(int groupId) {
        this.groupId = groupId;
        if (ApiSupport.useApi()) {
            loadFromApi(groupId);
        } else {
            loadPreviewData(groupId);
        }
    }

    @FXML
    private void onBackToAllStatistics() {
        if (navigator != null) {
            navigator.showStatisticsOverview();
        }
    }

    @FXML
    private void onOpenGroup() {
        if (navigator != null && groupId > 0) {
            navigator.showGroup(groupId);
        }
    }

    private void loadFromApi(int groupId) {
        new Thread(() -> ApiClient.getGroupStatistics(groupId).ifPresentOrElse(
                json -> Platform.runLater(() -> populate(json)),
                () -> Platform.runLater(() -> loadPreviewData(groupId))
        )).start();
    }

    private void populate(JsonObject json) {
        JsonObject group = json.getAsJsonObject("group");
        groupId = group.get("id").getAsInt();
        groupTitleLabel.setText(group.get("name").getAsString());

        String creator = group.has("creator_name") && !group.get("creator_name").isJsonNull()
                ? group.get("creator_name").getAsString()
                : null;
        String subtitle = "Group statistics";
        if (creator != null) {
            subtitle += " • Created by: " + creator;
        }
        groupSubtitleLabel.setText(subtitle);
        groupStatusBadge.setText(group.has("status") ? group.get("status").getAsString() : "Active");

        int members = json.get("members_count").getAsInt();
        int topics = json.get("topics_count").getAsInt();
        int posts = json.get("posts_count").getAsInt();

        membersCountLabel.setText(String.valueOf(members));
        topicsCountLabel.setText(String.valueOf(topics));
        postsCountLabel.setText(String.valueOf(posts));
        postsTodayLabel.setText(String.valueOf(json.get("posts_today").getAsInt()));
        postsWeekLabel.setText(String.valueOf(json.get("posts_this_week").getAsInt()));
        postsMonthLabel.setText(String.valueOf(json.get("posts_this_month").getAsInt()));
        avgPostsLabel.setText(topics > 0 ? String.valueOf(Math.round((posts * 10.0) / topics) / 10.0) : "0");

        mostActiveUserLabel.setText(formatMostActive(json.get("most_active_user"), "posts_count", " posts"));
        if (json.has("most_active_topic") && !json.get("most_active_topic").isJsonNull()) {
            JsonObject topic = json.getAsJsonObject("most_active_topic");
            mostActiveTopicLabel.setText(topic.get("title").getAsString()
                    + " — " + topic.get("posts_count").getAsInt() + " posts");
        } else {
            mostActiveTopicLabel.setText("No topics yet.");
        }

        topUsersBox.getChildren().clear();
        JsonArray topUsers = json.getAsJsonArray("top_users");
        int index = 0;
        for (JsonElement element : topUsers) {
            JsonObject user = element.getAsJsonObject();
            addTopUserRow(index++, user.get("name").getAsString(), user.get("posts_count").getAsInt());
        }
        if (topUsers.isEmpty()) {
            Label empty = new Label("No posts in this group yet.");
            empty.getStyleClass().add("dashboard-subtitle");
            topUsersBox.getChildren().add(empty);
        }

        List<String> monthLabels = readStringList(json.getAsJsonArray("month_labels"));
        List<Integer> monthlyPosts = readIntList(json.getAsJsonArray("monthly_posts"));
        List<String> topicLabels = readStringList(json.getAsJsonArray("topic_labels"));
        List<Integer> topicPostCounts = readIntList(json.getAsJsonArray("topic_post_counts"));

        monthlyChartBox.getChildren().clear();
        monthlyChartBox.getChildren().add(StatChartFactory.createChartCard(
                "Posts Per Month (This Group)",
                StatChartFactory.buildAreaChart(monthLabels, monthlyPosts)));

        topicsChartBox.getChildren().clear();
        if (!topicLabels.isEmpty()) {
            topicsChartBox.getChildren().add(StatChartFactory.createChartCard(
                    "Posts by Topic",
                    StatChartFactory.buildBarChart(topicLabels, topicPostCounts, true)));
        } else {
            Label emptyChart = new Label("No topics in this group yet.");
            emptyChart.getStyleClass().add("dashboard-subtitle");
            topicsChartBox.getChildren().add(emptyChart);
        }

        topicsBox.getChildren().clear();
        JsonArray topicsArray = json.getAsJsonArray("topics");
        if (topicsArray.isEmpty()) {
            Label empty = new Label("No topics in this group.");
            empty.getStyleClass().add("dashboard-subtitle");
            topicsBox.getChildren().add(empty);
        } else {
            for (JsonElement element : topicsArray) {
                JsonObject topic = element.getAsJsonObject();
                addTopicRow(
                        topic.get("id").getAsInt(),
                        topic.get("title").getAsString(),
                        topic.get("posts_count").getAsInt());
            }
        }
    }

    private void loadPreviewData(int groupId) {
        this.groupId = groupId;
        groupTitleLabel.setText("OOP1");
        groupSubtitleLabel.setText("Group statistics • Created by: Demo Lecturer");
        groupStatusBadge.setText("Active");
        membersCountLabel.setText("3");
        topicsCountLabel.setText("2");
        postsCountLabel.setText("14");
        postsTodayLabel.setText("0");
        postsWeekLabel.setText("0");
        postsMonthLabel.setText("14");
        avgPostsLabel.setText("7.0");
        mostActiveUserLabel.setText("Demo Student — 7 posts");
        mostActiveTopicLabel.setText("Inheritance — 10 posts");

        topUsersBox.getChildren().clear();
        addTopUserRow(0, "Demo Student", 7);
        addTopUserRow(1, "Anifa Onorio", 2);

        monthlyChartBox.getChildren().clear();
        monthlyChartBox.getChildren().add(StatChartFactory.createChartCard(
                "Posts Per Month (This Group)",
                StatChartFactory.buildAreaChart(
                        List.of("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"),
                        List.of(0, 0, 0, 0, 0, 0, 14, 0, 0, 0, 0, 0))));

        topicsChartBox.getChildren().clear();
        topicsChartBox.getChildren().add(StatChartFactory.createChartCard(
                "Posts by Topic",
                StatChartFactory.buildBarChart(List.of("Inheritance", "Polymorphism"), List.of(10, 4), true)));

        topicsBox.getChildren().clear();
        addTopicRow(1, "Inheritance", 10);
        addTopicRow(2, "Polymorphism", 4);
    }

    private void addTopUserRow(int index, String name, int posts) {
        String medal = switch (index) {
            case 0 -> "🥇 ";
            case 1 -> "🥈 ";
            case 2 -> "🥉 ";
            default -> (index + 1) + ". ";
        };

        Label titleLabel = new Label(medal + name);
        titleLabel.getStyleClass().add("list-item-title");
        HBox.setHgrow(titleLabel, Priority.ALWAYS);

        Label badge = new Label(posts + " posts");
        badge.getStyleClass().add("badge-primary");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        HBox row = new HBox(8, titleLabel, spacer, badge);
        row.setAlignment(Pos.CENTER_LEFT);
        row.getStyleClass().add("list-item-row");
        topUsersBox.getChildren().add(row);
    }

    private void addTopicRow(int topicId, String title, int posts) {
        HBox row = new HBox(12);
        row.getStyleClass().add("stats-table-row");
        row.setAlignment(Pos.CENTER_LEFT);

        Label titleLabel = new Label(title);
        titleLabel.getStyleClass().add("list-item-title");
        titleLabel.setPrefWidth(280);
        titleLabel.setWrapText(true);

        Label postsBadge = new Label(String.valueOf(posts));
        postsBadge.getStyleClass().add("badge-primary");
        postsBadge.setPrefWidth(80);

        Button openBtn = new Button("Open");
        openBtn.getStyleClass().add("btn-outline");
        openBtn.setOnAction(event -> {
            if (navigator != null) {
                navigator.showTopic(topicId);
            }
        });

        row.getChildren().addAll(titleLabel, postsBadge, openBtn);
        topicsBox.getChildren().add(row);
    }

    private String formatMostActive(JsonElement element, String countField, String suffix) {
        if (element == null || element.isJsonNull()) {
            return "No activity yet.";
        }
        JsonObject obj = element.getAsJsonObject();
        return obj.get("name").getAsString() + " — " + obj.get(countField).getAsInt() + suffix;
    }

    private List<String> readStringList(JsonArray array) {
        List<String> values = new ArrayList<>();
        for (JsonElement element : array) {
            values.add(element.getAsString());
        }
        return values;
    }

    private List<Integer> readIntList(JsonArray array) {
        List<Integer> values = new ArrayList<>();
        for (JsonElement element : array) {
            values.add(element.getAsInt());
        }
        return values;
    }
}
