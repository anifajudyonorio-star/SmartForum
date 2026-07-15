package com.smartforum.model;

public class Post {
    private int id;
    private int topicId;
    private int createdBy;
    private String postContent;
    private Integer parentPostId;
    private String createdAt;
    private String authorName;

    public int getId() { return id; }
    public int getTopicId() { return topicId; }
    public int getCreatedBy() { return createdBy; }
    public String getPostContent() { return postContent; }
    public Integer getParentPostId() { return parentPostId; }
    public String getCreatedAt() { return createdAt; }
    public String getAuthorName() { return authorName; }

    public void setId(int id) { this.id = id; }
    public void setTopicId(int topicId) { this.topicId = topicId; }
    public void setCreatedBy(int createdBy) { this.createdBy = createdBy; }
    public void setPostContent(String postContent) { this.postContent = postContent; }
    public void setParentPostId(Integer parentPostId) { this.parentPostId = parentPostId; }
    public void setCreatedAt(String createdAt) { this.createdAt = createdAt; }
    public void setAuthorName(String authorName) { this.authorName = authorName; }
}
