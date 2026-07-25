package com.smartforum.model;

public class Group {
    private int id;
    private String name;
    private String description;
    private String status;
    private int createdBy;
    private String creatorName;
    private int topicsCount;
    private int membersCount;
    private String myRole;
    private String joinStatus = "none";
    private String joinRules;
    private boolean inactivityMonitoringEnabled;
    private int inactivityThresholdDays = 14;
    private int inactivityGraceDays = 7;
    private int inactivityBlacklistDays = 30;

    public Group(int id, String name, String description, String status, int createdBy,
                 String creatorName, int topicsCount, int membersCount, String myRole) {
        this.id = id;
        this.name = name;
        this.description = description;
        this.status = status;
        this.createdBy = createdBy;
        this.creatorName = creatorName;
        this.topicsCount = topicsCount;
        this.membersCount = membersCount;
        this.myRole = myRole;
    }

    public int getId() {
        return id;
    }

    public String getName() {
        return name;
    }

    public void setName(String name) {
        this.name = name;
    }

    public String getDescription() {
        return description;
    }

    public void setDescription(String description) {
        this.description = description;
    }

    public String getStatus() {
        return status;
    }

    public int getCreatedBy() {
        return createdBy;
    }

    public String getCreatorName() {
        return creatorName;
    }

    public int getTopicsCount() {
        return topicsCount;
    }

    public void setTopicsCount(int topicsCount) {
        this.topicsCount = topicsCount;
    }

    public int getMembersCount() {
        return membersCount;
    }

    public void setMembersCount(int membersCount) {
        this.membersCount = membersCount;
    }

    public String getMyRole() {
        return myRole;
    }

    public void setMyRole(String myRole) {
        this.myRole = myRole;
    }

    public String getJoinStatus() {
        return joinStatus;
    }

    public void setJoinStatus(String joinStatus) {
        this.joinStatus = joinStatus == null ? "none" : joinStatus;
    }

    public String getJoinRules() {
        return joinRules;
    }

    public void setJoinRules(String joinRules) {
        this.joinRules = joinRules;
    }

    public boolean hasJoinRules() {
        return joinRules != null && !joinRules.isBlank();
    }

    public boolean isInactivityMonitoringEnabled() {
        return inactivityMonitoringEnabled;
    }

    public void setInactivityMonitoringEnabled(boolean inactivityMonitoringEnabled) {
        this.inactivityMonitoringEnabled = inactivityMonitoringEnabled;
    }

    public int getInactivityThresholdDays() {
        return inactivityThresholdDays;
    }

    public void setInactivityThresholdDays(int inactivityThresholdDays) {
        this.inactivityThresholdDays = inactivityThresholdDays;
    }

    public int getInactivityGraceDays() {
        return inactivityGraceDays;
    }

    public void setInactivityGraceDays(int inactivityGraceDays) {
        this.inactivityGraceDays = inactivityGraceDays;
    }

    public int getInactivityBlacklistDays() {
        return inactivityBlacklistDays;
    }

    public void setInactivityBlacklistDays(int inactivityBlacklistDays) {
        this.inactivityBlacklistDays = inactivityBlacklistDays;
    }

    @Override
    public String toString() {
        return name == null || name.isBlank() ? "Group #" + id : name;
    }
}
