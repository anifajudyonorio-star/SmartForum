package com.smartforum.util;

import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.geometry.Side;
import javafx.scene.Node;
import javafx.scene.chart.AreaChart;
import javafx.scene.chart.BarChart;
import javafx.scene.chart.CategoryAxis;
import javafx.scene.chart.Chart;
import javafx.scene.chart.NumberAxis;
import javafx.scene.chart.PieChart;
import javafx.scene.chart.XYChart;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import javafx.util.StringConverter;

import java.util.ArrayList;
import java.util.List;

/**
 * Desktop charts matching web statistics pages (Chart.js green theme).
 */
public final class StatChartFactory {

    private static final String CHART_GREEN = "#16a34a";
    private static final String CHART_AREA_FILL = "#16a34a22";
    private static final String[] PIE_COLORS = {
            "#166534", "#16a34a", "#4ade80", "#86efac", "#bbf7d0", "#dcfce7"
    };
    private static final List<String> DEFAULT_MONTH_LABELS = List.of(
            "Jan", "Feb", "Mar", "Apr", "May", "Jun",
            "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
    );

    private StatChartFactory() {
    }

    /** Web: white card, header title, padded body with ~240px chart. */
    public static VBox createChartCard(String title, Chart chart) {
        Label heading = new Label(title);
        heading.getStyleClass().add("dashboard-card-title");

        HBox header = new HBox(heading);
        header.getStyleClass().add("dashboard-card-header");
        header.setAlignment(Pos.CENTER_LEFT);
        header.setPadding(new Insets(8, 14, 8, 14));

        VBox body = new VBox(prepareChart(chart));
        body.getStyleClass().addAll("dashboard-card-body", "stats-chart-body");
        body.setPadding(new Insets(4, 12, 12, 12));
        body.setMinHeight(260);

        VBox card = new VBox(0, header, body);
        card.getStyleClass().addAll("dashboard-card", "stats-chart-card");
        card.setMinHeight(300);
        card.setMaxWidth(Double.MAX_VALUE);
        VBox.setVgrow(card, Priority.ALWAYS);
        HBox.setHgrow(card, Priority.ALWAYS);
        return card;
    }

    /** Mount a chart into an FXML card body (web-style statistics pages). */
    public static void mountChart(VBox chartBody, Chart chart) {
        chartBody.getChildren().forEach(node -> unbindChartSize(node));
        chartBody.getChildren().clear();
        Chart prepared = prepareChart(chart);
        chartBody.getChildren().add(prepared);
        VBox.setVgrow(prepared, Priority.ALWAYS);

        prepared.prefWidthProperty().bind(chartBody.widthProperty());
        if (chart instanceof PieChart pie) {
            chartBody.setMinHeight(280);
            pie.setMinSize(240, 220);
            pie.prefHeightProperty().bind(chartBody.heightProperty());
        } else {
            prepared.prefHeightProperty().bind(chartBody.heightProperty());
        }
    }

    private static void unbindChartSize(Node node) {
        if (node instanceof Chart chartNode) {
            if (chartNode.prefWidthProperty().isBound()) {
                chartNode.prefWidthProperty().unbind();
            }
            if (chartNode.prefHeightProperty().isBound()) {
                chartNode.prefHeightProperty().unbind();
            }
        }
    }

    public static Chart prepareChart(Chart chart) {
        chart.setMinHeight(220);
        chart.setPrefHeight(240);
        chart.setMaxHeight(Double.MAX_VALUE);
        chart.setMinWidth(180);
        chart.setPrefWidth(Region.USE_COMPUTED_SIZE);
        chart.setMaxWidth(Double.MAX_VALUE);
        chart.setPadding(new Insets(4, 8, 4, 8));
        attachChartTheme(chart);
        return chart;
    }

    public static BarChart<String, Number> buildBarChart(List<String> labels, List<Integer> values, boolean rotateLabels) {
        List<String> chartLabels = labels.isEmpty() ? List.of("No data") : labels;
        List<Integer> chartValues = values.isEmpty() ? List.of(0) : values;
        int count = Math.min(chartLabels.size(), chartValues.size());

        CategoryAxis xAxis = new CategoryAxis();
        xAxis.setLabel("");
        NumberAxis yAxis = createValueAxis(chartValues.subList(0, count));
        yAxis.setLabel("");

        BarChart<String, Number> chart = new BarChart<>(xAxis, yAxis);
        chart.setTitle("");
        chart.setLegendVisible(true);
        chart.setLegendSide(Side.TOP);
        chart.setAnimated(false);
        chart.setCategoryGap(24);
        chart.setBarGap(6);
        chart.getStyleClass().addAll("chart", "stats-bar-chart");
        configurePlot(chart, rotateLabels && count > 4);

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Posts");
        for (int i = 0; i < count; i++) {
            series.getData().add(new XYChart.Data<>(chartLabels.get(i), chartValues.get(i)));
        }
        chart.getData().add(series);
        return chart;
    }

