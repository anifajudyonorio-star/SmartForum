package com.smartforum.service;

import com.smartforum.api.ApiClient;
import com.smartforum.api.ApiMapper;
import com.google.gson.JsonObject;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Group;
import com.smartforum.model.GroupHighlight;
import com.smartforum.model.GroupMember;
import com.smartforum.model.GroupStats;
import com.smartforum.model.PendingJoinRequest;
import com.smartforum.model.PostReport;
import com.smartforum.model.Post;
import com.smartforum.model.Topic;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.stream.Collectors;

public class GroupService {
    //create a new object
    private static final GroupService INSTANCE = new GroupService();

    private final Map<Integer, Group> groups = new HashMap<>();
    private final Map<Integer, List<GroupMember>> membersByGroup = new HashMap<>();
    private final Map<Integer, GroupContext> groupContexts = new HashMap<>();
    private final Map<Integer, GroupStats> statsCache = new HashMap<>();
    private final Map<Integer, List<ForumUser>> availableUsersCache = new HashMap<>();
    private final Map<Integer, List<PendingJoinRequest>> pendingJoinRequestsByGroup = new HashMap<>();
    private static final class GroupContext {
        private boolean canManage;
        private boolean canParticipate;
        private boolean isMember;
    }

    private GroupService() {
    }
    //remove cached data
    public void clearCache() {
        groups.clear();
        membersByGroup.clear();
        groupContexts.clear();
        statsCache.clear();
        availableUsersCache.clear();
        pendingJoinRequestsByGroup.clear();
    }
    //return same object
    public static GroupService getInstance() {
        return INSTANCE;
    }
    //returns instance of topic topic service
    private TopicService topics() {
        return TopicService.getInstance();
    }
    //return groups belonging to the logged in user
    public List<Group> getGroupsForCurrentUser() {
        syncGroupsFromApi();
        return groups.values().stream()
                .sorted((a, b) -> Integer.compare(b.getId(), a.getId()))
                .map(this::withCountsAndRole)
                .collect(Collectors.toList());
    }

    public List<Group> getExploreGroups() {
        if (AppSession.getInstance().isSystemAdmin()) {
            return List.of();
        }

        return ApiClient.fetchExploreGroups().stream()
                .sorted((a, b) -> Integer.compare(b.getId(), a.getId()))
                .collect(Collectors.toList());
    }

    public Optional<Group> findExploreGroup(int groupId) {
        return getExploreGroups().stream()
                .filter(group -> group.getId() == groupId)
                .findFirst();
    }

    public boolean requestJoinGroup(int groupId) {
        return requestJoinGroup(groupId, false);
    }

    public boolean requestJoinGroup(int groupId, boolean acceptedRules) {
        if (AppSession.getInstance().isSystemAdmin()) {
            return false;
        }

        return ApiClient.requestJoinGroup(groupId, acceptedRules);
    }

    public Optional<Group> getGroup(int groupId) {
        syncGroupDetail(groupId);

        Group group = groups.get(groupId);
        if (group == null) {
            return Optional.empty();
        }
        return Optional.of(withCountsAndRole(group));
    }

    public boolean canViewGroup(int groupId) {
        if (AppSession.getInstance().isSystemAdmin()) {
            return true;
        }

        GroupContext context = groupContexts.get(groupId);
        if (context != null) {
            return context.isMember;
        }
        syncGroupDetail(groupId);
        context = groupContexts.get(groupId);
        if (context != null) {
            return context.isMember;
        }
        return false;
    }

    public boolean canManageGroup(int groupId) {
        GroupContext context = groupContexts.get(groupId);
        if (context == null) {
            syncGroupDetail(groupId);
            context = groupContexts.get(groupId);
        }
        if (context != null) {
            return AppSession.getInstance().isSystemAdmin() || context.canManage;
        }
        return false;
    }

    public boolean canParticipateInGroup(int groupId) {
        GroupContext context = groupContexts.get(groupId);
        if (context == null) {
            syncGroupDetail(groupId);
            context = groupContexts.get(groupId);
        }
        if (context != null) {
            return context.canParticipate;
        }
        return false;
    }

