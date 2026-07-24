package com.smartforum.controller;

import com.smartforum.dao.QuestionDAO;
import com.smartforum.model.Quiz;
import com.smartforum.util.QuizSchedule;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.util.Duration;

import java.time.LocalDateTime;

public class QuizLaunchDialogController {

    @FXML private Label lblTitle;
    @FXML private Label lblDescription;
    @FXML private Label lblTimerLabel;
    @FXML private Label lblTimer;
    @FXML private Label lblDuration;
    @FXML private Label lblQuestions;
    @FXML private Button btnLater;
    @FXML private Button btnStart;

    private Quiz quiz;
    private boolean prestart;
    private Runnable onLater;
    private Runnable onStart;
    private Timeline timeline;
    private long secondsLeft;

    public void setup(Quiz quiz, boolean prestart, Runnable onLater, Runnable onStart) {
        this.quiz = quiz;
        this.prestart = prestart;
        this.onLater = onLater;
        this.onStart = onStart;

        int questionCount = new QuestionDAO().getQuestionsByQuizId(quiz.getId()).size();
        lblTitle.setText(prestart ? quiz.getTitle() + " starts soon" : quiz.getTitle() + " is live");
        String description = quiz.getDescription();
        lblDescription.setText(
            description == null || description.isBlank()
                ? "Your scheduled quiz window is open. Start now to begin the countdown."
                : description
        );
        lblDuration.setText(quiz.getDuration() + " min");
        lblQuestions.setText(questionCount + " questions");

        if (prestart) {
            lblTimerLabel.setText("Starts in");
            btnStart.setText("Wait for start");
            btnStart.setDisable(true);
            LocalDateTime start = QuizSchedule.parseStart(quiz.getStartDate());
            secondsLeft = start == null ? 0
                : Math.max(0, java.time.Duration.between(LocalDateTime.now(), start).getSeconds());
        } else {
            lblTimerLabel.setText("Quiz duration");
            btnStart.setText("Start Quiz");
            btnStart.setDisable(false);
            secondsLeft = Math.max(0, quiz.getDuration() * 60L);
        }

        updateTimer();
        timeline = new Timeline(new KeyFrame(Duration.seconds(1), e -> {
            secondsLeft = Math.max(0, secondsLeft - 1);
            updateTimer();
            if (prestart && secondsLeft <= 0) {
                flipToLive();
            }
        }));
        timeline.setCycleCount(Timeline.INDEFINITE);
        timeline.play();
    }

    private void flipToLive() {
        prestart = false;
        lblTitle.setText(quiz.getTitle() + " is live");
        lblTimerLabel.setText("Quiz duration");
        secondsLeft = Math.max(0, quiz.getDuration() * 60L);
        btnStart.setText("Start Quiz");
        btnStart.setDisable(false);
        updateTimer();
    }

    private void updateTimer() {
        long hours = secondsLeft / 3600;
        long mins = (secondsLeft % 3600) / 60;
        long secs = secondsLeft % 60;
        if (hours > 0) {
            lblTimer.setText(String.format("%02d:%02d:%02d", hours, mins, secs));
        } else {
            lblTimer.setText(String.format("%02d:%02d", mins, secs));
        }
        lblTimer.getStyleClass().remove("is-urgent");
        if (secondsLeft <= 30) {
            lblTimer.getStyleClass().add("is-urgent");
        }
    }

    @FXML
    private void onLater() {
        stopTimer();
        if (onLater != null) {
            onLater.run();
        }
    }

    @FXML
    private void onStart() {
        if (prestart) {
            return;
        }
        stopTimer();
        if (onStart != null) {
            onStart.run();
        }
    }

    private void stopTimer() {
        if (timeline != null) {
            timeline.stop();
        }
    }
}
