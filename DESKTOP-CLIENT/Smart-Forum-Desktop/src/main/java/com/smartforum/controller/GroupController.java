package com.smartforum.controller;

import com.smartforum.model.Group;
import com.smartforum.model.GroupHighlight;
import com.smartforum.model.GroupMember;
import com.smartforum.model.GroupStats;
import com.smartforum.model.Topic;
import com.smartforum.service.AppSession;
import com.smartforum.service.GroupService;
import javafx.beans.property.SimpleIntegerProperty;
import javafx.collections.FXCollections;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ComboBox;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.control.TableCell;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.List;
import java.util.function.Consumer;

/**
 * Single controller for all group screens — mirrors web {@code GroupController}
 * (index, create, show, store, addMember, etc.) with one FXML and internal view switching.
 */
public class GroupController {

    // Index
    @FXML private ScrollPane contentScroll;
    @FXML private VBox indexPane;
    @FXML private Label sectionTitleLabel;
    @FXML private FlowPane groupsGrid;
    @FXML private VBox emptyStateBox;

    // Show
    @FXML private VBox showPane;
    @FXML private Label groupNameLabel;
    @FXML private Label groupDescLabel;
    @FXML private Label statusBadge;
    @FXML private Label creatorLabel;
    @FXML private Label roleBadge;
    @FXML private HBox adminActionsBox;
    @FXML private GridPane groupStatsGrid;
    @FXML private Label totalMembersLabel;
    @FXML private Label totalTopicsLabel;
    @FXML private Label totalPostsLabel;
    @FXML private Label activeMembersLabel;
    @FXML private Label suspendedMembersLabel;
    @FXML private Label blockedMembersLabel;
    @FXML private Label mostActiveMemberNameLabel;
    @FXML private Label mostActiveMemberMetaLabel;
    @FXML private Label topTopicCreatorNameLabel;
    @FXML private Label topTopicCreatorMetaLabel;
    @FXML private Label mostActiveTopicNameLabel;
    @FXML private Label mostActiveTopicMetaLabel;
    @FXML private Label avgPostsLabel;
    @FXML private Label membersWithWarningsLabel;
    @FXML private Label adminCountLabel;
    @FXML private HBox addMemberBox;
    @FXML private ComboBox<String> userCombo;
    @FXML private ComboBox<String> roleCombo;
    @FXML private TableView<GroupMember> membersTable;
    @FXML private TableColumn<GroupMember, String> nameColumn;
    @FXML private TableColumn<GroupMember, String> roleColumn;
    @FXML private TableColumn<GroupMember, String> statusColumn;
    @FXML private TableColumn<GroupMember, Number> warningsColumn;
    @FXML private TableColumn<GroupMember, String> emailColumn;
    @FXML private TableColumn<GroupMember, Void> actionsColumn;
    @FXML private VBox topicsBox;
    @FXML private VBox topicsEmptyBox;
    @FXML private Label restrictionLabel;
    @FXML private Button newTopicBtn;

    // Create
    @FXML private VBox createPane;
    @FXML private TextField nameField;
    @FXML private TextArea descriptionField;

    private int groupId;
    private Consumer<String> pageTitleUpdater;
    private ShellNavigator navigator;
    private Region rootNode;
    private final GroupService groupService = GroupService.getInstance();

    public Region getRootNode() {
        return rootNode;
    }

    public int getGroupId() {
        return groupId;
    }

    public boolean isShowingDetail() {
        return showPane != null && showPane.isVisible();
    }

    public boolean isShowingCreate() {
        return createPane != null && createPane.isVisible();
    }

    public boolean isExploreMode() {
        return exploreMode;
    }

    public void setRootNode(Region rootNode) {
        this.rootNode = rootNode;
    }

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    public void setPageTitleUpdater(Consumer<String> pageTitleUpdater) {
        this.pageTitleUpdater = pageTitleUpdater;
    }

    private boolean exploreMode;

    private boolean membersTableReady;

    @FXML
    private void initialize() {
        sectionTitleLabel.setText("My Groups");
        switchTo(indexPane);
    }

