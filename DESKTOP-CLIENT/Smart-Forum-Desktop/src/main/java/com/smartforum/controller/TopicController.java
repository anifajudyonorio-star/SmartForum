package com.smartforum.controller;

import com.smartforum.api.ApiClient;
import com.smartforum.model.Group;
import com.smartforum.model.GroupMember;
import com.smartforum.model.Post;
import com.smartforum.model.Topic;
import com.smartforum.service.AppSession;
import com.smartforum.service.GroupService;
import com.smartforum.service.SyncStatusService;
import com.smartforum.service.TopicService;
import javafx.animation.PauseTransition;
import javafx.application.Platform;
import javafx.beans.binding.Bindings;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Node;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ButtonType;
import javafx.scene.control.CheckBox;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import javafx.scene.control.TextInputDialog;
import javafx.scene.control.Tooltip;
import com.smartforum.util.ApiConfig;
import javafx.scene.control.ContextMenu;
import javafx.scene.control.MenuItem;
import javafx.scene.input.Clipboard;
import javafx.scene.input.ClipboardContent;
import javafx.scene.input.KeyCode;
import javafx.scene.input.MouseEvent;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;

import com.smartforum.util.NetworkMonitor;
import com.smartforum.util.TopicPdfExporter;
import javafx.stage.FileChooser;
import javafx.stage.Window;

import java.io.File;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.awt.Desktop;
import java.net.URI;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.*;
import java.util.function.Consumer;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

import javafx.util.Duration;

/**
 * Mirrors web {@code TopicController} for topics; delegates posts to {@link PostController}.
 */
public class TopicController {

    @FXML private ScrollPane contentScroll;
    @FXML private VBox createPane;
    @FXML private Label createGroupNameLabel;
    @FXML private Label createGroupDescLabel;
    @FXML private TextField titleField;
    @FXML private TextArea descriptionField;

    @FXML private VBox showPane;
    @FXML private Label chatAvatarLabel;
    @FXML private Label chatTitleLabel;
    @FXML private Label chatSubtitleLabel;
    @FXML private ScrollPane messagesScroll;
    @FXML private VBox messagesBox;
    @FXML private VBox composerBox;
    @FXML private HBox replyBar;
    @FXML private Label replyUserLabel;
    @FXML private Label replyTextLabel;
    @FXML private VBox excludePanel;
    @FXML private FlowPane excludeList;
    @FXML private TextArea messageInput;
    @FXML private Button excludeToggleBtn;
    @FXML private Label readOnlyLabel;

    @FXML private VBox editPane;
    @FXML private Label editTopicTitleLabel;
    @FXML private Label editTopicSubtitleLabel;
    @FXML private TextArea editContentField;
    @FXML private VBox editExcludeSection;
    @FXML private FlowPane editExcludeList;

    private int groupId;
    private int topicId;
    private int editingPostId;
    private Integer replyToPostId;
    private ShellNavigator navigator;
    private Consumer<String> pageTitleUpdater;
    private Consumer<Boolean> chatLayoutCallback;
    private Region rootNode;

    private final Map<Integer, CheckBox> excludeCheckboxes = new HashMap<>();
    private final Map<Integer, CheckBox> editExcludeCheckboxes = new HashMap<>();
    private final Map<Integer, javafx.scene.Node> messageNodesById = new HashMap<>();

    private List<Post> currentPosts = List.of();
    private String currentGroupName = "";
    private final List<Post> pendingPosts = new ArrayList<>();

    // Maps pending post ID -> tick Label, so we can update in-place after sync
    private final Map<Integer, Label> pendingTickLabels = new HashMap<>();

    private ScheduledExecutorService messagePoller;
    private volatile int pollingTopicId;

    private final PostController postController = new PostController();
    private final GroupService groupService = GroupService.getInstance();
    private final TopicService topicService = TopicService.getInstance();

    public Region getRootNode() {
        return rootNode;
    }

    public void setRootNode(Region rootNode) {
        this.rootNode = rootNode;
    }

    public int getGroupId() {
        return groupId;
    }

    public int getTopicId() {
        return topicId;
    }

    public boolean isCreating() {
        return createPane != null && createPane.isVisible();
    }

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    public void setPageTitleUpdater(Consumer<String> pageTitleUpdater) {
        this.pageTitleUpdater = pageTitleUpdater;
    }

