package com.smartforum.service;

import com.smartforum.controller.QuizLaunchDialogController;
import com.smartforum.controller.QuizModalController;
import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuizAttemptDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Quiz;
import com.smartforum.util.QuizSchedule;
import javafx.application.Platform;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.stage.Window;

import java.time.Duration;
import java.time.LocalDateTime;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Polls local quizzes for the signed-in student and pops a launch dialog
 * when a quiz is about to start or becomes available (Laravel-parity).
 */
public final class QuizLaunchMonitor {

    private static final QuizLaunchMonitor INSTANCE = new QuizLaunchMonitor();
    private static final int PRESTART_SECONDS = 60;
    private static final AtomicBoolean QUIZ_WINDOW_OPEN = new AtomicBoolean(false);

    private final Set<Integer> dismissedIds = new HashSet<>();
    private ScheduledExecutorService scheduler;
    private Window ownerWindow;
    private final AtomicBoolean dialogOpen = new AtomicBoolean(false);

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
        this.ownerWindow = owner;
        if (scheduler != null && !scheduler.isShutdown()) {
            return;
        }
        scheduler = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r, "quiz-launch-monitor");
            t.setDaemon(true);
            return t;
        });
        scheduler.scheduleAtFixedRate(this::tick, 2, 5, TimeUnit.SECONDS);
    }

    public void stop() {
        if (scheduler != null) {
            scheduler.shutdownNow();
            scheduler = null;
        }
        dismissedIds.clear();
        dialogOpen.set(false);
    }

    private void tick() {
        if (!AppSession.getInstance().isStudent() || QUIZ_WINDOW_OPEN.get() || dialogOpen.get()) {
            return;
        }
        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null || user.getId() <= 0) {
            return;
        }

        int categoryId = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
        if (categoryId < 0) {
            return;
        }

        LocalDateTime now = LocalDateTime.now();
        QuizAttemptDAO attemptDAO = new QuizAttemptDAO();
        List<Quiz> quizzes = new QuizDAO().getQuizzesByCategory(categoryId);

        Quiz soon = null;
        Quiz available = null;
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
                available = quiz;
                break;
            }
            if ("Upcoming".equals(availability)) {
                LocalDateTime start = QuizSchedule.parseStart(quiz.getStartDate());
                if (start == null) {
                    continue;
                }
                long seconds = Duration.between(now, start).getSeconds();
                if (seconds > 0 && seconds <= PRESTART_SECONDS && seconds < soonSeconds) {
                    soonSeconds = seconds;
                    soon = quiz;
                }
            }
        }

        Quiz chosen = available != null ? available : soon;
        if (chosen == null) {
            return;
        }

        boolean prestart = available == null;
        Platform.runLater(() -> showDialog(user, chosen, prestart));
    }

    private void showDialog(ForumUser user, Quiz quiz, boolean prestart) {
        if (dialogOpen.get() || QUIZ_WINDOW_OPEN.get()) {
            return;
        }
        dialogOpen.set(true);
        try {
            FXMLLoader loader = new FXMLLoader(
                getClass().getResource("/fxml/QuizLaunchDialog.fxml"));
            Scene scene = new Scene(loader.load(), 440, 360);
            scene.getStylesheets().add(
                getClass().getResource("/com/smartforum/css/app.css").toExternalForm()
            );

            QuizLaunchDialogController controller = loader.getController();
            Stage stage = new Stage(StageStyle.UTILITY);
            stage.initModality(Modality.APPLICATION_MODAL);
            if (ownerWindow != null) {
                stage.initOwner(ownerWindow);
            }
            stage.setTitle("Quiz time");
            stage.setScene(scene);
            stage.setResizable(false);
            stage.setAlwaysOnTop(true);

            controller.setup(quiz, prestart, () -> {
                dismissedIds.add(quiz.getId());
                stage.close();
            }, () -> {
                dismissedIds.add(quiz.getId());
                stage.close();
                startQuiz(user, quiz);
            });

            stage.setOnHidden(e -> dialogOpen.set(false));
            stage.show();
        } catch (Exception e) {
            dialogOpen.set(false);
            e.printStackTrace();
        }
    }

    private void startQuiz(ForumUser user, Quiz quiz) {
        try {
            StudentQuizLauncher.LaunchRequest request = StudentQuizLauncher.prepare(user, quiz);
            openQuizWindow(request);
        } catch (Exception e) {
            javafx.scene.control.Alert alert = new javafx.scene.control.Alert(
                javafx.scene.control.Alert.AlertType.WARNING);
            alert.setTitle("Unable to Start");
            alert.setHeaderText(null);
            alert.setContentText(e.getMessage());
            alert.showAndWait();
        }
    }

    public static void openQuizWindow(StudentQuizLauncher.LaunchRequest request) throws Exception {
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
                false
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
}
