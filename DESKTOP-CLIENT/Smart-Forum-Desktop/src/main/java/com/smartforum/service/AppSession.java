package com.smartforum.service;

import com.smartforum.model.ForumUser;
import com.smartforum.model.Group;
import com.smartforum.model.GroupMember;
import com.smartforum.model.Topic;

public final class AppSession {
    private static final AppSession INSTANCE = new AppSession();

    private ForumUser currentUser = new ForumUser(2, "Anifa Onorio", "anifa@student.edu", "student");

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

    public boolean isSystemAdmin() {
        return "admin".equalsIgnoreCase(currentUser.getSystemRole());
    }

    public boolean isStudent() {
        return "student".equalsIgnoreCase(currentUser.getSystemRole());
    }

    public boolean isLecturer() {
        return "lecturer".equalsIgnoreCase(currentUser.getSystemRole());
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
