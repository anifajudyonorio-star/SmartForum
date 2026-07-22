package com.smartforum.api;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.model.*;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;

public final class ApiMapper {
    private ApiMapper() {
    }

    public static Group toGroup(JsonObject json) {
        Group group = new Group(
                json.get("id").getAsInt(),
                text(json, "name", "Unknown"),
                text(json, "description", ""),
                text(json, "status", "Active"),
                json.has("created_by") ? json.get("created_by").getAsInt() : 0,
                text(json, "creator_name", ""),
                json.has("topics_count") ? json.get("topics_count").getAsInt() : 0,
                json.has("members_count") ? json.get("members_count").getAsInt() : 0,
                text(json, "my_role", "member")
        );
        if (json.has("join_status")) {
            group.setJoinStatus(json.get("join_status").getAsString());
        }
        return group;
    }

    public static GroupMember toGroupMember(JsonObject json) {
        return new GroupMember(
                json.get("user_id").getAsInt(),
                text(json, "name", "Unknown"),
                text(json, "email", ""),
                text(json, "member_role", "member"),
                text(json, "member_status", "Active"),
                json.has("warnings") ? json.get("warnings").getAsInt() : 0,
                json.has("is_creator") && json.get("is_creator").getAsBoolean()
        );
    }

    public static ForumUser toForumUser(JsonObject json) {
        String fname = text(json, "Fname", "");
        String lname = text(json, "Lname", "");
        String name = (fname + " " + lname).trim();
        if (name.isBlank()) {
            name = text(json, "name", "User");
        }

        return new ForumUser(
                json.get("id").getAsInt(),
                name,
                text(json, "email", ""),
                text(json, "role", "student"),
                json.has("can_view_statistics") && json.get("can_view_statistics").getAsBoolean(),
                json.has("can_view_participation") && json.get("can_view_participation").getAsBoolean(),
                json.has("administers_groups") && json.get("administers_groups").getAsBoolean()
        );
    }

    public static Topic toTopic(JsonObject json) {
        return new Topic(
                json.get("id").getAsInt(),
                json.get("group_id").getAsInt(),
                text(json, "title", "Untitled"),
                text(json, "topic_description", text(json, "description", "")),
                json.has("created_by") ? json.get("created_by").getAsInt() : 0,
                text(json, "author_name", "")
        );
    }

    public static Post toPost(JsonObject json) {
        String content = text(json, "post_content", text(json, "content", ""));
        String author = text(json, "author_name", text(json, "user_name", "User"));
        Integer parentId = json.has("parent_post_id") && !json.get("parent_post_id").isJsonNull()
                ? json.get("parent_post_id").getAsInt()
                : null;

        return new Post(
                json.get("id").getAsInt(),
                json.has("topic_id") ? json.get("topic_id").getAsInt() : 0,
                parentId,
                json.has("created_by") ? json.get("created_by").getAsInt() : 0,
                author,
                content,
                parseDateTime(text(json, "created_at", null)),
                List.of()
        );
    }

    public static GroupStats toGroupStats(JsonObject stats) {
        return new GroupStats(
                intVal(stats, "members_count"),
                intVal(stats, "topics_count"),
                intVal(stats, "posts_count"),
                intVal(stats, "active_members"),
                intVal(stats, "suspended_members"),
                intVal(stats, "blocked_members"),
                toHighlight(stats, "most_active_member", "No posts yet"),
                toHighlight(stats, "top_topic_creator", "No topics yet"),
                toHighlight(stats, "most_active_topic", "No posts yet"),
                intVal(stats, "members_with_warnings"),
                intVal(stats, "admin_count"),
                stats.has("avg_posts_per_topic")
                        ? String.valueOf(stats.get("avg_posts_per_topic").getAsDouble())
                        : "0"
        );
    }

    public static TopicSearchResult toSearchResult(JsonObject json) {
        Topic topic = toTopic(json);
        String groupName = text(json, "group_name", "Unknown group");
        int postsCount = json.has("posts_count") ? json.get("posts_count").getAsInt() : 0;
        return new TopicSearchResult(topic, groupName, postsCount);
    }

    public static List<Group> toGroups(JsonArray array) {
        List<Group> groups = new ArrayList<>();
        for (JsonElement element : array) {
            groups.add(toGroup(element.getAsJsonObject()));
        }
        return groups;
    }

    public static List<Topic> toTopics(JsonArray array) {
        List<Topic> topics = new ArrayList<>();
        for (JsonElement element : array) {
            topics.add(toTopic(element.getAsJsonObject()));
        }
        return topics;
    }

    public static List<Post> toPosts(JsonArray array) {
        List<Post> posts = new ArrayList<>();
        for (JsonElement element : array) {
            posts.add(toPost(element.getAsJsonObject()));
        }
        return posts;
    }

    public static List<GroupMember> toMembers(JsonArray array) {
        List<GroupMember> members = new ArrayList<>();
        for (JsonElement element : array) {
            members.add(toGroupMember(element.getAsJsonObject()));
        }
        return members;
    }

    public static List<ForumUser> toAvailableUsers(JsonArray array) {
        List<ForumUser> users = new ArrayList<>();
        for (JsonElement element : array) {
            users.add(toForumUser(element.getAsJsonObject()));
        }
        return users;
    }

    private static GroupHighlight toHighlight(JsonObject stats, String key, String emptyDetail) {
        if (!stats.has(key) || stats.get(key).isJsonNull()) {
            return GroupHighlight.none(emptyDetail);
        }
        JsonObject item = stats.getAsJsonObject(key);
        String name = text(item, "name", "ΓÇö");
        int count = intVal(item, "count");
        String label = text(item, "label", "");
        String detail = count + (label.isBlank() ? "" : " " + label);
        return new GroupHighlight(name, detail.isBlank() ? emptyDetail : detail);
    }

    private static String text(JsonObject json, String key, String fallback) {
        if (!json.has(key) || json.get(key).isJsonNull()) {
            return fallback;
        }
        return json.get(key).getAsString();
    }

    private static int intVal(JsonObject json, String key) {
        if (!json.has(key) || json.get(key).isJsonNull()) {
            return 0;
        }
        return json.get(key).getAsInt();
    }

    private static LocalDateTime parseDateTime(String value) {
        if (value == null || value.isBlank()) {
            return LocalDateTime.now();
        }
        try {
            return LocalDateTime.parse(value, DateTimeFormatter.ISO_DATE_TIME);
        } catch (Exception ignored) {
            return LocalDateTime.now();
        }
    }
}
