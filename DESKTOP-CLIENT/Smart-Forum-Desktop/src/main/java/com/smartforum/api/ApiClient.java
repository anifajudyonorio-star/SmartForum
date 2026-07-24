package com.smartforum.api;

import com.google.gson.*;
import com.smartforum.model.*;
import com.smartforum.util.SessionManager;
import com.smartforum.UserSession;

import java.net.URI;
import java.net.http.*;
import java.time.Duration;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ApiClient {
    public static final String BASE_URL = "http://127.0.0.1:8000";
    private static final Duration REQUEST_TIMEOUT = Duration.ofSeconds(20);
    private static final HttpClient HTTP = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(10))
            .build();

    private ApiClient() {
    }

    private static HttpRequest.Builder builder(String path) {
        String token = SessionManager.getInstance().getToken();
        if (token == null || token.isBlank()) {
            token = UserSession.getInstance().getToken();
        }
        HttpRequest.Builder b = HttpRequest.newBuilder()
                .uri(URI.create(BASE_URL + path))
                .timeout(REQUEST_TIMEOUT)
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
        return mutateJson(method, path, body).success();
    }

    public record MutationResult(boolean success, int statusCode, String message, JsonObject body) {
    }

    public static MutationResult mutateJson(String method, String path, JsonObject body) {
        try {
            HttpRequest.Builder req = builder(path);
            HttpRequest.BodyPublisher publisher = body == null
                    ? HttpRequest.BodyPublishers.noBody()
                    : HttpRequest.BodyPublishers.ofString(body.toString());

            HttpRequest request = switch (method) {
                case "POST" -> req.POST(publisher).build();
                case "PUT" -> req.PUT(publisher).build();
                case "PATCH" -> req.method("PATCH", publisher).build();
                case "DELETE" -> body == null
                        ? req.DELETE().build()
                        : req.method("DELETE", publisher).build();
                default -> req.GET().build();
            };

            HttpResponse<String> res = HTTP.send(request, HttpResponse.BodyHandlers.ofString());
            JsonObject parsed = parseBody(res.body());
            boolean ok = res.statusCode() >= 200 && res.statusCode() < 300;
            String message = ok ? extractMessage(parsed, true) : describeHttpFailure(res.statusCode(), parsed);
            return new MutationResult(ok, res.statusCode(), message, parsed);
        } catch (Exception e) {
            e.printStackTrace();
            JsonObject err = new JsonObject();
            err.addProperty("message", "Could not reach the server.");
            return new MutationResult(false, 0, describeHttpFailure(0, err), err);
        }
    }

    private static JsonObject parseBody(String body) {
        if (body == null || body.isBlank()) {
            return new JsonObject();
        }
        try {
            return JsonParser.parseString(body).getAsJsonObject();
        } catch (Exception ignored) {
            JsonObject fallback = new JsonObject();
            fallback.addProperty("message", body);
            return fallback;
        }
    }

    private static String extractMessage(JsonObject json, boolean ok) {
        if (json.has("message")) {
            return json.get("message").getAsString();
        }
        return ok ? "Success" : "Request failed";
    }

    public static String describeHttpFailure(int statusCode, JsonObject json) {
        if (statusCode == 401) {
            return "Session expired — sign in again to sync.";
        }
        if (statusCode == 403) {
            return "Not allowed to perform this action on the server.";
        }
        if (statusCode == 404) {
            return "The topic or group no longer exists.";
        }
        if (statusCode == 422) {
            String fieldError = firstFieldError(json, "actions.0.payload.content");
            if (fieldError != null) {
                return fieldError;
            }
        }
        if (statusCode == 0) {
            return "Could not reach the server. Make sure Laravel is running on " + BASE_URL + ".";
        }
        return extractMessage(json, false);
    }

    public static String firstFieldError(JsonObject json, String field) {
        if (json == null || !json.has("errors") || !json.get("errors").isJsonObject()) {
            return null;
        }
        JsonObject errors = json.getAsJsonObject("errors");
        if (!errors.has(field) || !errors.get(field).isJsonArray()) {
            return null;
        }
        JsonArray arr = errors.getAsJsonArray(field);
        return arr.isEmpty() ? null : arr.get(0).getAsString();
    }

    public static MutationResult updateProfile(String name, String email) {
        JsonObject body = new JsonObject();
        body.addProperty("name", name);
        body.addProperty("email", email);
        return mutateJson("PATCH", "/api/profile", body);
    }

    public static MutationResult updatePassword(String currentPassword, String password, String confirmation) {
        JsonObject body = new JsonObject();
        body.addProperty("current_password", currentPassword);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", confirmation);
        return mutateJson("PUT", "/api/profile/password", body);
    }

    public static MutationResult deleteAccount(String password) {
        JsonObject body = new JsonObject();
        body.addProperty("password", password);
        return mutateJson("DELETE", "/api/profile", body);
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

    public static Optional<JsonObject> getQuizAnnouncements() {
        return getJson("/api/quiz-announcements");
    }

    public static Optional<JsonObject> getStudentAnnouncements() {
        return getJson("/api/student/announcements");
    }

    public static Optional<JsonObject> getAdminUsers() {
        return getJson("/api/admin/users");
    }

    public static MutationResult fetchAdminUsers() {
        try {
            HttpResponse<String> res = HTTP.send(
                    builder("/api/admin/users").GET().build(),
                    HttpResponse.BodyHandlers.ofString());
            JsonObject parsed = parseBody(res.body());
            boolean ok = res.statusCode() >= 200 && res.statusCode() < 300;
            String message = extractMessage(parsed, ok);
            if (!ok && (message == null || message.isBlank() || "Request failed".equals(message))) {
                message = "Request failed (HTTP " + res.statusCode() + ").";
            }
            return new MutationResult(ok, res.statusCode(), message, parsed);
        } catch (Exception e) {
            e.printStackTrace();
            JsonObject err = new JsonObject();
            err.addProperty("message", "Could not reach the server.");
            return new MutationResult(false, 0, err.get("message").getAsString(), err);
        }
    }

    public static MutationResult createAdminUser(String fname, String lname, String email, String password, String confirmation) {
        JsonObject body = new JsonObject();
        body.addProperty("Fname", fname);
        body.addProperty("Lname", lname);
        body.addProperty("email", email);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", confirmation);
        return mutateJson("POST", "/api/admin/users", body);
    }

    public static MutationResult warnAdminUser(int userId, String reason) {
        JsonObject body = new JsonObject();
        body.addProperty("reason", reason == null ? "" : reason);
        return mutateJson("POST", "/api/admin/users/" + userId + "/warn", body);
    }

    public static MutationResult promoteAdminUser(int userId, String role) {
        JsonObject body = new JsonObject();
        body.addProperty("role", role);
        return mutateJson("POST", "/api/admin/users/" + userId + "/promote", body);
    }

    public static MutationResult blacklistAdminUser(int userId, String reason) {
        JsonObject body = new JsonObject();
        body.addProperty("reason", reason == null ? "" : reason);
        return mutateJson("POST", "/api/admin/users/" + userId + "/blacklist", body);
    }

    public static MutationResult unblacklistAdminUser(int userId) {
        return mutateJson("POST", "/api/admin/users/" + userId + "/unblacklist", new JsonObject());
    }

    public static MutationResult postQuizAnnouncement(int categoryId, String title, String message) {
        JsonObject body = new JsonObject();
        body.addProperty("category_id", categoryId);
        body.addProperty("title", title);
        body.addProperty("message", message);
        return mutateJson("POST", "/api/quiz-announcements", body);
    }

    public static MutationResult deleteQuizAnnouncement(int announcementId) {
        return mutateJson("DELETE", "/api/quiz-announcements/" + announcementId, null);
    }

    public static Optional<JsonObject> getManagedQuizzes() {
        return getJson("/api/quizzes");
    }

    public static MutationResult publishQuiz(int quizId) {
        return mutateJson("PATCH", "/api/quizzes/" + quizId + "/publish", new JsonObject());
    }

    public static MutationResult deleteQuiz(int quizId) {
        return mutateJson("DELETE", "/api/quizzes/" + quizId, null);
    }

    public static Optional<JsonObject> getStudentQuizzes() {
        return getJson("/api/student/quizzes");
    }

    public static MutationResult enrollInQuizCategory(int categoryId) {
        JsonObject body = new JsonObject();
        body.addProperty("category_id", categoryId);
        return mutateJson("POST", "/api/student/quizzes/enroll", body);
    }

    public static MutationResult unenrollFromQuizCategory() {
        return mutateJson("POST", "/api/student/quizzes/unenroll", new JsonObject());
    }

    public static Optional<JsonObject> getStudentQuizSession(int quizId, boolean start) {
        String path = "/api/student/quizzes/" + quizId + (start ? "?start=1" : "");
        return getJson(path);
    }

    public static MutationResult submitStudentQuiz(int quizId, int attemptId, JsonObject answers) {
        JsonObject body = new JsonObject();
        body.addProperty("attempt_id", attemptId);
        body.add("answers", answers);
        return mutateJson("POST", "/api/student/quizzes/" + quizId + "/submit", body);
    }

    // ΓöÇΓöÇ Groups ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    public static List<Group> fetchGroups() {
        return getJson("/api/groups")
                .map(json -> ApiMapper.toGroups(json.getAsJsonArray("groups")))
                .orElseGet(ArrayList::new);
    }

    public static List<Group> fetchExploreGroups() {
        return getJson("/api/groups/explore")
                .map(json -> ApiMapper.toGroups(json.getAsJsonArray("groups")))
                .orElseGet(ArrayList::new);
    }

    public static boolean requestJoinGroup(int groupId) {
        return sendJson("POST", "/api/groups/" + groupId + "/join", new JsonObject());
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
        return tryFetchPosts(topicId).orElseGet(ArrayList::new);
    }

    /** Empty optional when the API is unreachable or returns a non-success response. */
    public static Optional<List<Post>> tryFetchPosts(int topicId) {
        try {
            HttpResponse<String> res = HTTP.send(
                    builder("/api/topics/" + topicId).GET().build(),
                    HttpResponse.BodyHandlers.ofString());
            if (res.statusCode() != 200) {
                return Optional.empty();
            }
            JsonObject json = JsonParser.parseString(res.body()).getAsJsonObject();
            JsonArray arr = json.has("posts") ? json.getAsJsonArray("posts") : new JsonArray();
            List<Post> posts = ApiMapper.toPosts(arr);
            return Optional.of(posts.stream()
                    .map(post -> new Post(
                            post.getId(),
                            topicId,
                            post.getParentPostId(),
                            post.getAuthorId(),
                            post.getAuthorName(),
                            post.getContent(),
                            post.getCreatedAt(),
                            post.getHiddenFromUserIds()))
                    .toList());
        } catch (Exception e) {
            e.printStackTrace();
            return Optional.empty();
        }
    }

    public static boolean recordTopicView(int topicId) {
        return sendJson("POST", "/api/topics/" + topicId + "/view", null);
    }
 
    public static boolean sendPost(int topicId, String content, Integer parentPostId) {
        return sendPostResult(topicId, content, parentPostId, List.of()).success();
    }

    public static boolean sendPost(int topicId, String content, Integer parentPostId, List<Integer> excludedUserIds) {
        return sendPostResult(topicId, content, parentPostId, excludedUserIds).success();
    }

    public static MutationResult sendPostResult(int topicId, String content, Integer parentPostId) {
        return sendPostResult(topicId, content, parentPostId, List.of());
    }

    public static MutationResult sendPostResult(
            int topicId,
            String content,
            Integer parentPostId,
            List<Integer> excludedUserIds) {
        JsonObject body = new JsonObject();
        body.addProperty("Post_Content", content);
        if (parentPostId != null) {
            body.addProperty("Parent_Post_ID", parentPostId);
        }
        addExcludedUsers(body, excludedUserIds);
        return mutateJson("POST", "/api/topics/" + topicId + "/posts", body);
    }

    public static boolean updatePost(int postId, String content) {
        return updatePost(postId, content, List.of());
    }

    public static boolean updatePost(int postId, String content, List<Integer> excludedUserIds) {
        JsonObject body = new JsonObject();
        body.addProperty("Post_Content", content);
        addExcludedUsers(body, excludedUserIds);
        return sendJson("PUT", "/api/posts/" + postId, body);
    }

    private static void addExcludedUsers(JsonObject body, List<Integer> excludedUserIds) {
        if (excludedUserIds == null || excludedUserIds.isEmpty()) {
            return;
        }
        JsonArray arr = new JsonArray();
        for (Integer id : excludedUserIds) {
            if (id != null && id > 0) {
                arr.add(id);
            }
        }
        if (!arr.isEmpty()) {
            body.add("excluded_users", arr);
        }
    }

    public static boolean deletePost(int postId) {
        return sendJson("DELETE", "/api/posts/" + postId, null);
    }

    public static MutationResult reportPost(int postId, String reason) {
        JsonObject body = new JsonObject();
        if (reason != null && !reason.isBlank()) {
            body.addProperty("reason", reason);
        }
        return mutateJson("POST", "/api/posts/" + postId + "/report", body);
    }

    public static Optional<JsonObject> fetchPostReports(int groupId) {
        return getJson("/api/groups/" + groupId + "/post-reports");
    }

    public static MutationResult restoreReportedPost(int groupId, int reportId) {
        return mutateJson("POST", "/api/groups/" + groupId + "/post-reports/" + reportId + "/restore", new JsonObject());
    }

    public static MutationResult deleteReportedPost(int groupId, int reportId) {
        return mutateJson("DELETE", "/api/groups/" + groupId + "/post-reports/" + reportId, null);
    }

    // ΓöÇΓöÇ Notifications ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ

    public static Optional<JsonObject> getNotifications() {
        return getJson("/api/notifications");
    }

    public static Optional<JsonObject> pollNotifications(int afterId) {
        return getJson("/api/notifications/poll?after=" + afterId);
    }

    public static boolean markNotificationRead(int notificationId) {
        return mutateJson("PATCH", "/api/notifications/" + notificationId + "/read", null).success();
    }

    public static MutationResult markNotificationReadResult(int notificationId) {
        return mutateJson("PATCH", "/api/notifications/" + notificationId + "/read", null);
    }

    // Legacy aliases used by ChatController
    public static List<Topic> getTopics() {
        return fetchTopics();
    }

    public static List<Post> getPosts(int topicId) {
        return fetchPosts(topicId);
    }

    // ── Admin user management ──────────────────────────────────────────────────

    public static MutationResult adminWarnUser(int userId, String reason) {
        JsonObject body = new JsonObject();
        if (reason != null && !reason.isBlank()) body.addProperty("reason", reason);
        return mutateJson("POST", "/api/admin/users/" + userId + "/warn", body);
    }

    public static MutationResult adminBlacklistUser(int userId, String reason) {
        JsonObject body = new JsonObject();
        if (reason != null && !reason.isBlank()) body.addProperty("reason", reason);
        return mutateJson("POST", "/api/admin/users/" + userId + "/blacklist", body);
    }

    public static MutationResult adminUnblacklistUser(int userId) {
        return mutateJson("POST", "/api/admin/users/" + userId + "/unblacklist", new JsonObject());
    }

    public static MutationResult adminChangeRole(int userId, String role) {
        JsonObject body = new JsonObject();
        body.addProperty("role", role);
        return mutateJson("PATCH", "/api/admin/users/" + userId + "/role", body);
    }

    public static MutationResult adminCreateLecturer(String fname, String lname, String email, String password) {
        JsonObject body = new JsonObject();
        body.addProperty("Fname", fname);
        body.addProperty("Lname", lname);
        body.addProperty("email", email);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", password);
        return mutateJson("POST", "/api/admin/users", body);
    }

    // ── Offline sync (mirrors web offline.js) ────────────────────────────────

    public static boolean registerSyncDevice(String deviceId) {
        return registerSyncDeviceResult(deviceId).success();
    }

    public static MutationResult registerSyncDeviceResult(String deviceId) {
        JsonObject body = new JsonObject();
        body.addProperty("device_id", deviceId);
        body.addProperty("device_name", System.getProperty("os.name", "Desktop"));
        body.addProperty("device_type", "desktop");
        return mutateJson("POST", "/api/sync/device", body);
    }

    public static MutationResult uploadSyncActions(JsonArray actions) {
        JsonObject body = new JsonObject();
        body.add("actions", actions);
        return mutateJson("POST", "/api/sync/upload", body);
    }

    public static MutationResult runSync(String deviceId) {
        JsonObject body = new JsonObject();
        body.addProperty("device_id", deviceId);
        return mutateJson("POST", "/api/sync", body);
    }

    public static boolean pingServer() {
        for (int attempt = 0; attempt < 2; attempt++) {
            if (pingServerOnce()) {
                return true;
            }
            if (attempt == 0) {
                try {
                    Thread.sleep(350);
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                    return false;
                }
            }
        }
        return false;
    }

    private static boolean pingServerOnce() {
        try {
            HttpResponse<String> res = HTTP.send(
                    HttpRequest.newBuilder()
                            .uri(URI.create(BASE_URL + "/up"))
                            .timeout(java.time.Duration.ofMillis(4500))
                            .GET()
                            .build(),
                    HttpResponse.BodyHandlers.ofString());
            return res.statusCode() >= 200 && res.statusCode() < 300;
        } catch (Exception e) {
            return false;
        }
    }
}
