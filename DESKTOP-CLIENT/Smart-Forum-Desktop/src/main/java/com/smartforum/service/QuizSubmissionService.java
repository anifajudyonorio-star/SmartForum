package com.smartforum.service;

import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuestionDAO;
import com.smartforum.dao.QuizAttemptDAO;
import com.smartforum.dao.QuizDAO;
import com.smartforum.dao.QuizResultDAO;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Question;
import com.smartforum.model.Quiz;
import com.smartforum.model.QuizAttempt;
import com.smartforum.model.QuizResult;

import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class QuizSubmissionService {
    private final QuizAttemptDAO attemptDAO = new QuizAttemptDAO();
    private final QuizResultDAO resultDAO = new QuizResultDAO();
    private final QuizDAO quizDAO = new QuizDAO();
    private final QuestionDAO questionDAO = new QuestionDAO();
    private final CategoryStudentDAO enrollmentDAO = new CategoryStudentDAO();

    public Submission submitForCurrentStudent(int attemptId) throws SQLException {
        ForumUser student = requireCurrentStudent();
        QuizAttempt attempt = attemptDAO.getForStudent(attemptId, student.getId(), student.getName());
        if (attempt == null) throw new SQLException("The quiz attempt was not found for this student session.");

        Quiz quiz = quizDAO.getById(attempt.getQuizId());
        int enrolledCategory = enrollmentDAO.getCategoryForStudent(student.getId(), student.getName());
        if (quiz == null || enrolledCategory < 0 || attempt.getCategoryId() != enrolledCategory
                || quiz.getCategoryId() != enrolledCategory) {
            throw new SQLException("Your current enrollment no longer permits this quiz submission.");
        }

        QuizResult existing = resultDAO.getStudentResult(quiz.getId(), student.getId(), student.getName());
        if (existing != null) return new Submission(existing, false, true);
        if (!"IN_PROGRESS".equals(attempt.getStatus())) {
            throw new SQLException("This attempt has already been finalized.");
        }

        List<Question> questions = questionDAO.getQuestionsByQuizId(quiz.getId());
        if (questions.isEmpty()) throw new SQLException("This quiz no longer has questions to grade.");
        Map<Integer, String> answers = decodeAnswers(attempt.getAnswers());
        int score = 0;
        int authoredTotal = 0;
        for (Question question : questions) {
            authoredTotal += Math.max(0, question.getMarks());
            if (question.getCorrectAnswer() != null
                    && question.getCorrectAnswer().equals(answers.get(question.getId()))) {
                score += Math.max(0, question.getMarks());
            }
        }
        int participation = Math.max(0, quiz.getParticipationMarks());

        QuizResult result = new QuizResult();
        result.setQuizId(quiz.getId());
        result.setQuizTitle(quiz.getTitle());
        result.setStudentId(student.getId());
        result.setStudentName(student.getName());
        result.setCategoryId(enrolledCategory);
        result.setScore(score);
        result.setTotalMarks(authoredTotal);
        result.setParticipationMarks(participation);
        result.setTotalScore(score + participation);
        result.setFinalPossibleMarks(authoredTotal + participation);

        QuizResultDAO.SubmissionReceipt receipt = resultDAO.submitResult(result, attempt.getId());
        return new Submission(receipt.getResult(), receipt.isTimedOut(), receipt.isAlreadySubmitted());
    }

    public FinalizationSummary finalizeExpiredForCurrentStudent() {
        int finalized = 0;
        int failed = 0;
        try {
            ForumUser student = requireCurrentStudent();
            int categoryId = enrollmentDAO.getCategoryForStudent(student.getId(), student.getName());
            if (categoryId < 0) return new FinalizationSummary(0, 0);
            List<QuizAttempt> expired = attemptDAO.getExpiredInProgressAttempts(
                student.getId(), student.getName(), categoryId, LocalDateTime.now());
            for (QuizAttempt attempt : expired) {
                try {
                    Submission submission = submitForCurrentStudent(attempt.getId());
                    if (!submission.isAlreadySubmitted()) finalized++;
                } catch (Exception e) {
                    failed++;
                }
            }
        } catch (Exception e) {
            failed++;
        }
        return new FinalizationSummary(finalized, failed);
    }

    private ForumUser requireCurrentStudent() throws SQLException {
        AppSession session = AppSession.getInstance();
        ForumUser student = session.getCurrentUser();
        if (student == null || !"student".equalsIgnoreCase(student.getSystemRole())) {
            throw new SQLException("A signed-in student session is required.");
        }
        return student;
    }

    private Map<Integer, String> decodeAnswers(String encoded) {
        Map<Integer, String> answers = new HashMap<>();
        if (encoded == null || encoded.isBlank()) return answers;
        for (String entry : encoded.split(";")) {
            String[] parts = entry.split("=", 2);
            if (parts.length != 2 || !parts[1].matches("[ABCD]")) continue;
            try {
                answers.put(Integer.parseInt(parts[0]), parts[1]);
            } catch (NumberFormatException ignored) {
                // Ignore malformed legacy progress while preserving all valid answers.
            }
        }
        return answers;
    }

    public static final class Submission {
        private final QuizResult result;
        private final boolean timedOut;
        private final boolean alreadySubmitted;

        public Submission(QuizResult result, boolean timedOut, boolean alreadySubmitted) {
            this.result = result;
            this.timedOut = timedOut;
            this.alreadySubmitted = alreadySubmitted;
        }

        public QuizResult getResult() { return result; }
        public boolean isTimedOut() { return timedOut; }
        public boolean isAlreadySubmitted() { return alreadySubmitted; }
    }

    public static final class FinalizationSummary {
        private final int finalized;
        private final int failed;

        public FinalizationSummary(int finalized, int failed) {
            this.finalized = finalized;
            this.failed = failed;
        }

        public int getFinalized() { return finalized; }
        public int getFailed() { return failed; }
    }
}
