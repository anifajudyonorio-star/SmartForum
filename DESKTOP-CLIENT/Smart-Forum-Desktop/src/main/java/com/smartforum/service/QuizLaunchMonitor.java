package com.smartforum.service;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.controller.QuizLaunchDialogController;
import com.smartforum.controller.QuizModalController;
import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuestionDAO;
import com.smartforum.dao.QuizAttemptDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizAttempt;
import com.smartforum.util.ApiDateTimes;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.QuizSchedule;
import javafx.application.Platform;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Alert;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.stage.Window;

import java.time.Duration;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Optional;
import java.util.Set;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Polls for student quizzes that are starting / active and shows a launch popup.
 * Works with Laravel API (online) and local SQLite (offline).
 */
public final class QuizLaunchMonitor {

    private static final QuizLaunchMonitor INSTANCE = new QuizLaunchMonitor();
    private static final int PRESTART_SECONDS = 60;
    private static final AtomicBoolean QUIZ_WINDOW_OPEN = new AtomicBoolean(false);

    private final Set<Integer> dismissedIds = new HashSet<>();
    private ScheduledExecutorService scheduler;
    private Window ownerWindow;
    private final AtomicBoolean dialogOpen = new AtomicBoolean(false);
    private final AtomicBoolean started = new AtomicBoolean(false);

    private QuizLaunchMonitor() {}

    public static QuizLaunchMonitor getInstance() {
        return INSTANCE;
    }

    public static void setQuizWindowOpen(boolean open) {
        QUIZ_WINDOW_OPEN.set(open);
    }

    public static boolean isQuizWindowOpen() {
        return QUIZ_WINDOW_OPEN.get();
    }

