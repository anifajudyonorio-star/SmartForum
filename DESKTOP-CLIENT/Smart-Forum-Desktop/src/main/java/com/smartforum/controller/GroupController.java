package com.smartforum.controller;

import com.smartforum.model.Group;
import com.smartforum.model.GroupHighlight;
import com.smartforum.model.GroupMember;
import com.smartforum.model.GroupStats;
import com.smartforum.model.PendingJoinRequest;
import com.smartforum.model.PostReport;
import com.smartforum.model.Topic;
import com.smartforum.model.ForumUser;
import com.smartforum.service.AppSession;
import com.smartforum.service.GroupService;
import javafx.application.Platform;
import javafx.beans.property.SimpleIntegerProperty;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ButtonBar;
import javafx.scene.control.ButtonType;
import javafx.scene.control.CheckBox;
import javafx.scene.control.ComboBox;
import javafx.scene.control.Label;
import javafx.scene.control.ListCell;
import javafx.scene.control.ScrollPane;
import javafx.scene.control.Spinner;
import javafx.scene.control.SpinnerValueFactory;
import javafx.scene.control.TableCell;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableRow;
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
import java.util.Optional;
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
    @FXML private Label pageTitleLabel;
    @FXML private Label pageSubtitleLabel;
    @FXML private FlowPane groupsGrid;
    @FXML private VBox emptyStateBox;
    @FXML private Label emptyStateIconLabel;
    @FXML private Label emptyStateMessageLabel;
    @FXML private Button emptyStateActionButton;

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
    @FXML private Label membersCountLabel;
    @FXML private Label adminCountLabel;
    @FXML private VBox addMemberBox;
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

    // Edit group
    @FXML private VBox editPane;
    @FXML private TextField editNameField;
    @FXML private TextArea editDescField;
    @FXML private TextArea editJoinRulesField;
    @FXML private CheckBox inactivityEnabledCheck;
    @FXML private Spinner<Integer> inactivityThresholdSpinner;
    @FXML private Spinner<Integer> inactivityGraceSpinner;
    @FXML private Spinner<Integer> inactivityBlacklistSpinner;

    // Pending join requests
    @FXML private VBox pendingJoinBox;
    @FXML private TableView<PendingJoinRequest> pendingJoinTable;
    @FXML private TableColumn<PendingJoinRequest, String> joinNameColumn;
    @FXML private TableColumn<PendingJoinRequest, String> joinEmailColumn;
    @FXML private TableColumn<PendingJoinRequest, Void> joinActionsColumn;

    // Post reports
    @FXML private VBox postReportsBox;
    @FXML private Label postReportsCountLabel;
    @FXML private TableView<PostReport> postReportsTable;
    @FXML private TableColumn<PostReport, String> reportTopicColumn;
    @FXML private TableColumn<PostReport, String> reportContentColumn;
    @FXML private TableColumn<PostReport, String> reportAuthorColumn;
    @FXML private TableColumn<PostReport, String> reportReporterColumn;
    @FXML private TableColumn<PostReport, String> reportReasonColumn;
    @FXML private TableColumn<PostReport, Void> reportActionsColumn;

    // Create
    @FXML private VBox createPane;
    @FXML private TextField nameField;
    @FXML private TextArea descriptionField;
    @FXML private TextArea createJoinRulesField;

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

    public boolean isShowingEdit() {
        return editPane != null && editPane.isVisible();
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
    private boolean joinRequestsTableReady;
    private boolean postReportsTableReady;

    private static final double[] MEMBER_COLUMN_WEIGHTS_MANAGE = {2.0, 1.1, 0.9, 0.7, 1.8, 2.5};
    private static final double[] MEMBER_COLUMN_WEIGHTS_VIEW = {2.2, 1.2, 1.0, 0.0, 2.6, 0.0};

    @FXML
    private void initialize() {
        sectionTitleLabel.setText("My Groups");
        configureInactivitySpinners();
        switchTo(indexPane);
    }

    private void configureInactivitySpinners() {
        if (inactivityThresholdSpinner != null) {
            inactivityThresholdSpinner.setValueFactory(new SpinnerValueFactory.IntegerSpinnerValueFactory(1, 365, 14));
        }
        if (inactivityGraceSpinner != null) {
            inactivityGraceSpinner.setValueFactory(new SpinnerValueFactory.IntegerSpinnerValueFactory(1, 90, 7));
        }
        if (inactivityBlacklistSpinner != null) {
            inactivityBlacklistSpinner.setValueFactory(new SpinnerValueFactory.IntegerSpinnerValueFactory(1, 365, 30));
        }
    }

    private void ensureMembersTableReady() {
        if (membersTableReady) {
            return;
        }

        roleCombo.setItems(FXCollections.observableArrayList("member", "lecturer", "admin"));
        configureRoleCombo(roleCombo);
        roleCombo.getSelectionModel().select("member");

        nameColumn.setCellValueFactory(new PropertyValueFactory<>("name"));
        emailColumn.setCellValueFactory(new PropertyValueFactory<>("email"));
        roleColumn.setCellValueFactory(new PropertyValueFactory<>("memberRole"));
        statusColumn.setCellValueFactory(new PropertyValueFactory<>("memberStatus"));
        warningsColumn.setCellValueFactory(cell ->
                new SimpleIntegerProperty(cell.getValue().getWarnings()));
        actionsColumn.setCellValueFactory(param -> null);

        membersTable.setColumnResizePolicy(TableView.UNCONSTRAINED_RESIZE_POLICY);
        membersTable.setFixedCellSize(40);
        membersTable.widthProperty().addListener((obs, oldWidth, newWidth) ->
                balanceMembersColumnWidths(newWidth.doubleValue()));
        VBox.setVgrow(membersTable, Priority.NEVER);
        setupMembersRowStyles();
        setupNameColumn();
        setupRoleColumn();
        setupStatusColumn();
        setupWarningsColumn();
        setupEmailColumn();
        setupActionsColumn();

        membersTableReady = true;
    }

    private void balanceMembersColumnWidths(double tableWidth) {
        if (tableWidth <= 0) {
            return;
        }

        boolean canManage = warningsColumn.isVisible();
        double[] weights = canManage ? MEMBER_COLUMN_WEIGHTS_MANAGE : MEMBER_COLUMN_WEIGHTS_VIEW;
        @SuppressWarnings("unchecked")
        TableColumn<GroupMember, ?>[] columns = new TableColumn[] {
                nameColumn, roleColumn, statusColumn, warningsColumn, emailColumn, actionsColumn
        };

        double weightTotal = 0;
        for (int i = 0; i < columns.length; i++) {
            if ((i == 3 || i == 5) && !canManage) {
                continue;
            }
            weightTotal += weights[i];
        }
        if (weightTotal <= 0) {
            return;
        }

        double usableWidth = Math.max(tableWidth - 2, 0);
        for (int i = 0; i < columns.length; i++) {
            if ((i == 3 || i == 5) && !canManage) {
                columns[i].setPrefWidth(0);
                columns[i].setMinWidth(0);
                columns[i].setMaxWidth(0);
                continue;
            }
            double width = usableWidth * (weights[i] / weightTotal);
            columns[i].setMaxWidth(Double.MAX_VALUE);
            columns[i].setPrefWidth(width);
            columns[i].setMinWidth(Math.max(columns[i].getMinWidth(), width * 0.45));
        }
    }

    private void refreshMembersTableLayout() {
        if (membersTable == null) {
            return;
        }
        Platform.runLater(() -> {
            balanceMembersColumnWidths(membersTable.getWidth());
            membersTable.refresh();
            membersTable.layout();
        });
    }

    /** Web: index() */
    @FXML
    public void index() {
        exploreMode = false;
        sectionTitleLabel.setText("My Groups");
        updateIndexHeader(false);
        switchTo(indexPane);
        updateTitle("Discussion Groups");
        refreshGroups();
    }

    /** Web: explore() */
    public void explore() {
        exploreMode = true;
        sectionTitleLabel.setText("Explore Groups");
        updateIndexHeader(true);
        switchTo(indexPane);
        updateTitle("Explore Groups");
        refreshExploreGroups();
    }

    /** Web: create() */
    public void create() {
        nameField.clear();
        descriptionField.clear();
        if (createJoinRulesField != null) {
            createJoinRulesField.clear();
        }
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

        Group group = groupService.createGroup(
                name,
                description,
                createJoinRulesField == null ? null : createJoinRulesField.getText()
        );
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
        int userId = groupService.getAvailableUsers(groupId).stream()
                .filter(user -> selected.equals(user.getName() + " — " + user.getEmail()))
                .map(ForumUser::getId)
                .findFirst()
                .orElse(-1);
        if (userId < 0) {
            showAlert(Alert.AlertType.WARNING, "Invalid selection", "Could not find the selected user.");
            return;
        }
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
            showEmptyState();
            return;
        }

        hideEmptyState();

        for (Group group : groups) {
            groupsGrid.getChildren().add(buildGroupCard(group));
        }
    }

    private void refreshExploreGroups() {
        List<Group> groups = groupService.getExploreGroups();
        groupsGrid.getChildren().clear();

        if (groups.isEmpty()) {
            showEmptyState();
            return;
        }

        hideEmptyState();

        for (Group group : groups) {
            groupsGrid.getChildren().add(buildExploreGroupCard(group));
        }
    }

    private VBox buildExploreGroupCard(Group group) {
        VBox card = buildGroupCard(group);
        card.setOnMouseClicked(null);

        Label actionLabel = new Label();
        actionLabel.getStyleClass().add("group-card-action");
        if (AppSession.getInstance().isSystemAdmin()) {
            actionLabel.setText("Open group");
            card.setOnMouseClicked(event -> {
                if (navigator != null) {
                    navigator.showGroup(group.getId());
                }
            });
        } else {
            String joinStatus = group.getJoinStatus() == null ? "none" : group.getJoinStatus();
            if ("pending".equalsIgnoreCase(joinStatus)) {
                actionLabel.setText("Pending approval");
            } else if ("blocked".equalsIgnoreCase(joinStatus)) {
                actionLabel.setText("Cannot join");
            } else {
                actionLabel.setText("Request to Join");
                card.setOnMouseClicked(event -> handleExploreJoinRequest(group));
            }
        }

        if (card.getChildren().size() >= 5 && card.getChildren().get(4) instanceof HBox footer) {
            footer.getChildren().set(footer.getChildren().size() - 1, actionLabel);
        }

        return card;
    }

    private void handleExploreJoinRequest(Group group) {
        boolean acceptedRules = true;
        if (group.hasJoinRules()) {
            Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
            alert.setTitle("Group rules — " + group.getName());
            alert.setHeaderText("Please read the rules below. You must agree before your join request can be sent to the group admin.");
            TextArea rulesArea = new TextArea(group.getJoinRules());
            rulesArea.setEditable(false);
            rulesArea.setWrapText(true);
            rulesArea.setPrefRowCount(10);
            rulesArea.setMaxWidth(Double.MAX_VALUE);
            alert.getDialogPane().setContent(rulesArea);
            ButtonType accept = new ButtonType("Agree & Request to Join", ButtonBar.ButtonData.OK_DONE);
            alert.getButtonTypes().setAll(ButtonType.CANCEL, accept);
            Optional<ButtonType> choice = alert.showAndWait();
            if (choice.isEmpty() || choice.get() != accept) {
                return;
            }
        }

        if (groupService.requestJoinGroup(group.getId(), acceptedRules)) {
            showAlert(Alert.AlertType.INFORMATION, "Request sent",
                    "Your request to join \"" + group.getName() + "\" was sent for admin approval.");
            refreshExploreGroups();
        } else {
            showAlert(Alert.AlertType.ERROR, "Request failed", "Could not send your join request.");
        }
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

        Label open = new Label("Open");
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
        int memberCount = membersTable.getItems().size();
        if (membersCountLabel != null) {
            membersCountLabel.setText("(" + memberCount + ")");
        }
        membersTable.setPrefHeight(Math.min(400, Math.max(120, 42 + memberCount * 40)));
        refreshMembersTableLayout();
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

        loadPendingJoinRequests(canManage);
        loadPostReports(canManage);
    }

    @FXML
    public void onAdminStatistics(ActionEvent event) {
        if (navigator != null && groupId > 0) {
            navigator.showGroupStatistics(groupId);
        }
    }

    @FXML
    public void onAdminParticipation(ActionEvent event) {
        if (navigator != null && groupId > 0) {
            navigator.showParticipationForGroup(groupId);
        }
    }

    @FXML
    public void onAdminEdit(ActionEvent event) {
        edit();
    }

    public void edit() {
        Group group = groupService.getGroup(groupId).orElse(null);
        if (group == null || !groupService.canManageGroup(groupId)) {
            showAlert(Alert.AlertType.ERROR, "Access denied", "You cannot edit this group.");
            return;
        }

        editNameField.setText(group.getName());
        editDescField.setText(group.getDescription());
        editJoinRulesField.setText(group.getJoinRules() == null ? "" : group.getJoinRules());
        inactivityEnabledCheck.setSelected(group.isInactivityMonitoringEnabled());
        inactivityThresholdSpinner.getValueFactory().setValue(group.getInactivityThresholdDays());
        inactivityGraceSpinner.getValueFactory().setValue(group.getInactivityGraceDays());
        inactivityBlacklistSpinner.getValueFactory().setValue(group.getInactivityBlacklistDays());
        switchTo(editPane);
        updateTitle("Edit Group");
    }

    @FXML
    public void onSaveEdit(ActionEvent event) {
        saveEdit();
    }

    public void saveEdit() {
        String name = editNameField.getText() == null ? "" : editNameField.getText().trim();
        String description = editDescField.getText() == null ? "" : editDescField.getText().trim();
        if (name.isBlank() || description.isBlank()) {
            showAlert(Alert.AlertType.WARNING, "Missing fields", "Group name and description are required.");
            return;
        }

        try {
            groupService.updateGroup(
                    groupId,
                    name,
                    description,
                    editJoinRulesField.getText(),
                    inactivityEnabledCheck.isSelected(),
                    inactivityThresholdSpinner.getValue(),
                    inactivityGraceSpinner.getValue(),
                    inactivityBlacklistSpinner.getValue()
            );
            showAlert(Alert.AlertType.INFORMATION, "Saved", "Group settings updated.");
            show(groupId);
        } catch (IllegalStateException ex) {
            showAlert(Alert.AlertType.ERROR, "Update failed", ex.getMessage());
        }
    }

    @FXML
    public void onCancelEdit(ActionEvent event) {
        show(groupId);
    }

    private void ensureJoinRequestsTableReady() {
        if (joinRequestsTableReady) {
            return;
        }

        joinNameColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue() == null ? "" : cell.getValue().name()));
        joinEmailColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue() == null ? "" : cell.getValue().email()));
        joinActionsColumn.setCellFactory(column -> new TableCell<>() {
            private final HBox actions = new HBox(4);

            {
                actions.setAlignment(Pos.CENTER_LEFT);
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                actions.getChildren().clear();
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    return;
                }

                PendingJoinRequest request = getTableRow().getItem();
                actions.getChildren().add(memberActionButton("Approve", "btn-success", () -> {
                    try {
                        groupService.approveJoinRequest(groupId, request.userId());
                        loadGroup();
                    } catch (IllegalStateException ex) {
                        showAlert(Alert.AlertType.ERROR, "Approve failed", ex.getMessage());
                    }
                }));
                actions.getChildren().add(memberActionButton("Decline", "btn-outline-danger", () -> {
                    try {
                        groupService.rejectJoinRequest(groupId, request.userId());
                        loadGroup();
                    } catch (IllegalStateException ex) {
                        showAlert(Alert.AlertType.ERROR, "Decline failed", ex.getMessage());
                    }
                }));
                setGraphic(actions);
            }
        });

        joinRequestsTableReady = true;
    }

    private void loadPendingJoinRequests(boolean canManage) {
        ensureJoinRequestsTableReady();
        if (!canManage) {
            pendingJoinBox.setVisible(false);
            pendingJoinBox.setManaged(false);
            return;
        }

        List<PendingJoinRequest> requests = groupService.getPendingJoinRequests(groupId);
        if (requests.isEmpty()) {
            pendingJoinBox.setVisible(false);
            pendingJoinBox.setManaged(false);
            return;
        }

        pendingJoinBox.setVisible(true);
        pendingJoinBox.setManaged(true);
        pendingJoinTable.setItems(FXCollections.observableArrayList(requests));
        pendingJoinTable.setPrefHeight(Math.min(200, Math.max(80, 42 + requests.size() * 40)));
    }

    private void ensurePostReportsTableReady() {
        if (postReportsTableReady) {
            return;
        }

        reportTopicColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue() == null ? "" : cell.getValue().topicTitle()));
        reportContentColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue() == null ? "" : truncate(cell.getValue().postContent(), 120)));
        reportAuthorColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue() == null ? "" : cell.getValue().authorName()));
        reportReporterColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue() == null ? "" : cell.getValue().reporterName()));
        reportReasonColumn.setCellValueFactory(cell ->
                new SimpleStringProperty(cell.getValue() == null ? "" : cell.getValue().reason()));
        reportActionsColumn.setCellFactory(column -> new TableCell<>() {
            private final HBox actions = new HBox(4);

            {
                actions.setAlignment(Pos.CENTER_LEFT);
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                actions.getChildren().clear();
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    return;
                }

                PostReport report = getTableRow().getItem();
                actions.getChildren().add(memberActionButton("Restore", "btn-outline-primary", () -> {
                    try {
                        groupService.restorePostReport(groupId, report.id());
                        loadGroup();
                    } catch (IllegalStateException ex) {
                        showAlert(Alert.AlertType.ERROR, "Restore failed", ex.getMessage());
                    }
                }));
                actions.getChildren().add(memberActionButton("Delete", "btn-outline-danger", () -> {
                    Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
                    confirm.setTitle("Delete message");
                    confirm.setHeaderText("Permanently delete this message?");
                    confirm.setContentText("This cannot be undone.");
                    confirm.showAndWait().ifPresent(choice -> {
                        if (choice == ButtonType.OK) {
                            try {
                                groupService.deletePostReport(groupId, report.id());
                                loadGroup();
                            } catch (IllegalStateException ex) {
                                showAlert(Alert.AlertType.ERROR, "Delete failed", ex.getMessage());
                            }
                        }
                    });
                }));
                setGraphic(actions);
            }
        });

        postReportsTableReady = true;
    }

    private void loadPostReports(boolean canManage) {
        ensurePostReportsTableReady();
        if (!canManage) {
            postReportsBox.setVisible(false);
            postReportsBox.setManaged(false);
            return;
        }

        new Thread(() -> {
            List<PostReport> reports = groupService.getPostReports(groupId);
            Platform.runLater(() -> {
                if (reports.isEmpty()) {
                    postReportsBox.setVisible(false);
                    postReportsBox.setManaged(false);
                    return;
                }

                postReportsBox.setVisible(true);
                postReportsBox.setManaged(true);
                postReportsCountLabel.setText(reports.size() + " pending");
                postReportsTable.setItems(FXCollections.observableArrayList(reports));
                postReportsTable.setPrefHeight(Math.min(280, Math.max(120, 42 + reports.size() * 40)));
            });
        }).start();
    }

    private void setupAddMemberForm() {
        userCombo.getItems().clear();
        groupService.getAvailableUsers(groupId).forEach(user ->
                userCombo.getItems().add(user.getName() + " — " + user.getEmail())
        );
        if (roleCombo.getSelectionModel().getSelectedItem() == null) {
            roleCombo.getSelectionModel().select("member");
        }
    }

    private void setupMembersRowStyles() {
        membersTable.setRowFactory(tableView -> {
            TableRow<GroupMember> row = new TableRow<>();
            row.itemProperty().addListener((obs, oldMember, member) -> {
                row.getStyleClass().removeAll("member-row-blocked", "member-row-suspended");
                if (member != null) {
                    if ("Blocked".equalsIgnoreCase(member.getMemberStatus())) {
                        row.getStyleClass().add("member-row-blocked");
                    } else if ("Suspended".equalsIgnoreCase(member.getMemberStatus())) {
                        row.getStyleClass().add("member-row-suspended");
                    }
                }
            });
            return row;
        });
    }

    private void setupNameColumn() {
        nameColumn.setCellFactory(column -> new TableCell<>() {
            private final HBox content = new HBox(6);
            private final Label nameLabel = new Label();
            private final Label creatorBadge = new Label("Creator");
            private final Label youLabel = new Label("(You)");

            {
                content.setAlignment(Pos.CENTER_LEFT);
                creatorBadge.getStyleClass().add("member-creator-badge");
                youLabel.getStyleClass().add("member-you-label");
                creatorBadge.setManaged(false);
                creatorBadge.setVisible(false);
                youLabel.setManaged(false);
                youLabel.setVisible(false);
                content.getChildren().addAll(nameLabel, creatorBadge, youLabel);
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
                nameLabel.setText(member.getName());
                creatorBadge.setVisible(member.isCreator());
                creatorBadge.setManaged(member.isCreator());

                ForumUser currentUser = AppSession.getInstance().getCurrentUser();
                boolean isSelf = currentUser != null && member.getUserId() == currentUser.getId();
                youLabel.setVisible(isSelf);
                youLabel.setManaged(isSelf);

                setGraphic(content);
                setText(null);
            }
        });
    }

    private void configureRoleCombo(ComboBox<String> combo) {
        combo.setCellFactory(listView -> roleComboCell());
        combo.setButtonCell(roleComboCell());
    }

    private ListCell<String> roleComboCell() {
        return new ListCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                setText(empty || item == null ? null : capitalize(item));
            }
        };
    }

    private Label roleBadge(String role) {
        Label badge = new Label(capitalize(role));
        badge.getStyleClass().add("member-role-badge");
        if ("admin".equalsIgnoreCase(role)) {
            badge.getStyleClass().add("member-role-admin");
        } else if ("lecturer".equalsIgnoreCase(role)) {
            badge.getStyleClass().add("member-role-lecturer");
        } else {
            badge.getStyleClass().add("member-role-member");
        }
        return badge;
    }

    private void setupRoleColumn() {
        roleColumn.setCellFactory(column -> new TableCell<GroupMember, String>() {
            private final ComboBox<String> roleSelect = new ComboBox<>(
                    FXCollections.observableArrayList("member", "lecturer", "admin"));

            {
                roleSelect.getStyleClass().add("member-role-combo");
                configureRoleCombo(roleSelect);
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
                    setGraphic(roleBadge(member.getMemberRole()));
                    setText(null);
                }
            }
        });
    }

    private void setupStatusColumn() {
        statusColumn.setCellFactory(column -> new TableCell<GroupMember, String>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }

                Label badge = new Label(item);
                badge.getStyleClass().add("member-status-badge");
                if ("Active".equalsIgnoreCase(item)) {
                    badge.getStyleClass().add("member-status-active");
                } else if ("Suspended".equalsIgnoreCase(item)) {
                    badge.getStyleClass().add("member-status-suspended");
                } else {
                    badge.getStyleClass().add("member-status-blocked");
                }
                setGraphic(badge);
                setText(null);
            }
        });
    }

    private void setupWarningsColumn() {
        warningsColumn.setCellFactory(column -> new TableCell<GroupMember, Number>() {
            @Override
            protected void updateItem(Number item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }

                int warnings = item.intValue();
                Label badge = new Label(warnings + "/2");
                badge.getStyleClass().add("member-warnings-badge");
                if (warnings >= 2) {
                    badge.getStyleClass().add("member-warnings-danger");
                } else if (warnings == 1) {
                    badge.getStyleClass().add("member-warnings-warn");
                } else {
                    badge.getStyleClass().add("member-warnings-none");
                }
                setGraphic(badge);
                setText(null);
            }
        });
    }

    private void setupEmailColumn() {
        emailColumn.setCellFactory(column -> new TableCell<GroupMember, String>() {
            private final Label emailLabel = new Label();

            {
                emailLabel.getStyleClass().add("member-email-cell");
            }

            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }
                emailLabel.setText(item);
                setGraphic(emailLabel);
                setText(null);
            }
        });
    }

    private void setupActionsColumn() {
        actionsColumn.setCellFactory(column -> new TableCell<GroupMember, Void>() {
            private final HBox actions = new HBox(4);
            private final Label mutedLabel = new Label();

            {
                actions.setAlignment(Pos.CENTER_LEFT);
                mutedLabel.getStyleClass().add("member-actions-muted");
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                actions.getChildren().clear();
                if (empty || getTableRow() == null || getTableRow().getItem() == null) {
                    setGraphic(null);
                    return;
                }

                ForumUser currentUser = AppSession.getInstance().getCurrentUser();
                int currentUserId = currentUser == null ? -1 : currentUser.getId();
                GroupMember member = getTableRow().getItem();
                if (member.getUserId() == currentUserId) {
                    mutedLabel.setText("You");
                    setGraphic(mutedLabel);
                    return;
                }
                if ("admin".equalsIgnoreCase(member.getMemberRole())
                        && !AppSession.getInstance().isSystemAdmin()) {
                    mutedLabel.setText("Protected");
                    setGraphic(mutedLabel);
                    return;
                }

                if ("Active".equalsIgnoreCase(member.getMemberStatus())) {
                    actions.getChildren().add(memberActionButton("Warn", "btn-warning", () -> {
                        groupService.warnMember(groupId, member.getUserId());
                        loadGroup();
                    }));
                    actions.getChildren().add(memberActionButton("Suspend", "btn-outline-warning", () -> {
                        groupService.suspendMember(groupId, member.getUserId());
                        loadGroup();
                    }));
                    actions.getChildren().add(memberActionButton("Block", "btn-outline-danger", () -> {
                        groupService.blockMember(groupId, member.getUserId());
                        loadGroup();
                    }));
                } else {
                    actions.getChildren().add(memberActionButton("Reinstate", "btn-success", () -> {
                        groupService.reinstateMember(groupId, member.getUserId());
                        loadGroup();
                    }));
                }

                actions.getChildren().add(memberActionButton("Remove", "btn-outline-secondary", () -> {
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

    private Button memberActionButton(String text, String styleClass, Runnable action) {
        Button button = new Button(text);
        button.getStyleClass().addAll("btn-sm", styleClass);
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
        for (VBox child : List.of(indexPane, showPane, createPane, editPane)) {
            boolean active = child == pane;
            child.setVisible(active);
            child.setManaged(active);
        }
        if (contentScroll != null) {
            contentScroll.setVvalue(0);
        }
    }

    private void updateIndexHeader(boolean explore) {
        if (pageTitleLabel == null || pageSubtitleLabel == null) {
            return;
        }
        if (explore) {
            pageTitleLabel.setText("🧭  Explore Groups");
            pageSubtitleLabel.setText(
                    "Discover discussion groups you are not in yet. Request to join and a group admin will review your request.");
        } else {
            pageTitleLabel.setText("👥  Discussion Groups");
            pageSubtitleLabel.setText(
                    "Create groups, invite members, and assign admin or lecturer roles — just like WhatsApp.");
        }
    }

    private void showEmptyState() {
        if (emptyStateBox == null) {
            return;
        }
        emptyStateBox.setVisible(true);
        emptyStateBox.setManaged(true);
        if (exploreMode) {
            if (emptyStateIconLabel != null) {
                emptyStateIconLabel.setText("🧭");
            }
            if (emptyStateMessageLabel != null) {
                emptyStateMessageLabel.setText(
                        "There are no other groups to explore right now. You may already be in every available group.");
            }
            if (emptyStateActionButton != null) {
                emptyStateActionButton.setText("Back to My Groups");
                emptyStateActionButton.setOnAction(event -> {
                    if (navigator != null) {
                        navigator.showGroups();
                    } else {
                        index();
                    }
                });
            }
        } else {
            if (emptyStateIconLabel != null) {
                emptyStateIconLabel.setText("👥");
            }
            if (emptyStateMessageLabel != null) {
                emptyStateMessageLabel.setText("No groups yet. Create one and invite others to join.");
            }
            if (emptyStateActionButton != null) {
                emptyStateActionButton.setText("Create Group");
                emptyStateActionButton.setOnAction(this::onCreate);
            }
        }
    }

    private void hideEmptyState() {
        if (emptyStateBox == null) {
            return;
        }
        emptyStateBox.setVisible(false);
        emptyStateBox.setManaged(false);
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
