package com.smartforum.model;

public record TopicSearchResult(
        Topic topic,
        String groupName,
        int postsCount
) {
}
