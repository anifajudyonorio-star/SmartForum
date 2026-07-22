package com.smartforum.controller;

import com.smartforum.dao.QuizResultDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.QuizResult;
import com.smartforum.service.AppSession;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.chart.LineChart;
import javafx.scene.chart.XYChart;
import javafx.scene.control.Label;
import javafx.scene.control.TableCell;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.layout.VBox;

import java.sql.SQLException;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.OffsetDateTime;
import java.time.format.DateTimeFormatter;
import java.time.format.DateTimeParseException;
import java.util.List;
import java.util.Locale;
import java.util.Optional;
import java.util.function.Function;

public class QuizProgressController {

    @FXML private Label quizzesAttemptedLabel;
    @FXML private Label averagePercentageLabel;
    @FXML private Label bestPercentageLabel;
    @FXML private Label latestPercentageLabel;
    @FXML private Label quizProgressMessage;
    @FXML private LineChart<String, Number> quizProgressChart;
    @FXML private TableView<QuizResult> quizAttemptTable;
    @FXML private TableColumn<QuizResult, String> quizTitleColumn;
    @FXML private TableColumn<QuizResult, String> quizSubmittedColumn;
    @FXML private TableColumn<QuizResult, String> quizStatusColumn;
    @FXML private TableColumn<QuizResult, String> quizQuestionScoreColumn;
    @FXML private TableColumn<QuizResult, String> quizParticipationColumn;
    @FXML private TableColumn<QuizResult, String> quizFinalScoreColumn;
    @FXML private TableColumn<QuizResult, String> quizPercentageColumn;
    @FXML private TableColumn<QuizResult, String> quizReportColumn;

    private final QuizResultDAO quizResultDAO = new QuizResultDAO();
    private static final DateTimeFormatter WEB_DISPLAY_DATE =
            DateTimeFormatter.ofPattern("MMM d, yyyy h:mm a", Locale.ENGLISH);

    @FXML
    private void initialize() {
        configureQuizProgressTable();
        loadQuizProgress(AppSession.getInstance().getCurrentUser());
    }

    private static final double[] COLUMN_WEIGHTS = {
            2.1, 1.5, 1.0, 1.3, 1.0, 1.2, 1.0, 1.0
    };

    private void configureQuizProgressTable() {
        quizAttemptTable.setColumnResizePolicy(TableView.UNCONSTRAINED_RESIZE_POLICY);
        quizAttemptTable.setFixedCellSize(-1);
        quizAttemptTable.widthProperty().addListener((obs, oldWidth, newWidth) ->
                balanceColumnWidths(newWidth.doubleValue()));
        Platform.runLater(() -> balanceColumnWidths(quizAttemptTable.getWidth()));

        quizTitleColumn.setCellFactory(col -> quizTitleCell());
        quizSubmittedColumn.setCellFactory(col -> wrapTextCell(
                result -> formatSubmittedAt(result.getSubmittedAt())));
        quizStatusColumn.setCellFactory(col -> statusCell());
        quizQuestionScoreColumn.setCellFactory(col -> wrapTextCell(result -> {
            String denominator = result.getTotalMarks() > 0
                    ? String.valueOf(result.getTotalMarks())
                    : "snapshot unavailable";
            return result.getScore() + " / " + denominator;
        }));
        quizParticipationColumn.setCellFactory(col -> wrapTextCell(
                result -> String.valueOf(result.getParticipationMarks())));
        quizFinalScoreColumn.setCellFactory(col -> finalScoreCell());
        quizPercentageColumn.setCellFactory(col -> percentageCell());
        quizReportColumn.setCellFactory(col -> mutedCell("Unavailable"));

        quizProgressChart.setAnimated(false);
        quizProgressChart.setCreateSymbols(true);
        resetQuizProgress("Loading quiz progress...");
    }