    private void ensureMembersTableReady() {
        if (membersTableReady) {
            return;
        }

        roleCombo.setItems(FXCollections.observableArrayList("member", "lecturer", "admin"));

        nameColumn.setCellValueFactory(new PropertyValueFactory<>("name"));
        emailColumn.setCellValueFactory(new PropertyValueFactory<>("email"));
        roleColumn.setCellValueFactory(new PropertyValueFactory<>("memberRole"));
        statusColumn.setCellValueFactory(new PropertyValueFactory<>("memberStatus"));
        warningsColumn.setCellValueFactory(cell ->
                new SimpleIntegerProperty(cell.getValue().getWarnings()));
        actionsColumn.setCellValueFactory(param -> null);

        membersTable.setColumnResizePolicy(TableView.UNCONSTRAINED_RESIZE_POLICY);
        membersTable.setFixedCellSize(32);
        VBox.setVgrow(membersTable, Priority.NEVER);
        setupRoleColumn();
        setupActionsColumn();

        membersTableReady = true;
    }

    /** Web: index() */
    @FXML
    public void index() {
        exploreMode = false;
        sectionTitleLabel.setText("My Groups");
        switchTo(indexPane);
        updateTitle("Discussion Groups");
        refreshGroups();
    }

    /** Web: explore() */
    public void explore() {
        exploreMode = true;
        sectionTitleLabel.setText("Explore Groups");
        switchTo(indexPane);
        updateTitle("Explore Groups");
        refreshExploreGroups();
    }

    /** Web: create() */
    public void create() {
        nameField.clear();
        descriptionField.clear();
        switchTo(createPane);
        updateTitle("Create Group");
    }

    @FXML
    public void onCreate(ActionEvent event) {
        if (navigator != null) {
            navigator.showCreateGroup();
        } else {
            create();
        }
    }

    /** Web: show($group) */
    public void show(int groupId) {
        this.groupId = groupId;
        switchTo(showPane);
        loadGroup();
    }

    /** Web: store() */
    public void store() {
        String name = nameField.getText() == null ? "" : nameField.getText().trim();
        String description = descriptionField.getText() == null ? "" : descriptionField.getText().trim();

        if (name.isBlank() || description.isBlank()) {
            showAlert(Alert.AlertType.WARNING, "Missing fields", "Group name and description are required.");
            return;
        }

        Group group = groupService.createGroup(name, description);
        show(group.getId());
    }

    @FXML
    public void onStore(ActionEvent event) {
        store();
    }

    /** Web: addMember() */
    public void addMember() {
        String selected = userCombo.getSelectionModel().getSelectedItem();
        if (selected == null || selected.isBlank()) {
            return;
        }
        int userId = Integer.parseInt(selected.split(" — ")[0].replace("ID ", ""));
        String role = roleCombo.getSelectionModel().getSelectedItem();
        groupService.addMember(groupId, userId, role);
        loadGroup();
    }

    @FXML
    public void onAddMember(ActionEvent event) {
        addMember();
    }

    private void refreshGroups() {
        List<Group> groups = groupService.getGroupsForCurrentUser();
        groupsGrid.getChildren().clear();

        if (groups.isEmpty()) {
            emptyStateBox.setVisible(true);
            emptyStateBox.setManaged(true);
            return;
        }

        emptyStateBox.setVisible(false);
        emptyStateBox.setManaged(false);

        for (Group group : groups) {
            groupsGrid.getChildren().add(buildGroupCard(group));
        }
    }

    private void refreshExploreGroups() {
        List<Group> groups = groupService.getExploreGroups();
        groupsGrid.getChildren().clear();

        if (groups.isEmpty()) {
            emptyStateBox.setVisible(true);
            emptyStateBox.setManaged(true);
            return;
        }

        emptyStateBox.setVisible(false);
        emptyStateBox.setManaged(false);

        for (Group group : groups) {
            groupsGrid.getChildren().add(buildExploreGroupCard(group));
        }
    }