    /** Web uses a filled line chart; AreaChart is the closest JavaFX equivalent. */
    public static AreaChart<String, Number> buildAreaChart(List<String> labels, List<Integer> values) {
        List<String> chartLabels = labels.isEmpty() ? DEFAULT_MONTH_LABELS : labels;
        List<Integer> chartValues = normalizeSeries(chartLabels.size(), values);
        int count = chartLabels.size();

        CategoryAxis xAxis = new CategoryAxis();
        xAxis.setLabel("");
        NumberAxis yAxis = createValueAxis(chartValues.subList(0, count));
        yAxis.setLabel("");

        AreaChart<String, Number> chart = new AreaChart<>(xAxis, yAxis);
        chart.setTitle("");
        chart.setLegendVisible(true);
        chart.setLegendSide(Side.TOP);
        chart.setAnimated(false);
        chart.setCreateSymbols(true);
        chart.getStyleClass().addAll("chart", "stats-area-chart");
        configurePlot(chart, false);

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Posts");
        for (int i = 0; i < count; i++) {
            series.getData().add(new XYChart.Data<>(chartLabels.get(i), chartValues.get(i)));
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
        chart.setTitle("");
        chart.setLegendVisible(true);
        chart.setLegendSide(Side.BOTTOM);
        chart.setAnimated(false);
        chart.setLabelsVisible(false);
        chart.setStartAngle(90);
        chart.getStyleClass().addAll("chart", "stats-pie-chart");
        return chart;
    }

    private static List<Integer> normalizeSeries(int size, List<Integer> values) {
        List<Integer> normalized = new ArrayList<>();
        for (int i = 0; i < size; i++) {
            normalized.add(i < values.size() ? values.get(i) : 0);
        }
        return normalized;
    }

    public static NumberAxis createValueAxis(List<Integer> values) {
        int max = values.stream().mapToInt(Integer::intValue).max().orElse(0);
        double upper = max <= 0 ? 4 : Math.ceil(max * 1.15 / 2.0) * 2.0;
        double tick = upper <= 10 ? Math.max(1, upper / 4) : Math.max(2, upper / 5);
        NumberAxis yAxis = new NumberAxis(0, upper, tick);
        yAxis.setForceZeroInRange(true);
        yAxis.setMinorTickVisible(false);
        yAxis.setTickMarkVisible(true);
        yAxis.setAutoRanging(false);
        yAxis.setTickLabelFormatter(new StringConverter<>() {
            @Override
            public String toString(Number value) {
                if (value == null) {
                    return "";
                }
                double numeric = value.doubleValue();
                return Math.rint(numeric) == numeric ? String.valueOf((int) numeric) : String.valueOf(numeric);
            }

            @Override
            public Number fromString(String string) {
                return 0;
            }
        });
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

    private static void attachChartTheme(Chart chart) {
        Runnable apply = () -> applyChartTheme(chart);
        chart.sceneProperty().addListener((obs, oldScene, newScene) -> {
            if (newScene != null) {
                Platform.runLater(apply);
                Platform.runLater(apply);
            }
        });
        chart.layoutBoundsProperty().addListener((obs, oldBounds, newBounds) -> {
            if (newBounds.getWidth() > 0 && newBounds.getHeight() > 0 && chart.getScene() != null) {
                Platform.runLater(apply);
            }
        });
    }

    private static void applyChartTheme(Chart chart) {
        if (chart == null || chart.getScene() == null) {
            return;
        }

        chart.applyCss();
        chart.layout();

        for (Node node : chart.lookupAll(".chart-plot-area .chart-bar")) {
            node.setStyle("-fx-bar-fill: " + CHART_GREEN + ";");
        }

        for (Node node : chart.lookupAll(".chart-plot-area .chart-series-area-line")) {
            node.setStyle("-fx-stroke: " + CHART_GREEN + "; -fx-stroke-width: 2px;");
        }

        for (Node node : chart.lookupAll(".chart-plot-area .chart-series-area-fill")) {
            node.setStyle("-fx-fill: " + CHART_AREA_FILL + ";");
        }

        for (Node node : chart.lookupAll(".chart-plot-area .chart-line-symbol")) {
            node.setStyle(
                    "-fx-background-color: " + CHART_GREEN + ", white;"
                            + "-fx-background-insets: 0, 2;"
                            + "-fx-padding: 3;"
            );
        }

        int colorIndex = 0;
        for (Node node : chart.lookupAll(".chart-plot-area .chart-pie")) {
            String color = PIE_COLORS[colorIndex % PIE_COLORS.length];
            node.setStyle("-fx-pie-color: " + color + ";");
            colorIndex++;
        }

        colorIndex = 0;
        for (Node node : chart.lookupAll(".chart-legend-item-symbol")) {
            if (chart instanceof PieChart) {
                String color = PIE_COLORS[colorIndex % PIE_COLORS.length];
                node.setStyle("-fx-background-color: " + color + "; -fx-background-insets: 0; -fx-padding: 6;");
                colorIndex++;
            } else {
                node.setStyle("-fx-background-color: " + CHART_GREEN + "; -fx-background-insets: 0; -fx-padding: 6;");
            }
        }
    }
}
