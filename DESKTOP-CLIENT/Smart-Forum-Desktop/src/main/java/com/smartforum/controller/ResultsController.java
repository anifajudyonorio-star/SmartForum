package com.smartforum.controller;

import com.smartforum.dao.QuizResultDAO;
import com.smartforum.model.QuizResult;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.chart.BarChart;
import javafx.scene.chart.PieChart;
import javafx.scene.chart.XYChart;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;

public class ResultsController {

    @FXML private TableView<QuizResult> tblResults;
    @FXML private TableColumn<QuizResult, Integer> colId;
    @FXML private TableColumn<QuizResult, String> colStudent;
    @FXML private TableColumn<QuizResult, String> colQuiz;
    @FXML private TableColumn<QuizResult, Integer> colScore;
    @FXML private TableColumn<QuizResult, Integer> colTotalMarks;
    @FXML private TableColumn<QuizResult, String> colPercentage;
    @FXML private TableColumn<QuizResult, Integer> colParticipation;
    @FXML private TableColumn<QuizResult, Integer> colTotalScore;
    @FXML private TableColumn<QuizResult, String> colDate;
    @FXML private ComboBox<QuizFilter> quizFilter;
    @FXML private Label totalSubmissionsLabel;
    @FXML private Label studentsAssessedLabel;
    @FXML private Label averageScoreLabel;
    @FXML private Label passRateLabel;
    @FXML private BarChart<String, Number> quizAverageChart;
    @FXML private PieChart scoreDistributionChart;

    private final QuizResultDAO dao = new QuizResultDAO();
    private List<QuizResult> allResults = new ArrayList<>();
    private static final QuizFilter ALL_QUIZZES = new QuizFilter(null, "All quizzes");

    @FXML
    public void initialize() {
        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colStudent.setCellValueFactory(new PropertyValueFactory<>("studentName"));
        colQuiz.setCellValueFactory(new PropertyValueFactory<>("quizTitle"));
        colScore.setCellValueFactory(new PropertyValueFactory<>("score"));
        colTotalMarks.setCellValueFactory(new PropertyValueFactory<>("totalMarks"));
        colPercentage.setCellValueFactory(new PropertyValueFactory<>("percentage"));
        colParticipation.setCellValueFactory(new PropertyValueFactory<>("participationMarks"));
        colTotalScore.setCellValueFactory(new PropertyValueFactory<>("totalScore"));
        colDate.setCellValueFactory(new PropertyValueFactory<>("submittedAt"));

        quizFilter.getSelectionModel().selectedItemProperty().addListener(
            (obs, oldVal, newVal) -> applySelectedFilter());

        loadResults();
    }

    @FXML
    private void refreshResults() {
        loadResults();
    }

    private void loadResults() {
        Integer previousQuizId = quizFilter.getValue() == null ? null : quizFilter.getValue().quizId;
        allResults = dao.getAllResults();

        Map<Integer, String> quizzes = new LinkedHashMap<>();
        for (QuizResult result : allResults) {
            quizzes.put(result.getQuizId(), result.getQuizTitle());
        }

        quizFilter.getItems().setAll(ALL_QUIZZES);
        quizzes.forEach((id, title) -> quizFilter.getItems().add(
            new QuizFilter(id, title + " (#" + id + ")")));
        quizFilter.setValue(ALL_QUIZZES);
        if (previousQuizId != null) {
            quizFilter.getItems().stream()
                .filter(item -> previousQuizId.equals(item.quizId))
                .findFirst().ifPresent(quizFilter::setValue);
        }
        applySelectedFilter();
    }

    private void applySelectedFilter() {
        QuizFilter selectedQuiz = quizFilter.getValue();
        List<QuizResult> filtered = new ArrayList<>();
        for (QuizResult result : allResults) {
            if (selectedQuiz == null || selectedQuiz.quizId == null
                    || selectedQuiz.quizId == result.getQuizId()) {
                filtered.add(result);
            }
        }

        tblResults.setItems(FXCollections.observableArrayList(filtered));
        updateSummary(filtered);
        updateQuizAverageChart(filtered);
        updateScoreDistribution(filtered);
    }

    private void updateSummary(List<QuizResult> results) {
        totalSubmissionsLabel.setText(String.valueOf(results.size()));

        Set<String> students = new LinkedHashSet<>();
        double percentageTotal = 0;
        int passed = 0;
        for (QuizResult result : results) {
            students.add(result.getStudentId() == null
                ? "legacy:" + result.getStudentName()
                : "id:" + result.getStudentId());
            double percentage = scorePercentage(result);
            percentageTotal += percentage;
            if (percentage >= 50) {
                passed++;
            }
        }

        studentsAssessedLabel.setText(String.valueOf(students.size()));
        if (results.isEmpty()) {
            averageScoreLabel.setText("0.0%");
            passRateLabel.setText("0.0%");
            return;
        }

        averageScoreLabel.setText(formatPercentage(percentageTotal / results.size()));
        passRateLabel.setText(formatPercentage(passed * 100.0 / results.size()));
    }

    private void updateQuizAverageChart(List<QuizResult> results) {
        Map<Integer, double[]> quizStats = new LinkedHashMap<>();
        Map<Integer, String> quizLabels = new LinkedHashMap<>();
        for (QuizResult result : results) {
            double[] stats = quizStats.computeIfAbsent(result.getQuizId(), key -> new double[2]);
            quizLabels.put(result.getQuizId(), result.getQuizTitle() + " (#" + result.getQuizId() + ")");
            stats[0] += scorePercentage(result);
            stats[1]++;
        }

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Average score");
        for (Map.Entry<Integer, double[]> entry : quizStats.entrySet()) {
            double[] stats = entry.getValue();
            series.getData().add(new XYChart.Data<>(quizLabels.get(entry.getKey()), stats[0] / stats[1]));
        }
        quizAverageChart.getData().setAll(series);
    }

    private void updateScoreDistribution(List<QuizResult> results) {
        int excellent = 0;
        int good = 0;
        int pass = 0;
        int needsSupport = 0;

        for (QuizResult result : results) {
            double percentage = scorePercentage(result);
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
    }

    private void addDistributionSlice(String label, int count) {
        if (count > 0) {
            scoreDistributionChart.getData().add(new PieChart.Data(label + " — " + count, count));
        }
    }

    private double scorePercentage(QuizResult result) {
        if (result.getFinalPossibleMarks() <= 0) {
            return 0;
        }
        return result.getTotalScore() * 100.0 / result.getFinalPossibleMarks();
    }

    private String formatPercentage(double percentage) {
        return String.format(Locale.US, "%.1f%%", percentage);
    }

    private void showAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private static final class QuizFilter {
        private final Integer quizId;
        private final String label;

        private QuizFilter(Integer quizId, String label) {
            this.quizId = quizId;
            this.label = label;
        }

        @Override
        public String toString() {
            return label;
        }
    }
}