    public String groupRole(int groupId) {
        return memberRole(groupId, AppSession.getInstance().getCurrentUser().getId());
    }

    public List<GroupMember> getMembers(int groupId) {
        syncGroupDetail(groupId);
        return new ArrayList<>(membersByGroup.getOrDefault(groupId, List.of()));
    }

    public List<Topic> getTopics(int groupId) {
        return topics().getTopicsForGroup(groupId);
    }

    public GroupStats getGroupStats(int groupId) {
        syncGroupDetail(groupId);
        GroupStats cached = statsCache.get(groupId);
        if (cached != null) {
            return cached;
        }
        List<GroupMember> members = getMembers(groupId);
        List<Topic> groupTopics = getTopics(groupId);
        PostService postService = PostService.getInstance();

        int totalPosts = 0;
        Map<Integer, Integer> postsByUser = new HashMap<>();
        Map<Integer, String> userNames = new HashMap<>();
        Topic mostActiveTopic = null;
        int mostActiveTopicPosts = 0;

        for (Topic topic : groupTopics) {
            List<Post> posts = postService.getAllPostsForTopic(topic.getId());
            totalPosts += posts.size();

            if (posts.size() > mostActiveTopicPosts) {
                mostActiveTopicPosts = posts.size();
                mostActiveTopic = topic;
            }

            for (Post post : posts) {
                postsByUser.merge(post.getAuthorId(), 1, Integer::sum);
                userNames.putIfAbsent(post.getAuthorId(), post.getAuthorName());
            }
        }

        int active = 0;
        int suspended = 0;
        int blocked = 0;
        int membersWithWarnings = 0;
        int adminCount = 0;

        for (GroupMember member : members) {
            String status = member.getMemberStatus();
            if ("Suspended".equalsIgnoreCase(status)) {
                suspended++;
            } else if ("Blocked".equalsIgnoreCase(status)) {
                blocked++;
            } else {
                active++;
            }

            if (member.getWarnings() > 0) {
                membersWithWarnings++;
            }
            if ("admin".equalsIgnoreCase(member.getMemberRole())) {
                adminCount++;
            }
            userNames.putIfAbsent(member.getUserId(), member.getName());
        }

        Map<Integer, Integer> topicsByUser = new HashMap<>();
        for (Topic topic : groupTopics) {
            topicsByUser.merge(topic.getCreatedBy(), 1, Integer::sum);
            userNames.putIfAbsent(topic.getCreatedBy(), topic.getAuthorName());
        }

        GroupHighlight mostActiveMember = topByCount(postsByUser, userNames, "posts", "No posts yet");
        GroupHighlight topTopicCreator = topByCount(topicsByUser, userNames, "topics", "No topics yet");
        GroupHighlight mostActiveTopicHighlight = mostActiveTopic == null || mostActiveTopicPosts == 0
                ? GroupHighlight.none("No posts yet")
                : new GroupHighlight(truncate(mostActiveTopic.getTitle(), 28),
                mostActiveTopicPosts + (mostActiveTopicPosts == 1 ? " post" : " posts"));

        String avgPosts = groupTopics.isEmpty()
                ? "0"
                : String.format("%.1f", (double) totalPosts / groupTopics.size());

        return new GroupStats(
                members.size(),
                groupTopics.size(),
                totalPosts,
                active,
                suspended,
                blocked,
                mostActiveMember,
                topTopicCreator,
                mostActiveTopicHighlight,
                membersWithWarnings,
                adminCount,
                avgPosts
        );
    }

    private GroupHighlight topByCount(
            Map<Integer, Integer> counts,
            Map<Integer, String> names,
            String unit,
            String emptyDetail
    ) {
        Optional<Map.Entry<Integer, Integer>> top = counts.entrySet().stream()
                .max(Map.Entry.comparingByValue());

        if (top.isEmpty() || top.get().getValue() == 0) {
            return GroupHighlight.none(emptyDetail);
        }

        int count = top.get().getValue();
        String name = names.getOrDefault(top.get().getKey(), "Unknown");
        String unitLabel = count == 1
                ? (unit.startsWith("post") ? "post" : "topic")
                : unit;
        return new GroupHighlight(name, count + " " + unitLabel);
    }

