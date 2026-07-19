package com.smartforum.model;

public class ForumUser {
    private final int id;
    private final String name;
    private final String email;
    private final String systemRole;
    private final boolean canViewStatistics;
    private final boolean canViewParticipation;
    private final boolean administersGroups;

    public ForumUser(int id, String name, String email, String systemRole) {
        this(id, name, email, systemRole, false, false, false);
    }

    public ForumUser(int id, String name, String email, String systemRole,
                     boolean canViewStatistics, boolean canViewParticipation, boolean administersGroups) {
        this.id = id;
        this.name = name;
        this.email = email;
        this.systemRole = systemRole;
        this.canViewStatistics = canViewStatistics;
        this.canViewParticipation = canViewParticipation;
        this.administersGroups = administersGroups;
    }

    public int getId() {
        return id;
    }

    public String getName() {
        return name;
    }

    public String getEmail() {
        return email;
    }

    public String getSystemRole() {
        return systemRole;
    }

    public boolean canViewStatistics() {
        return canViewStatistics;
    }

    public boolean canViewParticipation() {
        return canViewParticipation;
    }

    public boolean administersGroups() {
        return administersGroups;
    }

    public String getInitials() {
        if (name == null || name.isBlank()) {
            return "?";
        }
        return name.substring(0, 1).toUpperCase();
    }
}
