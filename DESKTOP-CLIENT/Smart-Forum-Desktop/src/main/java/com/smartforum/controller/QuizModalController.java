package com.smartforum.controller;

import com.smartforum.dao.QuizAttemptDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizAttempt;
import com.smartforum.model.QuizResult;
import com.smartforum.service.AppSession;
import com.smartforum.service.QuizSubmissionService;

import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;
import javafx.util.Duration;

import java.time.LocalDateTime;
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
    private ForumUser student;
    private QuizAttempt attempt;
    private Timeline timer;
    private long secondsLeft;
    private boolean submitting;
    private boolean apiMode;

    @FXML
    public void initialize() {
        answerGroup = new ToggleGroup();
        rbA.setToggleGroup(answerGroup);
        rbB.setToggleGroup(answerGroup);
        rbC.setToggleGroup(answerGroup);
        rbD.setToggleGroup(answerGroup);
    }

    public void setup(Quiz quiz, List<Question> questions, ForumUser student, QuizAttempt attempt) {
        setup(quiz, questions, student, attempt, false);
    }

    public void setup(Quiz quiz, List<Question> questions, ForumUser student, QuizAttempt attempt, boolean apiMode) {
        if (quiz == null || questions == null || questions.isEmpty() || student == null || attempt == null) {
            throw new IllegalStateException("Quiz session data is incomplete.");
        }
        if (attempt.getDeadlineAt() == null) {
            throw new IllegalStateException("Quiz attempt deadline is missing.");
        }

        this.quiz = quiz;
        this.questions = questions;
        this.student = student;
        this.attempt = attempt;
        this.apiMode = apiMode;
        restoreAnswers(attempt.getAnswers());

        lblQuizTitle.setText(quiz.getTitle());
        lblDurationBadge.setText(quiz.getDuration() + " min  •  " + questions.size() + " questions");

        if (attempt.getRemainingSeconds() >= 0) {
            secondsLeft = attempt.getRemainingSeconds();
        } else {
            secondsLeft = Math.max(0, java.time.Duration.between(
                LocalDateTime.now(), attempt.getDeadlineAt()).getSeconds());
        }
        updateTimerLabel();

        timer = new Timeline(new KeyFrame(Duration.seconds(1), e -> {
            secondsLeft--;
            updateTimerLabel();
            if (secondsLeft <= 300) {
                lblTimer.getStyleClass().remove("quiz-timer");
                if (!lblTimer.getStyleClass().contains("quiz-timer-warning")) {
                    lblTimer.getStyleClass().add("quiz-timer-warning");
                }
            }
            if (secondsLeft <= 0) {
                timer.stop();
                lblTimer.setText("Submitting...");
                submit(true);
            }
        }));
        timer.setCycleCount(Timeline.INDEFINITE);

        loadQuestion();

        if (secondsLeft <= 0) {
            submit(true);
        } else {
            timer.play();
        }
    }

    private void updateTimerLabel() {
        long m = secondsLeft / 60;
        long s = secondsLeft % 60;
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
        persistAnswers();
        if (currentIndex > 0) { currentIndex--; loadQuestion(); }
    }

    @FXML
    private void nextQuestion() {
        saveCurrentAnswer();
        persistAnswers();
        if (currentIndex < questions.size() - 1) { currentIndex++; loadQuestion(); }
    }

    @FXML
    private void submitQuiz() {
        submit(false);
    }

    private void submit(boolean timedOut) {
        if (submitting) return;
        submitting = true;
        if (timer != null) timer.stop();
        saveCurrentAnswer();
        persistAnswers();

        long unanswered = questions.stream().filter(q -> !answers.containsKey(q.getId())).count();
        if (!timedOut) {
            Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
            confirm.setTitle("Submit Quiz");
            confirm.setHeaderText(null);
            confirm.setContentText("Submit this quiz now?\n(" + unanswered + " question(s) unanswered)");
            if (confirm.showAndWait().orElse(ButtonType.CANCEL) != ButtonType.OK) {
                submitting = false;
                if (secondsLeft > 0) timer.play();
                return;
            }
        }

        ForumUser current = AppSession.getInstance().getCurrentUser();
        if (current == null || !AppSession.getInstance().isStudent() || current.getId() != student.getId()) {
            submitting = false;
            showError("Your student session changed. Answers were preserved; sign in again before submitting.");
            return;
        }

        if (apiMode) {
            submitViaApi(timedOut);
            return;
        }

        QuizSubmissionService.Submission submission;
        try {
            submission = new QuizSubmissionService().submitForCurrentStudent(attempt.getId());
        } catch (Exception e) {
            submitting = false;
            showError("Submission was not saved. Your answers are preserved.\n" + e.getMessage());
            if (secondsLeft > 0) timer.play();
            return;
        }
        QuizResult result = submission.getResult();
        int score = result.getScore();
        int authoredTotal = result.getTotalMarks();
        int participationMarks = result.getParticipationMarks();
        int finalScore = result.getTotalScore();
        int finalPossibleMarks = result.getFinalPossibleMarks();
        double pct = finalPossibleMarks <= 0 ? 0 : finalScore * 100.0 / finalPossibleMarks;

        showResultPane(score, authoredTotal, participationMarks, finalScore, finalPossibleMarks, pct, submission.isTimedOut());
    }

    private void submitViaApi(boolean timedOut) {
        new Thread(() -> {
            com.google.gson.JsonObject answerPayload = new com.google.gson.JsonObject();
            answers.forEach((questionId, letter) -> {
                Question question = questions.stream()
                        .filter(item -> item.getId() == questionId)
                        .findFirst()
                        .orElse(null);
                if (question != null) {
                    int optionId = question.optionIdForLetter(letter);
                    if (optionId > 0) {
                        answerPayload.addProperty(String.valueOf(questionId), optionId);
                    }
                }
            });

            com.smartforum.api.ApiClient.MutationResult result = com.smartforum.api.ApiClient.submitStudentQuiz(
                    quiz.getId(), attempt.getId(), answerPayload);

            javafx.application.Platform.runLater(() -> {
                if (!result.success() || result.body() == null || !result.body().has("result")) {
                    submitting = false;
                    showError("Submission was not saved.\n" + (result.message().isBlank()
                            ? "Please try again."
                            : result.message()));
                    if (secondsLeft > 0) timer.play();
                    return;
                }

                com.google.gson.JsonObject resultJson = result.body().getAsJsonObject("result");
                int score = resultJson.get("score").getAsInt();
                int authoredTotal = resultJson.get("total_marks").getAsInt();
                int participationMarks = resultJson.get("participation_marks").getAsInt();
                int finalScore = resultJson.get("total_score").getAsInt();
                int finalPossibleMarks = resultJson.get("final_possible_marks").getAsInt();
                double pct = resultJson.get("percentage").getAsDouble();

                showResultPane(score, authoredTotal, participationMarks, finalScore, finalPossibleMarks, pct, timedOut);
            });
        }, "quiz-api-submit").start();
    }

    private void showResultPane(int score, int authoredTotal, int participationMarks, int finalScore,
                                int finalPossibleMarks, double pct, boolean timedOut) {
        lblResultQuizTitle.setText(quiz.getTitle());
        lblStudentResult.setText(student.getName());
        lblScoreResult.setText(score + " / " + authoredTotal);
        lblParticipationResult.setText(String.valueOf(participationMarks));
        lblFinalScore.setText(String.valueOf(finalScore));
        lblPercentResult.setText(String.format("%.1f%%", pct));
        lblFeedback.setText(timedOut
            ? "Time expired — your last saved answers were submitted."
            : pct >= 75 ? "Excellent!" : pct >= 50 ? "Good effort!" : "Keep studying!");

        quizPane.setVisible(false);  quizPane.setManaged(false);
        resultPane.setVisible(true); resultPane.setManaged(true);
    }

    @FXML
    private void closeModal() {
        if (timer != null) timer.stop();
        if (resultPane.getScene() != null && resultPane.getScene().getWindow() instanceof Stage stage) {
            stage.close();
        }
    }

    private void saveCurrentAnswer() {
        RadioButton sel = (RadioButton) answerGroup.getSelectedToggle();
        if (sel != null && !questions.isEmpty()) {
            String ans = sel == rbA ? "A" : sel == rbB ? "B" : sel == rbC ? "C" : "D";
            answers.put(questions.get(currentIndex).getId(), ans);
        }
    }

    private void persistAnswers() {
        if (attempt == null || apiMode) return;
        StringBuilder encoded = new StringBuilder();
        answers.forEach((id, answer) -> encoded.append(id).append('=').append(answer).append(';'));
        attempt.setAnswers(encoded.toString());
        try {
            new QuizAttemptDAO().saveAnswers(attempt.getId(), student.getId(), encoded.toString());
        } catch (Exception e) {
            showError("Could not save answer progress locally: " + e.getMessage());
        }
    }

    private void restoreAnswers(String encoded) {
        if (encoded == null || encoded.isBlank()) return;
        for (String entry : encoded.split(";")) {
            String[] parts = entry.split("=", 2);
            if (parts.length == 2 && parts[1].matches("[ABCD]")) {
                try {
                    answers.put(Integer.parseInt(parts[0]), parts[1]);
                } catch (NumberFormatException ignored) {
                    // Ignore malformed legacy progress without blocking the attempt.
                }
            }
        }
    }

    private void showError(String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle("Quiz Submission");
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

}