    private String truncate(String value, int maxLength) {
        if (value == null || value.length() <= maxLength) {
            return value == null ? "ΓÇö" : value;
        }
        return value.substring(0, maxLength - 1) + "ΓÇª";
    }

    public List<ForumUser> getAvailableUsers(int groupId) {
        syncGroupDetail(groupId);
        return new ArrayList<>(availableUsersCache.getOrDefault(groupId, List.of()));
    }

    public Group createGroup(String name, String description) {
        return createGroup(name, description, null);
    }

    public Group createGroup(String name, String description, String joinRules) {
        if (ApiClient.createGroup(name, description, joinRules)) {
            syncGroupsFromApi();
            return groups.values().stream()
                    .filter(group -> name.equals(group.getName()))
                    .findFirst()
                    .map(this::withCountsAndRole)
                    .orElseThrow(() -> new IllegalStateException("Group created but not returned by API."));
        }
        throw new IllegalStateException("Could not create group via API.");
    }

    public void addMember(int groupId, int userId, String role) {
        if (!ApiClient.addGroupMember(groupId, userId, role)) {
            throw new IllegalStateException("Could not add member via API.");
        }
        syncGroupDetail(groupId);
    }

    public void removeMember(int groupId, int userId) {
        if (!ApiClient.removeGroupMember(groupId, userId)) {
            throw new IllegalStateException("Could not remove member via API.");
        }
        syncGroupDetail(groupId);
    }

    public void updateMemberRole(int groupId, int userId, String role) {
        if (!ApiClient.updateMemberRole(groupId, userId, role)) {
            throw new IllegalStateException("Could not update member role via API.");
        }
        syncGroupDetail(groupId);
    }

    public void warnMember(int groupId, int userId) {
        ApiClient.MutationResult result = ApiClient.warnGroupMember(groupId, userId, null);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
        syncGroupDetail(groupId);
    }

    public void suspendMember(int groupId, int userId) {
        ApiClient.MutationResult result = ApiClient.suspendGroupMember(groupId, userId, null);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
        syncGroupDetail(groupId);
    }

    public void blockMember(int groupId, int userId) {
        ApiClient.MutationResult result = ApiClient.blockGroupMember(groupId, userId, null);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
        syncGroupDetail(groupId);
    }

    public void reinstateMember(int groupId, int userId) {
        ApiClient.MutationResult result = ApiClient.reinstateGroupMember(groupId, userId);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
        syncGroupDetail(groupId);
    }

    public List<PendingJoinRequest> getPendingJoinRequests(int groupId) {
        syncGroupDetail(groupId);
        return new ArrayList<>(pendingJoinRequestsByGroup.getOrDefault(groupId, List.of()));
    }

    public void approveJoinRequest(int groupId, int userId) {
        ApiClient.MutationResult result = ApiClient.approveJoinRequest(groupId, userId);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
        syncGroupDetail(groupId);
    }

    public void rejectJoinRequest(int groupId, int userId) {
        ApiClient.MutationResult result = ApiClient.rejectJoinRequest(groupId, userId);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
        syncGroupDetail(groupId);
    }

    public List<PostReport> getPostReports(int groupId) {
        return ApiClient.fetchPostReports(groupId)
                .map(json -> ApiMapper.toPostReports(json.getAsJsonArray("reports")))
                .orElseGet(ArrayList::new);
    }

    public void restorePostReport(int groupId, int reportId) {
        ApiClient.MutationResult result = ApiClient.restoreReportedPost(groupId, reportId);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
    }

    public void deletePostReport(int groupId, int reportId) {
        ApiClient.MutationResult result = ApiClient.deleteReportedPost(groupId, reportId);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
    }