    private void balanceColumnWidths(double tableWidth) {
        if (tableWidth <= 0) {
            return;
        }

        @SuppressWarnings("unchecked")
        TableColumn<QuizResult, ?>[] columns = new TableColumn[] {
                quizTitleColumn,
                quizSubmittedColumn,
                quizStatusColumn,
                quizQuestionScoreColumn,
                quizParticipationColumn,
                quizFinalScoreColumn,
                quizPercentageColumn,
                quizReportColumn,
        };

        double weightTotal = 0;
        for (double weight : COLUMN_WEIGHTS) {
            weightTotal += weight;
        }

        double usableWidth = Math.max(tableWidth - 4, 0);
        for (int i = 0; i < columns.length; i++) {
            double width = usableWidth * (COLUMN_WEIGHTS[i] / weightTotal);
            columns[i].setPrefWidth(width);
            columns[i].setMinWidth(Math.max(columns[i].getMinWidth(), width * 0.55));
        }
    }

    private TableCell<QuizResult, String> wrapTextCell(Function<QuizResult, String> formatter) {
        return new TableCell<>() {
            private final Label label = new Label();

            {
                label.setWrapText(true);
                label.maxWidthProperty().bind(widthProperty().subtract(16));
            }

            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    return;
                }
                label.setText(formatter.apply(getTableRow().getItem()));
                setGraphic(label);
            }
        };
    }

    private void bindWrapWidth(Label label, TableCell<?, ?> cell) {
        label.setWrapText(true);
        label.maxWidthProperty().bind(cell.widthProperty().subtract(16));
    }

    private TableCell<QuizResult, String> quizTitleCell() {
        return new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    return;
                }

                QuizResult result = getTableRow().getItem();
                Label title = new Label(safeTitle(result));
                title.getStyleClass().add("quiz-history-title");
                bindWrapWidth(title, this);

                Label meta = new Label("Result #" + result.getId());
                meta.getStyleClass().add("quiz-history-meta");
                bindWrapWidth(meta, this);

                VBox box = new VBox(2, title, meta);
                box.maxWidthProperty().bind(widthProperty().subtract(16));
                box.setAlignment(Pos.CENTER_LEFT);
                setGraphic(box);
            }
        };
    }

    private TableCell<QuizResult, String> statusCell() {
        return new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    return;
                }

                Label label = new Label("Submitted");
                label.getStyleClass().add("badge-primary");
                setGraphic(label);
            }
        };
    }

    private TableCell<QuizResult, String> finalScoreCell() {
        return new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    return;
                }

                QuizResult result = getTableRow().getItem();
                String denominator = result.getFinalPossibleMarks() > 0
                        ? String.valueOf(result.getFinalPossibleMarks())
                        : "snapshot unavailable";

                Label label = new Label(result.getTotalScore() + " / " + denominator);
                label.getStyleClass().add("quiz-history-final-score");
                bindWrapWidth(label, this);
                setGraphic(label);
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
                    return;
                }

                Double value = percentage(getTableRow().getItem());
                Label label = new Label(value == null ? "Not comparable" : formatPercentage(value));
                if (value == null) {
                    label.getStyleClass().add("quiz-history-muted");
                } else {
                    label.getStyleClass().add("badge-primary");
                }
                bindWrapWidth(label, this);
                setGraphic(label);
            }
        };
    }

    private TableCell<QuizResult, String> mutedCell(String text) {
        return new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                    return;
                }

                Label label = new Label(text);
                label.getStyleClass().add("quiz-history-muted");
                bindWrapWidth(label, this);
                setGraphic(label);
            }
        };
    }

    private void loadQuizProgress(ForumUser currentUser) {
        if (currentUser == null || currentUser.getId() <= 0) {
            resetQuizProgress("Quiz progress is unavailable for this session.");
            return;
        }

        Thread loader = new Thread(() -> {
            try {
                List<QuizResult> results =
                        quizResultDAO.getStudentProgress(currentUser.getId(), currentUser.getName());
                Platform.runLater(() -> showQuizProgress(results));
            } catch (SQLException | RuntimeException e) {
                Platform.runLater(() ->
                        resetQuizProgress("Quiz progress could not be loaded right now."));
            }
        }, "student-quiz-progress");
        loader.setDaemon(true);
        loader.start();
    }

    private void showQuizProgress(List<QuizResult> results) {
        quizAttemptTable.getItems().setAll(results);
        quizProgressChart.getData().clear();

        if (results.isEmpty()) {
            resetQuizProgress("No quiz attempts yet. Take a quiz to start tracking your progress.");
            return;
        }

        quizzesAttemptedLabel.setText(String.valueOf(results.size()));
        List<Double> validPercentages = results.stream()
                .map(this::percentage)
                .filter(value -> value != null)
                .toList();
        averagePercentageLabel.setText(validPercentages.isEmpty() ? "N/A" :
                formatPercentage(validPercentages.stream().mapToDouble(Double::doubleValue).average().orElse(0)));
        bestPercentageLabel.setText(validPercentages.isEmpty() ? "N/A" :
                formatPercentage(validPercentages.stream().mapToDouble(Double::doubleValue).max().orElse(0)));
        latestPercentageLabel.setText(formatPercentage(percentage(results.get(results.size() - 1))));

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Percentage");
        int attemptNumber = 1;
        for (QuizResult result : results) {
            Double value = percentage(result);
            if (value != null) {
                XYChart.Data<String, Number> point = new XYChart.Data<>(
                        attemptNumber + ". " + safeTitle(result), value);
                point.setExtraValue(result.getQuizId());
                series.getData().add(point);
            }
            attemptNumber++;
        }
        if (!series.getData().isEmpty()) {
            quizProgressChart.getData().add(series);
            quizProgressChart.setVisible(true);
            quizProgressChart.setManaged(true);
        } else {
            quizProgressChart.setVisible(false);
            quizProgressChart.setManaged(false);
        }
        quizProgressMessage.setText(series.getData().isEmpty()
                ? "Percentages are unavailable because these attempts have no possible marks."
                : "");
        quizProgressMessage.setVisible(!quizProgressMessage.getText().isEmpty());
        quizProgressMessage.setManaged(quizProgressMessage.isVisible());
    }

    private void resetQuizProgress(String message) {
        quizzesAttemptedLabel.setText("0");
        averagePercentageLabel.setText("N/A");
        bestPercentageLabel.setText("N/A");
        latestPercentageLabel.setText("N/A");
        quizAttemptTable.getItems().clear();
        quizProgressChart.getData().clear();
        quizProgressChart.setVisible(false);
        quizProgressChart.setManaged(false);
        quizProgressMessage.setText(message);
        quizProgressMessage.setVisible(true);
        quizProgressMessage.setManaged(true);
    }

    private Double percentage(QuizResult result) {
        int possibleMarks = result.getFinalPossibleMarks();
        if (possibleMarks <= 0) return null;
        return result.getTotalScore() * 100.0 / possibleMarks;
    }

    private String formatPercentage(Double percentage) {
        return percentage == null ? "N/A" : String.format(Locale.ENGLISH, "%.1f%%", percentage);
    }

    private String safeTitle(QuizResult result) {
        String title = result.getQuizTitle();
        return title == null || title.isBlank() ? "Deleted quiz" : title;
    }

    private String formatSubmittedAt(String submittedAt) {
        return parseSubmissionDate(submittedAt)
                .map(WEB_DISPLAY_DATE::format)
                .orElse("Date unavailable");
    }

    private Optional<LocalDateTime> parseSubmissionDate(String value) {
        if (value == null || value.isBlank()) return Optional.empty();
        try {
            return Optional.of(LocalDateTime.parse(value, DateTimeFormatter.ISO_LOCAL_DATE_TIME));
        } catch (DateTimeParseException ignored) {
            try {
                return Optional.of(OffsetDateTime.parse(value).toLocalDateTime());
            } catch (DateTimeParseException ignoredOffset) {
                DateTimeFormatter[] legacyFormats = {
                    DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss"),
                    DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm")
                };
                for (DateTimeFormatter formatter : legacyFormats) {
                    try {
                        return Optional.of(LocalDateTime.parse(value, formatter));
                    } catch (DateTimeParseException ignoredLegacy) {
                        // Try the next supported legacy format.
                    }
                }
                try {
                    return Optional.of(LocalDate.parse(value, DateTimeFormatter.ISO_LOCAL_DATE).atStartOfDay());
                } catch (DateTimeParseException ignoredDate) {
                    return Optional.empty();
                }
            }
        }
    }
}
