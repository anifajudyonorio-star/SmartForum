package com.smartforum.service;

import com.smartforum.api.ApiClient;
import com.smartforum.api.ApiMapper;
import com.smartforum.model.ForumUser;
import com.smartforum.model.Group;
import com.smartforum.model.GroupHighlight;
import com.smartforum.model.GroupMember;
import com.smartforum.model.GroupStats;
import com.smartforum.model.Post;
import com.smartforum.model.Topic;
import com.smartforum.util.ApiSupport;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.stream.Collectors;

public class GroupService {
    private static final GroupService INSTANCE = new GroupService();

    private final Map<Integer, ForumUser> users = new HashMap<>();
    private final Map<Integer, Group> groups = new HashMap<>();
    private final Map<Integer, List<GroupMember>> membersByGroup = new HashMap<>();
    private final Map<Integer, GroupContext> groupContexts = new HashMap<>();
    private final Map<Integer, GroupStats> statsCache = new HashMap<>();
    private final Map<Integer, List<ForumUser>> availableUsersCache = new HashMap<>();
    private int nextGroupId = 10;

    private static final class GroupContext {
        private boolean canManage;
        private boolean canParticipate;
        private boolean isMember;
    }

    private GroupService() {
        seedData();
    }

    public static GroupService getInstance() {
        return INSTANCE;
    }

    private TopicService topics() {
        return TopicService.getInstance();
    }

    public List<Group> getGroupsForCurrentUser() {
        if (ApiSupport.useApi()) {
            syncGroupsFromApi();
            return groups.values().stream()
                    .sorted((a, b) -> Integer.compare(b.getId(), a.getId()))
                    .map(this::withCountsAndRole)
                    .collect(Collectors.toList());
        }

        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (AppSession.getInstance().isSystemAdmin()) {
            return groups.values().stream()
                    .sorted((a, b) -> Integer.compare(b.getId(), a.getId()))
                    .map(this::withCountsAndRole)
                    .collect(Collectors.toList());
        }

        return groups.values().stream()
                .filter(group -> isMember(group.getId(), user.getId()))
                .sorted((a, b) -> Integer.compare(b.getId(), a.getId()))
                .map(this::withCountsAndRole)
                .collect(Collectors.toList());
    }

    public Optional<Group> getGroup(int groupId) {
        if (ApiSupport.useApi()) {
            syncGroupDetail(groupId);
        }

        Group group = groups.get(groupId);
        if (group == null) {
            return Optional.empty();
        }
        return Optional.of(withCountsAndRole(group));
    }

    public boolean canViewGroup(int groupId) {
        if (ApiSupport.useApi()) {
            GroupContext context = groupContexts.get(groupId);
            if (context != null) {
                return AppSession.getInstance().isSystemAdmin() || context.isMember;
            }
            syncGroupDetail(groupId);
            context = groupContexts.get(groupId);
            if (context != null) {
                return AppSession.getInstance().isSystemAdmin() || context.isMember;
            }
        }

        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (AppSession.getInstance().isSystemAdmin()) {
            return true;
        }
        if (!isMember(groupId, user.getId())) {
            return false;
        }
        return !"Blocked".equalsIgnoreCase(memberStatus(groupId, user.getId()));
    }

    public boolean canManageGroup(int groupId) {
        if (ApiSupport.useApi()) {
            GroupContext context = groupContexts.get(groupId);
            if (context == null) {
                syncGroupDetail(groupId);
                context = groupContexts.get(groupId);
            }
            if (context != null) {
                return AppSession.getInstance().isSystemAdmin() || context.canManage;
            }
        }

        ForumUser user = AppSession.getInstance().getCurrentUser();
        if (AppSession.getInstance().isSystemAdmin()) {
            return true;
        }
        return "admin".equalsIgnoreCase(memberRole(groupId, user.getId()));
    }

    public boolean canParticipateInGroup(int groupId) {
        if (ApiSupport.useApi()) {
            GroupContext context = groupContexts.get(groupId);
            if (context == null) {
                syncGroupDetail(groupId);
                context = groupContexts.get(groupId);
            }
            if (context != null) {
                return context.canParticipate;
            }
        }

        ForumUser user = AppSession.getInstance().getCurrentUser();
        return isMember(groupId, user.getId())
                && "Active".equalsIgnoreCase(memberStatus(groupId, user.getId()));
    }

    public String groupRole(int groupId) {
        return memberRole(groupId, AppSession.getInstance().getCurrentUser().getId());
    }