    private VBox buildExploreGroupCard(Group group) {
        VBox card = buildGroupCard(group);
        card.setOnMouseClicked(null);

        Label actionLabel = new Label();
        actionLabel.getStyleClass().add("group-card-action");
        String joinStatus = group.getJoinStatus() == null ? "none" : group.getJoinStatus();
        if ("pending".equalsIgnoreCase(joinStatus)) {
            actionLabel.setText("Pending approval");
        } else if ("blocked".equalsIgnoreCase(joinStatus)) {
            actionLabel.setText("Cannot join");
        } else {
            actionLabel.setText("Request to Join");
            card.setOnMouseClicked(event -> {
                if (groupService.requestJoinGroup(group.getId())) {
                    showAlert(Alert.AlertType.INFORMATION, "Request sent",
                            "Your request to join \"" + group.getName() + "\" was sent for admin approval.");
                    refreshExploreGroups();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Request failed", "Could not send your join request.");
                }
            });
        }

        if (card.getChildren().size() >= 5 && card.getChildren().get(4) instanceof HBox footer) {
            footer.getChildren().set(footer.getChildren().size() - 1, actionLabel);
        }

        return card;
    }

    private VBox buildGroupCard(Group group) {
        Label icon = new Label("💬");
        icon.getStyleClass().add("group-card-icon");

        Label title = new Label(group.getName());
        title.getStyleClass().add("group-card-title");
        title.setWrapText(true);

        Label desc = new Label(group.getDescription() == null || group.getDescription().isBlank()
                ? "No description." : group.getDescription());
        desc.getStyleClass().add("group-card-desc");
        desc.setWrapText(true);

        HBox badges = new HBox(6);
        badges.getChildren().addAll(
                badge(group.getStatus(), "group-status-badge"),
                badge(group.getTopicsCount() + " Topics", "group-topics-badge"),
                badge(group.getMembersCount() + " Members", "group-topics-badge")
        );
        if (group.getMyRole() != null && !group.getMyRole().isBlank()) {
            badges.getChildren().add(badge(capitalize(group.getMyRole()), "group-topics-badge"));
        }

        Label creator = new Label("🛡 " + (group.getCreatorName() != null ? group.getCreatorName() : "Unknown"));
        creator.getStyleClass().add("group-card-creator");

        Label open = new Label("Open →");
        open.getStyleClass().add("group-card-action");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        HBox footer = new HBox(8, creator, spacer, open);
        footer.setAlignment(Pos.CENTER_LEFT);
        footer.getStyleClass().add("group-card-footer");

        VBox card = new VBox(6, icon, title, desc, badges, footer);
        card.getStyleClass().add("group-card-modern");
        card.setMinWidth(280);
        card.setMaxWidth(340);
        card.setOnMouseClicked(event -> {
            if (navigator != null) {
                navigator.showGroup(group.getId());
            } else {
                show(group.getId());
            }
        });

        return card;
    }

    @FXML
    public void onNewTopic(ActionEvent event) {
        if (groupId <= 0) {
            showAlert(Alert.AlertType.WARNING, "Group not loaded", "Open a group first, then create a topic.");
            return;
        }
        if (navigator != null) {
            navigator.showCreateTopic(groupId);
        } else {
            showAlert(Alert.AlertType.ERROR, "Navigation error", "Could not open the create topic screen.");
        }
    }

