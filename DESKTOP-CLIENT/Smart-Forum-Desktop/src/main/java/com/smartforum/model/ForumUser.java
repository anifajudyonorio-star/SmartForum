package com.smartforum.model;

public class ForumUser {
    private final int id;
    private final String name;
    private final String email;
    private final String systemRole;

    public ForumUser(int id, String name, String email, String systemRole) {
        this.id = id;
        this.name = name;
        this.email = email;
        this.systemRole = systemRole;
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

    public String getInitials() {
        if (name == null || name.isBlank()) {
            return "?";
        }
        return name.substring(0, 1).toUpperCase();
    }
}
