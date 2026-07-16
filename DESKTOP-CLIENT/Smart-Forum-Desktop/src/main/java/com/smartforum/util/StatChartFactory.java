package com.smartforum.util;

import javafx.collections.FXCollections;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.chart.AreaChart;
import javafx.scene.chart.BarChart;
import javafx.scene.chart.CategoryAxis;
import javafx.scene.chart.NumberAxis;
import javafx.scene.chart.PieChart;
import javafx.scene.chart.XYChart;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.ArrayList;
import java.util.List;

public final class StatChartFactory {

    private StatChartFactory() {
    }

    public static VBox createChartCard(String title, Region chart) {
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

    public static BarChart<String, Number> buildBarChart(List<String> labels, List<Integer> values, boolean rotateLabels) {
        CategoryAxis xAxis = new CategoryAxis();
        NumberAxis yAxis = createValueAxis(values);

        BarChart<String, Number> chart = new BarChart<>(xAxis, yAxis);
        chart.setLegendVisible(false);
        chart.setAnimated(false);
        chart.setCategoryGap(12);
        chart.getStyleClass().add("stats-bar-chart");
        configurePlot(chart, rotateLabels);

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Posts");
        for (int i = 0; i < labels.size(); i++) {
            series.getData().add(new XYChart.Data<>(labels.get(i), values.get(i)));
        }
        chart.getData().add(series);
        return chart;
    }

    public static AreaChart<String, Number> buildAreaChart(List<String> labels, List<Integer> values) {
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

    public static PieChart buildPieChart(List<String> labels, List<Integer> values) {
        List<PieChart.Data> slices = new ArrayList<>();
        for (int i = 0; i < labels.size(); i++) {
            if (values.get(i) > 0) {
                slices.add(new PieChart.Data(labels.get(i), values.get(i)));
            }
        }
        if (slices.isEmpty()) {
            slices.add(new PieChart.Data("No data", 1));
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

    public static NumberAxis createValueAxis(List<Integer> values) {
        int max = values.stream().mapToInt(Integer::intValue).max().orElse(0);
        double upper = max <= 0 ? 4 : Math.ceil(max * 1.15 / 2.0) * 2.0;
        NumberAxis yAxis = new NumberAxis(0, upper, upper <= 10 ? 2 : Math.max(2, upper / 5));
        yAxis.setForceZeroInRange(true);
        yAxis.setMinorTickVisible(false);
        yAxis.setTickMarkVisible(true);
        yAxis.setAutoRanging(false);
        return yAxis;
    }

    private static void configurePlot(XYChart<String, Number> chart, boolean rotateLabels) {
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
}