    private void loadGroup() {
        ensureMembersTableReady();

        Group group = groupService.getGroup(groupId).orElse(null);
        if (group == null || !groupService.canViewGroup(groupId)) {
            showAlert(Alert.AlertType.ERROR, "Access denied", "You cannot view this group.");
            index();
            return;
        }

        updateTitle(group.getName());
        groupNameLabel.setText(group.getName());
        groupDescLabel.setText(group.getDescription());
        statusBadge.setText(group.getStatus());
        creatorLabel.setText("Created by " + group.getCreatorName());
        roleBadge.setText("Your role: " + capitalize(group.getMyRole()));

        boolean canManage = groupService.canManageGroup(groupId);
        boolean canParticipate = groupService.canParticipateInGroup(groupId);

        adminActionsBox.setVisible(canManage);
        adminActionsBox.setManaged(canManage);
        addMemberBox.setVisible(canManage);
        addMemberBox.setManaged(canManage);
        warningsColumn.setVisible(canManage);
        actionsColumn.setVisible(canManage);

        if (canManage) {
            setupAddMemberForm();
        }

        GroupStats stats = groupService.getGroupStats(groupId);
        totalMembersLabel.setText(String.valueOf(stats.totalMembers()));
        totalTopicsLabel.setText(String.valueOf(stats.totalTopics()));
        totalPostsLabel.setText(String.valueOf(stats.totalPosts()));
        activeMembersLabel.setText(String.valueOf(stats.activeMembers()));
        suspendedMembersLabel.setText(String.valueOf(stats.suspendedMembers()));
        blockedMembersLabel.setText(String.valueOf(stats.blockedMembers()));
        applyHighlight(mostActiveMemberNameLabel, mostActiveMemberMetaLabel, stats.mostActiveMember());
        applyHighlight(topTopicCreatorNameLabel, topTopicCreatorMetaLabel, stats.topTopicCreator());
        applyHighlight(mostActiveTopicNameLabel, mostActiveTopicMetaLabel, stats.mostActiveTopic());
        avgPostsLabel.setText(stats.avgPostsPerTopic());
        membersWithWarningsLabel.setText(String.valueOf(stats.membersWithWarnings()));
        adminCountLabel.setText(String.valueOf(stats.adminCount()));

        membersTable.setItems(FXCollections.observableArrayList(groupService.getMembers(groupId)));
        boolean canView = groupService.canViewGroup(groupId);
        loadTopics(groupService.getTopics(groupId), canView, canParticipate);

        newTopicBtn.setDisable(!canParticipate);
        newTopicBtn.setVisible(true);
        newTopicBtn.setManaged(true);

        if (!canParticipate) {
            restrictionLabel.setText("Your access in this group is restricted. You cannot create topics or post until a group admin reinstates you.");
            restrictionLabel.setVisible(true);
            restrictionLabel.setManaged(true);
        } else {
            restrictionLabel.setVisible(false);
            restrictionLabel.setManaged(false);
        }
    }

    private void setupAddMemberForm() {
        userCombo.getItems().clear();
        groupService.getAvailableUsers(groupId).forEach(user ->
                userCombo.getItems().add("ID " + user.getId() + " — " + user.getName() + " — " + user.getEmail())
        );
    }