    public List<GroupMember> getMembers(int groupId) {
        if (ApiSupport.useApi()) {
            syncGroupDetail(groupId);
        }
        return new ArrayList<>(membersByGroup.getOrDefault(groupId, List.of()));
    }

    public List<Topic> getTopics(int groupId) {
        return topics().getTopicsForGroup(groupId);
    }

    public GroupStats getGroupStats(int groupId) {
        if (ApiSupport.useApi()) {
            syncGroupDetail(groupId);
            GroupStats cached = statsCache.get(groupId);
            if (cached != null) {
                return cached;
            }
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
        if (ApiSupport.useApi()) {
            syncGroupDetail(groupId);
            return new ArrayList<>(availableUsersCache.getOrDefault(groupId, List.of()));
        }

        List<Integer> memberIds = getMembers(groupId).stream()
                .map(GroupMember::getUserId)
                .toList();

        return users.values().stream()
                .filter(user -> user.getId() != AppSession.getInstance().getCurrentUser().getId())
                .filter(user -> !memberIds.contains(user.getId()))
                .sorted((a, b) -> a.getName().compareToIgnoreCase(b.getName()))
                .collect(Collectors.toList());
    }

    public Group createGroup(String name, String description) {
        if (ApiSupport.useApi()) {
            if (ApiClient.createGroup(name, description)) {
                syncGroupsFromApi();
                return groups.values().stream()
                        .filter(group -> name.equals(group.getName()))
                        .findFirst()
                        .map(this::withCountsAndRole)
                        .orElseThrow(() -> new IllegalStateException("Group created but not returned by API."));
            }
            throw new IllegalStateException("Could not create group via API.");
        }

        ForumUser creator = AppSession.getInstance().getCurrentUser();
        int id = nextGroupId++;

        Group group = new Group(
                id,
                name,
                description,
                "Active",
                creator.getId(),
                creator.getName(),
                0,
                1,
                "admin"
        );
        groups.put(id, group);
        membersByGroup.put(id, new ArrayList<>(List.of(
                new GroupMember(creator.getId(), creator.getName(), creator.getEmail(),
                        "admin", "Active", 0, true)
        )));
        topics().initGroupTopics(id);
        return withCountsAndRole(group);
    }

    public void addMember(int groupId, int userId, String role) {
        if (ApiSupport.useApi()) {
            if (!ApiClient.addGroupMember(groupId, userId, role)) {
                throw new IllegalStateException("Could not add member via API.");
            }
            syncGroupDetail(groupId);
            return;
        }

        if (isMember(groupId, userId)) {
            return;
        }
        ForumUser user = users.get(userId);
        if (user == null) {
            return;
        }
        membersByGroup.computeIfAbsent(groupId, key -> new ArrayList<>())
                .add(new GroupMember(user.getId(), user.getName(), user.getEmail(),
                        role, "Active", 0, false));
        refreshMemberCount(groupId);
    }

    public void removeMember(int groupId, int userId) {
        if (ApiSupport.useApi()) {
            if (!ApiClient.removeGroupMember(groupId, userId)) {
                throw new IllegalStateException("Could not remove member via API.");
            }
            syncGroupDetail(groupId);
            return;
        }

        if (isLastAdmin(groupId, userId)) {
            throw new IllegalStateException("Cannot remove the last group admin.");
        }
        membersByGroup.getOrDefault(groupId, List.of())
                .removeIf(member -> member.getUserId() == userId);
        refreshMemberCount(groupId);
    }

    public void updateMemberRole(int groupId, int userId, String role) {
        if (ApiSupport.useApi()) {
            if (!ApiClient.updateMemberRole(groupId, userId, role)) {
                throw new IllegalStateException("Could not update member role via API.");
            }
            syncGroupDetail(groupId);
            return;
        }

        if (isLastAdmin(groupId, userId) && !"admin".equalsIgnoreCase(role)) {
            throw new IllegalStateException("Cannot demote the last group admin.");
        }
        for (GroupMember member : membersByGroup.getOrDefault(groupId, List.of())) {
            if (member.getUserId() == userId) {
                member.setMemberRole(role);
                break;
            }
        }
    }

    public void warnMember(int groupId, int userId) {
        GroupMember member = findMember(groupId, userId);
        if (member == null) {
            return;
        }
        int warnings = Math.min(2, member.getWarnings() + 1);
        member.setWarnings(warnings);
        if (warnings >= 2) {
            member.setMemberStatus("Suspended");
        }
    }

    public void suspendMember(int groupId, int userId) {
        GroupMember member = findMember(groupId, userId);
        if (member != null) {
            member.setMemberStatus("Suspended");
        }
    }

    public void blockMember(int groupId, int userId) {
        GroupMember member = findMember(groupId, userId);
        if (member != null) {
            member.setMemberStatus("Blocked");
        }
    }

    public void reinstateMember(int groupId, int userId) {
        GroupMember member = findMember(groupId, userId);
        if (member != null) {
            member.setMemberStatus("Active");
            member.setWarnings(0);
        }
    }

    private Group withCountsAndRole(Group group) {
        int topics = topics().countTopicsForGroup(group.getId());
        int members = membersByGroup.getOrDefault(group.getId(), List.of()).size();
        String role = memberRole(group.getId(), AppSession.getInstance().getCurrentUser().getId());
        return new Group(
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
    }

    private void refreshMemberCount(int groupId) {
        Group group = groups.get(groupId);
        if (group != null) {
            group.setMembersCount(membersByGroup.getOrDefault(groupId, List.of()).size());
        }
    }

    private boolean isMember(int groupId, int userId) {
        return membersByGroup.getOrDefault(groupId, List.of()).stream()
                .anyMatch(member -> member.getUserId() == userId);
    }

    private String memberRole(int groupId, int userId) {
        GroupMember member = findMember(groupId, userId);
        return member == null ? null : member.getMemberRole();
    }

    private String memberStatus(int groupId, int userId) {
        GroupMember member = findMember(groupId, userId);
        return member == null ? null : member.getMemberStatus();
    }

    private GroupMember findMember(int groupId, int userId) {
        return membersByGroup.getOrDefault(groupId, List.of()).stream()
                .filter(member -> member.getUserId() == userId)
                .findFirst()
                .orElse(null);
    }

    private boolean isLastAdmin(int groupId, int userId) {
        if (!"admin".equalsIgnoreCase(memberRole(groupId, userId))) {
            return false;
        }
        long adminCount = membersByGroup.getOrDefault(groupId, List.of()).stream()
                .filter(member -> "admin".equalsIgnoreCase(member.getMemberRole()))
                .count();
        return adminCount <= 1;
    }

    private void syncGroupsFromApi() {
        List<Group> apiGroups = ApiClient.fetchGroups();
        if (apiGroups.isEmpty()) {
            return;
        }
        groups.clear();
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

            GroupContext context = new GroupContext();
            context.canManage = json.has("can_manage") && json.get("can_manage").getAsBoolean();
            context.canParticipate = json.has("can_participate") && json.get("can_participate").getAsBoolean();
            context.isMember = json.has("is_member") && json.get("is_member").getAsBoolean();
            groupContexts.put(groupId, context);
        });
    }

