package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.NotificationItem;
import com.smartforum.util.ApiSupport;
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

    public void refresh() {
        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadEmptyState();
        }
    }

    private void loadFromApi() {
        new Thread(() -> ApiClient.getNotifications().ifPresentOrElse(json -> Platform.runLater(() -> {
            int unread = json.get("unread_count").getAsInt();
            unreadCountLabel.setText(unread + " unread");
            updateUnreadBadge(unread);

            notificationsBox.getChildren().clear();
            JsonArray notifications = json.getAsJsonArray("notifications");
            if (notifications.isEmpty()) {
                VBox empty = new VBox(8);
                empty.setAlignment(Pos.CENTER);
                empty.getStyleClass().add("groups-empty-state");
                empty.getChildren().addAll(new Label("🔕"), new Label("No notifications yet."));
                notificationsBox.getChildren().add(empty);
                return;
            }

            for (JsonElement element : notifications) {
                JsonObject obj = element.getAsJsonObject();
                Integer topicId = obj.has("topic_id") && !obj.get("topic_id").isJsonNull()
                        ? obj.get("topic_id").getAsInt()
                        : null;
                NotificationItem item = new NotificationItem(
                        obj.get("id").getAsInt(),
                        obj.get("title").getAsString(),
                        obj.get("message").getAsString(),
                        obj.has("type") ? obj.get("type").getAsString() : "",
                        !obj.has("is_read") ? false : obj.get("is_read").getAsBoolean(),
                        obj.has("time") ? obj.get("time").getAsString() : "",
                        topicId
                );
                notificationsBox.getChildren().add(buildNotificationRow(item));
            }
        }), () -> Platform.runLater(this::loadEmptyState))).start();
    }

    private void loadEmptyState() {
        unreadCountLabel.setText("0 unread");
        updateUnreadBadge(0);
        notificationsBox.getChildren().clear();
        VBox empty = new VBox(8);
        empty.setAlignment(Pos.CENTER);
        empty.getStyleClass().add("groups-empty-state");
        empty.getChildren().addAll(new Label("🔕"), new Label("No notifications yet."));
        notificationsBox.getChildren().add(empty);
    }

    private HBox buildNotificationRow(NotificationItem item) {
        HBox row = new HBox(12);
        row.setAlignment(Pos.TOP_LEFT);
        row.setPadding(new Insets(12));
        row.getStyleClass().addAll("notif-item");
        if (!item.isRead()) {
            row.getStyleClass().add("unread");
        }

        Label icon = new Label(item.getIcon());
        icon.getStyleClass().add("notif-icon");

        VBox body = new VBox(4);
        HBox top = new HBox(8);
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
            onNotificationClicked(item);
        });

        return row;
    }

    private void onNotificationClicked(NotificationItem item) {
        if (ApiSupport.useApi()) {
            new Thread(() -> {
                ApiClient.markNotificationRead(item.getId());
                Platform.runLater(() -> {
                    if (item.getTopicId() != null && navigator != null) {
                        navigator.showTopic(item.getTopicId());
                    } else {
                        refresh();
                    }
                });
            }).start();
            return;
        }

        if (item.getTopicId() != null && navigator != null) {
            navigator.showTopic(item.getTopicId());
        }
    }

    private void updateUnreadBadge(int unread) {
        if (unreadCountUpdater != null) {
            unreadCountUpdater.accept(unread);
        }
    }
}