    public Group updateGroup(
            int groupId,
            String name,
            String description,
            String joinRules,
            boolean inactivityMonitoringEnabled,
            int inactivityThresholdDays,
            int inactivityGraceDays,
            int inactivityBlacklistDays) {
        JsonObject body = new JsonObject();
        body.addProperty("Group_Name", name);
        body.addProperty("Description", description);
        body.addProperty("join_rules", joinRules == null ? "" : joinRules);
        body.addProperty("inactivity_monitoring_enabled", inactivityMonitoringEnabled);
        body.addProperty("inactivity_threshold_days", inactivityThresholdDays);
        body.addProperty("inactivity_grace_days", inactivityGraceDays);
        body.addProperty("inactivity_blacklist_days", inactivityBlacklistDays);
        ApiClient.MutationResult result = ApiClient.updateGroup(groupId, body);
        if (!result.success()) {
            throw new IllegalStateException(result.message());
        }
        syncGroupDetail(groupId);
        return getGroup(groupId).orElseThrow(() -> new IllegalStateException("Group not found after update."));
    }

    private Group withCountsAndRole(Group group) {
        int topics = group.getTopicsCount();
        int members = group.getMembersCount();
        String role = memberRole(group.getId(), AppSession.getInstance().getCurrentUser().getId());
        Group enriched = new Group(
                group.getId(),
                group.getName(),
                group.getDescription(),
                group.getStatus(),
                group.getCreatedBy(),
                group.getCreatorName(),
                topics,
                members,
                role
        );
        enriched.setJoinStatus(group.getJoinStatus());
        enriched.setJoinRules(group.getJoinRules());
        enriched.setInactivityMonitoringEnabled(group.isInactivityMonitoringEnabled());
        enriched.setInactivityThresholdDays(group.getInactivityThresholdDays());
        enriched.setInactivityGraceDays(group.getInactivityGraceDays());
        enriched.setInactivityBlacklistDays(group.getInactivityBlacklistDays());
        return enriched;
    }

    private String memberRole(int groupId, int userId) {
        GroupMember member = findMember(groupId, userId);
        return member == null ? null : member.getMemberRole();
    }

    private GroupMember findMember(int groupId, int userId) {
        return membersByGroup.getOrDefault(groupId, List.of()).stream()
                .filter(member -> member.getUserId() == userId)
                .findFirst()
                .orElse(null);
    }

    private void syncGroupsFromApi() {
        List<Group> apiGroups = ApiClient.fetchGroups();
        groups.clear();
        membersByGroup.clear();
        groupContexts.clear();
        statsCache.clear();
        availableUsersCache.clear();
        for (Group group : apiGroups) {
            groups.put(group.getId(), group);
        }
    }

    private void syncGroupDetail(int groupId) {
        ApiClient.fetchGroupDetail(groupId).ifPresent(json -> {
            groups.put(groupId, ApiMapper.toGroup(json.getAsJsonObject("group")));

            if (json.has("members")) {
                membersByGroup.put(groupId, ApiMapper.toMembers(json.getAsJsonArray("members")));
            }
            if (json.has("topics")) {
                topics().replaceTopicsForGroup(groupId, ApiMapper.toTopics(json.getAsJsonArray("topics")));
            }
            if (json.has("stats")) {
                statsCache.put(groupId, ApiMapper.toGroupStats(json.getAsJsonObject("stats")));
            }
            if (json.has("available_users")) {
                availableUsersCache.put(groupId, ApiMapper.toAvailableUsers(json.getAsJsonArray("available_users")));
            }
            if (json.has("pending_join_requests")) {
                pendingJoinRequestsByGroup.put(
                        groupId,
                        ApiMapper.toPendingJoinRequests(json.getAsJsonArray("pending_join_requests"))
                );
            } else {
                pendingJoinRequestsByGroup.put(groupId, List.of());
            }

            GroupContext context = new GroupContext();
            context.canManage = json.has("can_manage") && json.get("can_manage").getAsBoolean();
            context.canParticipate = json.has("can_participate") && json.get("can_participate").getAsBoolean();
            context.isMember = json.has("is_member") && json.get("is_member").getAsBoolean();
            groupContexts.put(groupId, context);
        });
    }
}
