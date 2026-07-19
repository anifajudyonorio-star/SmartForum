package com.smartforum.controller;

import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.Post;
import com.smartforum.model.Topic;
import com.smartforum.util.NetworkMonitor;
import com.smartforum.util.OfflineQueue;
import com.smartforum.util.SessionManager;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.scene.control.*;
import javafx.scene.layout.*;

import java.util.List;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class ChatController {

    @FXML private ListView<Topic> topicListView;
    @FXML private VBox messagesBox;
    @FXML private ScrollPane scrollPane;
    @FXML private TextArea messageInput;
    @FXML private Button sendButton;
    @FXML private Label topicTitleLabel;
    @FXML private Button networkToggleBtn;
    @FXML private Label offlineBanner;
    @FXML private Label syncStatusLabel;
    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private Label userAvatarLabel;
    @FXML private Label navAvatarLabel;
    @FXML private Label navNameLabel;
    @FXML private Label menuNameLabel;
    @FXML private Label menuEmailLabel;
    @FXML private Label menuRoleLabel;
    @FXML private VBox userMenu;

    private Topic selectedTopic;
    private final ScheduledExecutorService scheduler = Executors.newSingleThreadScheduledExecutor();
    private boolean wasOnline = true;

    @FXML
    public void initialize() {
        com.smartforum.util.SessionManager session = com.smartforum.util.SessionManager.getInstance();
        com.smartforum.UserSession us = com.smartforum.UserSession.getInstance();
        String name = session.getUserName() != null ? session.getUserName() : "User";
        String email = us.getEmail() != null ? us.getEmail() : "";
        String role = us.getRole() != null ? capitalize(us.getRole()) : "Student";
        String initial = name.isEmpty() ? "U" : String.valueOf(name.charAt(0)).toUpperCase();

        navAvatarLabel.setText(initial);
        navNameLabel.setText(name);
        menuNameLabel.setText(name);
        menuEmailLabel.setText(email);
        menuRoleLabel.setText(role);

        loadTopics();
        topicListView.getSelectionModel().selectedItemProperty().addListener(
                (obs, old, topic) -> { if (topic != null) openTopic(topic); });
        startNetworkMonitor();
        startPollMessages();
    }

    private void loadTopics() {
        new Thread(() -> {
            List<Topic> topics = ApiClient.getTopics();
            Platform.runLater(() -> topicListView.getItems().setAll(topics));
        }).start();
    }

    private void openTopic(Topic topic) {
        selectedTopic = topic;
        topicTitleLabel.setText(topic.getTitle());
        messagesBox.getChildren().clear();
        loadMessages();
    }

    private void loadMessages() {
        if (selectedTopic == null) return;
        new Thread(() -> {
            List<Post> posts = ApiClient.getPosts(selectedTopic.getId());
            Platform.runLater(() -> {
                messagesBox.getChildren().clear();
                for (Post post : posts) addBubble(post, "sent", null);
                scrollToBottom();
            });
        }).start();
    }

    @FXML
    private void onSend() {
        String content = messageInput.getText().trim();
        if (content.isEmpty() || selectedTopic == null) return;

        messageInput.clear();
        sendButton.setDisable(true);

        if (NetworkMonitor.isOnline()) {
            // Add bubble with pending tick immediately
            Label tickLabel = new Label("✓");
            tickLabel.getStyleClass().add("tick-pending");
            HBox bubbleRow = addBubble(null, "pending", tickLabel);

            new Thread(() -> {
                boolean ok = ApiClient.sendPost(selectedTopic.getId(), content, null);
                Platform.runLater(() -> {
                    sendButton.setDisable(false);
                    if (ok) {
                        // Upgrade tick to sent (double blue)
                        tickLabel.setText("✓✓");
                        tickLabel.getStyleClass().setAll("tick-sent");
                    } else {
                        queueOffline(content, tickLabel);
                    }
                });
            }).start();

            // Build the bubble content now
            updatePendingBubble(bubbleRow, content);
        } else {
            // Offline — queue and show pending bubble
            Label tickLabel = new Label("✓");
            tickLabel.getStyleClass().add("tick-pending");
            HBox bubbleRow = addBubble(null, "pending", tickLabel);
            updatePendingBubble(bubbleRow, content);
            queueOffline(content, tickLabel);
            sendButton.setDisable(false);
        }
    }

    private void queueOffline(String content, Label tickLabel) {
        JsonObject payload = new JsonObject();
        payload.addProperty("topic_id", selectedTopic.getId());
        payload.addProperty("content", content);
        OfflineQueue.add("create_post", payload);
        showBanner("You're offline. Message queued — will send when reconnected.", "warning");
        updateSyncBadge();
    }

    private HBox addBubble(Post post, String state, Label tickLabel) {
        boolean isMine = post == null ||
                post.getCreatedBy() == SessionManager.getInstance().getUserId();

        VBox bubble = new VBox(3);
        bubble.getStyleClass().add(isMine ? "bubble-mine" : "bubble-theirs");

        if (post != null && !isMine) {
            Label author = new Label(post.getAuthorName() != null ? post.getAuthorName() : "User");
            author.getStyleClass().add("bubble-author");
            bubble.getChildren().add(author);
        }

        Label text = new Label(post != null ? post.getPostContent() : "");
        text.getStyleClass().add("bubble-text");
        text.setWrapText(true);
        bubble.getChildren().add(text);

        HBox meta = new HBox(4);
        meta.getStyleClass().add("bubble-meta");
        Label time = new Label(post != null ? formatTime(post.getCreatedAt()) : "now");
        time.getStyleClass().add("bubble-time");
        meta.getChildren().add(time);

        if (isMine) {
            Label tick = tickLabel != null ? tickLabel : new Label("✓✓");
            if (tickLabel == null) tick.getStyleClass().add("tick-sent");
            meta.getChildren().add(tick);
        }

        bubble.getChildren().add(meta);

        HBox row = new HBox(bubble);
        row.getStyleClass().add(isMine ? "bubble-row-mine" : "bubble-row-theirs");
        row.setPadding(new Insets(1, 0, 1, 0));

        messagesBox.getChildren().add(row);
        scrollToBottom();
        return row;
    }

    private void updatePendingBubble(HBox row, String content) {
        // Find the bubble VBox inside the row and update its text label
        if (row.getChildren().isEmpty()) return;
        VBox bubble = (VBox) row.getChildren().get(0);
        for (var node : bubble.getChildren()) {
            if (node instanceof Label l && l.getStyleClass().contains("bubble-text")) {
                l.setText(content);
                break;
            }
        }
    }

    @FXML
    private void toggleUserMenu() {
        boolean show = !userMenu.isVisible();
        userMenu.setVisible(show);
        userMenu.setManaged(show);
    }

    @FXML
    private void onToggleNetwork() {
        boolean currentlyOnline = NetworkMonitor.isOnline();
        NetworkMonitor.setOverride(!currentlyOnline);
        boolean nowOnline = !currentlyOnline;

        updateNetworkButton(nowOnline);
        updateSyncBadge();

        if (nowOnline) {
            showBanner("Back online. Syncing…", "info");
            flushQueue();
        } else {
            showBanner("Switched to offline mode.", "warning");
        }
        wasOnline = nowOnline;
    }

    private void updateNetworkButton(boolean online) {
        networkToggleBtn.setText(online ? "● Online" : "● Offline");
        networkToggleBtn.getStyleClass().setAll(online ? "network-btn-online" : "network-btn-offline");
    }

    private void startNetworkMonitor() {
        scheduler.scheduleAtFixedRate(() -> {
            // Only auto-detect when no manual override is set
            if (NetworkMonitor.getOverride() != null) return;
            boolean online = NetworkMonitor.isOnline();
            Platform.runLater(() -> {
                updateNetworkButton(online);
                if (online && !wasOnline) {
                    showBanner("Reconnected. Syncing…", "info");
                    flushQueue();
                } else if (!online && wasOnline) {
                    showBanner("You're offline. Messages will sync when reconnected.", "warning");
                }
                wasOnline = online;
                updateSyncBadge();
            });
        }, 0, 5, TimeUnit.SECONDS);
    }

    private void startPollMessages() {
        scheduler.scheduleAtFixedRate(() -> {
            if (selectedTopic != null && NetworkMonitor.isOnline()) {
                List<Post> posts = ApiClient.getPosts(selectedTopic.getId());
                Platform.runLater(() -> {
                    messagesBox.getChildren().clear();
                    for (Post post : posts) addBubble(post, "sent", null);
                    scrollToBottom();
                });
            }
        }, 10, 10, TimeUnit.SECONDS);
    }

    private void flushQueue() {
        OfflineQueue.flush(
                () -> Platform.runLater(() -> {
                    showBanner("Offline actions synced!", "success");
                    updateSyncBadge();
                    upgradePendingTicks();
                    loadMessages();
                }),
                () -> Platform.runLater(() -> {
                    showBanner("Sync failed. Will retry.", "danger");
                    updateSyncBadge();
                })
        );
    }

    private void upgradePendingTicks() {
        messagesBox.getChildren().forEach(row -> {
            if (row instanceof HBox hbox && !hbox.getChildren().isEmpty()) {
                VBox bubble = (VBox) hbox.getChildren().get(0);
                bubble.getChildren().forEach(node -> {
                    if (node instanceof HBox meta) {
                        meta.getChildren().forEach(child -> {
                            if (child instanceof Label l &&
                                    l.getStyleClass().contains("tick-pending")) {
                                l.setText("✓✓");
                                l.getStyleClass().setAll("tick-sent");
                            }
                        });
                    }
                });
            }
        });
    }

    private void showBanner(String message, String type) {
        offlineBanner.setText(message);
        offlineBanner.getStyleClass().setAll("offline-banner", "offline-banner-" + type);
        offlineBanner.setVisible(true);
        offlineBanner.setManaged(true);
        scheduler.schedule(() -> Platform.runLater(() -> {
            offlineBanner.setVisible(false);
            offlineBanner.setManaged(false);
        }), 4, TimeUnit.SECONDS);
    }

    private void updateSyncBadge() {
        int pending = OfflineQueue.size();
        if (pending > 0) {
            syncStatusLabel.setText(pending + " pending");
            syncStatusLabel.setVisible(true);
        } else {
            syncStatusLabel.setVisible(false);
        }
    }

    private void scrollToBottom() {
        scrollPane.layout();
        scrollPane.setVvalue(1.0);
    }

    private String formatTime(String createdAt) {
        if (createdAt == null) return "";
        try { return createdAt.substring(11, 16); } catch (Exception e) { return ""; }
    }

    private String capitalize(String s) {
        if (s == null || s.isEmpty()) return s;
        return s.substring(0, 1).toUpperCase() + s.substring(1);
    }

    @FXML
    public void handleLogout() {
        shutdown();
        com.smartforum.util.SessionManager.getInstance().clear();
        com.smartforum.UserSession.getInstance().clear();
        try {
            javafx.fxml.FXMLLoader loader = new javafx.fxml.FXMLLoader(
                getClass().getResource("/com/smartforum/auth-view.fxml"));
            javafx.scene.Scene scene = new javafx.scene.Scene(loader.load(), 480, 600);
            scene.setFill(javafx.scene.paint.Color.web("#0a0f1e"));
            javafx.stage.Stage stage = (javafx.stage.Stage) topicListView.getScene().getWindow();
            stage.setScene(scene);
            stage.setResizable(false);
            stage.setTitle("Smart Discussion Forum");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    public void shutdown() {
        scheduler.shutdownNow();
    }
}
