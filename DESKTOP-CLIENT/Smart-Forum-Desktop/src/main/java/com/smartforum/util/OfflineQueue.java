package com.smartforum.util;

import com.google.gson.*;
import com.google.gson.reflect.TypeToken;
import com.smartforum.api.ApiClient;
import com.smartforum.service.PostService;
import com.smartforum.service.TopicService;
import com.smartforum.UserSession;

import java.io.IOException;
import java.lang.reflect.Type;
import java.nio.file.*;
import java.util.*;

public class OfflineQueue {
    private static final Path QUEUE_FILE = Paths.get(System.getProperty("user.home"), ".smartforum_queue.json");
    private static final Gson GSON = new Gson();
    private static final Set<String> DONE_STATUSES = Set.of("succeeded", "duplicate");
    private static final Set<String> REMOVE_STATUSES = Set.of("succeeded", "duplicate", "failed");

    private static volatile String lastFlushMessage = "";

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

    public static String getLastFlushMessage() {
        return lastFlushMessage;
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
        NetworkMonitor.probeNow();
        return entry.pendingId;
    }

    public static int size() {
        return getQueue().size();
    }

    public static void flush(Runnable onSuccess, Runnable onFailure) {
        if (flushInternal()) {
            if (onSuccess != null) onSuccess.run();
        } else if (onFailure != null) {
            onFailure.run();
        }
    }

    private static boolean flushInternal() {
        lastFlushMessage = "";
        List<QueueEntry> queue = getQueue();
        if (queue.isEmpty()) {
            return true;
        }

        if (!NetworkMonitor.isOnline()) {
            lastFlushMessage = "Still offline — actions will sync when the server is reachable.";
            return false;
        }

        String token = resolveToken();
        if (token == null || token.isBlank()) {
            lastFlushMessage = "Sign in again to sync offline actions.";
            return false;
        }

        int initialSize = queue.size();

        queue = applyDirectActions(queue);
        saveQueue(queue);
        if (queue.isEmpty()) {
            if (lastFlushMessage.isBlank()) {
                lastFlushMessage = "Offline actions synced.";
                return true;
            }
            return false;
        }

        try {
            String deviceId = DeviceIdStore.getDeviceId();
            ApiClient.MutationResult deviceResult = ApiClient.registerSyncDeviceResult(deviceId);
            if (!deviceResult.success()) {
                queue = applyDirectActions(queue);
                saveQueue(queue);
                if (queue.isEmpty()) {
                    lastFlushMessage = "Offline actions synced.";
                    return true;
                }
                lastFlushMessage = deviceResult.message();
                return false;
            }

            Map<Integer, Integer> remaps = new HashMap<>();

            ApiClient.MutationResult upload = ApiClient.uploadSyncActions(buildUploadPayload(queue));
            if (upload.success()) {
                collectTopicRemaps(queue, upload.body(), remaps);
                queue = mergeActionResults(queue, upload.body());
                applyRemapsToQueue(queue, remaps);
                saveQueue(queue);

                ApiClient.MutationResult sync = ApiClient.runSync(deviceId);
                if (sync.success()) {
                    collectTopicRemaps(queue, sync.body(), remaps);
                    queue = mergeActionResults(queue, sync.body());
                    applyRemapsToQueue(queue, remaps);
                    saveQueue(queue);
                    if (!remaps.isEmpty()) {
                        TopicService.getInstance().remapTopicIds(remaps);
                        PostService.getInstance().remapTopicIds(remaps);
                    }
                } else if (lastFlushMessage.isBlank()) {
                    lastFlushMessage = sync.message();
                }
            } else if (lastFlushMessage.isBlank()) {
                lastFlushMessage = upload.message();
            }

            if (!queue.isEmpty()) {
                queue = applyDirectActions(queue);
                saveQueue(queue);
            }

            if (queue.isEmpty()) {
                if (lastFlushMessage.isBlank()) {
                    lastFlushMessage = "Offline actions synced.";
                    return true;
                }
                return false;
            }

            if (queue.size() < initialSize) {
                lastFlushMessage = queue.size() + " action(s) could not be synced yet.";
                return false;
            }

            if (lastFlushMessage.isBlank()) {
                lastFlushMessage = "Could not reach the server. Make sure Laravel is running on "
                        + ApiClient.getBaseUrl() + ".";
            }
            return false;
        } catch (Exception e) {
            e.printStackTrace();
            lastFlushMessage = "Sync error: " + e.getMessage();
            return false;
        }
    }

    private static List<QueueEntry> applyDirectActions(List<QueueEntry> queue) {
        if (!NetworkMonitor.isOnline()) {
            return queue;
        }

        List<QueueEntry> remaining = new ArrayList<>();
        for (QueueEntry entry : queue) {
            if (tryDirectAction(entry)) {
                continue;
            }
            remaining.add(entry);
        }
        return remaining;
    }

