package com.smartforum.service;

import com.smartforum.api.ApiClient;
import com.smartforum.model.ForumUser;
import com.smartforum.model.GroupMember;
import com.smartforum.model.Post;
import com.smartforum.model.Topic;
import com.smartforum.model.TopicSearchResult;
import com.smartforum.util.ApiSupport;

import java.time.LocalDateTime;
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
    private int nextTopicId = 10;

    private TopicService() {
        seedData();
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
            if (!ApiClient.createTopic(groupId, title, description)) {
                throw new IllegalStateException("Could not create topic via API.");
            }
            syncTopicsForGroup(groupId);
            return topicsByGroup.getOrDefault(groupId, List.of()).stream()
                    .filter(topic -> title.equals(topic.getTitle()))
                    .findFirst()
                    .orElseThrow(() -> new IllegalStateException("Topic created but not returned by API."));
        }

        ForumUser creator = AppSession.getInstance().getCurrentUser();
        int id = nextTopicId++;

        Topic topic = new Topic(id, groupId, title, description, creator.getId(), creator.getName());
        topicsByGroup.computeIfAbsent(groupId, key -> new ArrayList<>()).add(0, topic);
        topicsById.put(id, topic);
        PostService.getInstance().initTopicPosts(id);
        return topic;
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
        if (AppSession.getInstance().isSystemAdmin()) {
            return new ArrayList<>(topicsByGroup.keySet());
        }
        return groups().getGroupsForCurrentUser().stream()
                .map(group -> group.getId())
                .collect(Collectors.toList());
    }

    private boolean containsIgnoreCase(String value, String needle) {
        return value != null && value.toLowerCase().contains(needle);
    }

    private void syncTopicsForGroup(int groupId) {
        replaceTopicsForGroup(groupId, ApiClient.fetchTopicsForGroup(groupId));
    }

    private void syncAllTopicsFromApi() {
        List<Topic> topics = ApiClient.fetchTopics();
        if (topics.isEmpty()) {
            return;
        }
        topicsByGroup.clear();
        topicsById.clear();
        for (Topic topic : topics) {
            topicsByGroup.computeIfAbsent(topic.getGroupId(), key -> new ArrayList<>()).add(topic);
            topicsById.put(topic.getId(), topic);
            PostService.getInstance().initTopicPosts(topic.getId());
            nextTopicId = Math.max(nextTopicId, topic.getId() + 1);
        }
    }

    private void seedData() {
        addTopic(new Topic(1, 1, "Introduction to Algorithms",
                "Share resources and ask questions about this week's algorithms lecture.",
                3, "Demo Lecturer"));
        addTopic(new Topic(2, 1, "Database Normalization Help",
                "Need help with 3NF and BCNF examples from the assignment.",
                2, "Anifa Onorio"));
        addTopic(new Topic(3, 1, "Project Team Formation",
                "Find teammates for the semester group project.",
                4, "James Okello"));
        addTopic(new Topic(4, 2, "Sprint Planning Thread",
                "Week 3 sprint goals and task assignments.",
                3, "Demo Lecturer"));
        addTopic(new Topic(5, 2, "UI Mockup Feedback",
                "Please review the Figma mockups and leave comments.",
                5, "Sarah Nakato"));

        LocalDateTime base = LocalDateTime.now().minusDays(1);
        PostService postService = PostService.getInstance();

        postService.seedPost(new Post(1, 1, null, 3, "Demo Lecturer",
                "Welcome everyone! Please share your questions about this week's lecture.",
                base.minusHours(2), List.of()));
        postService.seedPost(new Post(2, 1, null, 2, "Anifa Onorio",
                "Can someone explain the difference between BFS and DFS for this assignment?",
                base.minusHours(1), List.of()));
        postService.seedPost(new Post(3, 1, 2, 3, "Demo Lecturer",
                "BFS explores level by level; DFS goes deep first. I'll post a short example shortly.",
                base.minusMinutes(45), List.of()));
        postService.seedPost(new Post(4, 2, null, 2, "Anifa Onorio",
                "I'm stuck on converting a table to 3NF ΓÇö can we walk through an example?",
                base.minusMinutes(30), List.of()));
        postService.seedPost(new Post(5, 4, null, 3, "Demo Lecturer",
                "Sprint goal: finish user stories 12ΓÇô15 by Friday. Post blockers here.",
                base.minusHours(3), List.of()));
    }

    private void addTopic(Topic topic) {
        topicsByGroup.computeIfAbsent(topic.getGroupId(), key -> new ArrayList<>()).add(topic);
        topicsById.put(topic.getId(), topic);
        PostService.getInstance().initTopicPosts(topic.getId());
        nextTopicId = Math.max(nextTopicId, topic.getId() + 1);
    }
}
