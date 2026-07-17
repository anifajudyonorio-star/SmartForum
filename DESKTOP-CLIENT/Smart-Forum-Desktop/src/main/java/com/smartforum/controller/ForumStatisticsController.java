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

public class ForumStatisticsController {

    @FXML private Label groupCountBadge;
    @FXML private Label totalGroupsLabel;
    @FXML private Label totalTopicsLabel;
    @FXML private Label totalPostsLabel;
    @FXML private Label totalUsersLabel;
    @FXML private Label postsTodayLabel;
    @FXML private Label postsWeekLabel;
    @FXML private Label postsMonthLabel;
    @FXML private Label mostActiveUserLabel;
    @FXML private Label mostActiveGroupLabel;
    @FXML private Label mostActiveTopicLabel;
    @FXML private VBox groupSummariesBox;
    @FXML private VBox topUsersBox;
    @FXML private VBox postsPerGroupChartBox;
    @FXML private VBox postsPerMonthChartBox;
    @FXML private VBox topicsByGroupChartBox;

    private ShellNavigator navigator;

    @FXML
    private void initialize() {
        mountEmptyCharts();
        refresh();
    }

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    public void refresh() {
        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            showEmptyState();
        }
    }

    private void loadFromApi() {
        new Thread(() -> ApiClient.getStatistics().ifPresentOrElse(json -> Platform.runLater(() -> {
            populateSummaryCards(json);
            populateGroupTable(json);
            populateTopUsers(json);
            populateMostActive(json);
            populateCharts(json);
        }), () -> Platform.runLater(this::showEmptyState))).start();
    }

    private void populateSummaryCards(JsonObject json) {
        totalGroupsLabel.setText(String.valueOf(json.get("total_groups").getAsInt()));
        totalTopicsLabel.setText(String.valueOf(json.get("total_topics").getAsInt()));
        totalPostsLabel.setText(String.valueOf(json.get("total_posts").getAsInt()));
        totalUsersLabel.setText(String.valueOf(json.get("total_users").getAsInt()));
        postsTodayLabel.setText(String.valueOf(json.get("posts_today").getAsInt()));
        postsWeekLabel.setText(String.valueOf(json.get("posts_this_week").getAsInt()));
        postsMonthLabel.setText(String.valueOf(json.get("posts_this_month").getAsInt()));
    }

    private void populateGroupTable(JsonObject json) {
        groupSummariesBox.getChildren().clear();
        JsonArray summaries = json.getAsJsonArray("group_summaries");
        groupCountBadge.setText(summaries.size() + (summaries.size() == 1 ? " group" : " groups"));

        for (JsonElement element : summaries) {
            JsonObject summary = element.getAsJsonObject();
            addSummaryRow(
                    summary.get("group_id").getAsInt(),
                    summary.get("group_name").getAsString(),
                    summary.get("members_count").getAsInt(),
                    summary.get("topics_count").getAsInt(),
                    summary.get("posts_count").getAsInt(),
                    summary.has("lecturer_name") && !summary.get("lecturer_name").isJsonNull()
                            ? summary.get("lecturer_name").getAsString()
                            : "—"
            );
        }
    }

    private void populateTopUsers(JsonObject json) {
        topUsersBox.getChildren().clear();
        JsonArray topUsers = json.getAsJsonArray("top_users");
        int index = 0;
        for (JsonElement element : topUsers) {
            JsonObject user = element.getAsJsonObject();
            addTopUserRow(index++, user.get("name").getAsString(), user.get("posts_count").getAsInt());
        }
    }

    private void populateMostActive(JsonObject json) {
        mostActiveUserLabel.setText(formatMostActive(json.get("most_active_user"), "posts_count", " posts"));
        mostActiveGroupLabel.setText(formatMostActive(json.get("most_active_group"), "topics_count", " topics"));
        if (json.has("most_active_topic") && !json.get("most_active_topic").isJsonNull()) {
            JsonObject topic = json.getAsJsonObject("most_active_topic");
            mostActiveTopicLabel.setText(topic.get("title").getAsString()
                    + " — " + topic.get("posts_count").getAsInt() + " posts");
        } else {
            mostActiveTopicLabel.setText("No data available.");
        }
    }

    private void populateCharts(JsonObject json) {
        List<String> groupLabels = readStringList(json, "group_labels");
        List<Integer> groupPosts = readIntList(json, "group_posts");
        List<String> monthLabels = readStringList(json, "month_labels");
        List<Integer> monthlyPosts = readIntList(json, "monthly_posts");
        List<String> topicLabels = readStringList(json, "topic_labels");
        List<Integer> topicCounts = readIntList(json, "topic_counts");

        StatChartFactory.mountChart(postsPerGroupChartBox,
                StatChartFactory.buildBarChart(groupLabels, groupPosts, groupLabels.size() > 4));

        StatChartFactory.mountChart(postsPerMonthChartBox,
                StatChartFactory.buildAreaChart(monthLabels, monthlyPosts));

        StatChartFactory.mountChart(topicsByGroupChartBox,
                StatChartFactory.buildPieChart(topicLabels, topicCounts));
    }

    private void mountEmptyCharts() {
        StatChartFactory.mountChart(postsPerGroupChartBox,
                StatChartFactory.buildBarChart(List.of(), List.of(), false));
        StatChartFactory.mountChart(postsPerMonthChartBox,
                StatChartFactory.buildAreaChart(List.of(), List.of()));
        StatChartFactory.mountChart(topicsByGroupChartBox,
                StatChartFactory.buildPieChart(List.of(), List.of()));
    }

    private void showEmptyState() {
        groupCountBadge.setText("0 groups");
        totalGroupsLabel.setText("0");
        totalTopicsLabel.setText("0");
        totalPostsLabel.setText("0");
        totalUsersLabel.setText("0");
        postsTodayLabel.setText("0");
        postsWeekLabel.setText("0");
        postsMonthLabel.setText("0");
        mostActiveUserLabel.setText("No statistics available.");
        mostActiveGroupLabel.setText("No statistics available.");
        mostActiveTopicLabel.setText("No statistics available.");
        groupSummariesBox.getChildren().clear();
        topUsersBox.getChildren().clear();
        mountEmptyCharts();
    }

    private void addSummaryRow(int groupId, String group, int members, int topics, int posts, String lecturer) {
        HBox row = new HBox(12);
        row.getStyleClass().add("stats-table-row");
        row.setAlignment(Pos.CENTER_LEFT);

        Label groupLabel = new Label(group);
        groupLabel.getStyleClass().add("list-item-title");
        groupLabel.setPrefWidth(160);
        groupLabel.setWrapText(true);

        Label membersLabel = new Label(String.valueOf(members));
        membersLabel.setPrefWidth(64);
        Label topicsLabel = new Label(String.valueOf(topics));
        topicsLabel.setPrefWidth(64);
        Label postsLabel = new Label(String.valueOf(posts));
        postsLabel.setPrefWidth(64);

        Label lecturerLabel = new Label(lecturer);
        lecturerLabel.getStyleClass().add("list-item-meta");
        lecturerLabel.setPrefWidth(120);
        lecturerLabel.setWrapText(true);

        Button viewBtn = new Button("View Stats");
        viewBtn.getStyleClass().addAll("btn-primary", "btn-sm");
        viewBtn.setOnAction(event -> {
            if (navigator != null) {
                navigator.showGroupStatistics(groupId);
            }
        });

        row.getChildren().addAll(groupLabel, membersLabel, topicsLabel, postsLabel, lecturerLabel, viewBtn);
        groupSummariesBox.getChildren().add(row);
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

        Label badge = new Label(posts + " Posts");
        badge.getStyleClass().add("badge-primary");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        HBox row = new HBox(8, titleLabel, spacer, badge);
        row.setAlignment(Pos.CENTER_LEFT);
        row.getStyleClass().add("list-item-row");
        topUsersBox.getChildren().add(row);
    }

    private String formatMostActive(JsonElement element, String countField, String suffix) {
        if (element == null || element.isJsonNull()) {
            return "No data available.";
        }
        JsonObject obj = element.getAsJsonObject();
        String name = obj.has("name") ? obj.get("name").getAsString() : obj.get("title").getAsString();
        return name + " — " + obj.get(countField).getAsInt() + suffix;
    }

    private List<String> readStringList(JsonObject json, String key) {
        if (!json.has(key) || json.get(key).isJsonNull()) {
            return List.of();
        }
        return readStringList(json.getAsJsonArray(key));
    }

    private List<Integer> readIntList(JsonObject json, String key) {
        if (!json.has(key) || json.get(key).isJsonNull()) {
            return List.of();
        }
        return readIntList(json.getAsJsonArray(key));
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