    public void setChatLayoutCallback(Consumer<Boolean> chatLayoutCallback) {
        this.chatLayoutCallback = chatLayoutCallback;
    }

    @FXML
    private void initialize() {
        if (messageInput != null) {
            messageInput.setOnKeyPressed(event -> {
                if (event.getCode() == KeyCode.ENTER && !event.isShiftDown()) {
                    event.consume();
                    onSendMessage(null);
                }
            });
        }
        if (messagesBox != null && messagesScroll != null) {
            messagesBox.minHeightProperty().bind(Bindings.createDoubleBinding(
                    () -> Math.max(0, messagesScroll.getViewportBounds().getHeight()),
                    messagesScroll.viewportBoundsProperty(),
                    messagesScroll.heightProperty()));
        }
    }

    /** Web: TopicController@create */
    public void create(int groupId) {
        if (!groupService.canParticipateInGroup(groupId)) {
            showAlert(Alert.AlertType.ERROR, "Access denied",
                    "You must be an active member of this group to create topics.");
            return;
        }

        Group group = groupService.getGroup(groupId).orElse(null);
        if (group == null) {
            return;
        }

        this.groupId = groupId;
        titleField.clear();
        descriptionField.clear();
        createGroupNameLabel.setText("Create Topic in " + group.getName());
        createGroupDescLabel.setText(group.getDescription());
        stopMessagePolling();
        switchTo(createPane);
        updateTitle("Create Topic in " + group.getName());
        Platform.runLater(() -> {
            if (contentScroll != null) {
                contentScroll.setVvalue(0);
            }
            if (titleField != null) {
                titleField.requestFocus();
            }
        });
    }

    /** Web: TopicController@store */
    public void store() {
        String title = titleField.getText() == null ? "" : titleField.getText().trim();
        String description = descriptionField.getText() == null ? "" : descriptionField.getText().trim();

        if (title.isBlank() || description.isBlank()) {
            showAlert(Alert.AlertType.WARNING, "Missing fields", "Topic title and description are required.");
            return;
        }

        topicService.createTopic(groupId, title, description);
        if (navigator != null) {
            navigator.showGroup(groupId);
        }
    }

    /** Web: TopicController@show */
    public void show(int topicId) {
        Topic topic = topicService.getTopic(topicId).orElse(null);
        if (topic == null || !topicService.canViewTopic(topicId)) {
            showAlert(Alert.AlertType.ERROR, "Access denied",
                    "You must be a member of this group to view this discussion.");
            if (navigator != null) {
                navigator.showGroups();
            }
            return;
        }

        this.topicId = topicId;
        this.groupId = topic.getGroupId();
        editingPostId = 0;
        replyToPostId = null;
        hideReplyBar();
        hideExcludePanel();
        clearExcludeSelections(excludeCheckboxes);

        // Update tick labels in-place when sync succeeds, then reload to get real IDs
        SyncStatusService.getInstance().setOnSyncSuccess(() -> {
            if (this.topicId != topicId) return;
            int reloadTopicId = topicService.resolveTopicId(topicId);
            this.topicId = reloadTopicId;
            if (!pendingTickLabels.isEmpty()) {
                // Immediately flip all visible pending ticks to blue double tick
                for (Label tickLabel : pendingTickLabels.values()) {
                    tickLabel.setText("\u2713\u2713");
                    tickLabel.getStyleClass().remove("msg-tick-pending");
                    tickLabel.getStyleClass().add("msg-tick-sent");
                }
                pendingTickLabels.clear();
            }
            pendingPosts.clear();
            // Reload in background to replace local IDs with real server IDs
            new Thread(() -> {
                List<Post> posts = postController.loadPosts(reloadTopicId);
                Platform.runLater(() -> {
                    currentPosts = posts;
                    Group g = groupService.getGroup(groupId).orElse(null);
                    Optional<Topic> refreshed = topicService.getTopic(reloadTopicId);
                    loadMessages(refreshed.orElse(topic), g != null ? g.getName() : "Group");
                    scrollMessagesToBottom();
                });
            }, "sync-reload").start();
        });
        Group group = groupService.getGroup(groupId).orElse(null);
        String groupName = group != null ? group.getName() : "Group";

        chatAvatarLabel.setText(topic.getInitials());
        chatTitleLabel.setText(topic.getTitle());
        updateTitle(topic.getTitle());

        boolean canPost = postController.canParticipate(topicId);
        configureComposer(canPost);
        loadMessages(topic, groupName);
        topicService.recordTopicView(topicId);
        switchTo(showPane);
        scrollMessagesToBottom();
    }

