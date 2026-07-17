package com.smartforum.service;

/**
 * Clears in-memory forum data so the desktop client only shows API-backed content.
 */
public final class ForumDataCache {
    private ForumDataCache() {
    }

    public static void clearAll() {
        GroupService.getInstance().clearCache();
        TopicService.getInstance().clearCache();
        PostService.getInstance().clearCache();
    }
}
