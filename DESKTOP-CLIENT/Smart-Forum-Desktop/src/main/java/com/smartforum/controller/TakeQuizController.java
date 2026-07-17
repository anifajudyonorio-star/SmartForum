package com.smartforum.controller;

import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuestionDAO;
import com.smartforum.dao.QuizAttemptDAO;
import com.smartforum.dao.QuizCategoryDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.dao.QuizResultDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizAttempt;
import com.smartforum.model.QuizCategory;
import com.smartforum.model.QuizPerformanceRow;
import com.smartforum.service.AppSession;
import com.smartforum.service.QuizSubmissionService;
import com.smartforum.util.QuizSchedule;

import javafx.beans.property.SimpleIntegerProperty;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.VBox;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.stage.StageStyle;

import java.util.List;
import java.time.LocalDateTime;

public class TakeQuizController {

    @FXML private TextField txtStudentName;
    @FXML private Label lblCategoryInfo;
    @FXML private ComboBox<QuizCategory> cmbSelfCategory;
    @FXML private Button btnSelfEnroll;
    @FXML private TableView<Quiz> tblAvailableQuizzes;
    @FXML private TableColumn<Quiz, String> colQTitle, colQCategory, colQStart, colQEnd, colQStatus;
    @FXML private TableColumn<Quiz, Integer> colQQuestions, colQDuration;
    @FXML private Label lblAttemptNotice, lblReportTitle, lblReportEmpty;
    @FXML private VBox performanceReport;
    @FXML private TableView<QuizPerformanceRow> tblPerformance;
    @FXML private TableColumn<QuizPerformanceRow, String> colStudentName, colStudentScore,
        colStudentPercentage, colStudentSubmission;

    private int studentCategoryId = -1;

    @FXML
    public void initialize() {
        colQTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colQCategory.setCellValueFactory(new PropertyValueFactory<>("categoryName"));
        colQDuration.setCellValueFactory(new PropertyValueFactory<>("duration"));
        colQStart.setCellValueFactory(new PropertyValueFactory<>("startDate"));
        colQEnd.setCellValueFactory(new PropertyValueFactory<>("endDate"));

        colQStatus.setCellValueFactory(cd -> {
            return new SimpleStringProperty(QuizSchedule.availability(cd.getValue(), LocalDateTime.now()));
        });

        colQQuestions.setCellValueFactory(cd ->
            new SimpleIntegerProperty(
                new QuestionDAO().getQuestionsByQuizId(cd.getValue().getId()).size()
            ).asObject()
        );
        colStudentName.setCellValueFactory(new PropertyValueFactory<>("studentName"));
        colStudentScore.setCellValueFactory(new PropertyValueFactory<>("scoreDisplay"));
        colStudentPercentage.setCellValueFactory(new PropertyValueFactory<>("percentage"));
        colStudentSubmission.setCellValueFactory(new PropertyValueFactory<>("status"));
        txtStudentName.setEditable(false);
        cmbSelfCategory.setItems(FXCollections.observableArrayList(
            new QuizCategoryDAO().getAllCategories()
        ));
        loadForCurrentStudent();
    }

    /** Kept for callers compiled against the former API; the supplied name is intentionally ignored. */
    public void loadForStudent(String name) {
        loadForCurrentStudent();
    }

    public void loadForCurrentStudent() {
        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null || !AppSession.getInstance().isStudent()) {
            txtStudentName.clear();
            lblCategoryInfo.setText("Student access is required.");
            tblAvailableQuizzes.setItems(FXCollections.observableArrayList());
            return;
        }
        txtStudentName.setText(user.getName());

        QuizSubmissionService.FinalizationSummary finalization =
            new QuizSubmissionService().finalizeExpiredForCurrentStudent();
        showFinalizationNotice(finalization);
        studentCategoryId = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
        List<Quiz> quizzes;

