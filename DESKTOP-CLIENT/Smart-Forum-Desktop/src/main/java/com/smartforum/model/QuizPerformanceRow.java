package com.smartforum.model;

public class QuizPerformanceRow {
    private final String studentName;
    private final int score;
    private final int finalPossibleMarks;
    private final boolean submitted;

    public QuizPerformanceRow(String studentName, int score, int finalPossibleMarks, boolean submitted) {
        this.studentName = studentName;
        this.score = score;
        this.finalPossibleMarks = finalPossibleMarks;
        this.submitted = submitted;
    }

    public String getStudentName() {
        return studentName;
    }

    public String getScoreDisplay() {
        return submitted ? score + " / " + finalPossibleMarks : "— / " + finalPossibleMarks;
    }

    public String getPercentage() {
        if (!submitted) return "—";
        if (finalPossibleMarks <= 0) return "N/A";
        return String.format("%.1f%%", score * 100.0 / finalPossibleMarks);
    }

    public String getStatus() {
        return submitted ? "Submitted" : "Not submitted";
    }
}
