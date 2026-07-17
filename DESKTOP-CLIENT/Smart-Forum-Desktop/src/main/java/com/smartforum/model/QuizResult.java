package com.smartforum.model;

public class QuizResult {

    private int id;
    private int quizId;
    private String quizTitle;
    private String studentName;
    private Integer studentId;
    private int categoryId;
    private int score;
    private int totalMarks;
    private int participationMarks;
    private int totalScore;
    private int finalPossibleMarks;
    private String submittedAt;

    public QuizResult() {}

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getQuizId() { return quizId; }
    public void setQuizId(int quizId) { this.quizId = quizId; }

    public String getQuizTitle() { return quizTitle; }
    public void setQuizTitle(String quizTitle) { this.quizTitle = quizTitle; }

    public String getStudentName() { return studentName; }
    public void setStudentName(String studentName) { this.studentName = studentName; }
    public Integer getStudentId() { return studentId; }
    public void setStudentId(Integer studentId) { this.studentId = studentId; }

    public int getCategoryId() { return categoryId; }
    public void setCategoryId(int categoryId) { this.categoryId = categoryId; }

    public int getScore() { return score; }
    public void setScore(int score) { this.score = score; }

    public int getTotalMarks() { return totalMarks; }
    public void setTotalMarks(int totalMarks) { this.totalMarks = totalMarks; }

    public int getParticipationMarks() { return participationMarks; }
    public void setParticipationMarks(int participationMarks) { this.participationMarks = participationMarks; }

    public int getTotalScore() { return totalScore; }
    public void setTotalScore(int totalScore) { this.totalScore = totalScore; }
    public int getFinalPossibleMarks() {
        return finalPossibleMarks > 0 ? finalPossibleMarks : totalMarks + Math.max(0, participationMarks);
    }
    public void setFinalPossibleMarks(int finalPossibleMarks) { this.finalPossibleMarks = finalPossibleMarks; }

    public String getSubmittedAt() { return submittedAt; }
    public void setSubmittedAt(String submittedAt) { this.submittedAt = submittedAt; }

    public String getPercentage() {
        int possible = getFinalPossibleMarks();
        if (possible <= 0) return "N/A";
        double pct = (totalScore * 100.0) / possible;
        return String.format("%.1f%%", pct);
    }
}