    @FXML
    public void onStore(ActionEvent event) {
        store();
    }

    @FXML
    public void onExportPdf(ActionEvent event) {
        String safeTitle = chatTitleLabel.getText() == null ? "discussion" : chatTitleLabel.getText();
        String defaultName = "discussion-" + safeTitle.replaceAll("[^a-zA-Z0-9]+", "-").toLowerCase() + ".pdf";

        FileChooser chooser = new FileChooser();
        chooser.setTitle("Export discussion as PDF");
        chooser.setInitialFileName(defaultName);
        chooser.getExtensionFilters().add(new FileChooser.ExtensionFilter("PDF files", "*.pdf"));

        Window window = messagesBox.getScene() != null ? messagesBox.getScene().getWindow() : null;
        File destination = chooser.showSaveDialog(window);
        if (destination == null) {
            return;
        }

        try {
            TopicPdfExporter.export(destination, safeTitle, currentGroupName, currentPosts);
            showAlert(Alert.AlertType.INFORMATION, "Export complete",
                    "Discussion exported to " + destination.getName());
        } catch (Exception ex) {
            showAlert(Alert.AlertType.ERROR, "Export failed",
                    ex.getMessage() == null ? "Could not create PDF." : ex.getMessage());
        }
    }

    @FXML
    public void onBackToGroup(ActionEvent event) {
        if (navigator != null) {
            navigator.reopenGroup(groupId);
        }
    }

    /** Web: PostController@store */
    @FXML
    public void onSendMessage(ActionEvent event) {
        if (!postController.canParticipate(topicId)) {
            showAlert(Alert.AlertType.WARNING, "Cannot post",
                    "You must be an active member of this group to post.");
            return;
        }

        String content = messageInput.getText() == null ? "" : messageInput.getText().trim();
        if (content.isBlank()) {
            return;
        }

        try {
            Post post = postController.store(topicId, content, replyToPostId, collectExcluded(excludeCheckboxes));
            messageInput.clear();
            onCancelReply(null);
            hideExcludePanel();
            clearExcludeSelections(excludeCheckboxes);
            // Track pending post locally if it was queued offline
            if (post != null && post.getId() >= 1000) {
                pendingPosts.add(post);
            }
            refreshChat();
        } catch (IllegalArgumentException | IllegalStateException ex) {
            showAlert(Alert.AlertType.WARNING, "Cannot send", ex.getMessage());
        }
    }

    @FXML
    public void onCancelReply(ActionEvent event) {
        replyToPostId = null;
        hideReplyBar();
    }

    @FXML
    public void onToggleExclude(ActionEvent event) {
        boolean show = !excludePanel.isVisible();
        excludePanel.setVisible(show);
        excludePanel.setManaged(show);
    }

    @FXML
    public void onUpdatePost(ActionEvent event) {
        String content = editContentField.getText() == null ? "" : editContentField.getText().trim();
        if (content.isBlank()) {
            showAlert(Alert.AlertType.WARNING, "Missing content", "Message content is required.");
            return;
        }

        try {
            postController.update(editingPostId, content, collectExcluded(editExcludeCheckboxes));
            Topic topic = topicService.getTopic(topicId).orElseThrow();
            Group group = groupService.getGroup(groupId).orElse(null);
            loadMessages(topic, group != null ? group.getName() : "Group");
            switchTo(showPane);
            updateTitle(topic.getTitle());
            scrollMessagesToBottom();
        } catch (IllegalArgumentException | IllegalStateException ex) {
            showAlert(Alert.AlertType.WARNING, "Cannot update", ex.getMessage());
        }
    }

    @FXML
    public void onCancelEdit(ActionEvent event) {
        editingPostId = 0;
        Topic topic = topicService.getTopic(topicId).orElse(null);
        if (topic != null) {
            switchTo(showPane);
            updateTitle(topic.getTitle());
            scrollMessagesToBottom();
        }
    }

