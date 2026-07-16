package com.smartforum;

import com.smartforum.api.ApiClient;
import com.smartforum.controller.ChatController;
import com.smartforum.model.Group;
import com.smartforum.model.Topic;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.control.ListCell;
import javafx.scene.control.ListView;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;

import java.util.List;

public class StudentDashboardController {

    @FXML private Label userNameLabel;
    @FXML private Label userRoleLabel;
    @FXML private ListView<Group> groupsListView;
    @FXML private ListView<Topic> topicsListView;
    @FXML private Label topicsPlaceholder;

    @FXML
    public void initialize() {
        UserSession s = UserSession.getInstance();
        userNameLabel.setText(s.getFullName());
        userRoleLabel.setText(s.getRole().substring(0, 1).toUpperCase() + s.getRole().substring(1));

        groupsListView.setCellFactory(lv -> new ListCell<>() {
            @Override
            protected void updateItem(Group g, boolean empty) {
                super.updateItem(g, empty);
                setText(empty || g == null ? null : g.getName());
            }
        });

        topicsListView.setCellFactory(lv -> new ListCell<>() {
            @Override
            protected void updateItem(Topic t, boolean empty) {
                super.updateItem(t, empty);
                setText(empty || t == null ? null : t.getTitle());
            }
        });

        groupsListView.getSelectionModel().selectedItemProperty().addListener(
                (obs, old, group) -> { if (group != null) loadTopicsForGroup(group); });

        topicsListView.setOnMouseClicked(e -> {
            if (e.getClickCount() == 2) {
                Topic t = topicsListView.getSelectionModel().getSelectedItem();
                if (t != null) openChat(t);
            }
        });

        loadGroups();
    }

    private void loadGroups() {
        new Thread(() -> {
            List<Group> groups = ApiClient.getGroups();
            Platform.runLater(() -> {
                groupsListView.getItems().setAll(groups);
                if (!groups.isEmpty()) groupsListView.getSelectionModel().selectFirst();
            });
        }).start();
    }

    private void loadTopicsForGroup(Group group) {
        topicsListView.getItems().clear();
        topicsPlaceholder.setText("Loading…");
        topicsPlaceholder.setVisible(true);

        new Thread(() -> {
            List<Topic> all = ApiClient.getTopics();
            List<Topic> filtered = all.stream()
                    .filter(t -> t.getGroupId() == group.getId())
                    .toList();
            Platform.runLater(() -> {
                topicsListView.getItems().setAll(filtered);
                topicsPlaceholder.setVisible(filtered.isEmpty());
                topicsPlaceholder.setText("No topics in this group yet.");
            });
        }).start();
    }

    private void openChat(Topic topic) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/smartforum/chat.fxml"));
            Scene scene = new Scene(loader.load(), 900, 620);
            ChatController controller = loader.getController();

            Stage stage = (Stage) topicsListView.getScene().getWindow();
            stage.setScene(scene);
            stage.setWidth(900);
            stage.setHeight(620);
            stage.setResizable(true);

            // Pre-select the topic
            controller.selectTopic(topic);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    public void handleLogout() {
        UserSession.getInstance().clear();
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/smartforum/auth-view.fxml"));
            Scene scene = new Scene(loader.load(), 480, 600);
            scene.setFill(javafx.scene.paint.Color.web("#0a0f1e"));
            Stage stage = (Stage) userNameLabel.getScene().getWindow();
            stage.setScene(scene);
            stage.setWidth(480);
            stage.setHeight(600);
            stage.setResizable(false);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
