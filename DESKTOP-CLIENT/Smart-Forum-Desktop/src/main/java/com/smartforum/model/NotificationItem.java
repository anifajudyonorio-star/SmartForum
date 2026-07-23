package com.smartforum.model;

public class NotificationItem {
    private final int id;
    private final String title;
    private final String message;
    private final String type;
    private boolean read;
    private final String time;
    private final Integer topicId;
    private final Integer groupId;
    private final Integer quizId;

    public NotificationItem(int id, String title, String message, String type, boolean read,
                            String time, Integer topicId, Integer groupId, Integer quizId) {
        this.id = id;
        this.title = title;
        this.message = message;
        this.type = type;
        this.read = read;
        this.time = time;
        this.topicId = topicId;
        this.groupId = groupId;
        this.quizId = quizId;
    }

    public int getId() {
        return id;
    }

    public String getTitle() {
        return title;
    }

    public String getMessage() {
        return message;
    }

    public String getType() {
        return type;
    }

    public boolean isRead() {
        return read;
    }

    public void setRead(boolean read) {
        this.read = read;
    }

    public String getTime() {
        return time;
    }

    public Integer getTopicId() {
        return topicId;
    }

    public Integer getGroupId() {
        return groupId;
    }

    public Integer getQuizId() {
        return quizId;
    }

    public String getIcon() {
        return switch (type == null ? "" : type) {
            case "Quiz" -> "❓";
            case "warning" -> "⚠";
            case "PostCreated" -> "💬";
            case "reply" -> "↩";
            default -> "🔔";
        };
    }
}