    public void start(Window owner) {
        if (owner != null) {
            this.ownerWindow = owner;
        }
        if (!started.compareAndSet(false, true)) {
            return;
        }
        scheduler = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r, "quiz-launch-monitor");
            t.setDaemon(true);
            return t;
        });
        scheduler.scheduleAtFixedRate(this::tickSafe, 1, 5, TimeUnit.SECONDS);
    }

    public void stop() {
        started.set(false);
        if (scheduler != null) {
            scheduler.shutdownNow();
            scheduler = null;
        }
        dismissedIds.clear();
        dialogOpen.set(false);
        ownerWindow = null;
    }

    private void tickSafe() {
        try {
            tick();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void tick() {
        if (!AppSession.getInstance().isStudent() || QUIZ_WINDOW_OPEN.get() || dialogOpen.get()) {
            return;
        }
        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null || user.getId() <= 0) {
            return;
        }

        Candidate chosen = ApiSupport.useApi() ? findApiCandidate() : findOfflineCandidate(user);
        if (chosen == null) {
            return;
        }

        Platform.runLater(() -> showDialog(user, chosen));
    }

    private Candidate findApiCandidate() {
        Optional<JsonObject> response = ApiClient.getStudentQuizLaunchPoll();
        if (response.isEmpty()) {
            return findApiCandidateFromQuizList();
        }

        JsonArray quizzes = response.get().getAsJsonArray("quizzes");
        if (quizzes == null) {
            return null;
        }

        Candidate soon = null;
        long soonSeconds = Long.MAX_VALUE;

        for (JsonElement element : quizzes) {
            JsonObject item = element.getAsJsonObject();
            int id = item.get("id").getAsInt();
            if (dismissedIds.contains(id)) {
                continue;
            }

            String status = item.get("status").getAsString();
            Quiz quiz = quizFromPollItem(item);

            if ("Active".equals(status)) {
                return new Candidate(quiz, false, 0);
            }
            if ("Scheduled".equals(status)) {
                long seconds = item.get("seconds_until_start").getAsLong();
                if (seconds > 0 && seconds <= PRESTART_SECONDS && seconds < soonSeconds) {
                    soonSeconds = seconds;
                    soon = new Candidate(quiz, true, seconds);
                }
            }
        }
        return soon;
    }

    private Candidate findApiCandidateFromQuizList() {
        Optional<JsonObject> response = ApiClient.getStudentQuizzes();
        if (response.isEmpty()) {
            return null;
        }
        JsonArray quizzes = response.get().getAsJsonArray("quizzes");
        if (quizzes == null) {
            return null;
        }

        for (JsonElement element : quizzes) {
            JsonObject item = element.getAsJsonObject();
            int id = item.get("id").getAsInt();
            if (dismissedIds.contains(id) || item.get("completed").getAsBoolean()) {
                continue;
            }
            if (item.get("can_start").getAsBoolean() || "Available".equals(item.get("status_label").getAsString())) {
                Quiz quiz = new Quiz();
                quiz.setId(id);
                quiz.setTitle(item.get("title").getAsString());
                quiz.setDescription(item.has("description") && !item.get("description").isJsonNull()
                        ? item.get("description").getAsString() : "");
                quiz.setDuration(item.get("duration").getAsInt());
                quiz.setQuestionsCount(item.get("questions_count").getAsInt());
                quiz.setCanStart(true);
                quiz.setStatusLabel("Available");
                return new Candidate(quiz, false, 0);
            }
        }
        return null;
    }

    private Quiz quizFromPollItem(JsonObject item) {
        Quiz quiz = new Quiz();
        quiz.setId(item.get("id").getAsInt());
        quiz.setTitle(item.get("title").getAsString());
        quiz.setDescription(item.has("description") && !item.get("description").isJsonNull()
                ? item.get("description").getAsString() : "");
        quiz.setDuration(item.get("duration_minutes").getAsInt());
        quiz.setQuestionsCount(item.get("questions_count").getAsInt());
        if (item.has("start_time") && !item.get("start_time").isJsonNull()) {
            quiz.setStartDate(item.get("start_time").getAsString());
        }
        if (item.has("end_time") && !item.get("end_time").isJsonNull()) {
            quiz.setEndDate(item.get("end_time").getAsString());
        }
        return quiz;
    }

    private Candidate findOfflineCandidate(ForumUser user) {
        int categoryId = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
        if (categoryId < 0) {
            return null;
        }

        LocalDateTime now = LocalDateTime.now();
        QuizAttemptDAO attemptDAO = new QuizAttemptDAO();
        List<Quiz> quizzes = new QuizDAO().getQuizzesByCategory(categoryId);

        Quiz soon = null;
        long soonSeconds = Long.MAX_VALUE;

        for (Quiz quiz : quizzes) {
            if (dismissedIds.contains(quiz.getId())) {
                continue;
            }
            if (attemptDAO.hasCompletedResult(quiz.getId(), user.getId(), user.getName())) {
                continue;
            }

            String availability = QuizSchedule.availability(quiz, now);
            if ("Available".equals(availability)) {
                quiz.setQuestionsCount(new QuestionDAO().getQuestionsByQuizId(quiz.getId()).size());
                quiz.setCanStart(true);
                quiz.setStatusLabel("Available");
                return new Candidate(quiz, false, 0);
            }
            if ("Upcoming".equals(availability)) {
                LocalDateTime start = QuizSchedule.parseStart(quiz.getStartDate());
                if (start == null) {
                    continue;
                }
                long seconds = Duration.between(now, start).getSeconds();
                if (seconds > 0 && seconds <= PRESTART_SECONDS && seconds < soonSeconds) {
                    soonSeconds = seconds;
                    quiz.setQuestionsCount(new QuestionDAO().getQuestionsByQuizId(quiz.getId()).size());
                    quiz.setStatusLabel("Upcoming");
                    soon = quiz;
                }
            }
        }

        return soon == null ? null : new Candidate(soon, true, soonSeconds);
    }

    private void showDialog(ForumUser user, Candidate chosen) {
        if (dialogOpen.get() || QUIZ_WINDOW_OPEN.get()) {
            return;
        }
        dialogOpen.set(true);
        Quiz quiz = chosen.quiz();
        try {
            FXMLLoader loader = new FXMLLoader(
                getClass().getResource("/fxml/QuizLaunchDialog.fxml"));
            Scene scene = new Scene(loader.load(), 440, 360);
            scene.getStylesheets().add(
                getClass().getResource("/com/smartforum/css/app.css").toExternalForm()
            );

            QuizLaunchDialogController controller = loader.getController();
            Stage stage = new Stage();
            stage.initModality(Modality.APPLICATION_MODAL);
            if (ownerWindow != null) {
                stage.initOwner(ownerWindow);
            }
            stage.setTitle("Quiz time");
            stage.setScene(scene);
            stage.setResizable(false);
            stage.setAlwaysOnTop(true);

            controller.setup(quiz, chosen.prestart(), chosen.prestartSeconds(), () -> {
                // Prestart "Later" can remind again when the quiz becomes Active.
                // Active "Later" suppresses only until logout/restart.
                if (!chosen.prestart()) {
                    dismissedIds.add(quiz.getId());
                }
                stage.close();
            }, () -> {
                stage.close();
                Platform.runLater(() -> startQuiz(user, quiz));
            });

            stage.setOnHidden(e -> dialogOpen.set(false));
            stage.show();
            stage.toFront();
            stage.requestFocus();
        } catch (Exception e) {
            dialogOpen.set(false);
            e.printStackTrace();
        }
    }

    private void startQuiz(ForumUser user, Quiz quiz) {
        if (ApiSupport.useApi()) {
            new Thread(() -> ApiClient.getStudentQuizSession(quiz.getId(), true).ifPresentOrElse(
                session -> Platform.runLater(() -> {
                    dismissedIds.add(quiz.getId());
                    openApiQuizWindow(session);
                }),
                () -> Platform.runLater(() -> alert("Unable to Start",
                    "Could not start this quiz session. Open Quizzes and try Start Quiz."))
            ), "quiz-launch-start").start();
            return;
        }

        try {
            StudentQuizLauncher.LaunchRequest request = StudentQuizLauncher.prepare(user, quiz);
            dismissedIds.add(quiz.getId());
            openQuizWindow(request, false);
        } catch (Exception e) {
            alert("Unable to Start", e.getMessage());
        }
    }

    private void openApiQuizWindow(JsonObject session) {
        try {
            JsonObject quizJson = session.getAsJsonObject("quiz");
            JsonObject attemptJson = session.getAsJsonObject("attempt");
            Quiz quiz = new Quiz();
            quiz.setId(quizJson.get("id").getAsInt());
            quiz.setTitle(quizJson.get("title").getAsString());
            quiz.setDuration(quizJson.get("duration").getAsInt());

            List<Question> questions = new ArrayList<>();
            for (JsonElement element : session.getAsJsonArray("questions")) {
                JsonObject questionJson = element.getAsJsonObject();
                Question question = new Question();
                question.setId(questionJson.get("id").getAsInt());
                question.setQuestion(questionJson.get("question").getAsString());
                question.setMarks(questionJson.get("marks").getAsInt());

                JsonArray options = questionJson.getAsJsonArray("options");
                if (options.size() > 0) {
                    question.setOptionA(options.get(0).getAsJsonObject().get("text").getAsString());
                    question.setOptionAId(options.get(0).getAsJsonObject().get("id").getAsInt());
                }
                if (options.size() > 1) {
                    question.setOptionB(options.get(1).getAsJsonObject().get("text").getAsString());
                    question.setOptionBId(options.get(1).getAsJsonObject().get("id").getAsInt());
                }
                if (options.size() > 2) {
                    question.setOptionC(options.get(2).getAsJsonObject().get("text").getAsString());
                    question.setOptionCId(options.get(2).getAsJsonObject().get("id").getAsInt());
                }
                if (options.size() > 3) {
                    question.setOptionD(options.get(3).getAsJsonObject().get("text").getAsString());
                    question.setOptionDId(options.get(3).getAsJsonObject().get("id").getAsInt());
                }
                questions.add(question);
            }

            QuizAttempt attempt = new QuizAttempt();
            attempt.setId(attemptJson.get("id").getAsInt());
            attempt.setQuizId(quiz.getId());
            attempt.setDeadlineAt(ApiDateTimes.parseLocal(attemptJson.get("deadline_at").getAsString()));
            if (attemptJson.has("remaining_seconds") && !attemptJson.get("remaining_seconds").isJsonNull()) {
                attempt.setRemainingSeconds(attemptJson.get("remaining_seconds").getAsLong());
            }

            ForumUser user = AppSession.getInstance().getCurrentUser();
            openQuizWindow(new StudentQuizLauncher.LaunchRequest(quiz, questions, user, attempt), true);
        } catch (Exception e) {
            alert("Error", "Failed to open quiz window: " + e.getMessage());
        }
    }

    public static void openQuizWindow(StudentQuizLauncher.LaunchRequest request) throws Exception {
        openQuizWindow(request, false);
    }

    public static void openQuizWindow(StudentQuizLauncher.LaunchRequest request, boolean apiMode) throws Exception {
        QUIZ_WINDOW_OPEN.set(true);
        try {
            FXMLLoader loader = new FXMLLoader(
                QuizLaunchMonitor.class.getResource("/fxml/QuizModal.fxml"));
            Scene scene = new Scene(loader.load(), 760, 580);
            scene.getStylesheets().add(
                QuizLaunchMonitor.class.getResource("/com/smartforum/css/app.css").toExternalForm()
            );

            QuizModalController modal = loader.getController();
            modal.setup(
                request.getQuiz(),
                request.getQuestions(),
                request.getStudent(),
                request.getAttempt(),
                apiMode
            );

            Stage stage = new Stage();
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.setTitle(request.getQuiz().getTitle());
            stage.setMinWidth(720);
            stage.setMinHeight(560);
            Window owner = INSTANCE.ownerWindow;
            if (owner != null) {
                stage.initOwner(owner);
            }
            stage.setScene(scene);
            stage.setOnCloseRequest(e -> e.consume());
            stage.setOnHidden(e -> QUIZ_WINDOW_OPEN.set(false));
            stage.showAndWait();
        } catch (Exception e) {
            QUIZ_WINDOW_OPEN.set(false);
            throw e;
        }
    }

    private void alert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.WARNING);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private record Candidate(Quiz quiz, boolean prestart, long prestartSeconds) {}
}
