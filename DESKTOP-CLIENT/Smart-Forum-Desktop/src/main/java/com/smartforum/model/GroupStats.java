package com.smartforum.model;

public record GroupStats(
        int totalMembers,
        int totalTopics,
        int totalPosts,
        int activeMembers,
        int suspendedMembers,
        int blockedMembers,
        GroupHighlight mostActiveMember,
        GroupHighlight topTopicCreator,
        GroupHighlight mostActiveTopic,
        int membersWithWarnings,
        int adminCount,
        String avgPostsPerTopic
) {
}
