package com.smartforum.service;

import com.smartforum.model.ForumUser;

public final class AppSession {
    private static final AppSession INSTANCE = new AppSession();

    private ForumUser currentUser = new ForumUser(0, "Guest", "", "student");

    private AppSession() {
    }

    public static AppSession getInstance() {
        return INSTANCE;
    }

    public ForumUser getCurrentUser() {
        return currentUser;
    }

    public void setCurrentUser(ForumUser currentUser) {
        this.currentUser = currentUser;
    }

    public void clear() {
        this.currentUser = new ForumUser(0, "Guest", "", "student");
    }

    public boolean isSystemAdmin() {
        return "admin".equalsIgnoreCase(currentUser.getSystemRole());
    }

    public boolean isStudent() {
        return "student".equalsIgnoreCase(currentUser.getSystemRole());
    }

    public boolean isLecturer() {
        return "lecturer".equalsIgnoreCase(currentUser.getSystemRole());
    }

    public boolean canViewStatistics() {
        return currentUser.canViewStatistics();
    }

    public boolean canViewParticipation() {
        return currentUser.canViewParticipation();
    }

    public boolean administersGroups() {
        return currentUser.administersGroups();
    }

    public String getDashboardFxml() {
        if (isSystemAdmin()) {
            return "admin-dashboard.fxml";
        }
        if (isLecturer()) {
            return "lecturer-dashboard.fxml";
        }
        return "student-dashboard.fxml";
    }
}
