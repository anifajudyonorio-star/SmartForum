package com.smartforum.util;

import com.google.gson.*;
import com.google.gson.reflect.TypeToken;

import java.io.*;
import java.lang.reflect.Type;
import java.nio.file.*;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public class OfflineQueue {
    private static final Path QUEUE_FILE = Paths.get(System.getProperty("user.home"), ".smartforum_queue.json");
    private static final Gson GSON = new Gson();

    public static class QueueEntry {
        public String actionType;
        public JsonObject payload;
        public String actionUuid;
        public String pendingId;
        public long queuedAt;

        public QueueEntry(String actionType, JsonObject payload) {
            this.actionType = actionType;
            this.payload = payload;
            this.actionUuid = UUID.randomUUID().toString();
            this.pendingId = this.actionUuid;
            this.queuedAt = System.currentTimeMillis();
        }
    }

    public static List<QueueEntry> getQueue() {
        try {
            if (!Files.exists(QUEUE_FILE)) return new ArrayList<>();
            String json = Files.readString(QUEUE_FILE);
            Type type = new TypeToken<List<QueueEntry>>() {}.getType();
            List<QueueEntry> list = GSON.fromJson(json, type);
            if (list == null) return new ArrayList<>();
            for (QueueEntry entry : list) {
                if (entry.actionUuid == null || entry.actionUuid.isBlank()) {
                    entry.actionUuid = entry.pendingId != null && !entry.pendingId.isBlank()
                            ? entry.pendingId : UUID.randomUUID().toString();
                }
                if (entry.pendingId == null || entry.pendingId.isBlank()) {
                    entry.pendingId = entry.actionUuid;
                }
            }
            return list;
        } catch (IOException e) {
            return new ArrayList<>();
        }
    }

    public static void saveQueue(List<QueueEntry> queue) {
        try {
            Files.writeString(QUEUE_FILE, GSON.toJson(queue));
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    public static String add(String actionType, JsonObject payload) {
        List<QueueEntry> queue = getQueue();
        QueueEntry entry = new QueueEntry(actionType, payload);
        queue.add(entry);
        saveQueue(queue);
        return entry.pendingId;
    }

    public static int size() {
        return getQueue().size();
    }

    public static void flush(Runnable onSuccess, Runnable onFailure) {
        List<QueueEntry> queue = getQueue();
        if (queue.isEmpty()) { if (onSuccess != null) onSuccess.run(); return; }

        String token = SessionManager.getInstance().getToken();
        if (token == null) { if (onFailure != null) onFailure.run(); return; }

        new Thread(() -> {
            try {
                List<QueueEntry> remaining = new ArrayList<>();
                for (QueueEntry entry : queue) {
                    boolean ok = false;
                    if ("create_post".equals(entry.actionType)) {
                        int topicId = entry.payload.get("topic_id").getAsInt();
                        String content = entry.payload.get("content").getAsString();
                        Integer parentId = entry.payload.has("parent_post_id")
                                ? entry.payload.get("parent_post_id").getAsInt() : null;
                        ok = com.smartforum.api.ApiClient.sendPost(topicId, content, parentId);
                    } else if ("create_topic".equals(entry.actionType)) {
                        int groupId = entry.payload.get("group_id").getAsInt();
                        String title = entry.payload.get("title").getAsString();
                        String desc = entry.payload.has("description")
                                ? entry.payload.get("description").getAsString() : "";
                        ok = com.smartforum.api.ApiClient.createTopic(groupId, title, desc);
                    }
                    if (!ok) remaining.add(entry);
                }
                saveQueue(remaining);
                if (remaining.isEmpty()) {
                    if (onSuccess != null) onSuccess.run();
                } else {
                    if (onFailure != null) onFailure.run();
                }
            } catch (Exception e) {
                e.printStackTrace();
                if (onFailure != null) onFailure.run();
            }
        }).start();
    }
}
