package com.smartforum.model;

public class GroupAdminSummaryRow {
    private final int groupId;
    private final String groupName;
    private final int membersCount;
    private final int topicsCount;
    private final int postsCount;

    public GroupAdminSummaryRow(int groupId, String groupName, int membersCount, int topicsCount, int postsCount) {
        this.groupId = groupId;
        this.groupName = groupName;
        this.membersCount = membersCount;
        this.topicsCount = topicsCount;
        this.postsCount = postsCount;
    }

    public int getGroupId() {
        return groupId;
    }

    public String getGroupName() {
        return groupName;
    }

    public int getMembersCount() {
        return membersCount;
    }

    public int getTopicsCount() {
        return topicsCount;
    }

    public int getPostsCount() {
        return postsCount;
    }
}
