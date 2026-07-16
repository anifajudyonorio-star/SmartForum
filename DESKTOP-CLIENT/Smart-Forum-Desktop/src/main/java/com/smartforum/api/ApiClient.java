package com.smartforum.api;

import com.google.gson.*;
import com.smartforum.model.*;
import com.smartforum.util.SessionManager;

import java.net.URI;
import java.net.http.*;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ApiClient {
    public static final String BASE_URL = "http://127.0.0.1:8000";
    private static final HttpClient HTTP = HttpClient.newHttpClient();

    private ApiClient() {
    }

    private static HttpRequest.Builder builder(String path) {
        String token = SessionManager.getInstance().getToken();
        HttpRequest.Builder b = HttpRequest.newBuilder()
                .uri(URI.create(BASE_URL + path))
                .header("Accept", "application/json")
                .header("Content-Type", "application/json");
        if (token != null && !token.isBlank()) {
            b.header("Authorization", "Bearer " + token);
        }
        return b;
    }

    private static Optional<JsonObject> getJson(String path) {
        try {
            HttpResponse<String> res = HTTP.send(
                    builder(path).GET().build(),
                    HttpResponse.BodyHandlers.ofString());
            if (res.statusCode() < 200 || res.statusCode() >= 300) {
                return Optional.empty();
            }
            return Optional.of(JsonParser.parseString(res.body()).getAsJsonObject());
        } catch (Exception e) {
            e.printStackTrace();
            return Optional.empty();
        }
    }

    private static boolean sendJson(String method, String path, JsonObject body) {
        try {
            HttpRequest.Builder req = builder(path);
            HttpRequest.BodyPublisher publisher = body == null
                    ? HttpRequest.BodyPublishers.noBody()
                    : HttpRequest.BodyPublishers.ofString(body.toString());

            HttpRequest request = switch (method) {
                case "POST" -> req.POST(publisher).build();
                case "PUT" -> req.PUT(publisher).build();
                case "PATCH" -> req.method("PATCH", publisher).build();
                case "DELETE" -> req.DELETE().build();
                default -> req.GET().build();
            };

            HttpResponse<String> res = HTTP.send(request, HttpResponse.BodyHandlers.ofString());
            return res.statusCode() >= 200 && res.statusCode() < 300;
        } catch (Exception e) {
            e.printStackTrace();
            return false;
        }
    }

    // ΓöÇΓöÇ Auth ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    public static Optional<JsonObject> loginResponse(String email, String password) {
        try {
            JsonObject body = new JsonObject();
            body.addProperty("email", email);
            body.addProperty("password", password);

            HttpResponse<String> res = HTTP.send(
                    HttpRequest.newBuilder()
                            .uri(URI.create(BASE_URL + "/api/login"))
                            .header("Accept", "application/json")
                            .header("Content-Type", "application/json")
                            .POST(HttpRequest.BodyPublishers.ofString(body.toString()))
                            .build(),
                    HttpResponse.BodyHandlers.ofString());

            if (res.statusCode() == 200) {
                return Optional.of(JsonParser.parseString(res.body()).getAsJsonObject());
            }
            return Optional.empty();
        } catch (Exception e) {
            e.printStackTrace();
            return Optional.empty();
        }
    }

    public static String login(String email, String password) {
        return loginResponse(email, password)
                .map(json -> json.has("token") ? json.get("token").getAsString() : null)
                .orElse(null);
    }

    public static Optional<ForumUser> fetchCurrentUser() {
        return getJson("/api/user")
                .map(json -> json.has("user")
                        ? ApiMapper.toForumUser(json.getAsJsonObject("user"))
                        : null);
    }

    /** @deprecated Use {@link #login(String, String)} */
    public static String getToken(String email, String password) {
        return login(email, password);
    }

    // ΓöÇΓöÇ Dashboard ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    public static Optional<JsonObject> getDashboard() {
        return getJson("/api/dashboard");
    }

    public static Optional<JsonObject> getStatistics() {
        return getJson("/api/statistics");
    }

    public static Optional<JsonObject> getGroupStatistics(int groupId) {
        return getJson("/api/statistics/groups/" + groupId);
    }

    public static Optional<JsonObject> getParticipation(Integer groupId) {
        String path = groupId == null ? "/api/participation" : "/api/participation?group=" + groupId;
        return getJson(path);
    }

    // ΓöÇΓöÇ Groups ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    public static List<Group> fetchGroups() {
        return getJson("/api/groups")
                .map(json -> ApiMapper.toGroups(json.getAsJsonArray("groups")))
                .orElseGet(ArrayList::new);
    }

    public static Optional<JsonObject> fetchGroupDetail(int groupId) {
        return getJson("/api/groups/" + groupId);
    }

    public static boolean createGroup(String name, String description) {
        JsonObject body = new JsonObject();
        body.addProperty("Group_Name", name);
        body.addProperty("Description", description);
        return sendJson("POST", "/api/groups", body);
    }

    public static boolean addGroupMember(int groupId, int userId, String role) {
        JsonObject body = new JsonObject();
        body.addProperty("user_id", userId);
        body.addProperty("Member_Role", role);
        return sendJson("POST", "/api/groups/" + groupId + "/members", body);
    }

    public static boolean removeGroupMember(int groupId, int userId) {
        return sendJson("DELETE", "/api/groups/" + groupId + "/members/" + userId, null);
    }

    public static boolean updateMemberRole(int groupId, int userId, String role) {
        JsonObject body = new JsonObject();
        body.addProperty("Member_Role", role);
        return sendJson("PATCH", "/api/groups/" + groupId + "/members/" + userId + "/role", body);
    }

    // ΓöÇΓöÇ Topics ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    public static List<Topic> fetchTopics() {
        return getJson("/api/topics")
                .map(json -> ApiMapper.toTopics(json.getAsJsonArray("topics")))
                .orElseGet(ArrayList::new);
    }

    public static List<Topic> fetchTopicsForGroup(int groupId) {
        return getJson("/api/groups/" + groupId + "/topics")
                .map(json -> ApiMapper.toTopics(json.getAsJsonArray("topics")))
                .orElseGet(ArrayList::new);
    }

    public static List<TopicSearchResult> searchTopics(String query) {
        String path = "/api/topics/search?search="
                + java.net.URLEncoder.encode(query == null ? "" : query, java.nio.charset.StandardCharsets.UTF_8);
        return getJson(path)
                .map(json -> {
                    List<TopicSearchResult> results = new ArrayList<>();
                    for (JsonElement element : json.getAsJsonArray("topics")) {
                        results.add(ApiMapper.toSearchResult(element.getAsJsonObject()));
                    }
                    return results;
                })
                .orElseGet(ArrayList::new);
    }

    public static boolean createTopic(int groupId, String title, String description) {
        JsonObject body = new JsonObject();
        body.addProperty("Title", title);
        body.addProperty("Topic_Description", description);
        return sendJson("POST", "/api/groups/" + groupId + "/topics", body);
    }

    public static boolean updateTopic(int topicId, String title, String description) {
        JsonObject body = new JsonObject();
        body.addProperty("Title", title);
        body.addProperty("Topic_Description", description);
        return sendJson("PUT", "/api/topics/" + topicId, body);
    }

    public static boolean deleteTopic(int topicId) {
        return sendJson("DELETE", "/api/topics/" + topicId, null);
    }

    // ΓöÇΓöÇ Posts ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    public static List<Post> fetchPosts(int topicId) {
        try {
            HttpResponse<String> res = HTTP.send(
                    builder("/api/topics/" + topicId).GET().build(),
                    HttpResponse.BodyHandlers.ofString());
            if (res.statusCode() != 200) {
                return new ArrayList<>();
            }
            JsonObject json = JsonParser.parseString(res.body()).getAsJsonObject();
            JsonArray arr = json.has("posts") ? json.getAsJsonArray("posts") : new JsonArray();
            List<Post> posts = ApiMapper.toPosts(arr);
            for (Post post : posts) {
                if (post.getTopicId() == 0) {
                    // ApiMapper creates posts without topic id from array ΓÇö re-wrap if needed
                }
            }
            return posts.stream()
                    .map(post -> new Post(
                            post.getId(),
                            topicId,
                            post.getParentPostId(),
                            post.getAuthorId(),
                            post.getAuthorName(),
                            post.getContent(),
                            post.getCreatedAt(),
                            post.getHiddenFromUserIds()))
                    .toList();
        } catch (Exception e) {
            e.printStackTrace();
            return new ArrayList<>();
        }
    }

    public static boolean sendPost(int topicId, String content, Integer parentPostId) {
        JsonObject body = new JsonObject();
        body.addProperty("Post_Content", content);
        if (parentPostId != null) {
            body.addProperty("Parent_Post_ID", parentPostId);
        }
        return sendJson("POST", "/api/topics/" + topicId + "/posts", body);
    }

    public static boolean updatePost(int postId, String content) {
        JsonObject body = new JsonObject();
        body.addProperty("Post_Content", content);
        return sendJson("PUT", "/api/posts/" + postId, body);
    }

    public static boolean deletePost(int postId) {
        return sendJson("DELETE", "/api/posts/" + postId, null);
    }

    // ΓöÇΓöÇ Notifications ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    public static Optional<JsonObject> getNotifications() {
        return getJson("/api/notifications");
    }

    public static Optional<JsonObject> pollNotifications(int afterId) {
        return getJson("/api/notifications/poll?after=" + afterId);
    }

    public static boolean markNotificationRead(int notificationId) {
        return sendJson("PATCH", "/api/notifications/" + notificationId + "/read", null);
    }

    // Legacy aliases used by ChatController
    public static List<Topic> getTopics() {
        return fetchTopics();
    }

    public static List<Post> getPosts(int topicId) {
        return fetchPosts(topicId);
    }
}
