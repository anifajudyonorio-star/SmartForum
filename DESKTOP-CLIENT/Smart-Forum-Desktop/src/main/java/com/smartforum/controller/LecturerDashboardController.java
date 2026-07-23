package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.dao.QuizResultDAO;
import com.smartforum.model.GroupAdminSummaryRow;
import com.smartforum.model.ParticipantRow;
import com.smartforum.model.QuizResult;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.ButtonStyles;
import com.smartforum.util.GroupAdminDashboardSupport;
import javafx.application.Platform;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ListChangeListener;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.Node;
import javafx.scene.chart.BarChart;
import javafx.scene.chart.PieChart;
import javafx.scene.chart.XYChart;
import javafx.scene.control.Button;
import javafx.scene.control.ComboBox;
import javafx.scene.control.Label;
import javafx.scene.control.ListCell;
import javafx.scene.control.TableCell;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;

public class LecturerDashboardController {

    @FXML private Label myGroupsLabel;
    @FXML private Label myTopicsLabel;
    @FXML private Label participantsCountLabel;
    @FXML private VBox groupAdminCard;
    @FXML private HBox groupAdminTitleBox;
    @FXML private TableView<GroupAdminSummaryRow> groupAdminTable;
    @FXML private TableColumn<GroupAdminSummaryRow, String> groupAdminGroupColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminMembersColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminTopicsColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminPostsColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Void> groupAdminActionColumn;
    @FXML private Button viewStatisticsBtn;
    @FXML private Button quizReportsBtn;
    @FXML private Button participationBtn;
    @FXML private Button viewAllReportsBtn;
    @FXML private TableView<ParticipantRow> participantsTable;
    @FXML private TableColumn<ParticipantRow, String> nameColumn;
    @FXML private TableColumn<ParticipantRow, Number> topicsColumn;
    @FXML private TableColumn<ParticipantRow, Number> postsColumn;
    @FXML private TableColumn<ParticipantRow, Number> repliesColumn;
    @FXML private TableColumn<ParticipantRow, Number> scoreColumn;

    @FXML private Label quizSubmissionsLabel;
    @FXML private Label quizStudentsLabel;
    @FXML private Label quizAverageLabel;
    @FXML private Label quizPassRateLabel;
    @FXML private Label legacyResultsAlert;
    @FXML private BarChart<String, Number> quizAverageChart;
    @FXML private PieChart scoreDistributionChart;
    @FXML private Label quizAverageEmptyLabel;
    @FXML private Label scoreDistributionEmptyLabel;
    @FXML private ComboBox<QuizFilterOption> quizFilter;
    @FXML private TableView<QuizResult> quizResultsTable;
    @FXML private TableColumn<QuizResult, String> quizStudentColumn;
    @FXML private TableColumn<QuizResult, String> quizTitleColumn;
    @FXML private TableColumn<QuizResult, String> quizStatusColumn;
    @FXML private TableColumn<QuizResult, String> quizScoreColumn;
    @FXML private TableColumn<QuizResult, String> quizPercentageColumn;

    private ShellNavigator navigator;
    private final QuizResultDAO quizResultDAO = new QuizResultDAO();
    private List<QuizResult> allQuizResults = List.of();
    private static final QuizFilterOption ALL_QUIZZES = new QuizFilterOption(null, "All quizzes");
    private static final int MAX_RECENT_RESULTS = 25;
    private static final double TABLE_ROW_HEIGHT = 34;
    private static final double TABLE_HEADER_HEIGHT = 30;
    private static final double TABLE_EMPTY_HEIGHT = 88;

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    @FXML
    private void onViewParticipation() {
        if (navigator != null) {
            navigator.showParticipation();
        }
    }

    @FXML
    private void onViewStatistics() {
        if (navigator != null) {
            navigator.showStatisticsOverview();
        }
    }

    @FXML
    private void onViewQuizReports() {
        if (navigator != null) {
            navigator.showQuizReports();
        }
    }

