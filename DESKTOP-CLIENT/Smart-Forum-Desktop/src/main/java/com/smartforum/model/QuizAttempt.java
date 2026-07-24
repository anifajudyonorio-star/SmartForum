package com.smartforum.model;

import java.time.LocalDateTime;

public class QuizAttempt {
    private int id;
    private int quizId;
    private int studentId;
    private String studentName;
    private int categoryId;
    private LocalDateTime startedAt;
    private LocalDateTime deadlineAt;
    private String status;
    private String answers;
    /** When >= 0, preferred over deadlineAt for the countdown (API remaining_seconds). */
    private long remainingSeconds = -1;

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }
    public int getQuizId() { return quizId; }
    public void setQuizId(int quizId) { this.quizId = quizId; }
    public int getStudentId() { return studentId; }
    public void setStudentId(int studentId) { this.studentId = studentId; }
    public String getStudentName() { return studentName; }
    public void setStudentName(String studentName) { this.studentName = studentName; }
    public int getCategoryId() { return categoryId; }
    public void setCategoryId(int categoryId) { this.categoryId = categoryId; }
    public LocalDateTime getStartedAt() { return startedAt; }
    public void setStartedAt(LocalDateTime startedAt) { this.startedAt = startedAt; }
    public LocalDateTime getDeadlineAt() { return deadlineAt; }
    public void setDeadlineAt(LocalDateTime deadlineAt) { this.deadlineAt = deadlineAt; }
    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }
    public String getAnswers() { return answers; }
    public void setAnswers(String answers) { this.answers = answers; }
    public long getRemainingSeconds() { return remainingSeconds; }
    public void setRemainingSeconds(long remainingSeconds) { this.remainingSeconds = remainingSeconds; }
}
