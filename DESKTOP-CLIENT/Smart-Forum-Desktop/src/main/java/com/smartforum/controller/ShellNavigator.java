package com.smartforum.controller;

public interface ShellNavigator {
    void showGroups();
    void showExploreGroups();
    void showGroup(int groupId);
    /** Show a group without pushing onto the back stack (in-page back links). */
    void reopenGroup(int groupId);
    void showCreateGroup();
    void showCreateTopic(int groupId);
    void showTopic(int topicId);
    void showTopicSearch();
    void showDashboard();
    void showNotifications();
    void showStatistics();
    void showStatisticsOverview();
    /** Open statistics overview without pushing onto the back stack. */
    void reopenStatisticsOverview();
    void showGroupStatistics(int groupId);
    void showParticipationForGroup(int groupId);
    void showParticipation();
    void showQuizzes();
    void showAnnouncements();
    void showQuizProgress();
    void showQuizReports();
}