    @FXML
    private void initialize() {
        configureParticipantsTable();
        configureQuizResultsTable();
        configureQuizFilter();

        GroupAdminDashboardSupport.configureHeader(groupAdminTitleBox);
        ButtonStyles.applyOutlinePrimary(quizReportsBtn, true);
        ButtonStyles.applyPrimary(participationBtn, true);
        ButtonStyles.applyOutlinePrimary(viewAllReportsBtn, true);
        ButtonStyles.applyPrimary(viewStatisticsBtn, true);
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

        quizAverageChart.setAnimated(false);
        scoreDistributionChart.setAnimated(false);

        loadQuizProgress();

        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadEmptyState();
        }
    }

    private void configureParticipantsTable() {
        nameColumn.setCellValueFactory(new PropertyValueFactory<>("name"));
        topicsColumn.setCellValueFactory(new PropertyValueFactory<>("topics"));
        postsColumn.setCellValueFactory(new PropertyValueFactory<>("posts"));
        repliesColumn.setCellValueFactory(new PropertyValueFactory<>("replies"));
        scoreColumn.setCellValueFactory(new PropertyValueFactory<>("score"));
        scoreColumn.setCellFactory(column -> badgeCell());
        participantsTable.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);
        configureAutoHeightTable(participantsTable);
    }

    private void configureQuizResultsTable() {
        quizStudentColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue().getStudentName()));
        quizTitleColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue().getQuizTitle()));
        quizStatusColumn.setCellValueFactory(cell ->
                new SimpleStringProperty("Submitted"));
        quizScoreColumn.setCellValueFactory(param -> null);
        quizScoreColumn.setCellFactory(column -> finalScoreCell());
        quizPercentageColumn.setCellValueFactory(param -> null);
        quizPercentageColumn.setCellFactory(column -> percentageCell());
        quizResultsTable.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);
        configureAutoHeightTable(quizResultsTable);
    }

    private void configureAutoHeightTable(TableView<?> table) {
        table.setFixedCellSize(TABLE_ROW_HEIGHT);
        VBox.setVgrow(table, Priority.NEVER);
        table.getStyleClass().add("lecturer-dashboard-table");

        table.skinProperty().addListener((obs, oldSkin, newSkin) -> {
            if (newSkin != null) {
                Platform.runLater(() -> styleTableHeaderLabels(table));
            }
        });

        ListChangeListener<Object> rowListener = change -> {
            resizeTableToRows(table);
            Platform.runLater(() -> styleTableHeaderLabels(table));
        };
        table.itemsProperty().addListener((obs, oldItems, newItems) -> {
            if (oldItems != null) {
                oldItems.removeListener(rowListener);
            }
            if (newItems != null) {
                newItems.addListener(rowListener);
            }
            resizeTableToRows(table);
            Platform.runLater(() -> styleTableHeaderLabels(table));
        });
        resizeTableToRows(table);
    }

    private void styleTableHeaderLabels(TableView<?> table) {
        if (table == null) {
            return;
        }
        table.applyCss();
        table.layout();
        for (Node node : table.lookupAll(".column-header .label")) {
            node.setStyle("-fx-text-fill: #000000; -fx-font-weight: bold; -fx-font-size: 12px;");
        }
    }

    private void resizeTableToRows(TableView<?> table) {
        if (table == null) {
            return;
        }
        int rows = table.getItems() == null ? 0 : table.getItems().size();
        double height = rows == 0
                ? TABLE_EMPTY_HEIGHT
                : TABLE_HEADER_HEIGHT + (TABLE_ROW_HEIGHT * rows) + 2;
        table.setPrefHeight(height);
        table.setMinHeight(height);
        table.setMaxHeight(height);
    }

    private void configureQuizFilter() {
        quizFilter.setCellFactory(listView -> new ListCell<>() {
            @Override
            protected void updateItem(QuizFilterOption item, boolean empty) {
                super.updateItem(item, empty);
                setText(empty || item == null ? null : item.label());
            }
        });
        quizFilter.setButtonCell(new ListCell<>() {
            @Override
            protected void updateItem(QuizFilterOption item, boolean empty) {
                super.updateItem(item, empty);
                setText(empty || item == null ? null : item.label());
            }
        });
        quizFilter.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> applyQuizFilter());
    }

    private TableCell<ParticipantRow, Number> badgeCell() {
        return new TableCell<>() {
            @Override
            protected void updateItem(Number item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }
                Label badge = new Label(String.valueOf(item.intValue()));
                badge.getStyleClass().add("badge-primary");
                setGraphic(badge);
                setText(null);
            }
        };
    }

    private TableCell<QuizResult, String> finalScoreCell() {
        return new TableCell<>() {
            private final Label label = new Label();

            {
                label.getStyleClass().add("dashboard-subtitle");
            }

            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }
                QuizResult result = getTableRow().getItem();
                int possible = result.getFinalPossibleMarks();
                label.setText(possible > 0
                        ? result.getTotalScore() + " / " + possible
                        : result.getTotalScore() + " / snapshot unavailable");
                setGraphic(label);
                setText(null);
            }
        };
    }

    private TableCell<QuizResult, String> percentageCell() {
        return new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }
                Double percentage = scorePercentage(getTableRow().getItem());
                if (percentage == null) {
                    Label muted = new Label("Not comparable");
                    muted.getStyleClass().add("dashboard-subtitle");
                    setGraphic(muted);
                } else {
                    Label badge = new Label(formatPercentage(percentage));
                    badge.getStyleClass().add("badge-primary");
                    setGraphic(badge);
                }
                setText(null);
            }
        };
    }

    private void loadQuizProgress() {
        Thread loader = new Thread(() -> {
            try {
                List<QuizResult> results = quizResultDAO.getAllResults();
                Platform.runLater(() -> showQuizProgress(results));
            } catch (RuntimeException e) {
                Platform.runLater(() -> showQuizProgress(List.of()));
            }
        }, "lecturer-quiz-progress");
        loader.setDaemon(true);
        loader.start();
    }

    private void showQuizProgress(List<QuizResult> results) {
        allQuizResults = new ArrayList<>(results);
        populateQuizFilter();
        applyQuizFilter();
    }

    private void populateQuizFilter() {
        Map<Integer, String> quizzes = new LinkedHashMap<>();
        for (QuizResult result : allQuizResults) {
            quizzes.putIfAbsent(result.getQuizId(), result.getQuizTitle());
        }

        quizFilter.getItems().setAll(ALL_QUIZZES);
        quizzes.forEach((id, title) -> quizFilter.getItems().add(new QuizFilterOption(id, title)));
        quizFilter.getSelectionModel().select(ALL_QUIZZES);
    }

    private void applyQuizFilter() {
        QuizFilterOption selected = quizFilter.getSelectionModel().getSelectedItem();
        Integer selectedQuizId = selected == null ? null : selected.quizId();

        List<QuizResult> scoped = new ArrayList<>();
        for (QuizResult result : allQuizResults) {
            if (selectedQuizId == null || selectedQuizId.equals(result.getQuizId())) {
                scoped.add(result);
            }
        }

        List<QuizResult> recent = scoped.size() > MAX_RECENT_RESULTS
                ? scoped.subList(0, MAX_RECENT_RESULTS)
                : scoped;
        quizResultsTable.setItems(FXCollections.observableArrayList(recent));

        updateQuizSummary(scoped);
        updateQuizAverageChart(scoped);
        updateScoreDistributionChart(scoped);
    }

    private void updateQuizSummary(List<QuizResult> results) {
        quizSubmissionsLabel.setText(String.valueOf(results.size()));

        Set<String> students = new LinkedHashSet<>();
        double percentageTotal = 0;
        int comparable = 0;
        int passed = 0;
        for (QuizResult result : results) {
            students.add(result.getStudentId() == null
                    ? "legacy:" + result.getStudentName()
                    : "id:" + result.getStudentId());
            Double percentage = scorePercentage(result);
            if (percentage == null) {
                continue;
            }
            comparable++;
            percentageTotal += percentage;
            if (percentage >= 50) {
                passed++;
            }
        }

        quizStudentsLabel.setText(String.valueOf(students.size()));
        quizAverageLabel.setText(comparable == 0 ? "—" :
                String.format(Locale.ENGLISH, "%.1f%%", percentageTotal / comparable));
        quizPassRateLabel.setText(comparable == 0 ? "—" :
                String.format(Locale.ENGLISH, "%.0f%%", passed * 100.0 / comparable));

        int excluded = results.size() - comparable;
        if (excluded > 0) {
            legacyResultsAlert.setText(
                    "Percentage charts use only results with a saved final denominator. "
                            + excluded + " legacy result" + (excluded == 1 ? " is" : "s are")
                            + " excluded from averages and charts.");
            legacyResultsAlert.setVisible(true);
            legacyResultsAlert.setManaged(true);
        } else {
            legacyResultsAlert.setVisible(false);
            legacyResultsAlert.setManaged(false);
        }
    }

    private void updateQuizAverageChart(List<QuizResult> results) {
        Map<Integer, double[]> quizStats = new LinkedHashMap<>();
        Map<Integer, String> quizLabels = new LinkedHashMap<>();
        for (QuizResult result : results) {
            Double percentage = scorePercentage(result);
            if (percentage == null) {
                continue;
            }
            double[] stats = quizStats.computeIfAbsent(result.getQuizId(), key -> new double[2]);
            quizLabels.put(result.getQuizId(), result.getQuizTitle());
            stats[0] += percentage;
            stats[1]++;
        }

        if (quizStats.isEmpty()) {
            quizAverageChart.getData().clear();
            quizAverageChart.setVisible(false);
            quizAverageChart.setManaged(false);
            quizAverageEmptyLabel.setVisible(true);
            quizAverageEmptyLabel.setManaged(true);
            return;
        }

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        for (Map.Entry<Integer, double[]> entry : quizStats.entrySet()) {
            double[] stats = entry.getValue();
            series.getData().add(new XYChart.Data<>(
                    quizLabels.get(entry.getKey()),
                    stats[0] / stats[1]));
        }

        quizAverageChart.getData().setAll(series);
        quizAverageChart.setVisible(true);
        quizAverageChart.setManaged(true);
        quizAverageEmptyLabel.setVisible(false);
        quizAverageEmptyLabel.setManaged(false);
    }

    private void updateScoreDistributionChart(List<QuizResult> results) {
        int excellent = 0;
        int good = 0;
        int pass = 0;
        int needsSupport = 0;

        for (QuizResult result : results) {
            Double percentage = scorePercentage(result);
            if (percentage == null) {
                continue;
            }
            if (percentage >= 80) {
                excellent++;
            } else if (percentage >= 60) {
                good++;
            } else if (percentage >= 50) {
                pass++;
            } else {
                needsSupport++;
            }
        }

        scoreDistributionChart.getData().clear();
        addDistributionSlice("Excellent (80%+)", excellent);
        addDistributionSlice("Good (60–79%)", good);
        addDistributionSlice("Pass (50–59%)", pass);
        addDistributionSlice("Needs support (<50%)", needsSupport);

        boolean hasData = excellent + good + pass + needsSupport > 0;
        scoreDistributionChart.setVisible(hasData);
        scoreDistributionChart.setManaged(hasData);
        scoreDistributionEmptyLabel.setVisible(!hasData);
        scoreDistributionEmptyLabel.setManaged(!hasData);
    }

    private void addDistributionSlice(String label, int count) {
        if (count > 0) {
            scoreDistributionChart.getData().add(new PieChart.Data(label, count));
        }
    }

    private Double scorePercentage(QuizResult result) {
        int possible = result.getFinalPossibleMarks();
        if (possible <= 0) {
            return null;
        }
        return result.getTotalScore() * 100.0 / possible;
    }

    private String formatPercentage(double percentage) {
        return String.format(Locale.ENGLISH, "%.1f%%", percentage);
    }

    private void loadFromApi() {
        new Thread(() -> ApiClient.getDashboard().ifPresentOrElse(json -> {
            if (!"lecturer".equals(json.get("role").getAsString())) {
                Platform.runLater(this::loadEmptyState);
                return;
            }
            JsonObject stats = json.getAsJsonObject("stats");
            ObservableList<GroupAdminSummaryRow> adminRows = GroupAdminDashboardSupport.rowsFromApi(json);
            Platform.runLater(() -> {
                myGroupsLabel.setText(String.valueOf(stats.get("my_groups").getAsInt()));
                myTopicsLabel.setText(String.valueOf(stats.get("my_topics").getAsInt()));
                populateGroupAdminCard(adminRows);

                JsonArray participants = stats.getAsJsonArray("participants");
                participantsCountLabel.setText(String.valueOf(participants.size()));

                var rows = FXCollections.<ParticipantRow>observableArrayList();
                for (JsonElement element : participants) {
                    JsonObject participant = element.getAsJsonObject();
                    rows.add(new ParticipantRow(
                            participant.get("name").getAsString(),
                            participant.has("topics") ? participant.get("topics").getAsInt() : 0,
                            participant.get("posts").getAsInt(),
                            participant.get("replies").getAsInt(),
                            participant.get("score").getAsInt()
                    ));
                }
                participantsTable.setItems(rows);
            });
        }, () -> Platform.runLater(this::loadEmptyState))).start();
    }

    private void loadEmptyState() {
        myGroupsLabel.setText("0");
        myTopicsLabel.setText("0");
        participantsCountLabel.setText("0");
        populateGroupAdminCard(GroupAdminDashboardSupport.rowsFromApi(null));
        participantsTable.setItems(FXCollections.observableArrayList());
    }

    private void populateGroupAdminCard(ObservableList<GroupAdminSummaryRow> rows) {
        GroupAdminDashboardSupport.populateTable(groupAdminTable, groupAdminCard, rows);
    }

    private record QuizFilterOption(Integer quizId, String label) {
    }
}