    private void setupRoleColumn() {
        roleColumn.setCellFactory(column -> new TableCell<GroupMember, String>() {
            private final ComboBox<String> roleSelect = new ComboBox<>(
                    FXCollections.observableArrayList("member", "lecturer", "admin"));

            {
                roleSelect.setOnAction(event -> {
                    GroupMember member = getTableView().getItems().get(getIndex());
                    try {
                        groupService.updateMemberRole(groupId, member.getUserId(), roleSelect.getValue());
                        loadGroup();
                    } catch (IllegalStateException ex) {
                        showAlert(Alert.AlertType.WARNING, "Cannot change role", ex.getMessage());
                        loadGroup();
                    }
                });
            }

            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }
                GroupMember member = getTableRow().getItem();
                boolean canManage = groupService.canManageGroup(groupId);
                if (canManage) {
                    roleSelect.setValue(member.getMemberRole());
                    setGraphic(roleSelect);
                    setText(null);
                } else {
                    setGraphic(null);
                    setText(capitalize(member.getMemberRole()));
                }
            }
        });
    }

    private void setupActionsColumn() {
        int currentUserId = AppSession.getInstance().getCurrentUser().getId();
        actionsColumn.setCellFactory(column -> new TableCell<GroupMember, Void>() {
            private final HBox actions = new HBox(4);

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                actions.getChildren().clear();
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    return;
                }

                GroupMember member = getTableRow().getItem();
                if (member.getUserId() == currentUserId) {
                    setGraphic(new Label("You"));
                    return;
                }
                if ("admin".equalsIgnoreCase(member.getMemberRole())
                        && !AppSession.getInstance().isSystemAdmin()) {
                    setGraphic(new Label("Protected"));
                    return;
                }

                if ("Active".equalsIgnoreCase(member.getMemberStatus())) {
                    actions.getChildren().add(actionButton("Warn", () -> {
                        groupService.warnMember(groupId, member.getUserId());
                        loadGroup();
                    }));
                    actions.getChildren().add(actionButton("Suspend", () -> {
                        groupService.suspendMember(groupId, member.getUserId());
                        loadGroup();
                    }));
                    actions.getChildren().add(actionButton("Block", () -> {
                        groupService.blockMember(groupId, member.getUserId());
                        loadGroup();
                    }));
                } else {
                    actions.getChildren().add(actionButton("Reinstate", () -> {
                        groupService.reinstateMember(groupId, member.getUserId());
                        loadGroup();
                    }));
                }

                actions.getChildren().add(actionButton("Remove", () -> {
                    try {
                        groupService.removeMember(groupId, member.getUserId());
                        loadGroup();
                    } catch (IllegalStateException ex) {
                        showAlert(Alert.AlertType.WARNING, "Cannot remove member", ex.getMessage());
                    }
                }));
                setGraphic(actions);
            }
        });
    }

    private Button actionButton(String text, Runnable action) {
        Button button = new Button(text);
        button.getStyleClass().add("btn-outline");
        button.setOnAction(event -> action.run());
        return button;
    }

    private void loadTopics(List<Topic> topics, boolean canView, boolean canParticipate) {
        topicsBox.getChildren().clear();
        if (topics.isEmpty()) {
            topicsEmptyBox.setVisible(true);
            topicsEmptyBox.setManaged(true);
            return;
        }
        topicsEmptyBox.setVisible(false);
        topicsEmptyBox.setManaged(false);

        for (Topic topic : topics) {
            topicsBox.getChildren().add(buildTopicRow(topic, canView));
        }
    }

    @FXML
    public void onCreateTopicFromEmpty(ActionEvent event) {
        onNewTopic(event);
    }

    private HBox buildTopicRow(Topic topic, boolean canView) {
        Label avatar = new Label(topic.getInitials());
        avatar.getStyleClass().add("topic-chat-avatar");

        Label title = new Label(topic.getTitle());
        title.getStyleClass().add("topic-chat-title");

        Label desc = new Label(truncate(topic.getDescription(), 80));
        desc.getStyleClass().add("topic-chat-desc");
        desc.setWrapText(true);

        VBox content = new VBox(2, title, desc);
        HBox.setHgrow(content, Priority.ALWAYS);

        HBox row = new HBox(12, avatar, content);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setMaxWidth(Double.MAX_VALUE);
        row.getStyleClass().add("topic-chat-item");
        if (canView) {
            row.setOnMouseClicked(event -> {
                if (navigator != null) {
                    navigator.showTopic(topic.getId());
                }
            });
        } else {
            row.setOpacity(0.75);
        }
        return row;
    }

    private void switchTo(VBox pane) {
        for (VBox child : List.of(indexPane, showPane, createPane)) {
            boolean active = child == pane;
            child.setVisible(active);
            child.setManaged(active);
        }
        if (contentScroll != null) {
            contentScroll.setVvalue(0);
        }
    }

    private void updateTitle(String title) {
        if (pageTitleUpdater != null) {
            pageTitleUpdater.accept(title);
        }
    }

    private Label badge(String text, String styleClass) {
        Label label = new Label(text);
        label.getStyleClass().add(styleClass);
        return label;
    }

    private void applyHighlight(Label nameLabel, Label metaLabel, GroupHighlight highlight) {
        nameLabel.setText(truncate(highlight.name(), 20));
        metaLabel.setText(highlight.detail());
    }

    private void showAlert(Alert.AlertType type, String title, String message) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private String capitalize(String value) {
        if (value == null || value.isBlank()) {
            return "";
        }
        return value.substring(0, 1).toUpperCase() + value.substring(1);
    }

    private String truncate(String text, int max) {
        if (text == null) {
            return "";
        }
        return text.length() <= max ? text : text.substring(0, max - 3) + "...";
    }
}
