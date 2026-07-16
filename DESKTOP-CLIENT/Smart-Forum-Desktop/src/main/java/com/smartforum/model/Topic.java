package com.smartforum.model;

public class Topic {
    private final int id;
    private final int groupId;
    private final String title;
    private final String description;
    private final int createdBy;
    private final String authorName;

    public Topic(int id, int groupId, String title, String description, int createdBy, String authorName) {
        this.id = id;
        this.groupId = groupId;
        this.title = title;
        this.description = description;
        this.createdBy = createdBy;
        this.authorName = authorName;
    }

    public int getId() {
        return id;
    }

    public int getGroupId() {
        return groupId;
    }

    public String getTitle() {
        return title;
    }

    public String getDescription() {
        return description;
    }

    public int getCreatedBy() {
        return createdBy;
    }

    public String getAuthorName() {
        return authorName;
    }

    public String getInitials() {
        if (title == null || title.isBlank()) {
            return "??";
        }
        return title.substring(0, Math.min(2, title.length())).toUpperCase();
    }

    @Override
    public String toString() {
        return title;
    }
}
