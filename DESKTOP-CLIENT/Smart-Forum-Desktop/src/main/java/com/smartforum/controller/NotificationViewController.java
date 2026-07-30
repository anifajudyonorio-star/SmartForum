package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.api.ApiClient.MutationResult;
import com.smartforum.model.NotificationItem;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Label;
import javafx.scene.input.MouseButton;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.function.Consumer;

public class NotificationViewController {

    @FXML private Label unreadCountLabel;
    @FXML private VBox notificationsBox;

    private ShellNavigator navigator;
    private Consumer<Integer> unreadCountUpdater;
    private int unreadCount;

    @FXML
    private void initialize() {
        refresh();
    }

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    public void setUnreadCountUpdater(Consumer<Integer> unreadCountUpdater) {
        this.unreadCountUpdater = unreadCountUpdater;
    }

    public void applyUnreadCount(int unread) {
        unreadCount = Math.max(0, unread);
        if (unreadCountLabel != null) {
            if (unreadCount == 0) {
                unreadCountLabel.setVisible(false);
                unreadCountLabel.setManaged(false);
            } else {
                unreadCountLabel.setText(unreadCount + " unread");
                unreadCountLabel.setVisible(true);
                unreadCountLabel.setManaged(true);
            }
        }
        if (unreadCountUpdater != null) {
            unreadCountUpdater.accept(unreadCount);
        }
    }

    public void refresh() {

        loadFromApi();

    }

    private void loadFromApi() {
        new Thread(() -> ApiClient.getNotifications().ifPresentOrElse(json -> Platform.runLater(() -> {
            applyUnreadCount(json.get("unread_count").getAsInt());

            notificationsBox.getChildren().clear();
            JsonArray notifications = json.getAsJsonArray("notifications");
            if (notifications.isEmpty()) {
                notificationsBox.getChildren().add(buildEmptyState());
                return;
            }

            for (JsonElement element : notifications) {
                notificationsBox.getChildren().add(buildNotificationRow(parseNotification(element.getAsJsonObject())));
            }
        }), () -> Platform.runLater(this::loadEmptyState))).start();
    }

    private void loadEmptyState() {
        applyUnreadCount(0);
        notificationsBox.getChildren().clear();
        notificationsBox.getChildren().add(buildEmptyState());
    }

    private VBox buildEmptyState() {
        VBox empty = new VBox(8);
        empty.setAlignment(Pos.CENTER);
        empty.getStyleClass().add("groups-empty-state");
        empty.getChildren().addAll(new Label("🔕"), new Label("No notifications yet."));
        return empty;
    }

    private NotificationItem parseNotification(JsonObject obj) {
        return new NotificationItem(
                obj.get("id").getAsInt(),
                obj.get("title").getAsString(),
                obj.get("message").getAsString(),
                obj.has("type") ? obj.get("type").getAsString() : "",
                obj.has("is_read") && !obj.get("is_read").isJsonNull() && obj.get("is_read").getAsBoolean(),
                obj.has("time") ? obj.get("time").getAsString() : "",
                readNullableInt(obj, "topic_id"),
                readNullableInt(obj, "group_id"),
                readNullableInt(obj, "quiz_id")
        );
    }

    private Integer readNullableInt(JsonObject obj, String field) {
        if (!obj.has(field) || obj.get(field).isJsonNull()) {
            return null;
        }
        return obj.get(field).getAsInt();
    }

    private HBox buildNotificationRow(NotificationItem item) {
        HBox row = new HBox(12);
        row.setAlignment(Pos.TOP_LEFT);
        row.setPadding(new Insets(12));
        row.getStyleClass().add("notif-item");
        if (!item.isRead()) {
            row.getStyleClass().add("unread");
        }

        Label icon = new Label(item.getIcon());
        icon.getStyleClass().add("notif-icon");

        VBox body = new VBox(4);
        HBox top = new HBox(8);
        top.setAlignment(Pos.CENTER_LEFT);

        if (!item.isRead()) {
            Label unreadDot = new Label("●");
            unreadDot.getStyleClass().add("notif-unread-dot");
            top.getChildren().add(unreadDot);
        }

        Label title = new Label(item.getTitle());
        title.getStyleClass().add("notif-title");
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        Label time = new Label(item.getTime());
        time.getStyleClass().add("notif-time");
        top.getChildren().addAll(title, spacer, time);

        Label message = new Label(item.getMessage());
        message.getStyleClass().add("notif-message");
        message.setWrapText(true);
        body.getChildren().addAll(top, message);
        HBox.setHgrow(body, Priority.ALWAYS);

        row.getChildren().addAll(icon, body);

        row.setOnMouseClicked(event -> {
            if (event.getButton() != MouseButton.PRIMARY) {
                return;
            }
            onNotificationClicked(item, row);
        });

        return row;
    }

    private void onNotificationClicked(NotificationItem item, HBox row) {
        if (!item.isRead()) {
            new Thread(() -> {
                MutationResult result = ApiClient.markNotificationReadResult(item.getId());
                Platform.runLater(() -> {
                    if (result.success()) {
                        markRowAsRead(item, row);
                        if (result.body() != null && result.body().has("unread_count")) {
                            applyUnreadCount(result.body().get("unread_count").getAsInt());
                        } else {
                            applyUnreadCount(Math.max(0, unreadCount - 1));
                        }
                    }
                    navigateToNotification(item);
                });
            }).start();
            return;
        }

        navigateToNotification(item);
    }

    private void markRowAsRead(NotificationItem item, HBox row) {
        item.setRead(true);
        row.getStyleClass().remove("unread");
        row.lookupAll(".notif-unread-dot").forEach(node -> {
            node.setVisible(false);
            node.setManaged(false);
        });
    }

    private void navigateToNotification(NotificationItem item) {
        if (navigator == null) {
            return;
        }
        if (item.getTopicId() != null) {
            navigator.showTopic(item.getTopicId());
        } else if (item.getGroupId() != null) {
            navigator.showGroup(item.getGroupId());
        } else if (item.getQuizId() != null) {
            navigator.showQuizzes();
        }
    }
}
