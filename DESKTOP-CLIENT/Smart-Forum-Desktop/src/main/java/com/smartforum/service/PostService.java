package com.smartforum.service;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Post;
import com.smartforum.model.Topic;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.NetworkMonitor;
import com.smartforum.util.OfflineQueue;
import javafx.application.Platform;

import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.stream.Collectors;

/**
 * Post data layer ΓÇö mirrors web {@code PostController} + {@code Post} model operations.
 */
public class PostService {
    private static final PostService INSTANCE = new PostService();

    private final Map<Integer, List<Post>> postsByTopic = new HashMap<>();
    private int nextPostId = 1000;

    private PostService() {
    }

    public static PostService getInstance() {
        return INSTANCE;
    }

    private GroupService groups() {
        return GroupService.getInstance();
    }

    private TopicService topics() {
        return TopicService.getInstance();
    }

    private PostVisibilityService visibility() {
        return PostVisibilityService.getInstance();
    }

    public void clearCache() {
        postsByTopic.clear();
    }

    public void initTopicPosts(int topicId) {
        postsByTopic.putIfAbsent(topicId, new ArrayList<>());
    }

    public List<Post> getPosts(int topicId) {
        if (ApiSupport.useApi()) {
            syncPostsForTopic(topicId);
        }

        ForumUser user = AppSession.getInstance().getCurrentUser();
        boolean systemAdmin = AppSession.getInstance().isSystemAdmin();

        return postsByTopic.getOrDefault(topicId, List.of()).stream()
                .filter(post -> post.isVisibleTo(user.getId(), systemAdmin))
                .collect(Collectors.toList());
    }

    public int countPostsForTopic(int topicId) {
        if (ApiSupport.useApi()) {
            syncPostsForTopic(topicId);
        }
        return postsByTopic.getOrDefault(topicId, List.of()).size();
    }

    public List<Post> getAllPostsForTopic(int topicId) {
        if (ApiSupport.useApi()) {
            syncPostsForTopic(topicId);
        }
        return new ArrayList<>(postsByTopic.getOrDefault(topicId, List.of()));
    }

    public Optional<Post> getPost(int postId) {
        if (ApiSupport.useApi()) {
            syncAllCachedTopicsPosts();
        }
        for (List<Post> posts : postsByTopic.values()) {
            for (Post post : posts) {
                if (post.getId() == postId) {
                    return Optional.of(post);
                }
            }
        }
        return Optional.empty();
    }

    public boolean canParticipateInTopic(int topicId) {
        Optional<Topic> topic = topics().getTopic(topicId);
        return topic.filter(value -> groups().canParticipateInGroup(value.getGroupId())).isPresent();
    }

    public boolean canManagePost(int postId) {
        Optional<Post> postOpt = getPost(postId);
        if (postOpt.isEmpty()) {
            return false;
        }

        Post post = postOpt.get();
        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (AppSession.getInstance().isSystemAdmin()) {
            return true;
        }
        if (post.getAuthorId() == user.getId()) {
            return true;
        }

        Optional<Topic> topic = topics().getTopic(post.getTopicId());
        return topic.filter(value -> groups().canManageGroup(value.getGroupId())).isPresent();
    }

    public Post store(int topicId, String content, Integer parentPostId, List<Integer> excludedUserIds) {
        if (!canParticipateInTopic(topicId)) {
            throw new IllegalStateException("You must be an active member of this group to post.");
        }

        if (ApiSupport.useApi()) {
            Topic topic = topics().getTopic(topicId)
                    .orElseThrow(() -> new IllegalArgumentException("Topic not found."));
            List<Integer> hiddenFrom = visibility().resolveExcludedUserIds(
                    topic.getGroupId(),
                    AppSession.getInstance().getCurrentUser().getId(),
                    excludedUserIds);

            if (NetworkMonitor.isOnline()
                    && ApiClient.sendPost(topicId, content, parentPostId, hiddenFrom)) {
                syncPostsForTopic(topicId);
                List<Post> posts = getPosts(topicId);
                return posts.isEmpty() ? null : posts.get(posts.size() - 1);
            }
            queueOfflinePost(topicId, content, parentPostId, hiddenFrom);
            NetworkMonitor.probeNow();
            return buildLocalPendingPost(topicId, content, parentPostId, hiddenFrom);
        }

        Topic topic = topics().getTopic(topicId)
                .orElseThrow(() -> new IllegalArgumentException("Topic not found."));

        ForumUser author = AppSession.getInstance().getCurrentUser();
        List<Integer> hiddenFrom = visibility().resolveExcludedUserIds(
                topic.getGroupId(), author.getId(), excludedUserIds);

        int id = nextPostId++;
        Post post = new Post(
                id,
                topicId,
                parentPostId,
                author.getId(),
                author.getName(),
                content,
                LocalDateTime.now(),
                hiddenFrom
        );

        postsByTopic.computeIfAbsent(topicId, key -> new ArrayList<>()).add(post);
        return post;
    }