    private static boolean tryDirectAction(QueueEntry entry) {
        if (entry.payload == null || entry.actionType == null) {
            return false;
        }

        try {
            return switch (entry.actionType) {
                case "create_post" -> tryDirectCreatePost(entry);
                case "create_topic" -> tryDirectCreateTopic(entry);
                case "view_topic" -> tryDirectViewTopic(entry);
                case "update_post" -> tryDirectUpdatePost(entry);
                case "delete_post" -> tryDirectDeletePost(entry);
                default -> false;
            };
        } catch (Exception e) {
            if (lastFlushMessage.isBlank()) {
                lastFlushMessage = e.getMessage();
            }
            return false;
        }
    }

    private static boolean tryDirectCreatePost(QueueEntry entry) {
        int topicId = TopicService.getInstance().resolveTopicId(entry.payload.get("topic_id").getAsInt());
        String content = entry.payload.get("content").getAsString();
        Integer parentId = entry.payload.has("parent_post_id") && !entry.payload.get("parent_post_id").isJsonNull()
                ? entry.payload.get("parent_post_id").getAsInt()
                : null;

        ApiClient.MutationResult result = ApiClient.sendPostResult(
                topicId,
                content,
                parentId,
                readExcludedUserIds(entry.payload));
        if (result.success()) {
            lastFlushMessage = "";
            return true;
        }

        lastFlushMessage = result.message();
        return isPermanentFailure(result.statusCode());
    }

    private static boolean tryDirectCreateTopic(QueueEntry entry) {
        int groupId = entry.payload.get("group_id").getAsInt();
        String title = entry.payload.get("title").getAsString();
        String description = entry.payload.has("description") && !entry.payload.get("description").isJsonNull()
                ? entry.payload.get("description").getAsString()
                : "";

        if (!ApiClient.createTopic(groupId, title, description)) {
            return false;
        }

        if (entry.payload.has("client_topic_id")) {
            int clientId = entry.payload.get("client_topic_id").getAsInt();
            TopicService.getInstance().getTopicsForGroup(groupId).stream()
                    .filter(topic -> title.equals(topic.getTitle()))
                    .findFirst()
                    .ifPresent(topic -> TopicService.getInstance().remapTopicIds(
                            Map.of(clientId, topic.getId())));
        }
        return true;
    }

    private static boolean tryDirectViewTopic(QueueEntry entry) {
        int topicId = TopicService.getInstance().resolveTopicId(entry.payload.get("topic_id").getAsInt());
        return ApiClient.recordTopicView(topicId);
    }

    private static boolean tryDirectUpdatePost(QueueEntry entry) {
        int postId = entry.payload.get("post_id").getAsInt();
        String content = entry.payload.get("content").getAsString();
        ApiClient.MutationResult result = ApiClient.updatePostResult(
                postId,
                content,
                readExcludedUserIds(entry.payload));
        if (result.success()) {
            lastFlushMessage = "";
            return true;
        }
        lastFlushMessage = result.message();
        return isPermanentFailure(result.statusCode());
    }

    private static boolean tryDirectDeletePost(QueueEntry entry) {
        int postId = entry.payload.get("post_id").getAsInt();
        ApiClient.MutationResult result = ApiClient.deletePostResult(postId);
        if (result.success()) {
            lastFlushMessage = "";
            return true;
        }
        lastFlushMessage = result.message();
        return isPermanentFailure(result.statusCode()) || result.statusCode() == 404;
    }

    private static String resolveToken() {
        SessionManager session = SessionManager.getInstance();
        String token = session.getToken();
        if (token == null || token.isBlank()) {
            token = UserSession.getInstance().getToken();
            if (token != null && !token.isBlank()) {
                session.setToken(token);
            }
        }
        return token;
    }

    private static void applyRemapsToQueue(List<QueueEntry> queue, Map<Integer, Integer> remaps) {
        if (remaps == null || remaps.isEmpty()) {
            return;
        }
        for (QueueEntry entry : queue) {
            if (entry.payload == null || !entry.payload.has("topic_id")) {
                continue;
            }
            int topicId = entry.payload.get("topic_id").getAsInt();
            Integer serverId = remaps.get(topicId);
            if (serverId != null) {
                entry.payload.addProperty("topic_id", serverId);
            }
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
            if (result != null && result.has("status")) {
                String status = result.get("status").getAsString();
                if (REMOVE_STATUSES.contains(status)) {
                    if ("failed".equals(status) && result.has("reason")) {
                        lastFlushMessage = result.get("reason").getAsString();
                    }
                    continue;
                }
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

    private static boolean isPermanentFailure(int statusCode) {
        return statusCode == 403 || statusCode == 404 || statusCode == 422;
    }

    private static List<Integer> readExcludedUserIds(JsonObject payload) {
        if (payload == null || !payload.has("excluded_users") || !payload.get("excluded_users").isJsonArray()) {
            return List.of();
        }
        List<Integer> ids = new ArrayList<>();
        for (JsonElement element : payload.getAsJsonArray("excluded_users")) {
            if (element != null && !element.isJsonNull()) {
                ids.add(element.getAsInt());
            }
        }
        return ids;
    }
}
