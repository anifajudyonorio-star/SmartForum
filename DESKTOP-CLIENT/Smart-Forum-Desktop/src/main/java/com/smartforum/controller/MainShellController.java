package com.smartforum.controller;

import com.smartforum.api.ApiClient;
import com.smartforum.service.AppSession;
import com.smartforum.service.QuizLaunchMonitor;
import com.smartforum.service.SyncStatusService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Group;
import javafx.scene.Node;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ContentDisplay;
import javafx.scene.control.Label;
import javafx.scene.control.Tooltip;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.stage.Window;
import org.kordamp.ikonli.bootstrapicons.BootstrapIcons;
import org.kordamp.ikonli.javafx.FontIcon;

import java.io.IOException;
import java.net.URL;
import java.util.ArrayDeque;
import java.util.ArrayList;
import java.util.Deque;
import java.util.List;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.function.Consumer;

public class MainShellController implements ShellNavigator {

    private static final String APP_TITLE = "Smart Discussion";
    private static final Insets CONTENT_PADDING = new Insets(16, 20, 16, 20);

    @FXML private StackPane contentArea;
    @FXML private Label pageTitleLabel;
    @FXML private Label topBarUserLabel;
    @FXML private Button backNavBtn;
    @FXML private Button dashboardNavBtn;
    @FXML private Button groupsNavBtn;
    @FXML private Button topicSearchNavBtn;
    @FXML private Button notificationsNavBtn;
    @FXML private Button quizzesNavBtn;
    @FXML private Button announcementsNavBtn;
    @FXML private Button quizProgressNavBtn;
    @FXML private VBox studentNavSection;
    @FXML private VBox lecturerSection;
    @FXML private Button lecturerQuizzesNavBtn;
    @FXML private Button lecturerAnnouncementsNavBtn;
    @FXML private Button quizReportsNavBtn;
    @FXML private VBox superAdminSection;
    @FXML private Button userManagementNavBtn;
    @FXML private VBox groupAdminSection;
    @FXML private Button statisticsNavBtn;
    @FXML private Button participationNavBtn;
    @FXML private Label topBarUserAvatar;
    @FXML private Label syncStatusLabel;
    @FXML private Label offlineBanner;
    @FXML private VBox profileMenu;
    @FXML private Group profileMenuWrapper;
    @FXML private Label profileMenuName;
    @FXML private Label profileMenuEmail;
    @FXML private Label profileMenuRole;
    @FXML private HBox profileTrigger;

    private GroupController groupController;
    private TopicController topicController;
    private TopicSearchController topicSearchController;
    private ParticipationViewController participationViewController;
    private NotificationViewController notificationViewController;
    private Label notificationsNavBadge;
    private int lastNotificationPollId;
    private ScheduledExecutorService notificationPoller;

    private final List<Button> navButtons = new ArrayList<>();
    private final Deque<Runnable> backStack = new ArrayDeque<>();
    private String activeContentKey = "";
    private int lastGroupStatisticsId;

