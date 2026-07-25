package com.smartforum.model;

public record RecommendedTopic(
        int id,
        String title,
        String description,
        double score,
        int groupId,
        String groupName,
        boolean canView,
        String joinStatus
) {
}
