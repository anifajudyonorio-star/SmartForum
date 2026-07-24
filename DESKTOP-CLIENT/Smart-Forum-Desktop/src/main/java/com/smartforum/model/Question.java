package com.smartforum.model;

public class Question {

    private int id;
    private int quizId;
    private String quizTitle;
    private String question;
    private String optionA;
    private String optionB;
    private String optionC;
    private String optionD;
    private String correctAnswer;
    private int marks = 1;
    private int optionAId;
    private int optionBId;
    private int optionCId;
    private int optionDId;

    public Question() {}

    public Question(int id, int quizId, String quizTitle, String question,
                    String optionA, String optionB, String optionC,
                    String optionD, String correctAnswer) {
        this.id = id;
        this.quizId = quizId;
        this.quizTitle = quizTitle;
        this.question = question;
        this.optionA = optionA;
        this.optionB = optionB;
        this.optionC = optionC;
        this.optionD = optionD;
        this.correctAnswer = correctAnswer;
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getQuizId() { return quizId; }
    public void setQuizId(int quizId) { this.quizId = quizId; }

    public String getQuizTitle() { return quizTitle; }
    public void setQuizTitle(String quizTitle) { this.quizTitle = quizTitle; }

    public String getQuestion() { return question; }
    public void setQuestion(String question) { this.question = question; }

    public String getOptionA() { return optionA; }
    public void setOptionA(String optionA) { this.optionA = optionA; }

    public String getOptionB() { return optionB; }
    public void setOptionB(String optionB) { this.optionB = optionB; }

    public String getOptionC() { return optionC; }
    public void setOptionC(String optionC) { this.optionC = optionC; }

    public String getOptionD() { return optionD; }
    public void setOptionD(String optionD) { this.optionD = optionD; }

    public String getCorrectAnswer() { return correctAnswer; }
    public void setCorrectAnswer(String correctAnswer) { this.correctAnswer = correctAnswer; }

    public int getMarks() { return marks; }
    public void setMarks(int marks) { this.marks = marks; }

    /** Summary of options for review tables (A. text ✓ | B. text …). */
    public String getOptionsDisplay() {
        StringBuilder sb = new StringBuilder();
        appendOption(sb, "A", optionA, "A".equalsIgnoreCase(correctAnswer));
        appendOption(sb, "B", optionB, "B".equalsIgnoreCase(correctAnswer));
        appendOption(sb, "C", optionC, "C".equalsIgnoreCase(correctAnswer));
        appendOption(sb, "D", optionD, "D".equalsIgnoreCase(correctAnswer));
        return sb.toString();
    }

    private static void appendOption(StringBuilder sb, String letter, String text, boolean correct) {
        if (text == null || text.isBlank()) {
            return;
        }
        if (sb.length() > 0) {
            sb.append("  |  ");
        }
        sb.append(letter).append(". ").append(text);
        if (correct) {
            sb.append(" ✓");
        }
    }

    public int getOptionAId() { return optionAId; }
    public void setOptionAId(int optionAId) { this.optionAId = optionAId; }

    public int getOptionBId() { return optionBId; }
    public void setOptionBId(int optionBId) { this.optionBId = optionBId; }

    public int getOptionCId() { return optionCId; }
    public void setOptionCId(int optionCId) { this.optionCId = optionCId; }

    public int getOptionDId() { return optionDId; }
    public void setOptionDId(int optionDId) { this.optionDId = optionDId; }

    public int optionIdForLetter(String letter) {
        return switch (letter) {
            case "A" -> optionAId;
            case "B" -> optionBId;
            case "C" -> optionCId;
            case "D" -> optionDId;
            default -> 0;
        };
    }
}
