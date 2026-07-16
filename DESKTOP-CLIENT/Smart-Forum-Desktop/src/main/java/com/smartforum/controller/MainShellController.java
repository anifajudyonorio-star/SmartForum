package com.smartforum.controller;

import com.smartforum.service.AppSession;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Node;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ContentDisplay;
import javafx.scene.control.Label;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import org.kordamp.ikonli.bootstrapicons.BootstrapIcons;
import org.kordamp.ikonli.javafx.FontIcon;

import java.io.IOException;
import java.net.URL;
import java.util.ArrayDeque;
import java.util.ArrayList;
import java.util.Deque;
import java.util.List;
import java.util.function.Consumer;

public class MainShellController implements ShellNavigator {

    private static final String APP_TITLE = "Smart Discussion";

    @FXML private StackPane contentArea;
    @FXML private Label pageTitleLabel;
    @FXML private Label topBarUserLabel;
    @FXML private Button backNavBtn;
    @FXML private Button dashboardNavBtn;
    @FXML private Button groupsNavBtn;
    @FXML private Button topicSearchNavBtn;
    @FXML private Button notificationsNavBtn;
    @FXML private VBox groupAdminSection;
    @FXML private Button statisticsNavBtn;
    @FXML private Button participationNavBtn;
    @FXML private Button quizNavBtn;
    @FXML private Label topBarUserAvatar;

    private GroupController groupController;
    private TopicController topicController;
    private TopicSearchController topicSearchController;
    private NotificationViewController notificationViewController;

    private final List<Button> navButtons = new ArrayList<>();
    private final Deque<Runnable> backStack = new ArrayDeque<>();
    private String activeContentKey = "";

    @FXML
    private void initialize() {
        configureSidebarForRole();
        setupSidebarNavIcons();

        navButtons.addAll(List.of(
                dashboardNavBtn, groupsNavBtn, topicSearchNavBtn, notificationsNavBtn,
                statisticsNavBtn, participationNavBtn, quizNavBtn
        ));

        var user = AppSession.getInstance().getCurrentUser();
        topBarUserLabel.setText(user.getName());
        topBarUserAvatar.setText(user.getInitials());
        pageTitleLabel.setText(APP_TITLE);
        updateBackButton();
        showDashboard();
    }

    private void configureSidebarForRole() {
        boolean showGroupAdmin = AppSession.getInstance().isSystemAdmin()
                || AppSession.getInstance().isLecturer();
        groupAdminSection.setVisible(showGroupAdmin);
        groupAdminSection.setManaged(showGroupAdmin);
    }

    private void setupSidebarNavIcons() {
        setNavIcon(dashboardNavBtn, BootstrapIcons.SPEEDOMETER2);
        setNavIcon(groupsNavBtn, BootstrapIcons.PEOPLE_FILL);
        setNavIcon(topicSearchNavBtn, BootstrapIcons.SEARCH);
        setNavIcon(notificationsNavBtn, BootstrapIcons.BELL_FILL);
        setNavIcon(statisticsNavBtn, BootstrapIcons.GRAPH_UP);
        setNavIcon(participationNavBtn, BootstrapIcons.BAR_CHART_FILL);
        setNavIcon(quizNavBtn, BootstrapIcons.PATCH_QUESTION);
    }

    private void setNavIcon(Button button, BootstrapIcons icon) {
        FontIcon fontIcon = FontIcon.of(icon);
        fontIcon.getStyleClass().add("sidebar-nav-icon");
        button.setGraphic(fontIcon);
        button.setContentDisplay(ContentDisplay.LEFT);
        button.setGraphicTextGap(10);
    }

    @FXML
    private void showStatisticsFromNav() {
        showStatisticsOverview();
    }

    @FXML
    @Override
    public void showStatisticsOverview() {
        resetBackStack();
        showStatisticsInternal();
    }

    @FXML
    private void showParticipationFromNav() {
        resetBackStack();
        showParticipationInternal();
    }

    @FXML
    private void showQuizManagementFromNav() {
        resetBackStack();
        showQuizManagementInternal();
    }

