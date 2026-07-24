package com.smartforum.service;

import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuestionDAO;
import com.smartforum.dao.QuizAttemptDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizAttempt;
import com.smartforum.util.QuizSchedule;

import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.List;

/**
 * Shared validation + attempt start used by Take Quiz UI and the launch popup.
 */
public final class StudentQuizLauncher {

    private StudentQuizLauncher() {}

    public static final class LaunchRequest {
        private final Quiz quiz;
        private final List<Question> questions;
        private final ForumUser student;
        private final QuizAttempt attempt;

        public LaunchRequest(Quiz quiz, List<Question> questions, ForumUser student, QuizAttempt attempt) {
            this.quiz = quiz;
            this.questions = questions;
            this.student = student;
            this.attempt = attempt;
        }

        public Quiz getQuiz() { return quiz; }
        public List<Question> getQuestions() { return questions; }
        public ForumUser getStudent() { return student; }
        public QuizAttempt getAttempt() { return attempt; }
    }

    public static LaunchRequest prepare(ForumUser user, Quiz selectedQuiz) throws SQLException {
        if (user == null) {
            throw new IllegalStateException("A signed-in student session is required.");
        }
        if (selectedQuiz == null) {
            throw new IllegalArgumentException("Select a quiz first.");
        }

        int currentCategory = new CategoryStudentDAO().getCategoryForStudent(user.getId(), user.getName());
        Quiz freshQuiz = new QuizDAO().getById(selectedQuiz.getId());
        if (currentCategory < 0 || freshQuiz == null || freshQuiz.getCategoryId() != currentCategory) {
            throw new IllegalStateException("You are no longer enrolled for this quiz.");
        }

        String availability = QuizSchedule.availability(freshQuiz, LocalDateTime.now());
        if (!"Available".equals(availability)) {
            throw new IllegalStateException("This quiz cannot be started: " + availability + ".");
        }
        if (freshQuiz.getDuration() <= 0) {
            throw new IllegalStateException("This quiz has an invalid duration.");
        }

        List<Question> questions = new QuestionDAO().getQuestionsByQuizId(freshQuiz.getId());
        if (questions.isEmpty()) {
            throw new IllegalStateException("This quiz has no questions yet.");
        }
        boolean invalidQuestion = questions.stream().anyMatch(q ->
            q.getQuestion() == null || q.getQuestion().isBlank()
                || q.getOptionA() == null || q.getOptionA().isBlank()
                || q.getOptionB() == null || q.getOptionB().isBlank()
                || q.getOptionC() == null || q.getOptionC().isBlank()
                || q.getOptionD() == null || q.getOptionD().isBlank()
                || q.getCorrectAnswer() == null || !q.getCorrectAnswer().matches("[ABCD]")
                || q.getMarks() <= 0);
        if (invalidQuestion) {
            throw new IllegalStateException("This quiz contains an invalid question. Ask the lecturer to correct it.");
        }

        QuizAttemptDAO attemptDAO = new QuizAttemptDAO();
        if (attemptDAO.hasCompletedResult(freshQuiz.getId(), user.getId(), user.getName())) {
            throw new IllegalStateException("You have already submitted this quiz.");
        }

        QuizAttempt attempt = attemptDAO.startOrResume(
            freshQuiz, user.getId(), user.getName(), currentCategory);
        return new LaunchRequest(freshQuiz, questions, user, attempt);
    }
}