    @FXML
    private void initialize() {
        configureSidebarForRole();
        setupSidebarNavIcons();
        setupNotificationsNavButton();
        setupBackNavButton();

        navButtons.addAll(List.of(
                dashboardNavBtn, groupsNavBtn, topicSearchNavBtn, notificationsNavBtn,
                quizzesNavBtn, announcementsNavBtn, quizProgressNavBtn, lecturerQuizzesNavBtn,
                lecturerAnnouncementsNavBtn, quizReportsNavBtn, statisticsNavBtn, participationNavBtn,
                userManagementNavBtn
        ));

        var user = AppSession.getInstance().getCurrentUser();
        topBarUserLabel.setText(user.getName());
        topBarUserAvatar.setText(user.getInitials());
        pageTitleLabel.setText(APP_TITLE);
        updateBackButton();

        // Populate profile menu
        profileMenuName.setText(user.getName());
        profileMenuEmail.setText(user.getEmail() != null ? user.getEmail() : "");
        profileMenuRole.setText(formatRoleLabel(user.getSystemRole()));

        profileMenu.setMaxWidth(260);

        SyncStatusService sync = SyncStatusService.getInstance();
        syncStatusLabel.textProperty().bind(sync.statusTextProperty());
        sync.statusTextProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal != null && newVal.contains("Offline")) {
                syncStatusLabel.getStyleClass().remove("top-bar-online-badge");
                syncStatusLabel.getStyleClass().add("top-bar-online-badge-offline");
            } else {
                syncStatusLabel.getStyleClass().remove("top-bar-online-badge-offline");
                syncStatusLabel.getStyleClass().add("top-bar-online-badge");
            }
        });
        sync.setBannerCallback(this::showBanner);
        sync.start();

        startNotificationPolling();

        if (AppSession.getInstance().isStudent()) {
            // Always start the monitor; attach owner when the window is ready.
            Platform.runLater(this::attachQuizLaunchMonitor);
            contentArea.sceneProperty().addListener((obs, oldScene, newScene) -> {
                if (newScene != null) {
                    attachQuizLaunchMonitor();
                    newScene.windowProperty().addListener((wObs, oldWin, newWin) -> {
                        if (newWin != null) {
                            QuizLaunchMonitor.getInstance().start(newWin);
                        }
                    });
                }
            });
        }

        showDashboard();
    }

    private void attachQuizLaunchMonitor() {
        Window window = null;
        if (contentArea.getScene() != null) {
            window = contentArea.getScene().getWindow();
        }
        QuizLaunchMonitor.getInstance().start(window);
    }

    private void setupNotificationsNavButton() {
        FontIcon icon = FontIcon.of(BootstrapIcons.BELL_FILL);
        icon.getStyleClass().add("sidebar-nav-icon");

        Label label = new Label("Notifications");
        label.getStyleClass().add("sidebar-nav-label");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        notificationsNavBadge = new Label();
        notificationsNavBadge.getStyleClass().add("notif-badge");
        notificationsNavBadge.setVisible(false);
        notificationsNavBadge.setManaged(false);

        HBox row = new HBox(10, icon, label, spacer, notificationsNavBadge);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setMaxWidth(Double.MAX_VALUE);

        notificationsNavBtn.setGraphic(row);
        notificationsNavBtn.setText("");
        notificationsNavBtn.setMaxWidth(Double.MAX_VALUE);
    }

    public void updateNotificationsBadge(int unread) {
        if (notificationsNavBadge == null) {
            return;
        }
        if (unread <= 0) {
            notificationsNavBadge.setVisible(false);
            notificationsNavBadge.setManaged(false);
            notificationsNavBadge.setText("");
            return;
        }
        notificationsNavBadge.setText(unread > 99 ? "99+" : String.valueOf(unread));
        notificationsNavBadge.setVisible(true);
        notificationsNavBadge.setManaged(true);
    }

    private void startNotificationPolling() {


        stopNotificationPolling();
        refreshNotificationUnreadCount();

        notificationPoller = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread thread = new Thread(r, "notification-poller");
            thread.setDaemon(true);
            return thread;
        });
        notificationPoller.scheduleAtFixedRate(this::pollNotifications, 12, 12, TimeUnit.SECONDS);
    }

    private void stopNotificationPolling() {
        if (notificationPoller != null) {
            notificationPoller.shutdownNow();
            notificationPoller = null;
        }
    }

    private void refreshNotificationUnreadCount() {
        new Thread(() -> ApiClient.getNotifications().ifPresent(json -> {
            int unread = json.get("unread_count").getAsInt();
            Platform.runLater(() -> updateNotificationsBadge(unread));
        }), "notification-unread-refresh").start();
    }

    private void pollNotifications() {
        ApiClient.pollNotifications(lastNotificationPollId).ifPresent(json -> {
            if (json.has("latest_id") && !json.get("latest_id").isJsonNull()) {
                lastNotificationPollId = Math.max(lastNotificationPollId, json.get("latest_id").getAsInt());
            }
            int unread = json.get("unread_count").getAsInt();
            Platform.runLater(() -> {
                updateNotificationsBadge(unread);
                if (notificationViewController != null && "notifications.fxml".equals(activeContentKey)) {
                    notificationViewController.refresh();
                }
            });
        });
    }

    @FXML
    private void toggleProfileMenu() {
        boolean show = !profileMenuWrapper.isVisible();
        profileMenuWrapper.setVisible(show);
        profileMenuWrapper.setManaged(show);
        if (show) {
            javafx.scene.Scene scene = profileTrigger.getScene();
            if (scene == null) {
                return;
            }
            scene.addEventFilter(
                javafx.scene.input.MouseEvent.MOUSE_PRESSED, e -> {
                    if (isInsideNode(profileMenu, e.getSceneX(), e.getSceneY())
                            || isInsideNode(profileTrigger, e.getSceneX(), e.getSceneY())) {
                        return;
                    }
                    hideProfileMenu();
                }
            );
        }
    }

    private void hideProfileMenu() {
        profileMenuWrapper.setVisible(false);
        profileMenuWrapper.setManaged(false);
    }

    private String formatRoleLabel(String role) {
        if (role == null || role.isBlank()) {
            return "Student";
        }
        return switch (role.toLowerCase()) {
            case "admin" -> "Super Admin";
            case "lecturer" -> "Lecturer";
            case "student" -> "Student";
            default -> role.substring(0, 1).toUpperCase() + role.substring(1);
        };
    }

    private boolean isInsideNode(Node node, double sceneX, double sceneY) {
        if (node == null || node.getScene() == null) {
            return false;
        }
        return node.localToScene(node.getBoundsInLocal()).contains(sceneX, sceneY);
    }

    @FXML
    private void handleProfile() {
        hideProfileMenu();
        navigateWithBack(this::showProfileInternal);
    }

    private void showProfileInternal() {
        loadView("profile.fxml", null, controller -> {
            if (controller instanceof ProfileController profile) {
                profile.setOnUserUpdated(this::refreshUserDisplay);
                profile.setOnAccountDeleted(this::handleLogout);
            }
        });
        pageTitleLabel.setText("Profile");
    }

    private void refreshUserDisplay() {
        var user = AppSession.getInstance().getCurrentUser();
        topBarUserLabel.setText(user.getName());
        topBarUserAvatar.setText(user.getInitials());
        profileMenuName.setText(user.getName());
        profileMenuEmail.setText(user.getEmail() != null ? user.getEmail() : "");
        profileMenuRole.setText(formatRoleLabel(user.getSystemRole()));
    }

    @FXML
    private void handleLogout() {
        hideProfileMenu();
        stopNotificationPolling();
        QuizLaunchMonitor.getInstance().stop();
        SyncStatusService.getInstance().stop();
        if (notificationPoller != null && !notificationPoller.isShutdown()) {
            notificationPoller.shutdownNow();
            notificationPoller = null;
        }
        com.smartforum.util.SessionManager.getInstance().clear();
        com.smartforum.UserSession.getInstance().clear();
        AppSession.getInstance().clear();
        try {
            javafx.fxml.FXMLLoader loader = new javafx.fxml.FXMLLoader(
                getClass().getResource("/com/smartforum/auth-view.fxml"));
            javafx.scene.Scene scene = new javafx.scene.Scene(loader.load(), 480, 600);
            scene.setFill(javafx.scene.paint.Color.web("#0a0f1e"));
            javafx.stage.Stage stage = (javafx.stage.Stage) contentArea.getScene().getWindow();
            stage.setScene(scene);
            stage.setResizable(false);
            stage.setMaximized(false);
            stage.setTitle(APP_TITLE);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void showBanner(String message, String type) {
        offlineBanner.setText(message);
        offlineBanner.getStyleClass().removeAll("offline-banner-warning", "offline-banner-success", "offline-banner-danger", "offline-banner-info");
        offlineBanner.getStyleClass().add("offline-banner-" + type);
        offlineBanner.setVisible(true);
        offlineBanner.setManaged(true);
        if (!"warning".equals(type)) {
            new Thread(() -> {
                try { Thread.sleep("danger".equals(type) ? 6000 : 3000); } catch (InterruptedException ignored) {}
                javafx.application.Platform.runLater(() -> {
                    offlineBanner.setVisible(false);
                    offlineBanner.setManaged(false);
                });
            }).start();
        }
    }

    private void configureSidebarForRole() {
        boolean showStatistics = AppSession.getInstance().canViewStatistics();
        boolean showParticipation = AppSession.getInstance().canViewParticipation();
        groupAdminSection.setVisible(showStatistics || showParticipation);
        groupAdminSection.setManaged(showStatistics || showParticipation);
        statisticsNavBtn.setVisible(showStatistics);
        statisticsNavBtn.setManaged(showStatistics);
        participationNavBtn.setVisible(showParticipation);
        participationNavBtn.setManaged(showParticipation);

        boolean isStudent = AppSession.getInstance().isStudent();
        boolean isLecturerTools = AppSession.getInstance().isLecturer()
                || AppSession.getInstance().isSystemAdmin();
        boolean isSystemAdmin = AppSession.getInstance().isSystemAdmin();

        studentNavSection.setVisible(isStudent);
        studentNavSection.setManaged(isStudent);

        lecturerSection.setVisible(isLecturerTools);
        lecturerSection.setManaged(isLecturerTools);

        superAdminSection.setVisible(isSystemAdmin);
        superAdminSection.setManaged(isSystemAdmin);
    }

    private Button activeQuizzesNavBtn() {
        return AppSession.getInstance().isStudent() ? quizzesNavBtn : lecturerQuizzesNavBtn;
    }

    private Button activeAnnouncementsNavBtn() {
        return AppSession.getInstance().isStudent() ? announcementsNavBtn : lecturerAnnouncementsNavBtn;
    }

    private void setupSidebarNavIcons() {
        setNavIcon(dashboardNavBtn, BootstrapIcons.SPEEDOMETER2);
        setNavIcon(groupsNavBtn, BootstrapIcons.PEOPLE_FILL);
        setNavIcon(topicSearchNavBtn, BootstrapIcons.SEARCH);
        setNavIcon(quizzesNavBtn, BootstrapIcons.PATCH_QUESTION_FILL);
        setNavIcon(announcementsNavBtn, BootstrapIcons.MEGAPHONE_FILL);
        setNavIcon(quizProgressNavBtn, BootstrapIcons.GRAPH_UP);
        setNavIcon(lecturerQuizzesNavBtn, BootstrapIcons.PATCH_QUESTION_FILL);
        setNavIcon(lecturerAnnouncementsNavBtn, BootstrapIcons.MEGAPHONE_FILL);
        setNavIcon(quizReportsNavBtn, BootstrapIcons.GRAPH_UP);
        setNavIcon(statisticsNavBtn, BootstrapIcons.GRAPH_UP);
        setNavIcon(participationNavBtn, BootstrapIcons.BAR_CHART_FILL);
        setNavIcon(userManagementNavBtn, BootstrapIcons.PEOPLE);
    }

    private void setNavIcon(Button button, BootstrapIcons icon) {
        if (button == null) {
            return;
        }
        FontIcon fontIcon = FontIcon.of(icon);
        fontIcon.getStyleClass().add("sidebar-nav-icon");
        button.setGraphic(fontIcon);
        button.setContentDisplay(ContentDisplay.LEFT);
        button.setGraphicTextGap(10);
    }

    private void setupBackNavButton() {
        FontIcon icon = FontIcon.of(BootstrapIcons.ARROW_LEFT);
        icon.getStyleClass().add("top-bar-back-icon");
        icon.setMouseTransparent(true);
        backNavBtn.setGraphic(icon);
        backNavBtn.setText("");
        backNavBtn.setContentDisplay(ContentDisplay.GRAPHIC_ONLY);
        backNavBtn.setTooltip(new Tooltip("Go back"));
        // Ensure the handler is wired even if FXML binding is stale.
        backNavBtn.setOnAction(e -> onBack());
    }

    @FXML
    private void showQuizzesFromNav() {
        navigateWithBack(this::showQuizzesInternal);
    }

    @Override
    public void showQuizzes() {
        navigateWithBack(this::showQuizzesInternal);
    }

    @FXML
    private void showAnnouncementsFromNav() {
        navigateWithBack(this::showAnnouncementsInternal);
    }

    @Override
    public void showAnnouncements() {
        navigateWithBack(this::showAnnouncementsInternal);
    }

    @FXML
    private void showQuizProgressFromNav() {
        navigateWithBack(this::showQuizProgressInternal);
    }

    @Override
    public void showQuizProgress() {
        navigateWithBack(this::showQuizProgressInternal);
    }

    @FXML
    private void showQuizReportsFromNav() {
        navigateWithBack(this::showQuizReportsInternal);
    }

    @Override
    public void showQuizReports() {
        navigateWithBack(this::showQuizReportsInternal);
    }

    private void showQuizzesInternal() {
        if (AppSession.getInstance().isStudent()) {
            try {
                URL resource = getClass().getResource("/fxml/TakeQuiz.fxml");
                if (resource == null) throw new IOException("TakeQuiz.fxml not found at /fxml/TakeQuiz.fxml");
                FXMLLoader loader = new FXMLLoader(resource);
                Node view = loader.load();
                TakeQuizController ctrl = loader.getController();
                // Use public navigators so the back stack records Quizzes → Announcements/Progress.
                ctrl.setOpenAnnouncementsHandler(this::showAnnouncements);
                ctrl.setOpenQuizProgressHandler(this::showQuizProgress);
                ctrl.loadForCurrentStudent();
                fillContentArea(view);
                contentArea.getChildren().setAll(view);
                activeContentKey = "TakeQuiz.fxml";
                pageTitleLabel.setText(APP_TITLE);
                setActiveNav(activeQuizzesNavBtn());
                updateBackButton();
            } catch (Exception e) {
                showLoadError("TakeQuiz.fxml", e);
            }
        } else {
            loadView("quiz-management.fxml", activeQuizzesNavBtn(), null);
        }
    }

    private void showAnnouncementsInternal() {
        try {
            URL resource = getClass().getResource("/fxml/Announcements.fxml");
            if (resource == null) throw new IOException("Announcements.fxml not found at /fxml/Announcements.fxml");
            FXMLLoader loader = new FXMLLoader(resource);
            Node view = loader.load();
            AnnouncementsController ctrl = loader.getController();
            // Use public navigator so Available Quizzes pushes Announcements onto the back stack.
            ctrl.setOpenQuizzesHandler(this::showQuizzes);
            ctrl.configureForCurrentUser();
            fillContentArea(view);
            contentArea.getChildren().setAll(view);
            activeContentKey = "Announcements.fxml";
            pageTitleLabel.setText(APP_TITLE);
            setActiveNav(activeAnnouncementsNavBtn());
            updateBackButton();
        } catch (Exception e) {
            showLoadError("Announcements.fxml", e);
        }
    }

    private void showQuizProgressInternal() {
        loadView("quiz-progress.fxml", quizProgressNavBtn, null);
    }

    private void showQuizReportsInternal() {
        try {
            URL resource = getClass().getResource("/fxml/Results.fxml");
            if (resource == null) throw new IOException("Results.fxml not found at /fxml/Results.fxml");
            FXMLLoader loader = new FXMLLoader(resource);
            Node view = loader.load();
            fillContentArea(view);
            contentArea.getChildren().setAll(view);
            activeContentKey = "Results.fxml";
            pageTitleLabel.setText(APP_TITLE);
            setActiveNav(quizReportsNavBtn);
            updateBackButton();
        } catch (Exception e) {
            showLoadError("Results.fxml", e);
        }
    }

    @FXML
    private void showUserManagementFromNav() {
        navigateWithBack(this::showUserManagementInternal);
    }

    private void showUserManagementInternal() {
        loadView("user-management.fxml", userManagementNavBtn, null);
        pageTitleLabel.setText("User Management");
    }

    @FXML
    private void showStatisticsFromNav() {
        showStatisticsOverview();
    }

    @FXML
    @Override
    public void showStatisticsOverview() {
        navigateWithBack(this::showStatisticsInternal);
    }

    @FXML
    private void showParticipationFromNav() {
        navigateWithBack(this::showParticipationInternal);
    }

    @FXML
    private void onBack() {
        try {
            if (!backStack.isEmpty()) {
                // Restore the real previous page (no extra history entry).
                backStack.pop().run();
            } else if (activeContentKey != null && !activeContentKey.isBlank()
                    && !activeContentKey.endsWith("-dashboard.fxml")) {
                // Safety net: if history is empty but we left Home, return to dashboard.
                showDashboardInternal();
            }
        } catch (Exception ex) {
            ex.printStackTrace();
            showLoadError("previous page", ex);
        }
        updateBackButton();
    }

    /** Open a group without adding to the back stack (in-page "Back to Group" links). */
    @Override
    public void reopenGroup(int groupId) {
        showGroupInternal(groupId);
        updateBackButton();
    }

    /** Open statistics overview without adding to the back stack. */
    @Override
    public void reopenStatisticsOverview() {
        showStatisticsInternal();
        updateBackButton();
    }

    @FXML
    @Override
    public void showDashboard() {
        resetBackStack();
        showDashboardInternal();
    }

    @FXML
    @Override
    public void showNotifications() {
        navigateWithBack(this::showNotificationsInternal);
    }

    @FXML
    @Override
    public void showStatistics() {
        navigateWithBack(this::showStatisticsInternal);
    }

    @Override
    public void showGroupStatistics(int groupId) {
        navigateWithBack(() -> showGroupStatisticsInternal(groupId));
    }

    @FXML
    @Override
    public void showParticipation() {
        navigateWithBack(this::showParticipationInternal);
    }

    @Override
    public void showParticipationForGroup(int groupId) {
        navigateWithBack(() -> showParticipationForGroupInternal(groupId));
    }

    @FXML
    @Override
    public void showGroups() {
        navigateWithBack(this::showGroupsIndexInternal);
    }

    @Override
    public void showExploreGroups() {
        navigateWithBack(this::showExploreGroupsInternal);
    }

    @Override
    public void showGroup(int groupId) {
        navigateWithBack(() -> showGroupInternal(groupId));
    }

    @Override
    public void showCreateGroup() {
        navigateWithBack(this::showCreateGroupInternal);
    }

    @Override
    public void showCreateTopic(int groupId) {
        navigateWithBack(() -> showCreateTopicInternal(groupId));
    }

    @Override
    public void showTopic(int topicId) {
        navigateWithBack(() -> showTopicInternal(topicId));
    }

    @FXML
    @Override
    public void showTopicSearch() {
        navigateWithBack(this::openTopicSearchInternal);
    }

    private void showDashboardInternal() {
        String fxml = AppSession.getInstance().getDashboardFxml();
        loadView(fxml, dashboardNavBtn, controller -> wireDashboardController(controller));
    }

    private void showNotificationsInternal() {
        loadView("notifications.fxml", notificationsNavBtn, controller -> {
            notificationViewController = (NotificationViewController) controller;
            notificationViewController.setNavigator(this);
            notificationViewController.setUnreadCountUpdater(this::updateNotificationsBadge);
            notificationViewController.refresh();
        });
    }

    private void showStatisticsInternal() {
        loadView("statistics.fxml", statisticsNavBtn, controller -> {
            if (controller instanceof ForumStatisticsController stats) {
                stats.setNavigator(this);
                stats.refresh();
            }
        });
    }

    private void showGroupStatisticsInternal(int groupId) {
        lastGroupStatisticsId = groupId;
        loadView("group-statistics.fxml", statisticsNavBtn, controller -> {
            if (controller instanceof GroupStatisticsController stats) {
                stats.setNavigator(this);
                stats.loadGroup(groupId);
            }
        });
    }

    private void showParticipationInternal() {
        openParticipationView(null);
    }

    private void showParticipationForGroupInternal(int groupId) {
        openParticipationView(groupId);
    }

    private void openParticipationView(Integer groupId) {
        if (participationViewController == null) {
            loadView("participation.fxml", participationNavBtn, controller -> {
                participationViewController = (ParticipationViewController) controller;
                participationViewController.loadParticipation(groupId);
            });
            return;
        }

        if (!isCurrentView(participationViewController.getRootNode())) {
            contentArea.getChildren().setAll(participationViewController.getRootNode());
            fillContentArea(participationViewController.getRootNode());
        }
        activeContentKey = "participation.fxml";
        setActiveNav(participationNavBtn);
        participationViewController.loadParticipation(groupId);
    }

    private void showGroupsIndexInternal() {
        showGroupsView(GroupController::index);
        setActiveNav(groupsNavBtn);
    }

    private void showExploreGroupsInternal() {
        showGroupsView(GroupController::explore);
        setActiveNav(groupsNavBtn);
    }

    private void showGroupInternal(int groupId) {
        showGroupsView(controller -> controller.show(groupId));
    }

    private void showCreateGroupInternal() {
        showGroupsView(GroupController::create);
    }

    private void showCreateTopicInternal(int groupId) {
        showTopicsView(controller -> controller.create(groupId));
    }

    private void showTopicInternal(int topicId) {
        showTopicsView(controller -> controller.show(topicId));
    }

    private void openTopicSearchInternal() {
        if (topicSearchController == null) {
            loadView("topic-search.fxml", topicSearchNavBtn, controller -> {
                topicSearchController = (TopicSearchController) controller;
                wireTopicSearchController(topicSearchController);
                topicSearchController.index();
            });
            return;
        }

        if (!isCurrentView(topicSearchController.getRootNode())) {
            contentArea.getChildren().setAll(topicSearchController.getRootNode());
            fillContentArea(topicSearchController.getRootNode());
        }
        activeContentKey = "topic-search.fxml";
        setActiveNav(topicSearchNavBtn);
        topicSearchController.index();
    }

    private void navigateWithBack(Runnable forward) {
        // Snapshot the current page BEFORE leaving so Back can restore it.
        if (activeContentKey != null && !activeContentKey.isBlank()) {
            backStack.push(captureBackTarget());
        }
        forward.run();
        updateBackButton();
    }

    /**
     * Snapshot how to restore the page currently on screen.
     * Prefer activeContentKey over node-identity checks — those were unreliable
     * and caused Back to fall through to the dashboard.
     */
    private Runnable captureBackTarget() {
        String key = activeContentKey == null ? "" : activeContentKey;

        if ("groups.fxml".equals(key) && groupController != null) {
            if (groupController.isShowingEdit()) {
                int groupId = groupController.getGroupId();
                return () -> showGroupInternal(groupId);
            }
            if (groupController.isShowingDetail()) {
                int groupId = groupController.getGroupId();
                return () -> showGroupInternal(groupId);
            }
            if (groupController.isShowingCreate()) {
                return this::showCreateGroupInternal;
            }
            return groupController.isExploreMode()
                    ? this::showExploreGroupsInternal
                    : this::showGroupsIndexInternal;
        }

        if ("topics.fxml".equals(key) && topicController != null) {
            if (topicController.isCreating()) {
                int groupId = topicController.getGroupId();
                return () -> showCreateTopicInternal(groupId);
            }
            int topicId = topicController.getTopicId();
            if (topicId > 0) {
                return () -> showTopicInternal(topicId);
            }
            int groupId = topicController.getGroupId();
            if (groupId > 0) {
                return () -> showGroupInternal(groupId);
            }
        }

        if ("topic-search.fxml".equals(key)) {
            return this::openTopicSearchInternal;
        }

        if ("notifications.fxml".equals(key)) {
            return this::showNotificationsInternal;
        }

        if ("group-statistics.fxml".equals(key)) {
            int groupId = lastGroupStatisticsId;
            return () -> showGroupStatisticsInternal(groupId);
        }

        if ("statistics.fxml".equals(key)) {
            return this::showStatisticsInternal;
        }

        if ("participation.fxml".equals(key)) {
            return this::showParticipationInternal;
        }

        if ("TakeQuiz.fxml".equals(key)) {
            return this::showQuizzesInternal;
        }

        if ("Announcements.fxml".equals(key)) {
            return this::showAnnouncementsInternal;
        }

        if ("Results.fxml".equals(key)) {
            return this::showQuizReportsInternal;
        }

        if ("quiz-progress.fxml".equals(key)) {
            return this::showQuizProgressInternal;
        }

        if ("quiz-management.fxml".equals(key)) {
            return this::showQuizzesInternal;
        }

        if ("user-management.fxml".equals(key)) {
            return this::showUserManagementInternal;
        }

        if ("profile.fxml".equals(key)) {
            return this::showProfileInternal;
        }

        if (key.endsWith("-dashboard.fxml")) {
            return this::showDashboardInternal;
        }

        // Last resort: reload by known key via the standard view loader when possible.
        if (!key.isBlank() && !key.equals("TakeQuiz.fxml")
                && !key.equals("Announcements.fxml")
                && !key.equals("Results.fxml")) {
            String reloadKey = key;
            return () -> loadView(reloadKey, null, null);
        }

        return this::showDashboardInternal;
    }

    private void showQuizManagementInternal() {
    }

    private void resetBackStack() {
        backStack.clear();
        updateBackButton();
    }

    private void updateBackButton() {
        boolean canGoBack = !backStack.isEmpty()
                || (activeContentKey != null && !activeContentKey.isBlank()
                && !activeContentKey.endsWith("-dashboard.fxml"));
        backNavBtn.setDisable(!canGoBack);
    }

    private void wireDashboardController(Object controller) {
        if (controller instanceof AdminDashboardController admin) {
            admin.setNavigator(this);
        } else if (controller instanceof LecturerDashboardController lecturer) {
            lecturer.setNavigator(this);
        } else if (controller instanceof StudentDashboardController student) {
            student.setNavigator(this);
        }
    }

    private void showGroupsView(Consumer<GroupController> action) {
        if (groupController == null) {
            loadView("groups.fxml", groupsNavBtn, controller -> {
                groupController = (GroupController) controller;
                wireGroupController(groupController);
                action.accept(groupController);
            });
            activeContentKey = "groups.fxml";
            return;
        }

        if (!isCurrentView(groupController.getRootNode())) {
            contentArea.getChildren().setAll(groupController.getRootNode());
            fillContentArea(groupController.getRootNode());
        }
        resetContentPadding();
        activeContentKey = "groups.fxml";
        setActiveNav(groupsNavBtn);
        action.accept(groupController);
    }

    private void showTopicsView(Consumer<TopicController> action) {
        if (topicController == null) {
            loadView("topics.fxml", null, controller -> {
                topicController = (TopicController) controller;
                wireTopicController(topicController);
                action.accept(topicController);
            });
            activeContentKey = "topics.fxml";
            return;
        }

        if (!isCurrentView(topicController.getRootNode())) {
            contentArea.getChildren().setAll(topicController.getRootNode());
            fillContentArea(topicController.getRootNode());
        }
        activeContentKey = "topics.fxml";
        setActiveNav(null);
        action.accept(topicController);
    }

    private void wireGroupController(GroupController controller) {
        controller.setNavigator(this);
    }

    private void wireTopicController(TopicController controller) {
        controller.setNavigator(this);
        controller.setChatLayoutCallback(inChat ->
                contentArea.setPadding(inChat ? Insets.EMPTY : CONTENT_PADDING));
    }

    private void resetContentPadding() {
        contentArea.setPadding(CONTENT_PADDING);
    }

    private void wireTopicSearchController(TopicSearchController controller) {
        controller.setNavigator(this);
    }

    private void loadView(String fxmlFile, Button activeButton, Consumer<Object> controllerSetup) {
        try {
            URL resource = getClass().getResource("/com/smartforum/view/" + fxmlFile);
            if (resource == null) {
                throw new IOException("FXML not found on classpath: /com/smartforum/view/" + fxmlFile);
            }

            FXMLLoader loader = new FXMLLoader(resource);
            Node view = loader.load();
            if (controllerSetup != null) {
                controllerSetup.accept(loader.getController());
            }
            if (view instanceof Region region) {
                if ("groups.fxml".equals(fxmlFile) && loader.getController() instanceof GroupController group) {
                    group.setRootNode(region);
                } else if ("topics.fxml".equals(fxmlFile) && loader.getController() instanceof TopicController topic) {
                    topic.setRootNode(region);
                    wireTopicController(topic);
                } else if ("topic-search.fxml".equals(fxmlFile)
                        && loader.getController() instanceof TopicSearchController search) {
                    search.setRootNode(region);
                } else if ("participation.fxml".equals(fxmlFile)
                        && loader.getController() instanceof ParticipationViewController participation) {
                    participation.setRootNode(region);
                }
            }
            fillContentArea(view);
            contentArea.getChildren().setAll(view);
            if (!"topics.fxml".equals(fxmlFile)) {
                resetContentPadding();
            }
            activeContentKey = fxmlFile;
            pageTitleLabel.setText(APP_TITLE);
            setActiveNav(activeButton);
            updateBackButton();
        } catch (Exception e) {
            showLoadError(fxmlFile, e);
        }
    }

    private void fillContentArea(Node view) {
        if (!(view instanceof Region region)) {
            return;
        }
        unbindSize(region);
        region.maxWidthProperty().bind(contentArea.widthProperty());
        region.maxHeightProperty().bind(contentArea.heightProperty());
        region.prefWidthProperty().bind(contentArea.widthProperty());
        region.prefHeightProperty().bind(contentArea.heightProperty());
    }

    private void unbindSize(Region region) {
        if (region.maxWidthProperty().isBound()) {
            region.maxWidthProperty().unbind();
        }
        if (region.maxHeightProperty().isBound()) {
            region.maxHeightProperty().unbind();
        }
        if (region.prefWidthProperty().isBound()) {
            region.prefWidthProperty().unbind();
        }
        if (region.prefHeightProperty().isBound()) {
            region.prefHeightProperty().unbind();
        }
    }

    private boolean isCurrentView(Node view) {
        return view != null && !contentArea.getChildren().isEmpty()
                && contentArea.getChildren().get(0) == view;
    }

    private void showLoadError(String viewName, Exception e) {
        e.printStackTrace();
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle("Navigation error");
        alert.setHeaderText("Could not open " + viewName);
        alert.setContentText(rootMessage(e));
        alert.showAndWait();
    }

    private String rootMessage(Throwable error) {
        StringBuilder message = new StringBuilder();
        Throwable current = error;
        while (current != null) {
            if (current.getMessage() != null && !current.getMessage().isBlank()) {
                if (message.length() > 0) {
                    message.append("\n");
                }
                message.append(current.getMessage());
            }
            Throwable cause = current.getCause();
            if (cause == null || cause == current) {
                break;
            }
            current = cause;
        }
        return message.length() > 0 ? message.toString() : error.getClass().getSimpleName();
    }

    private void setActiveNav(Button activeButton) {
        for (Button button : navButtons) {
            if (button == null) {
                continue;
            }
            button.getStyleClass().remove("active");
        }
        if (activeButton != null) {
            activeButton.getStyleClass().add("active");
        }
    }
}
