package com.smartforum.util;

import com.google.gson.*;
import com.google.gson.reflect.TypeToken;
import com.smartforum.api.ApiClient;
import com.smartforum.service.PostService;
import com.smartforum.service.TopicService;

import java.io.IOException;
import java.lang.reflect.Type;
import java.nio.file.*;
import java.util.*;

public class OfflineQueue {
    private static final Path QUEUE_FILE = Paths.get(System.getProperty("user.home"), ".smartforum_queue.json");
    private static final Gson GSON = new Gson();
    private static final Set<String> DONE_STATUSES = Set.of("succeeded", "duplicate");

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
        if (queue.isEmpty()) {
            if (onSuccess != null) onSuccess.run();
            return;
        }

        String token = SessionManager.getInstance().getToken();
        if (token == null || token.isBlank()) {
            if (onFailure != null) onFailure.run();
            return;
        }

        try {
            String deviceId = DeviceIdStore.getDeviceId();
            if (!ApiClient.registerSyncDevice(deviceId)) {
                if (onFailure != null) onFailure.run();
                return;
            }

            JsonArray uploadActions = buildUploadPayload(queue);
            ApiClient.MutationResult upload = ApiClient.uploadSyncActions(uploadActions);
            if (!upload.success()) {
                if (onFailure != null) onFailure.run();
                return;
            }

            Map<Integer, Integer> remaps = new HashMap<>();
            collectTopicRemaps(queue, upload.body(), remaps);
            queue = mergeActionResults(queue, upload.body());
            saveQueue(queue);

            if (!queue.isEmpty()) {
                ApiClient.MutationResult sync = ApiClient.runSync(deviceId);
                if (!sync.success()) {
                    if (onFailure != null) onFailure.run();
                    return;
                }
                collectTopicRemaps(queue, sync.body(), remaps);
                queue = mergeActionResults(queue, sync.body());
                saveQueue(queue);
            }

            if (!remaps.isEmpty()) {
                TopicService.getInstance().remapTopicIds(remaps);
                PostService.getInstance().remapTopicIds(remaps);
            }

            if (queue.isEmpty()) {
                if (onSuccess != null) onSuccess.run();
            } else {
                if (onFailure != null) onFailure.run();
            }
        } catch (Exception e) {
            e.printStackTrace();
            if (onFailure != null) onFailure.run();
        }
    }

    private static JsonArray buildUploadPayload(List<QueueEntry> queue) {
        JsonArray actions = new JsonArray();
        for (QueueEntry entry : queue) {
            JsonObject action = new JsonObject();
            action.addProperty("action_uuid", entry.actionUuid);
            action.addProperty("action_type", entry.actionType);
            action.add("payload", entry.payload);
            actions.add(action);
        }
        return actions;
    }

    private static List<QueueEntry> mergeActionResults(List<QueueEntry> queue, JsonObject response) {
        if (response == null || !response.has("actions") || !response.get("actions").isJsonArray()) {
            return queue;
        }

        Map<String, JsonObject> byUuid = new HashMap<>();
        for (JsonElement element : response.getAsJsonArray("actions")) {
            if (!element.isJsonObject()) continue;
            JsonObject result = element.getAsJsonObject();
            if (result.has("action_uuid")) {
                byUuid.put(result.get("action_uuid").getAsString(), result);
            }
        }

        List<QueueEntry> remaining = new ArrayList<>();
        for (QueueEntry entry : queue) {
            JsonObject result = byUuid.get(entry.actionUuid);
            if (result != null && result.has("status")
                    && DONE_STATUSES.contains(result.get("status").getAsString())) {
                continue;
            }
            remaining.add(entry);
        }
        return remaining;
    }

    private static void collectTopicRemaps(
            List<QueueEntry> queue,
            JsonObject response,
            Map<Integer, Integer> remaps) {
        if (response == null || !response.has("actions") || !response.get("actions").isJsonArray()) {
            return;
        }

        Map<String, QueueEntry> byUuid = new HashMap<>();
        for (QueueEntry entry : queue) {
            byUuid.put(entry.actionUuid, entry);
        }

        for (JsonElement element : response.getAsJsonArray("actions")) {
            if (!element.isJsonObject()) continue;
            JsonObject result = element.getAsJsonObject();
            if (!result.has("status") || !DONE_STATUSES.contains(result.get("status").getAsString())) {
                continue;
            }
            if (!result.has("action_uuid") || !result.has("action_type")) continue;
            if (!"create_topic".equals(result.get("action_type").getAsString())) continue;
            if (!result.has("resource_id")) continue;

            QueueEntry entry = byUuid.get(result.get("action_uuid").getAsString());
            if (entry == null || entry.payload == null || !entry.payload.has("client_topic_id")) {
                continue;
            }

            int clientId = entry.payload.get("client_topic_id").getAsInt();
            int serverId = result.get("resource_id").getAsInt();
            remaps.put(clientId, serverId);
        }
    }
}
