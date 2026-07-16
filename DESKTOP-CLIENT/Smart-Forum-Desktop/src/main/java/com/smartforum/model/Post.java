package com.smartforum.model;

import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

public class Post {
    private final int id;
    private final int topicId;
    private final Integer parentPostId;
    private final int authorId;
    private final String authorName;
    private final String content;
    private final LocalDateTime createdAt;
    private final List<Integer> hiddenFromUserIds;

    public Post(int id, int topicId, Integer parentPostId, int authorId, String authorName,
                String content, LocalDateTime createdAt, List<Integer> hiddenFromUserIds) {
        this.id = id;
        this.topicId = topicId;
        this.parentPostId = parentPostId;
        this.authorId = authorId;
        this.authorName = authorName;
        this.content = content;
        this.createdAt = createdAt;
        this.hiddenFromUserIds = hiddenFromUserIds == null
                ? new ArrayList<>()
                : new ArrayList<>(hiddenFromUserIds);
    }

    public int getId() {
        return id;
    }

    public int getTopicId() {
        return topicId;
    }

    public Integer getParentPostId() {
        return parentPostId;
    }

    public int getAuthorId() {
        return authorId;
    }

    public String getAuthorName() {
        return authorName;
    }

    public String getContent() {
        return content;
    }

    public LocalDateTime getCreatedAt() {
        return createdAt;
    }

    public List<Integer> getHiddenFromUserIds() {
        return hiddenFromUserIds;
    }

    public boolean isMine(int userId) {
        return authorId == userId;
    }

    public boolean isVisibleTo(int userId, boolean systemAdmin) {
        if (systemAdmin || authorId == userId) {
            return true;
        }
        return !hiddenFromUserIds.contains(userId);
    }
}