    private void configureComposer(boolean canPost) {
        composerBox.setVisible(true);
        composerBox.setManaged(true);
        messageInput.setDisable(!canPost);
        excludeToggleBtn.setVisible(canPost && !topicService.getMembersForExclude(topicId).isEmpty());
        excludeToggleBtn.setManaged(excludeToggleBtn.isVisible());

        readOnlyLabel.setVisible(!canPost);
        readOnlyLabel.setManaged(!canPost);

        if (canPost) {
            setupExcludePanel();
        } else {
            excludeList.getChildren().clear();
            excludeCheckboxes.clear();
        }
    }

    private void setupExcludePanel() {
        populateExcludeList(excludeList, excludeCheckboxes, Set.of());
    }

    private void openEditPost(int postId) {
        Optional<PostController.PostEditContext> contextOpt = postController.edit(postId);
        if (contextOpt.isEmpty()) {
            showAlert(Alert.AlertType.ERROR, "Access denied", "You cannot edit this message.");
            return;
        }

        PostController.PostEditContext context = contextOpt.get();
        editingPostId = postId;
        editTopicTitleLabel.setText("Edit Message");
        editTopicSubtitleLabel.setText(context.topic().getTitle());
        editContentField.setText(context.post().getContent());

        Set<Integer> excluded = new HashSet<>(context.excludedUserIds());
        if (context.groupMembers().isEmpty()) {
            editExcludeSection.setVisible(false);
            editExcludeSection.setManaged(false);
            editExcludeList.getChildren().clear();
            editExcludeCheckboxes.clear();
        } else {
            editExcludeSection.setVisible(true);
            editExcludeSection.setManaged(true);
            populateExcludeList(editExcludeList, editExcludeCheckboxes, excluded);
        }

        switchTo(editPane);
        updateTitle("Edit Message");
        Platform.runLater(() -> editContentField.requestFocus());
    }

    private void confirmDeletePost(int postId) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Delete message");
        alert.setHeaderText(null);
        alert.setContentText("Delete this message?");
        alert.getButtonTypes().setAll(ButtonType.YES, ButtonType.NO);

        Optional<ButtonType> result = alert.showAndWait();
        if (result.isEmpty() || result.get() != ButtonType.YES) {
            return;
        }

