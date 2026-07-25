package com.smartforum.model;

public record PostReport(
        int id,
        String reason,
        String status,
        int postId,
        String postContent,
        String authorName,
        String topicTitle,
        int topicId,
        String reporterName
) {
}
