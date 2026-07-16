package com.smartforum.api;

import com.google.gson.*;
import com.google.gson.reflect.TypeToken;
import com.smartforum.model.Group;
import com.smartforum.model.Post;
import com.smartforum.model.Topic;
import com.smartforum.util.SessionManager;

import java.lang.reflect.Type;
import java.net.URI;
import java.net.http.*;
import java.util.ArrayList;
import java.util.List;

public class ApiClient {
    private static final String BASE_URL = "http://127.0.0.1:8000";
    private static final HttpClient HTTP = HttpClient.newHttpClient();
    private static final Gson GSON = new Gson();

    private static HttpRequest.Builder builder(String path) {
        String token = SessionManager.getInstance().getToken();
        HttpRequest.Builder b = HttpRequest.newBuilder()
                .uri(URI.create(BASE_URL + path))
                .header("Accept", "application/json")
                .header("Content-Type", "application/json");
        if (token != null) b.header("Authorization", "Bearer " + token);
        return b;
    }

    public static List<Group> getGroups() {
        try {
            HttpResponse<String> res = HTTP.send(
                    builder("/api/groups").GET().build(),
                    HttpResponse.BodyHandlers.ofString());
            if (res.statusCode() != 200) return new ArrayList<>();
            Type type = new TypeToken<List<Group>>() {}.getType();
            return GSON.fromJson(res.body(), type);
        } catch (Exception e) {
            return new ArrayList<>();
        }
    }

    public static List<Topic> getTopics() {
        try {
            HttpResponse<String> res = HTTP.send(
                    builder("/api/topics").GET().build(),
                    HttpResponse.BodyHandlers.ofString());
            if (res.statusCode() != 200) return new ArrayList<>();
            Type type = new TypeToken<List<Topic>>() {}.getType();
            return GSON.fromJson(res.body(), type);
        } catch (Exception e) {
            e.printStackTrace();
            return new ArrayList<>();
        }
    }

    public static List<Post> getPosts(int topicId) {
        try {
            HttpResponse<String> res = HTTP.send(
                    builder("/api/topics/" + topicId).GET().build(),
                    HttpResponse.BodyHandlers.ofString());
            if (res.statusCode() != 200) return new ArrayList<>();
            JsonObject json = JsonParser.parseString(res.body()).getAsJsonObject();
            JsonArray arr = json.has("posts") ? json.getAsJsonArray("posts") : new JsonArray();
            Type type = new TypeToken<List<Post>>() {}.getType();
            return GSON.fromJson(arr, type);
        } catch (Exception e) {
            e.printStackTrace();
            return new ArrayList<>();
        }
    }

    public static boolean sendPost(int topicId, String content, Integer parentPostId) {
        try {
            JsonObject body = new JsonObject();
            body.addProperty("Post_Content", content);
            body.addProperty("Topic_ID", topicId);
            if (parentPostId != null) body.addProperty("Parent_Post_ID", parentPostId);

            HttpResponse<String> res = HTTP.send(
                    builder("/topics/" + topicId + "/posts")
                            .POST(HttpRequest.BodyPublishers.ofString(body.toString()))
                            .build(),
                    HttpResponse.BodyHandlers.ofString());
            return res.statusCode() == 200 || res.statusCode() == 201 || res.statusCode() == 302;
        } catch (Exception e) {
            e.printStackTrace();
            return false;
        }
    }

    public static String getToken(String email, String password) {
        try {
            JsonObject body = new JsonObject();
            body.addProperty("email", email);
            body.addProperty("password", password);

            HttpResponse<String> res = HTTP.send(
                    HttpRequest.newBuilder()
                            .uri(URI.create(BASE_URL + "/api/token"))
                            .header("Accept", "application/json")
                            .header("Content-Type", "application/json")
                            .POST(HttpRequest.BodyPublishers.ofString(body.toString()))
                            .build(),
                    HttpResponse.BodyHandlers.ofString());

            if (res.statusCode() == 200) {
                JsonObject json = JsonParser.parseString(res.body()).getAsJsonObject();
                return json.has("token") ? json.get("token").getAsString() : null;
            }
            return null;
        } catch (Exception e) {
            e.printStackTrace();
            return null;
        }
    }
}
