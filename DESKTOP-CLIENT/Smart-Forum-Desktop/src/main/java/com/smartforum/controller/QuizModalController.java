package com.smartforum.controller;

import com.smartforum.dao.QuizResultDAO;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizResult;

import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;
import javafx.util.Duration;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class QuizModalController {

    // Header
    @FXML private Label lblQuizTitle, lblProgress, lblDurationBadge, lblTimer;

    // Quiz pane
    @FXML private VBox quizPane;
    @FXML private Label lblQuestion;
    @FXML private RadioButton rbA, rbB, rbC, rbD;
    @FXML private ToggleGroup answerGroup;
    @FXML private Button btnPrev, btnNext, btnSubmit;

    // Result pane
    @FXML private VBox resultPane;
    @FXML private Label lblResultQuizTitle, lblStudentResult, lblScoreResult,
                        lblParticipationResult, lblFinalScore, lblPercentResult, lblFeedback;

    private List<Question> questions;
    private int currentIndex = 0;
    private final Map<Integer, String> answers = new HashMap<>();
    private Quiz quiz;
    private String studentName;
    private int categoryId;
    private Timeline timer;
    private int secondsLeft;

    @FXML
    public void initialize() {
        answerGroup = new ToggleGroup();
        rbA.setToggleGroup(answerGroup);
        rbB.setToggleGroup(answerGroup);
        rbC.setToggleGroup(answerGroup);
        rbD.setToggleGroup(answerGroup);
    }

    public void setup(Quiz quiz, List<Question> questions, String studentName, int categoryId) {
        this.quiz = quiz;
        this.questions = questions;
        this.studentName = studentName;
        this.categoryId = categoryId;

        lblQuizTitle.setText(quiz.getTitle());
        lblDurationBadge.setText(quiz.getDuration() + " min  •  " + questions.size() + " questions");

        // Start countdown timer — matches Laravel JS countdown
        secondsLeft = quiz.getDuration() * 60;
        updateTimerLabel();

        timer = new Timeline(new KeyFrame(Duration.seconds(1), e -> {
            secondsLeft--;
            updateTimerLabel();
            if (secondsLeft <= 300) {
                lblTimer.setStyle("-fx-font-size:26;-fx-font-weight:bold;-fx-text-fill:#dc3545;");
            }
            if (secondsLeft <= 0) {
                timer.stop();
                lblTimer.setText("Submitting...");
                submitQuiz();
            }
        }));
        timer.setCycleCount(Timeline.INDEFINITE);
        timer.play();

        loadQuestion();
    }

    private void updateTimerLabel() {
        int m = secondsLeft / 60;
        int s = secondsLeft % 60;
        lblTimer.setText(String.format("%02d:%02d", m, s));
    }

    private void loadQuestion() {
        Question q = questions.get(currentIndex);
        lblProgress.setText("Question " + (currentIndex + 1) + " of " + questions.size());
        lblQuestion.setText((currentIndex + 1) + ".  " + q.getQuestion());
        rbA.setText(q.getOptionA());
        rbB.setText(q.getOptionB());
        rbC.setText(q.getOptionC());
        rbD.setText(q.getOptionD());

        answerGroup.selectToggle(null);
        String saved = answers.get(q.getId());
        if (saved != null) {
            switch (saved) {
                case "A" -> rbA.setSelected(true);
                case "B" -> rbB.setSelected(true);
                case "C" -> rbC.setSelected(true);
                case "D" -> rbD.setSelected(true);
            }
        }

        btnPrev.setDisable(currentIndex == 0);
        btnNext.setDisable(currentIndex == questions.size() - 1);
        btnSubmit.setVisible(currentIndex == questions.size() - 1);
    }

    @FXML
    private void prevQuestion() {
        saveCurrentAnswer();
        if (currentIndex > 0) { currentIndex--; loadQuestion(); }
    }

    @FXML
    private void nextQuestion() {
        saveCurrentAnswer();
        if (currentIndex < questions.size() - 1) { currentIndex++; loadQuestion(); }
    }

    @FXML
    private void submitQuiz() {
        if (timer != null) timer.stop();
        saveCurrentAnswer();

        long unanswered = questions.stream().filter(q -> !answers.containsKey(q.getId())).count();
        if (unanswered > 0) {
            Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
            confirm.setTitle("Submit Quiz");
            confirm.setHeaderText(null);
            confirm.setContentText("Are you sure you want to submit your quiz?\n(" + unanswered + " question(s) unanswered)");
            if (confirm.showAndWait().orElse(ButtonType.CANCEL) != ButtonType.OK) {
                // Restart timer if cancelled
                timer.play();
                return;
            }
        }

        int score = 0;
        for (Question q : questions) {
            String sel = answers.get(q.getId());
            if (sel != null && sel.equals(q.getCorrectAnswer())) score++;
        }

        int total = questions.size();
        int participationMarks = 2; // matches Laravel default
        int finalScore = score + participationMarks;
        double pct = (score * 100.0) / total;

        QuizResult result = new QuizResult();
        result.setQuizId(quiz.getId());
        result.setQuizTitle(quiz.getTitle());
        result.setStudentName(studentName);
        result.setCategoryId(categoryId);
        result.setScore(score);
        result.setTotalMarks(total);
        result.setParticipationMarks(participationMarks);
        result.setTotalScore(finalScore);
        result.setSubmittedAt(LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")));
        new QuizResultDAO().saveResult(result);

        // Populate result pane — matches Laravel result.blade.php table rows
        lblResultQuizTitle.setText(quiz.getTitle());
        lblStudentResult.setText(studentName);
        lblScoreResult.setText(score + " / " + total);
        lblParticipationResult.setText(String.valueOf(participationMarks));
        lblFinalScore.setText(String.valueOf(finalScore));
        lblPercentResult.setText(String.format("%.1f%%", pct));
        lblFeedback.setText(pct >= 75 ? "🎉 Excellent!" : pct >= 50 ? "👍 Good effort!" : "📚 Keep studying!");

        quizPane.setVisible(false);  quizPane.setManaged(false);
        resultPane.setVisible(true); resultPane.setManaged(true);
    }

    @FXML
    private void closeModal() {
        if (timer != null) timer.stop();
        ((Stage) resultPane.getScene().getWindow()).close();
    }

    private void saveCurrentAnswer() {
        RadioButton sel = (RadioButton) answerGroup.getSelectedToggle();
        if (sel != null && !questions.isEmpty()) {
            String ans = sel == rbA ? "A" : sel == rbB ? "B" : sel == rbC ? "C" : "D";
            answers.put(questions.get(currentIndex).getId(), ans);
        }
    }
}
