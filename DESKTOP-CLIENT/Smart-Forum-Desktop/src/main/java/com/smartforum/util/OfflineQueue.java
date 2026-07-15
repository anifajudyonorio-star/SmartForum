package com.smartforum.util;

import com.google.gson.*;
import com.google.gson.reflect.TypeToken;

import java.io.*;
import java.lang.reflect.Type;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.file.*;
import java.util.ArrayList;
import java.util.List;

public class OfflineQueue {
    private static final Path QUEUE_FILE = Paths.get(System.getProperty("user.home"), ".smartforum_queue.json");
    private static final String BASE_URL = "http://127.0.0.1:8000/api";
    private static final Gson GSON = new Gson();
    private static final HttpClient HTTP = HttpClient.newHttpClient();

    public static class QueueEntry {
        public String actionType;
        public JsonObject payload;
        public String pendingId;
        public long queuedAt;

        public QueueEntry(String actionType, JsonObject payload) {
            this.actionType = actionType;
            this.payload = payload;
            this.pendingId = "p-" + System.currentTimeMillis();
            this.queuedAt = System.currentTimeMillis();
        }
    }

    public static List<QueueEntry> getQueue() {
        try {
            if (!Files.exists(QUEUE_FILE)) return new ArrayList<>();
            String json = Files.readString(QUEUE_FILE);
            Type type = new TypeToken<List<QueueEntry>>() {}.getType();
            List<QueueEntry> list = GSON.fromJson(json, type);
            return list != null ? list : new ArrayList<>();
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
                // 1. Register device
                String deviceId = getDeviceId();
                JsonObject deviceBody = new JsonObject();
                deviceBody.addProperty("device_id", deviceId);
                deviceBody.addProperty("device_name", "Desktop Client");
                deviceBody.addProperty("device_type", "desktop");
                post("/sync/device", deviceBody.toString(), token);

                // 2. Upload actions
                JsonArray actions = new JsonArray();
                for (QueueEntry e : queue) {
                    JsonObject action = new JsonObject();
                    action.addProperty("action_type", e.actionType);
                    action.add("payload", e.payload);
                    actions.add(action);
                }
                JsonObject uploadBody = new JsonObject();
                uploadBody.add("actions", actions);
                HttpResponse<String> uploadRes = post("/sync/upload", uploadBody.toString(), token);
                if (uploadRes.statusCode() != 200) { if (onFailure != null) onFailure.run(); return; }

                // 3. Sync
                JsonObject syncBody = new JsonObject();
                syncBody.addProperty("device_id", deviceId);
                HttpResponse<String> syncRes = post("/sync", syncBody.toString(), token);
                if (syncRes.statusCode() == 200) {
                    saveQueue(new ArrayList<>());
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

    private static HttpResponse<String> post(String path, String body, String token) throws Exception {
        HttpRequest req = HttpRequest.newBuilder()
                .uri(URI.create(BASE_URL + path))
                .header("Content-Type", "application/json")
                .header("Accept", "application/json")
                .header("Authorization", "Bearer " + token)
                .POST(HttpRequest.BodyPublishers.ofString(body))
                .build();
        return HTTP.send(req, HttpResponse.BodyHandlers.ofString());
    }

    private static String getDeviceId() {
        Path idFile = Paths.get(System.getProperty("user.home"), ".smartforum_device_id");
        try {
            if (Files.exists(idFile)) return Files.readString(idFile).trim();
            String id = "desktop-" + java.util.UUID.randomUUID();
            Files.writeString(idFile, id);
            return id;
        } catch (IOException e) {
            return "desktop-unknown";
        }
    }
}
