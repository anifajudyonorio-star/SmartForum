package com.smartforum.model;

public class GroupMember {
    private final int userId;
    private final String name;
    private final String email;
    private String memberRole;
    private String memberStatus;
    private int warnings;
    private final boolean creator;

    public GroupMember(int userId, String name, String email, String memberRole,
                       String memberStatus, int warnings, boolean creator) {
        this.userId = userId;
        this.name = name;
        this.email = email;
        this.memberRole = memberRole;
        this.memberStatus = memberStatus;
        this.warnings = warnings;
        this.creator = creator;
    }

    public int getUserId() {
        return userId;
    }

    public String getName() {
        return name;
    }

    public String getEmail() {
        return email;
    }

    public String getMemberRole() {
        return memberRole;
    }

    public void setMemberRole(String memberRole) {
        this.memberRole = memberRole;
    }

    public String getMemberStatus() {
        return memberStatus;
    }

    public void setMemberStatus(String memberStatus) {
        this.memberStatus = memberStatus;
    }

    public int getWarnings() {
        return warnings;
    }

    public void setWarnings(int warnings) {
        this.warnings = warnings;
    }

    public boolean isCreator() {
        return creator;
    }
}
