package com.smartforum.service;

import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.ForumUser;
import com.smartforum.model.GroupMember;
import com.smartforum.model.Topic;
import com.smartforum.model.TopicSearchResult;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.NetworkMonitor;
import com.smartforum.util.OfflineQueue;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.stream.Collectors;

public class TopicService {
    private static final TopicService INSTANCE = new TopicService();

    private final Map<Integer, List<Topic>> topicsByGroup = new HashMap<>();
    private final Map<Integer, Topic> topicsById = new HashMap<>();
    private final Map<Integer, Integer> clientToServerTopicIds = new HashMap<>();
    private int nextTopicId = 10;

    private TopicService() {
    }

    public void clearCache() {
        topicsByGroup.clear();
        topicsById.clear();
        clientToServerTopicIds.clear();
    }

    public static TopicService getInstance() {
        return INSTANCE;
    }

    private GroupService groups() {
        return GroupService.getInstance();
    }

    public void initGroupTopics(int groupId) {
        topicsByGroup.putIfAbsent(groupId, new ArrayList<>());
    }

    public void replaceTopicsForGroup(int groupId, List<Topic> topics) {
        topicsByGroup.put(groupId, new ArrayList<>(topics));
        for (Topic topic : topics) {
            topicsById.put(topic.getId(), topic);
            nextTopicId = Math.max(nextTopicId, topic.getId() + 1);
        }
    }

    public List<Topic> getAllTopics() {
        return new ArrayList<>(topicsById.values());
    }

    public List<Topic> getTopicsForGroup(int groupId) {
        if (ApiSupport.useApi()) {
            syncTopicsForGroup(groupId);
        }
        return new ArrayList<>(topicsByGroup.getOrDefault(groupId, List.of()));
    }

    public int countTopicsForGroup(int groupId) {
        return topicsByGroup.getOrDefault(groupId, List.of()).size();
    }

    public Optional<Topic> getTopic(int topicId) {
        if (ApiSupport.useApi() && !topicsById.containsKey(topicId)) {
            syncAllTopicsFromApi();
        }
        return Optional.ofNullable(topicsById.get(topicId));
    }

    public Topic createTopic(int groupId, String title, String description) {
        if (ApiSupport.useApi()) {
            if (ApiClient.createTopic(groupId, title, description)) {
                syncTopicsForGroup(groupId);
                return topicsByGroup.getOrDefault(groupId, List.of()).stream()
                        .filter(topic -> title.equals(topic.getTitle()))
                        .findFirst()
                        .orElseThrow(() -> new IllegalStateException("Topic created but not returned by API."));
            }
            if (!NetworkMonitor.isOnline()) {
                Topic topic = buildLocalPendingTopic(groupId, title, description);
                queueOfflineTopic(groupId, title, description, topic.getId());
                return topic;
            }
            throw new IllegalStateException("Could not create topic via API.");
        }

        ForumUser creator = AppSession.getInstance().getCurrentUser();
        int id = nextTopicId++;

        Topic topic = new Topic(id, groupId, title, description, creator.getId(), creator.getName());
        topicsByGroup.computeIfAbsent(groupId, key -> new ArrayList<>()).add(0, topic);
        topicsById.put(id, topic);
        PostService.getInstance().initTopicPosts(id);
        return topic;
    }

    public void recordTopicView(int topicId) {
        if (ApiSupport.useApi()) {
            boolean success = NetworkMonitor.isOnline() && ApiClient.recordTopicView(topicId);
            if (!success) {
                queueOfflineTopicView(topicId);
            }
        }
    }

    public boolean canViewTopic(int topicId) {
        Optional<Topic> topic = getTopic(topicId);
        return topic.filter(value -> groups().canViewGroup(value.getGroupId())).isPresent();
    }

    public boolean canParticipateInTopic(int topicId) {
        Optional<Topic> topic = getTopic(topicId);
        return topic.filter(value -> groups().canParticipateInGroup(value.getGroupId())).isPresent();
    }

    public boolean canManageTopic(int topicId) {
        Optional<Topic> topicOpt = getTopic(topicId);
        if (topicOpt.isEmpty()) {
            return false;
        }

        Topic topic = topicOpt.get();
        ForumUser user = AppSession.getInstance().getCurrentUser();
        int groupId = topic.getGroupId();

        if (groups().canManageGroup(groupId)) {
            return true;
        }
        if (topic.getCreatedBy() == user.getId()) {
            return true;
        }
        return "lecturer".equalsIgnoreCase(groups().groupRole(groupId));
    }

    public List<GroupMember> getMembersForExclude(int topicId) {
        Optional<Topic> topic = getTopic(topicId);
        if (topic.isEmpty()) {
            return List.of();
        }

        int currentUserId = AppSession.getInstance().getCurrentUser().getId();
        return groups().getMembers(topic.get().getGroupId()).stream()
                .filter(member -> member.getUserId() != currentUserId)
                .collect(Collectors.toList());
    }

