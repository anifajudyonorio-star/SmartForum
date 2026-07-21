package com.smartforum.controller;

import com.smartforum.model.Post;
import com.smartforum.model.Topic;
import com.smartforum.service.AppSession;
import com.smartforum.service.PostService;
import com.smartforum.service.TopicService;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.scene.control.*;
import javafx.scene.layout.*;

import java.util.List;

public class ChatController {

    @FXML private ListView<Topic> topicListView;
    @FXML private VBox messagesBox;
    @FXML private ScrollPane scrollPane;
    @FXML private TextArea messageInput;
    @FXML private Button sendButton;
    @FXML private Label topicTitleLabel;
    @FXML private Label navAvatarLabel;
    @FXML private Label navNameLabel;
    @FXML private Label menuNameLabel;
    @FXML private Label menuEmailLabel;
    @FXML private Label menuRoleLabel;
    @FXML private VBox userMenu;

    private Topic selectedTopic;
    private final TopicService topicService = TopicService.getInstance();
    private final PostService postService = PostService.getInstance();

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
    }

    private void loadTopics() {
        List<Topic> topics = topicService.getAllTopics();
        topicListView.getItems().setAll(topics);
    }

    private void openTopic(Topic topic) {
        selectedTopic = topic;
        topicTitleLabel.setText(topic.getTitle());
        loadMessages();
    }

    private void loadMessages() {
        if (selectedTopic == null) return;
        messagesBox.getChildren().clear();
        for (Post post : postService.getPosts(selectedTopic.getId())) {
            addBubble(post, "sent", null);
        }
        scrollToBottom();
    }

    @FXML
    private void onSend() {
        String content = messageInput.getText().trim();
        if (content.isEmpty() || selectedTopic == null) return;

        messageInput.clear();
        sendButton.setDisable(true);

        Label tickLabel = new Label("✓");
        tickLabel.getStyleClass().add("tick-pending");
        HBox bubbleRow = addBubble(null, "pending", tickLabel);
        updatePendingBubble(bubbleRow, content);

        try {
            postService.store(selectedTopic.getId(), content, null, List.of());
            tickLabel.setText("✓✓");
            tickLabel.getStyleClass().setAll("tick-sent");
            loadMessages();
        } catch (RuntimeException ex) {
            showBanner(ex.getMessage(), "danger");
        } finally {
            sendButton.setDisable(false);
        }
    }

    private HBox addBubble(Post post, String state, Label tickLabel) {
        int currentUserId = AppSession.getInstance().getCurrentUser().getId();
        boolean isMine = post == null || post.isMine(currentUserId);

        VBox bubble = new VBox(3);
        bubble.getStyleClass().add(isMine ? "bubble-mine" : "bubble-theirs");

        if (post != null && !isMine) {
            Label author = new Label(post.getAuthorName() != null ? post.getAuthorName() : "User");
            author.getStyleClass().add("bubble-author");
            bubble.getChildren().add(author);
        }

        Label text = new Label(post != null ? post.getContent() : "");
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
        if (row.getChildren().isEmpty()) return;
        VBox bubble = (VBox) row.getChildren().get(0);
        for (var node : bubble.getChildren()) {
            if (node instanceof Label label && label.getStyleClass().contains("bubble-text")) {
                label.setText(content);
                break;
            }
        }
    }

    @FXML
    private void toggleUserMenu() {
        boolean show = !userMenu.isVisible();
        userMenu.setVisible(show);
        userMenu.setManaged(show);
        // Close menu when clicking elsewhere
        if (show) {
            userMenu.getScene().addEventFilter(
                javafx.scene.input.MouseEvent.MOUSE_PRESSED, e -> {
                    if (!userMenu.getBoundsInParent().contains(
                            userMenu.getParent().sceneToLocal(e.getSceneX(), e.getSceneY()))) {
                        userMenu.setVisible(false);
                        userMenu.setManaged(false);
                    }
                }
            );
        }
    }

    @FXML
    public void handleLogout() {
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

    private void showBanner(String message, String type) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle("Message");
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private void scrollToBottom() {
        scrollPane.layout();
        scrollPane.setVvalue(1.0);
    }

    private String formatTime(java.time.LocalDateTime createdAt) {
        if (createdAt == null) return "";
        return createdAt.format(java.time.format.DateTimeFormatter.ofPattern("HH:mm"));
    }

    private String capitalize(String s) {
        if (s == null || s.isEmpty()) return s;
        return s.substring(0, 1).toUpperCase() + s.substring(1);
    }
}
