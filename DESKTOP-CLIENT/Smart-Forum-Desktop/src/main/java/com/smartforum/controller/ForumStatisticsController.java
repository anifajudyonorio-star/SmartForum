package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.util.ApiSupport;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.chart.AreaChart;
import javafx.scene.chart.BarChart;
import javafx.scene.chart.CategoryAxis;
import javafx.scene.chart.NumberAxis;
import javafx.scene.chart.PieChart;
import javafx.scene.chart.XYChart;
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
        refresh();
    }

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    public void refresh() {
        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadPreviewData();
        }
    }

    private void loadFromApi() {
        new Thread(() -> ApiClient.getStatistics().ifPresentOrElse(json -> Platform.runLater(() -> {
            populateSummaryCards(json);
            populateGroupTable(json);
            populateTopUsers(json);
            populateMostActive(json);
            populateCharts(json);
        }), () -> Platform.runLater(this::loadPreviewData))).start();
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
        List<String> groupLabels = readStringList(json.getAsJsonArray("group_labels"));
        List<Integer> groupPosts = readIntList(json.getAsJsonArray("group_posts"));
        List<String> monthLabels = readStringList(json.getAsJsonArray("month_labels"));
        List<Integer> monthlyPosts = readIntList(json.getAsJsonArray("monthly_posts"));
        List<String> topicLabels = readStringList(json.getAsJsonArray("topic_labels"));
        List<Integer> topicCounts = readIntList(json.getAsJsonArray("topic_counts"));

        buildPostsPerGroupChart(groupLabels, groupPosts);
        buildPostsPerMonthChart(monthLabels, monthlyPosts);
        buildTopicsByGroupChart(topicLabels, topicCounts);
    }

    private void loadPreviewData() {
        groupCountBadge.setText("4 groups");
        totalGroupsLabel.setText("4");
        totalTopicsLabel.setText("5");
        totalPostsLabel.setText("15");
        totalUsersLabel.setText("6");
        postsTodayLabel.setText("0");
        postsWeekLabel.setText("0");
        postsMonthLabel.setText("15");
        mostActiveUserLabel.setText("Demo Student — 7 posts");
        mostActiveGroupLabel.setText("Introduction to Computer Science — 3 topics");
        mostActiveTopicLabel.setText("OOP Basics — 14 posts");

        groupSummariesBox.getChildren().clear();
        addSummaryRow(1, "Introduction to Computer Science", 3, 3, 1, "Super Admin");
        addSummaryRow(2, "OOP1", 3, 2, 14, "Demo Lecturer");
        addSummaryRow(3, "Technical Analysis", 2, 0, 0, "Demo Student");
        addSummaryRow(4, "Test Group", 1, 0, 0, "Demo Lecturer");

        topUsersBox.getChildren().clear();
        addTopUserRow(0, "Demo Student", 7);
        addTopUserRow(1, "Demo Lecturer", 6);
        addTopUserRow(2, "Anifa Onorio", 2);

        buildPostsPerGroupChart(
                List.of("OOP1", "Test Group", "Introduction to Computer Science", "Technical Analysis"),
                List.of(14, 0, 1, 0));
        buildPostsPerMonthChart(
                List.of("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"),
                List.of(0, 0, 0, 0, 0, 0, 16, 0, 0, 0, 0, 0));
        buildTopicsByGroupChart(
                List.of("OOP1", "Test Group", "Introduction to Computer Science", "Technical Analysis"),
                List.of(2, 0, 3, 0));
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
        viewBtn.getStyleClass().add("btn-primary-sm");
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

    private void buildPostsPerGroupChart(List<String> labels, List<Integer> values) {
        postsPerGroupChartBox.getChildren().clear();
        postsPerGroupChartBox.getChildren().add(createChartCard("Posts per Group", buildBarChart(labels, values)));
    }

    private void buildPostsPerMonthChart(List<String> labels, List<Integer> values) {
        postsPerMonthChartBox.getChildren().clear();
        postsPerMonthChartBox.getChildren().add(createChartCard("Posts Per Month", buildAreaChart(labels, values)));
    }

    private void buildTopicsByGroupChart(List<String> labels, List<Integer> values) {
        topicsByGroupChartBox.getChildren().clear();
        topicsByGroupChartBox.getChildren().add(createChartCard("Topics by Group", buildPieChart(labels, values)));
    }

    private VBox createChartCard(String title, Region chart) {
        Label heading = new Label(title);
        heading.getStyleClass().add("dashboard-card-title");

        HBox header = new HBox(heading);
        header.getStyleClass().add("dashboard-card-header");
        header.setAlignment(Pos.CENTER_LEFT);

        chart.setMinHeight(220);
        chart.setPrefHeight(240);
        chart.setMaxHeight(260);
        VBox.setVgrow(chart, Priority.ALWAYS);

        VBox card = new VBox(0, header, chart);
        card.setPadding(new Insets(0, 0, 12, 0));
        return card;
    }

    private BarChart<String, Number> buildBarChart(List<String> labels, List<Integer> values) {
        CategoryAxis xAxis = new CategoryAxis();
        NumberAxis yAxis = createValueAxis(values);

        BarChart<String, Number> chart = new BarChart<>(xAxis, yAxis);
        chart.setLegendVisible(false);
        chart.setAnimated(false);
        chart.setCategoryGap(12);
        chart.getStyleClass().add("stats-bar-chart");
        configurePlot(chart, true);

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Posts");
        for (int i = 0; i < labels.size(); i++) {
            series.getData().add(new XYChart.Data<>(labels.get(i), values.get(i)));
        }
        chart.getData().add(series);
        return chart;
    }

    private AreaChart<String, Number> buildAreaChart(List<String> labels, List<Integer> values) {
        CategoryAxis xAxis = new CategoryAxis();
        NumberAxis yAxis = createValueAxis(values);

        AreaChart<String, Number> chart = new AreaChart<>(xAxis, yAxis);
        chart.setLegendVisible(false);
        chart.setAnimated(false);
        chart.setCreateSymbols(true);
        chart.getStyleClass().add("stats-area-chart");
        configurePlot(chart, false);

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Posts");
        for (int i = 0; i < labels.size(); i++) {
            series.getData().add(new XYChart.Data<>(labels.get(i), values.get(i)));
        }
        chart.getData().add(series);
        return chart;
    }

    private PieChart buildPieChart(List<String> labels, List<Integer> values) {
        List<PieChart.Data> slices = new ArrayList<>();
        for (int i = 0; i < labels.size(); i++) {
            if (values.get(i) > 0) {
                slices.add(new PieChart.Data(labels.get(i), values.get(i)));
            }
        }
        if (slices.isEmpty()) {
            slices.add(new PieChart.Data("No topics", 1));
        }

        PieChart chart = new PieChart(FXCollections.observableArrayList(slices));
        chart.setLegendVisible(true);
        chart.setAnimated(false);
        chart.setLabelsVisible(false);
        chart.getStyleClass().add("stats-pie-chart");
        chart.setMinHeight(260);
        chart.setPrefHeight(280);
        return chart;
    }

    private NumberAxis createValueAxis(List<Integer> values) {
        int max = values.stream().mapToInt(Integer::intValue).max().orElse(0);
        double upper = max <= 0 ? 4 : Math.ceil(max * 1.15 / 2.0) * 2.0;
        NumberAxis yAxis = new NumberAxis(0, upper, upper <= 10 ? 2 : Math.max(2, upper / 5));
        yAxis.setForceZeroInRange(true);
        yAxis.setMinorTickVisible(false);
        yAxis.setTickMarkVisible(true);
        yAxis.setAutoRanging(false);
        return yAxis;
    }

    private void configurePlot(XYChart<String, Number> chart, boolean rotateLabels) {
        chart.setHorizontalGridLinesVisible(true);
        chart.setVerticalGridLinesVisible(false);
        chart.setAlternativeColumnFillVisible(false);
        chart.setAlternativeRowFillVisible(false);

        CategoryAxis xAxis = (CategoryAxis) chart.getXAxis();
        xAxis.setTickMarkVisible(false);
        if (rotateLabels) {
            xAxis.setTickLabelRotation(-35);
        }

        NumberAxis yAxis = (NumberAxis) chart.getYAxis();
        yAxis.setTickLabelGap(8);
    }

    private String formatMostActive(JsonElement element, String countField, String suffix) {
        if (element == null || element.isJsonNull()) {
            return "No data available.";
        }
        JsonObject obj = element.getAsJsonObject();
        String name = obj.has("name") ? obj.get("name").getAsString() : obj.get("title").getAsString();
        return name + " — " + obj.get(countField).getAsInt() + suffix;
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