    @FXML
    private void onBack() {
        if (backStack.isEmpty()) {
            return;
        }
        backStack.pop().run();
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
        resetBackStack();
        showNotificationsInternal();
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

    @FXML
    @Override
    public void showGroups() {
        resetBackStack();
        showGroupsIndexInternal();
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
        resetBackStack();
        openTopicSearchInternal();
    }

    private void showDashboardInternal() {
        String fxml = AppSession.getInstance().getDashboardFxml();
        loadView(fxml, dashboardNavBtn, controller -> wireDashboardController(controller));
    }

    private void showNotificationsInternal() {
        loadView("notifications.fxml", notificationsNavBtn, controller -> {
            notificationViewController = (NotificationViewController) controller;
            notificationViewController.setNavigator(this);
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
        loadView("group-statistics.fxml", statisticsNavBtn, controller -> {
            if (controller instanceof GroupStatisticsController stats) {
                stats.setNavigator(this);
                stats.loadGroup(groupId);
            }
        });
    }

    private void showParticipationInternal() {
        loadView("participation.fxml", participationNavBtn, null);
    }

    private void showQuizManagementInternal() {
        loadView("quiz-management.fxml", quizNavBtn, null);
    }

    private void showGroupsIndexInternal() {
        showGroupsView(GroupController::index);
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
        Runnable back = captureBackTarget();
        if (back != null) {
            backStack.push(back);
        }
        forward.run();
        updateBackButton();
    }

    private Runnable captureBackTarget() {
        if (topicSearchController != null && topicSearchController.getRootNode() != null
                && isCurrentView(topicSearchController.getRootNode())) {
            return this::openTopicSearchInternal;
        }

        if (groupController != null && groupController.getRootNode() != null
                && isCurrentView(groupController.getRootNode())) {
            if (groupController.isShowingDetail()) {
                int groupId = groupController.getGroupId();
                return () -> showGroupInternal(groupId);
            }
            if (groupController.isShowingCreate()) {
                return this::showGroupsIndexInternal;
            }
            return null;
        }

        if (topicController != null && topicController.getRootNode() != null
                && isCurrentView(topicController.getRootNode())) {
            int groupId = topicController.getGroupId();
            return () -> showGroupInternal(groupId);
        }

        if ("notifications.fxml".equals(activeContentKey)) {
            return this::showNotificationsInternal;
        }

        if ("group-statistics.fxml".equals(activeContentKey)) {
            return this::showStatisticsInternal;
        }

        if ("statistics.fxml".equals(activeContentKey)) {
            return this::showStatisticsInternal;
        }

        if ("participation.fxml".equals(activeContentKey)) {
            return this::showDashboardInternal;
        }

        if ("quiz-management.fxml".equals(activeContentKey)) {
            return this::showDashboardInternal;
        }

        if (activeContentKey != null && activeContentKey.endsWith("-dashboard.fxml")) {
            return this::showDashboardInternal;
        }

        if ("groups.fxml".equals(activeContentKey)) {
            return this::showGroupsIndexInternal;
        }

        return this::showDashboardInternal;
    }

    private void resetBackStack() {
        backStack.clear();
        updateBackButton();
    }

    private void updateBackButton() {
        boolean canGoBack = !backStack.isEmpty();
        backNavBtn.setVisible(canGoBack);
        backNavBtn.setManaged(canGoBack);
    }

    private void wireDashboardController(Object controller) {
        if (controller instanceof AdminDashboardController admin) {
            admin.setNavigator(this);
        } else if (controller instanceof LecturerDashboardController lecturer) {
            lecturer.setNavigator(this);
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
                } else if ("topic-search.fxml".equals(fxmlFile)
                        && loader.getController() instanceof TopicSearchController search) {
                    search.setRootNode(region);
                }
            }
            fillContentArea(view);
            contentArea.getChildren().setAll(view);
            activeContentKey = fxmlFile;
            pageTitleLabel.setText(APP_TITLE);
            setActiveNav(activeButton);
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
    }

    private void unbindSize(Region region) {
        if (region.maxWidthProperty().isBound()) {
            region.maxWidthProperty().unbind();
        }
        if (region.maxHeightProperty().isBound()) {
            region.maxHeightProperty().unbind();
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
            button.getStyleClass().remove("active");
        }
        if (activeButton != null) {
            activeButton.getStyleClass().add("active");
        }
    }
}