    public Post update(int postId, String content, List<Integer> excludedUserIds) {
        if (!canManagePost(postId)) {
            throw new IllegalStateException("You are not allowed to edit this message.");
        }

        if (ApiSupport.useApi()) {
            Post existing = getPost(postId).orElseThrow(() -> new IllegalArgumentException("Post not found."));
            List<Integer> hiddenFrom = visibility().resolveExcludedUserIds(
                    topics().getTopic(existing.getTopicId()).orElseThrow().getGroupId(),
                    existing.getAuthorId(),
                    excludedUserIds);

            if (!ApiClient.updatePost(postId, content, hiddenFrom)) {
                throw new IllegalStateException("Could not update post via API.");
            }
            syncPostsForTopic(existing.getTopicId());
            return getPost(postId).orElseThrow(() -> new IllegalArgumentException("Post not found."));
        }

        Post existing = getPost(postId).orElseThrow(() -> new IllegalArgumentException("Post not found."));
        Topic topic = topics().getTopic(existing.getTopicId())
                .orElseThrow(() -> new IllegalArgumentException("Topic not found."));

        List<Integer> hiddenFrom = visibility().resolveExcludedUserIds(
                topic.getGroupId(), existing.getAuthorId(), excludedUserIds);

        Post updated = new Post(
                existing.getId(),
                existing.getTopicId(),
                existing.getParentPostId(),
                existing.getAuthorId(),
                existing.getAuthorName(),
                content,
                existing.getCreatedAt(),
                hiddenFrom
        );

        replacePost(updated);
        return updated;
    }

    public void destroy(int postId) {
        if (!canManagePost(postId)) {
            throw new IllegalStateException("You are not allowed to delete this message.");
        }

        Post post = getPost(postId).orElseThrow(() -> new IllegalArgumentException("Post not found."));

        if (ApiSupport.useApi()) {
            if (!ApiClient.deletePost(postId)) {
                throw new IllegalStateException("Could not delete post via API.");
            }
            syncPostsForTopic(post.getTopicId());
            return;
        }

        List<Post> posts = postsByTopic.get(post.getTopicId());
        if (posts != null) {
            posts.removeIf(item -> item.getId() == postId);
        }
    }

    private void replacePost(Post updated) {
        List<Post> posts = postsByTopic.get(updated.getTopicId());
        if (posts == null) {
            return;
        }
        for (int i = 0; i < posts.size(); i++) {
            if (posts.get(i).getId() == updated.getId()) {
                posts.set(i, updated);
                return;
            }
        }
    }

    private void syncPostsForTopic(int topicId) {
        if (!NetworkMonitor.isOnline()) {
            return;
        }

        int resolvedId = topics().resolveTopicId(topicId);
        Optional<List<Post>> fetched = ApiClient.tryFetchPosts(resolvedId);
        if (fetched.isEmpty()) {
            return;
        }

        List<Post> posts = new ArrayList<>(fetched.get());
        postsByTopic.put(topicId, posts);
        if (resolvedId != topicId) {
            postsByTopic.put(resolvedId, new ArrayList<>(posts));
        }
    }

    private void syncAllCachedTopicsPosts() {
        for (Integer topicId : new ArrayList<>(postsByTopic.keySet())) {
            syncPostsForTopic(topicId);
        }
    }

    public void remapTopicIds(Map<Integer, Integer> remaps) {
        if (remaps == null || remaps.isEmpty()) {
            return;
        }

        for (Map.Entry<Integer, Integer> entry : remaps.entrySet()) {
            int clientId = entry.getKey();
            int serverId = entry.getValue();
            List<Post> posts = postsByTopic.remove(clientId);
            if (posts != null) {
                postsByTopic.put(serverId, posts);
            } else {
                initTopicPosts(serverId);
            }
        }
    }

    private void queueOfflinePost(
            int topicId,
            String content,
            Integer parentPostId,
            List<Integer> excludedUserIds) {
        JsonObject payload = new JsonObject();
        payload.addProperty("topic_id", topicId);
        payload.addProperty("content", content);
        if (parentPostId != null) {
            payload.addProperty("parent_post_id", parentPostId);
        }
        if (excludedUserIds != null && !excludedUserIds.isEmpty()) {
            JsonArray excluded = new JsonArray();
            excludedUserIds.forEach(excluded::add);
            payload.add("excluded_users", excluded);
        }
        OfflineQueue.add("create_post", payload);
        Platform.runLater(() -> SyncStatusService.getInstance().refreshNow());
    }

    private Post buildLocalPendingPost(
            int topicId,
            String content,
            Integer parentPostId,
            List<Integer> hiddenFrom) {
        ForumUser author = AppSession.getInstance().getCurrentUser();
        return new Post(
                nextPostId++,
                topicId,
                parentPostId,
                author.getId(),
                author.getName(),
                content,
                LocalDateTime.now(),
                hiddenFrom == null ? List.of() : hiddenFrom
        );
    }
}