        try {
            postController.destroy(postId);
            refreshChat();
        } catch (IllegalStateException ex) {
            showAlert(Alert.AlertType.WARNING, "Cannot delete", ex.getMessage());
        }
    }

    private void reportPost(Post post) {
        TextInputDialog dialog = new TextInputDialog();
        dialog.setTitle("Report Message");
        dialog.setHeaderText("Report as irrelevant");
        dialog.setContentText("Optional reason:");

        Optional<String> reason = dialog.showAndWait();
        if (reason.isEmpty()) {
            return;
        }

        ApiClient.MutationResult result = ApiClient.reportPost(post.getId(), reason.orElse(""));
        if (!result.success()) {
            showAlert(Alert.AlertType.WARNING, "Report failed", result.message());
            return;
        }

        Node row = messageNodesById.remove(post.getId());
        if (row != null) {
            messagesBox.getChildren().remove(row);
        }

        showAlert(Alert.AlertType.INFORMATION, "Reported", result.message());
    }

    private void sharePost(Post post, Button anchor) {
        Topic topic = topicService.getTopic(topicId).orElse(null);
        if (topic == null) {
            return;
        }

        String shareUrl = ApiConfig.baseUrl() + "/topics/" + topicId + "#msg-" + post.getId();
        String author = post.getAuthorName() != null ? post.getAuthorName() : "Member";
        String content = post.getContent() != null ? post.getContent() : "";
        String excerpt = content.length() > 180 ? content.substring(0, 180) + "…" : content;
        String text = author + " in \"" + topic.getTitle() + "\": " + excerpt + "\n" + shareUrl;

        ContextMenu menu = new ContextMenu();
        menu.getItems().add(shareMenuItem("WhatsApp", whatsAppShareUrl(text)));
        menu.getItems().add(shareMenuItem("Facebook", facebookShareUrl(shareUrl, text)));
        menu.getItems().add(shareMenuItem("X / Twitter", twitterShareUrl(text)));
        menu.getItems().add(shareMenuItem("LinkedIn", linkedInShareUrl(shareUrl)));
        menu.getItems().add(shareMenuItem("Telegram", telegramShareUrl(shareUrl, text)));
        menu.getItems().add(copyShareLinkItem(text));
        menu.show(anchor, javafx.geometry.Side.TOP, 0, -4);
    }

    private MenuItem shareMenuItem(String label, String url) {
        MenuItem item = new MenuItem(label);
        item.setOnAction(event -> openExternalUrl(url));
        return item;
    }

    private MenuItem copyShareLinkItem(String text) {
        MenuItem item = new MenuItem("Copy link");
        item.setOnAction(event -> {
            ClipboardContent content = new ClipboardContent();
            content.putString(text);
            Clipboard.getSystemClipboard().setContent(content);
        });
        return item;
    }

    private void openExternalUrl(String url) {
        try {
            if (Desktop.isDesktopSupported()) {
                Desktop.getDesktop().browse(new URI(url));
            }
        } catch (Exception ex) {
            showAlert(Alert.AlertType.WARNING, "Share failed", ex.getMessage());
        }
    }

    private String encode(String value) {
        return URLEncoder.encode(value, StandardCharsets.UTF_8);
    }

    private String whatsAppShareUrl(String text) {
        return "https://wa.me/?text=" + encode(text);
    }

    private String facebookShareUrl(String shareUrl, String text) {
        return "https://www.facebook.com/sharer/sharer.php?u=" + encode(shareUrl) + "&quote=" + encode(text);
    }

    private String twitterShareUrl(String text) {
        return "https://twitter.com/intent/tweet?text=" + encode(text);
    }

    private String linkedInShareUrl(String shareUrl) {
        return "https://www.linkedin.com/sharing/share-offsite/?url=" + encode(shareUrl);
    }

    private String telegramShareUrl(String shareUrl, String text) {
        return "https://t.me/share/url?url=" + encode(shareUrl) + "&text=" + encode(text);
    }

    private void populateExcludeList(FlowPane container, Map<Integer, CheckBox> target, Set<Integer> preselected) {
        container.getChildren().clear();
        target.clear();

        for (GroupMember member : topicService.getMembersForExclude(topicId)) {
            CheckBox checkbox = new CheckBox(member.getName());
            checkbox.getStyleClass().add("wa-exclude-option");
            checkbox.setSelected(preselected.contains(member.getUserId()));
            target.put(member.getUserId(), checkbox);
            container.getChildren().add(checkbox);
        }
    }

    private List<Integer> collectExcluded(Map<Integer, CheckBox> source) {
        List<Integer> excluded = new ArrayList<>();
        source.forEach((userId, checkbox) -> {
            if (checkbox.isSelected()) {
                excluded.add(userId);
            }
        });
        return excluded;
    }

    private void clearExcludeSelections(Map<Integer, CheckBox> source) {
        source.values().forEach(box -> box.setSelected(false));
    }

    private void refreshChat() {
        Topic topic = topicService.getTopic(topicId).orElseThrow();
        Group group = groupService.getGroup(groupId).orElse(null);
        loadMessages(topic, group != null ? group.getName() : "Group");
        scrollMessagesToBottom();
    }

    private void startMessagePolling() {
        stopMessagePolling();
        pollingTopicId = topicId;
        messagePoller = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r, "topic-msg-poll");
            t.setDaemon(true);
            return t;
        });
        messagePoller.scheduleAtFixedRate(() -> {
            if (pollingTopicId <= 0 || !NetworkMonitor.isOnline()) {
                return;
            }
            if (!pendingPosts.isEmpty()) {
                return;
            }
            int pollTopicId = topicService.resolveTopicId(pollingTopicId);
            List<Post> fresh = postController.loadPosts(pollTopicId);
            Platform.runLater(() -> {
                if (pollingTopicId <= 0 || !showPane.isVisible()) {
                    return;
                }
                if (postsChanged(currentPosts, fresh)) {
                    Topic topic = topicService.getTopic(topicId).orElse(null);
                    if (topic == null) {
                        return;
                    }
                    Group group = groupService.getGroup(groupId).orElse(null);
                    loadMessages(topic, group != null ? group.getName() : "Group");
                    scrollMessagesToBottom();
                }
            });
        }, 5, 5, TimeUnit.SECONDS);
    }

    private void stopMessagePolling() {
        pollingTopicId = 0;
        if (messagePoller != null && !messagePoller.isShutdown()) {
            messagePoller.shutdownNow();
        }
        messagePoller = null;
    }

    private boolean postsChanged(List<Post> previous, List<Post> next) {
        if (previous.size() != next.size()) {
            return true;
        }
        for (int i = 0; i < previous.size(); i++) {
            Post a = previous.get(i);
            Post b = next.get(i);
            if (a.getId() != b.getId()
                    || !a.getContent().equals(b.getContent())
                    || !a.getCreatedAt().equals(b.getCreatedAt())) {
                return true;
            }
        }
        return false;
    }

    private void loadMessages(Topic topic, String groupName) {
        List<Post> posts = new ArrayList<>(postController.loadPosts(topicId));
        // Merge pending offline posts that aren't in the API response yet
        Set<Integer> apiIds = new HashSet<>();
        posts.forEach(p -> apiIds.add(p.getId()));
        for (Post pending : pendingPosts) {
            if (!apiIds.contains(pending.getId())) posts.add(pending);
        }
        posts.sort((a, b) -> a.getCreatedAt().compareTo(b.getCreatedAt()));
        currentPosts = posts;
        currentGroupName = groupName;
        chatSubtitleLabel.setText(groupName + " • " + posts.size() + " messages");

        messagesBox.getChildren().clear();
        messageNodesById.clear();
        pendingTickLabels.clear();

        if (posts.isEmpty()) {
            VBox empty = new VBox(8);
            empty.setAlignment(Pos.CENTER);
            empty.getStyleClass().add("wa-empty");
            empty.setMaxHeight(Double.MAX_VALUE);
            VBox.setVgrow(empty, Priority.ALWAYS);
            empty.getChildren().addAll(
                    new Label("💬"),
                    new Label("No messages yet. Start the conversation below.")
            );
            messagesBox.getChildren().add(empty);
            return;
        }

        String lastDateLabel = null;
        int currentUserId = AppSession.getInstance().getCurrentUser().getId();
        Map<Integer, Post> postsById = new HashMap<>();
        posts.forEach(post -> postsById.put(post.getId(), post));

        for (Post post : posts) {
            String dateLabel = formatDateLabel(post.getCreatedAt());
            if (!dateLabel.equals(lastDateLabel)) {
                messagesBox.getChildren().add(buildDateDivider(dateLabel));
                lastDateLabel = dateLabel;
            }
            HBox row = buildMessageRow(post, postsById, currentUserId);
            messagesBox.getChildren().add(row);
            messageNodesById.put(post.getId(), row);
        }
    }

    private HBox buildMessageRow(Post post, Map<Integer, Post> postsById, int currentUserId) {
        boolean mine = post.isMine(currentUserId);

        HBox row = new HBox();
        row.setId("msg-" + post.getId());
        row.getStyleClass().addAll("wa-msg", mine ? "mine" : "theirs");
        row.setAlignment(mine ? Pos.CENTER_RIGHT : Pos.CENTER_LEFT);
        row.setMaxWidth(Double.MAX_VALUE);

        VBox wrap = new VBox();
        wrap.getStyleClass().add("wa-bubble-wrap");

        VBox bubble = new VBox(4);
        bubble.getStyleClass().add("wa-bubble");
        bubble.setMaxWidth(420);

        HBox actions = new HBox(2);
        actions.getStyleClass().add("wa-bubble-actions");
        actions.setAlignment(Pos.CENTER);
        actions.setOpacity(0);
        actions.setMouseTransparent(true);
        actions.setMaxSize(Region.USE_PREF_SIZE, Region.USE_PREF_SIZE);
        actions.setPickOnBounds(true);

        actions.getChildren().add(copyActionButton(post.getContent()));
        actions.getChildren().add(actionButton("⤴", "Share to social media", event -> sharePost(post, (Button) event.getSource())));

        if (postController.canParticipate(topicId)) {
            actions.getChildren().add(actionButton("↩", "Reply", event -> startReply(post)));
        }

        if (mine) {
            actions.getChildren().add(actionButton("✎", "Edit", event -> openEditPost(post.getId())));
            actions.getChildren().add(actionButton("🗑", "Delete", event -> confirmDeletePost(post.getId())));
        } else if (postController.canParticipate(topicId)) {
            actions.getChildren().add(actionButton("⚑", "Report", event -> reportPost(post)));
        }

        if (!mine) {
            Label name = new Label(post.getAuthorName());
            name.getStyleClass().add("wa-bubble-name");
            bubble.getChildren().add(name);
        }

        if (post.getParentPostId() != null) {
            Post parent = postsById.get(post.getParentPostId());
            if (parent != null && parent.isVisibleTo(currentUserId, AppSession.getInstance().isSystemAdmin())) {
                bubble.getChildren().add(buildQuote(parent));
            }
        }

        Label text = new Label(post.getContent());
        text.getStyleClass().add("wa-bubble-text");
        text.setWrapText(true);
        bubble.getChildren().add(text);

        HBox meta = new HBox(6);
        meta.setAlignment(Pos.CENTER_RIGHT);
        meta.getStyleClass().add("wa-bubble-meta");

        if (mine && !post.getHiddenFromUserIds().isEmpty()) {
            Label hidden = new Label("👁 " + post.getHiddenFromUserIds().size());
            hidden.getStyleClass().add("wa-hidden-badge");
            meta.getChildren().add(hidden);
        }

        Label time = new Label(formatTime(post.getCreatedAt()));
        time.getStyleClass().add("wa-bubble-time");
        meta.getChildren().add(time);

        if (mine) {
            boolean isPending = pendingPosts.stream().anyMatch(p -> p.getId() == post.getId());
            Label tick = new Label(isPending ? "\u2713" : "\u2713\u2713");
            tick.getStyleClass().add(isPending ? "msg-tick-pending" : "msg-tick-sent");
            if (isPending) pendingTickLabels.put(post.getId(), tick);
            meta.getChildren().add(tick);
        }

        bubble.getChildren().add(meta);

        StackPane bubbleStack = new StackPane();
        bubbleStack.getStyleClass().add("wa-bubble-stack");
        bubbleStack.setAlignment(Pos.TOP_LEFT);
        StackPane.setAlignment(actions, Pos.TOP_RIGHT);
        StackPane.setMargin(actions, new Insets(2, 2, 0, 0));
        bubbleStack.getChildren().addAll(bubble, actions);

        PauseTransition hideDelay = new PauseTransition(Duration.millis(180));
        Runnable hideActions = () -> {
            actions.setOpacity(0);
            actions.setMouseTransparent(true);
        };
        Runnable showActions = () -> {
            hideDelay.stop();
            actions.setOpacity(1);
            actions.setMouseTransparent(false);
        };
        hideDelay.setOnFinished(e -> {
            if (!bubbleStack.isHover() && !actions.isHover()) {
                hideActions.run();
            }
        });
        Runnable scheduleHide = hideDelay::playFromStart;

        bubbleStack.setOnMouseEntered(e -> showActions.run());
        bubbleStack.setOnMouseExited(e -> scheduleHide.run());
        actions.setOnMouseEntered(e -> showActions.run());
        actions.setOnMouseExited(e -> scheduleHide.run());

        wrap.getChildren().add(bubbleStack);
        row.getChildren().add(wrap);
        return row;
    }

    private void copyPostContent(String content) {
        String text = content == null ? "" : content;
        if (text.isBlank()) {
            return;
        }

        ClipboardContent clipboardContent = new ClipboardContent();
        clipboardContent.putString(text);
        Clipboard.getSystemClipboard().setContent(clipboardContent);
    }

    private Button copyActionButton(String content) {
        Button button = new Button("⎘");
        button.getStyleClass().add("wa-action-btn");
        Tooltip tooltip = new Tooltip("Copy");
        Tooltip.install(button, tooltip);
        button.addEventFilter(MouseEvent.MOUSE_PRESSED, event -> {
            event.consume();
            copyPostContent(content);
            tooltip.setText("Copied!");
            PauseTransition pause = new PauseTransition(Duration.seconds(1.2));
            pause.setOnFinished(e -> tooltip.setText("Copy"));
            pause.play();
        });
        return button;
    }

    private VBox buildQuote(Post parent) {
        Label author = new Label(parent.getAuthorName());
        author.getStyleClass().add("wa-quote-author");

        Label quoteText = new Label(truncate(parent.getContent(), 120));
        quoteText.getStyleClass().add("wa-quote-text");
        quoteText.setWrapText(true);

        VBox quote = new VBox(2, author, quoteText);
        quote.getStyleClass().addAll("wa-quote", "reply-quote");
        quote.setOnMouseClicked(event -> scrollToMessage(parent.getId()));
        return quote;
    }

    private void scrollToMessage(int postId) {
        javafx.scene.Node node = messageNodesById.get(postId);
        if (node == null || messagesScroll == null) {
            return;
        }

        double height = messagesBox.getBoundsInLocal().getHeight();
        if (height <= 0) {
            return;
        }

        double nodeY = node.getBoundsInParent().getMinY();
        double viewport = messagesScroll.getViewportBounds().getHeight();
        double target = nodeY / Math.max(1, height - viewport);
        messagesScroll.setVvalue(Math.min(1, Math.max(0, target)));

        node.getStyleClass().add("wa-msg-highlight");
        Platform.runLater(() -> {
            javafx.animation.PauseTransition pause =
                    new javafx.animation.PauseTransition(javafx.util.Duration.seconds(1.5));
            pause.setOnFinished(e -> node.getStyleClass().remove("wa-msg-highlight"));
            pause.play();
        });
    }

    private Button actionButton(String text, String tooltipText, javafx.event.EventHandler<ActionEvent> handler) {
        Button button = new Button(text);
        button.getStyleClass().add("wa-action-btn");
        button.setOnAction(handler);
        Tooltip.install(button, new Tooltip(tooltipText));
        return button;
    }

    private HBox buildDateDivider(String label) {
        Label text = new Label(label);
        text.getStyleClass().add("chat-date-divider-label");

        HBox divider = new HBox(text);
        divider.setAlignment(Pos.CENTER);
        divider.setMaxWidth(Double.MAX_VALUE);
        divider.getStyleClass().add("chat-date-divider");
        return divider;
    }

    private void startReply(Post post) {
        replyToPostId = post.getId();
        replyUserLabel.setText("Replying to " + post.getAuthorName());
        replyTextLabel.setText(truncate(post.getContent(), 80));
        replyBar.setVisible(true);
        replyBar.setManaged(true);
        messageInput.requestFocus();
    }

    private void hideReplyBar() {
        replyBar.setVisible(false);
        replyBar.setManaged(false);
    }

    private void hideExcludePanel() {
        excludePanel.setVisible(false);
        excludePanel.setManaged(false);
    }

    private void scrollMessagesToBottom() {
        Platform.runLater(() -> {
            if (messagesScroll != null) {
                messagesScroll.setVvalue(1.0);
            }
        });
    }

    private void switchTo(VBox pane) {
        boolean create = pane == createPane;
        boolean show = pane == showPane;
        boolean edit = pane == editPane;

        if (!show) {
            stopMessagePolling();
        } else if (topicId > 0) {
            startMessagePolling();
        }

        createPane.setVisible(create);
        createPane.setManaged(create);
        showPane.setVisible(show);
        showPane.setManaged(show);
        editPane.setVisible(edit);
        editPane.setManaged(edit);

        if (contentScroll != null) {
            contentScroll.setVisible(!show);
            contentScroll.setManaged(!show);
            if (!show) {
                contentScroll.setVvalue(0);
            }
        }

        if (chatLayoutCallback != null) {
            chatLayoutCallback.accept(show);
        }
    }

    private void updateTitle(String title) {
        if (pageTitleUpdater != null) {
            pageTitleUpdater.accept(title);
        }
    }

    private String formatDateLabel(LocalDateTime time) {
        LocalDate date = time.toLocalDate();
        LocalDate today = LocalDate.now();
        if (date.equals(today)) {
            return "Today";
        }
        if (date.equals(today.minusDays(1))) {
            return "Yesterday";
        }
        return date.format(DateTimeFormatter.ofPattern("MMM d, yyyy"));
    }

    private String formatTime(LocalDateTime time) {
        return time.format(DateTimeFormatter.ofPattern("h:mm a"));
    }

    private void showAlert(Alert.AlertType type, String title, String message) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private String truncate(String text, int max) {
        if (text == null) {
            return "";
        }
        return text.length() <= max ? text : text.substring(0, max - 3) + "...";
    }
}
