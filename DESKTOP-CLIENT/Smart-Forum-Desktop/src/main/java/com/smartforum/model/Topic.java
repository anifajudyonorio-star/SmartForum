package com.smartforum.model;

public class Topic {
    private int id;
    private String title;
    private String topicDescription;
    private int groupId;
    private int createdBy;
    private String createdAt;

    public int getId() { return id; }
    public String getTitle() { return title; }
    public String getTopicDescription() { return topicDescription; }
    public int getGroupId() { return groupId; }
    public int getCreatedBy() { return createdBy; }
    public String getCreatedAt() { return createdAt; }

    public void setId(int id) { this.id = id; }
    public void setTitle(String title) { this.title = title; }
    public void setTopicDescription(String topicDescription) { this.topicDescription = topicDescription; }
    public void setGroupId(int groupId) { this.groupId = groupId; }
    public void setCreatedBy(int createdBy) { this.createdBy = createdBy; }
    public void setCreatedAt(String createdAt) { this.createdAt = createdAt; }

    @Override
    public String toString() { return title; }
}