    /**
     * Mirrors web {@code TopicController@search} ΓÇö topics in the user's groups,
     * optionally filtered by title or description.
     */
    public List<TopicSearchResult> searchTopics(String query) {
        if (ApiSupport.useApi()) {
            return ApiClient.searchTopics(query);
        }

        String search = query == null ? "" : query.trim();
        List<Integer> allowedGroupIds = getSearchableGroupIds();

        if (allowedGroupIds.isEmpty()) {
            return List.of();
        }

        PostService postService = PostService.getInstance();
        String needle = search.toLowerCase();

        return topicsById.values().stream()
                .filter(topic -> allowedGroupIds.contains(topic.getGroupId()))
                .filter(topic -> search.isEmpty()
                        || containsIgnoreCase(topic.getTitle(), needle)
                        || containsIgnoreCase(topic.getDescription(), needle))
                .sorted((a, b) -> Integer.compare(b.getId(), a.getId()))
                .map(topic -> {
                    String groupName = groups().getGroup(topic.getGroupId())
                            .map(group -> group.getName())
                            .orElse("Unknown group");
                    int postsCount = postService.countPostsForTopic(topic.getId());
                    return new TopicSearchResult(topic, groupName, postsCount);
                })
                .collect(Collectors.toList());
    }

    public boolean hasSearchableGroups() {
        return !getSearchableGroupIds().isEmpty();
    }

    private List<Integer> getSearchableGroupIds() {
        return groups().getGroupsForCurrentUser().stream()
                .map(group -> group.getId())
                .collect(Collectors.toList());
    }

    private void queueOfflineTopic(int groupId, String title, String description, int clientTopicId) {
        JsonObject payload = new JsonObject();
        payload.addProperty("group_id", groupId);
        payload.addProperty("title", title);
        payload.addProperty("client_topic_id", clientTopicId);
        if (description != null) payload.addProperty("description", description);
        OfflineQueue.add("create_topic", payload);
        SyncStatusService.getInstance().refreshNow();
    }

    private Topic buildLocalPendingTopic(int groupId, String title, String description) {
        ForumUser creator = AppSession.getInstance().getCurrentUser();
        int id = nextTopicId++;
        Topic topic = new Topic(id, groupId, title, description, creator.getId(), creator.getName());
        topicsByGroup.computeIfAbsent(groupId, key -> new ArrayList<>()).add(0, topic);
        topicsById.put(id, topic);
        PostService.getInstance().initTopicPosts(id);
        return topic;
    }

    private void queueOfflineTopicView(int topicId) {
        JsonObject payload = new JsonObject();
        payload.addProperty("topic_id", topicId);
        OfflineQueue.add("view_topic", payload);
        SyncStatusService.getInstance().refreshNow();
    }

    private boolean containsIgnoreCase(String value, String needle) {
        return value != null && value.toLowerCase().contains(needle);
    }

    private void syncTopicsForGroup(int groupId) {
        replaceTopicsForGroup(groupId, ApiClient.fetchTopicsForGroup(groupId));
    }

    private void syncAllTopicsFromApi() {
        List<Topic> topics = ApiClient.fetchTopics();
        topicsByGroup.clear();
        topicsById.clear();
        for (Topic topic : topics) {
            topicsByGroup.computeIfAbsent(topic.getGroupId(), key -> new ArrayList<>()).add(topic);
            topicsById.put(topic.getId(), topic);
            PostService.getInstance().initTopicPosts(topic.getId());
            nextTopicId = Math.max(nextTopicId, topic.getId() + 1);
        }
    }

    public void remapTopicIds(Map<Integer, Integer> remaps) {
        if (remaps == null || remaps.isEmpty()) {
            return;
        }

        clientToServerTopicIds.putAll(remaps);

        for (Map.Entry<Integer, Integer> entry : remaps.entrySet()) {
            int clientId = entry.getKey();
            int serverId = entry.getValue();
            Topic topic = topicsById.remove(clientId);
            if (topic == null) {
                continue;
            }

            Topic remapped = new Topic(
                    serverId,
                    topic.getGroupId(),
                    topic.getTitle(),
                    topic.getDescription(),
                    topic.getCreatedBy(),
                    topic.getAuthorName()
            );
            topicsById.put(serverId, remapped);

            List<Topic> groupTopics = topicsByGroup.get(topic.getGroupId());
            if (groupTopics != null) {
                for (int i = 0; i < groupTopics.size(); i++) {
                    if (groupTopics.get(i).getId() == clientId) {
                        groupTopics.set(i, remapped);
                        break;
                    }
                }
            }
            nextTopicId = Math.max(nextTopicId, serverId + 1);
        }
    }

    public int resolveTopicId(int topicId) {
        return clientToServerTopicIds.getOrDefault(topicId, topicId);
    }
}