        if (studentCategoryId == -1) {
            lblCategoryInfo.setText("⚠ You are not enrolled in a quiz category.");
            cmbSelfCategory.setDisable(false);
            btnSelfEnroll.setDisable(false);
            cmbSelfCategory.getSelectionModel().clearSelection();
            quizzes = List.of();
        } else {
            quizzes = new QuizDAO().getQuizzesByCategory(studentCategoryId);
            cmbSelfCategory.getItems().stream()
                .filter(category -> category.getId() == studentCategoryId)
                .findFirst()
                .ifPresent(cmbSelfCategory::setValue);
            cmbSelfCategory.setDisable(true);
            btnSelfEnroll.setDisable(true);
            lblCategoryInfo.setText("✔ Showing quizzes for your enrolled category (" + quizzes.size() + " available).");
        }

        tblAvailableQuizzes.setItems(FXCollections.observableArrayList(quizzes));
        performanceReport.setVisible(false);
        performanceReport.setManaged(false);
    }

    @FXML
    private void refreshPage() {
        loadForCurrentStudent();
    }

    @FXML
    private void selfEnroll() {
        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null || !AppSession.getInstance().isStudent()) {
            alert("Access Denied", "Only signed-in students can enroll in a quiz category.");
            return;
        }
        if (new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName()) >= 0) {
            alert("Already Enrolled", "You are already enrolled in a quiz category.");
            loadForCurrentStudent();
            return;
        }

        QuizCategory category = cmbSelfCategory.getValue();
        if (category == null) {
            alert("Select Category", "Choose the quiz category you want to join.");
            return;
        }

        Alert confirm = new Alert(
            Alert.AlertType.CONFIRMATION,
            "Enroll in \"" + category.getCategoryName() + "\"? You can belong to one quiz category.",
            ButtonType.YES,
            ButtonType.NO
        );
        confirm.setHeaderText("Confirm quiz category");
        if (confirm.showAndWait().orElse(ButtonType.NO) != ButtonType.YES) {
            return;
        }

        CategoryStudentDAO enrollmentDAO = new CategoryStudentDAO();
        if (!enrollmentDAO.enroll(category.getId(), user.getId(), user.getName())) {
            alert("Enrollment Failed", "You could not be enrolled. You may already belong to another category.");
            loadForCurrentStudent();
            return;
        }

        alert("Enrollment Complete", "You are now enrolled in " + category.getCategoryName() + ".");
        loadForCurrentStudent();
    }

    @FXML
    private void startQuiz() {
        Quiz selectedQuiz  = tblAvailableQuizzes.getSelectionModel().getSelectedItem();
        if (selectedQuiz == null)  { alert("Validation", "Select a quiz from the table."); return; }

        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null || !AppSession.getInstance().isStudent()) {
            alert("Access Denied", "Your session is no longer a student session.");
            return;
        }
        int currentCategory = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
        Quiz freshQuiz = new QuizDAO().getById(selectedQuiz.getId());
        if (currentCategory < 0 || freshQuiz == null || freshQuiz.getCategoryId() != currentCategory) {
            alert("Access Denied", "You are no longer enrolled for this quiz.");
            loadForCurrentStudent();
            return;
        }
        String availability = QuizSchedule.availability(freshQuiz, LocalDateTime.now());
        if (!"Available".equals(availability)) {
            alert("Quiz Unavailable", "This quiz cannot be started: " + availability + ".");
            loadForCurrentStudent();
            return;
        }
        if (freshQuiz.getDuration() <= 0) {
            alert("Quiz Unavailable", "This quiz has an invalid duration.");
            return;
        }
        List<Question> questions = new QuestionDAO().getQuestionsByQuizId(freshQuiz.getId());
        if (questions.isEmpty()) { alert("No Questions", "This quiz has no questions yet."); return; }
        boolean invalidQuestion = questions.stream().anyMatch(q ->
            q.getQuestion() == null || q.getQuestion().isBlank()
                || q.getOptionA() == null || q.getOptionA().isBlank()
                || q.getOptionB() == null || q.getOptionB().isBlank()
                || q.getOptionC() == null || q.getOptionC().isBlank()
                || q.getOptionD() == null || q.getOptionD().isBlank()
                || q.getCorrectAnswer() == null || !q.getCorrectAnswer().matches("[ABCD]")
                || q.getMarks() <= 0);
        if (invalidQuestion) {
            alert("Quiz Unavailable", "This quiz contains an invalid question. Ask the lecturer to correct it.");
            return;
        }
        QuizAttemptDAO attemptDAO = new QuizAttemptDAO();
        if (attemptDAO.hasCompletedResult(freshQuiz.getId(), user.getId(), user.getName())) {
            alert("Already Completed", "You have already submitted this quiz.");
            return;
        }
        try {
            QuizAttempt attempt = attemptDAO.startOrResume(
                freshQuiz, user.getId(), user.getName(), currentCategory);
            openLockedQuizWindow(freshQuiz, questions, user, attempt);
        } catch (Exception e) {
            alert("Unable to Start", e.getMessage());
        }
    }

    @FXML
    private void viewPerformanceReport() {
        Quiz selectedQuiz = tblAvailableQuizzes.getSelectionModel().getSelectedItem();
        if (selectedQuiz == null) {
            alert("Select Quiz", "Select an expired quiz to view its performance report.");
            return;
        }
        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (user == null || !AppSession.getInstance().isStudent()) {
            alert("Access Denied", "A signed-in student session is required.");
            return;
        }

        int currentCategory = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
        Quiz freshQuiz = new QuizDAO().getById(selectedQuiz.getId());
        if (currentCategory < 0 || freshQuiz == null || freshQuiz.getCategoryId() != currentCategory) {
            alert("Access Denied", "This report is not available for your current enrollment.");
            loadForCurrentStudent();
            return;
        }
        try {
            LocalDateTime globalEnd = QuizSchedule.parseEnd(freshQuiz.getEndDate());
            if (globalEnd == null || LocalDateTime.now().isBefore(globalEnd)) {
                alert("Report Unavailable", "Performance reports are available only after the quiz's global end time.");
                return;
            }
            List<QuizPerformanceRow> rows =
                new QuizResultDAO().getCategoryPerformanceReport(freshQuiz.getId(), currentCategory);
            lblReportTitle.setText("Category Performance — " + freshQuiz.getTitle());
            tblPerformance.setItems(FXCollections.observableArrayList(rows));
            lblReportEmpty.setText(rows.isEmpty()
                ? "No students are currently enrolled in this category."
                : rows.size() + " currently enrolled student(s). Correct answers are not shown.");
            performanceReport.setManaged(true);
            performanceReport.setVisible(true);
        } catch (Exception e) {
            alert("Report Unavailable", "The performance report could not be loaded: " + e.getMessage());
        }
    }

    private void showFinalizationNotice(QuizSubmissionService.FinalizationSummary summary) {
        int finalized = summary.getFinalized();
        int failed = summary.getFailed();
        if (finalized == 0 && failed == 0) {
            lblAttemptNotice.setVisible(false);
            lblAttemptNotice.setManaged(false);
            return;
        }
        String text = finalized > 0
            ? finalized + " expired quiz attempt(s) were submitted from saved answers."
            : "";
        if (failed > 0) {
            text += (text.isEmpty() ? "" : " ") + failed + " attempt(s) could not be finalized.";
        }
        lblAttemptNotice.setText(text);
        lblAttemptNotice.setManaged(true);
        lblAttemptNotice.setVisible(true);
    }

    private void openLockedQuizWindow(Quiz quiz, List<Question> questions, ForumUser user, QuizAttempt attempt) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/QuizModal.fxml"));
            Scene scene = new Scene(loader.load(), 760, 580);
            scene.getStylesheets().add(
                getClass().getResource("/com/smartforum/css/app.css").toExternalForm()
            );

            QuizModalController modal = loader.getController();
            modal.setup(quiz, questions, user, attempt);

            Stage stage = new Stage(StageStyle.UNDECORATED);
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.setAlwaysOnTop(true);
            stage.setScene(scene);
            stage.setOnCloseRequest(e -> e.consume());
            stage.showAndWait();

            // Reset after quiz closes
            loadForCurrentStudent();

        } catch (Exception e) {
            e.printStackTrace();
            alert("Error", "Failed to open quiz window: " + e.getMessage());
        }
    }

    private void alert(String title, String msg) {
        Alert a = new Alert(Alert.AlertType.INFORMATION);
        a.setTitle(title); a.setHeaderText(null); a.setContentText(msg);
        a.showAndWait();
    }
}