    private void seedData() {
        users.put(1, new ForumUser(1, "System Admin", "admin@smartforum.edu", "admin"));
        users.put(2, new ForumUser(2, "Anifa Onorio", "anifa@student.edu", "student"));
        users.put(3, new ForumUser(3, "Demo Lecturer", "lecturer@smartforum.edu", "lecturer"));
        users.put(4, new ForumUser(4, "James Okello", "james@student.edu", "student"));
        users.put(5, new ForumUser(5, "Sarah Nakato", "sarah@student.edu", "student"));

        groups.put(1, new Group(1, "CS Year 2",
                "Algorithms, databases, and coursework discussions for second-year CS students.",
                "Active", 3, "Demo Lecturer", 3, 4, "member"));
        groups.put(2, new Group(2, "Software Engineering",
                "Team projects, design patterns, and agile development topics.",
                "Active", 3, "Demo Lecturer", 2, 3, "member"));

        membersByGroup.put(1, new ArrayList<>(List.of(
                new GroupMember(3, "Demo Lecturer", "lecturer@smartforum.edu", "admin", "Active", 0, true),
                new GroupMember(2, "Anifa Onorio", "anifa@student.edu", "member", "Active", 0, false),
                new GroupMember(4, "James Okello", "james@student.edu", "member", "Active", 1, false),
                new GroupMember(5, "Sarah Nakato", "sarah@student.edu", "lecturer", "Active", 0, false)
        )));

        membersByGroup.put(2, new ArrayList<>(List.of(
                new GroupMember(3, "Demo Lecturer", "lecturer@smartforum.edu", "admin", "Active", 0, true),
                new GroupMember(2, "Anifa Onorio", "anifa@student.edu", "member", "Active", 0, false),
                new GroupMember(5, "Sarah Nakato", "sarah@student.edu", "member", "Suspended", 2, false)
        )));
    }
}
