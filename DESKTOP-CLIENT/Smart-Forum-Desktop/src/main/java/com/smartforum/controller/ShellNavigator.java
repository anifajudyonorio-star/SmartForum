package com.smartforum.controller;

public interface ShellNavigator {
    void showGroups();
    void showExploreGroups();
    void showGroup(int groupId);
    void showCreateGroup();
    void showCreateTopic(int groupId);
    void showTopic(int topicId);
    void showTopicSearch();
    void showDashboard();
    void showNotifications();
    void showStatistics();
    void showStatisticsOverview();
    void showGroupStatistics(int groupId);
    void showParticipation();
    void showQuizzes();
}
