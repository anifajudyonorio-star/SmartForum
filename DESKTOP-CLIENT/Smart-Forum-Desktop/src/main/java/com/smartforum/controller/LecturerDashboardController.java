package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.GroupAdminSummaryRow;
import com.smartforum.model.ParticipantRow;
import com.smartforum.util.ApiSupport;
import com.smartforum.util.GroupAdminDashboardSupport;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

public class LecturerDashboardController {

    @FXML private Label myGroupsLabel;
    @FXML private Label myTopicsLabel;
    @FXML private Label participantsCountLabel;
    @FXML private VBox groupAdminCard;
    @FXML private HBox groupAdminTitleBox;
    @FXML private TableView<GroupAdminSummaryRow> groupAdminTable;
    @FXML private TableColumn<GroupAdminSummaryRow, String> groupAdminGroupColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminMembersColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminTopicsColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Number> groupAdminPostsColumn;
    @FXML private TableColumn<GroupAdminSummaryRow, Void> groupAdminActionColumn;
    @FXML private Button viewStatisticsBtn;
    @FXML private TableView<ParticipantRow> participantsTable;
    @FXML private TableColumn<ParticipantRow, String> nameColumn;
    @FXML private TableColumn<ParticipantRow, Number> topicsColumn;
    @FXML private TableColumn<ParticipantRow, Number> postsColumn;
    @FXML private TableColumn<ParticipantRow, Number> repliesColumn;
    @FXML private TableColumn<ParticipantRow, Number> scoreColumn;

    private ShellNavigator navigator;

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    @FXML
    private void onViewParticipation() {
        if (navigator != null) {
            navigator.showParticipation();
        }
    }

    @FXML
    private void onViewStatistics() {
        if (navigator != null) {
            navigator.showStatisticsOverview();
        }
    }

    @FXML
    private void initialize() {
        nameColumn.setCellValueFactory(new PropertyValueFactory<>("name"));
        topicsColumn.setCellValueFactory(new PropertyValueFactory<>("topics"));
        postsColumn.setCellValueFactory(new PropertyValueFactory<>("posts"));
        repliesColumn.setCellValueFactory(new PropertyValueFactory<>("replies"));
        scoreColumn.setCellValueFactory(new PropertyValueFactory<>("score"));
        participantsTable.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);

        GroupAdminDashboardSupport.configureHeader(groupAdminTitleBox);
        GroupAdminDashboardSupport.configureViewStatisticsButton(viewStatisticsBtn);
        GroupAdminDashboardSupport.configureTable(
                groupAdminTable,
                groupAdminGroupColumn,
                groupAdminMembersColumn,
                groupAdminTopicsColumn,
                groupAdminPostsColumn,
                groupAdminActionColumn,
                groupId -> {
                    if (navigator != null) {
                        navigator.showGroupStatistics(groupId);
                    }
                }
        );

        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadEmptyState();
        }
    }

    private void loadFromApi() {
        new Thread(() -> ApiClient.getDashboard().ifPresentOrElse(json -> {
            if (!"lecturer".equals(json.get("role").getAsString())) {
                Platform.runLater(this::loadEmptyState);
                return;
            }
            JsonObject stats = json.getAsJsonObject("stats");
            ObservableList<GroupAdminSummaryRow> adminRows = GroupAdminDashboardSupport.rowsFromApi(json);
            Platform.runLater(() -> {
                myGroupsLabel.setText(String.valueOf(stats.get("my_groups").getAsInt()));
                myTopicsLabel.setText(String.valueOf(stats.get("my_topics").getAsInt()));
                populateGroupAdminCard(adminRows);

                JsonArray participants = stats.getAsJsonArray("participants");
                participantsCountLabel.setText(String.valueOf(participants.size()));

                var rows = FXCollections.<ParticipantRow>observableArrayList();
                for (JsonElement element : participants) {
                    JsonObject participant = element.getAsJsonObject();
                    rows.add(new ParticipantRow(
                            participant.get("name").getAsString(),
                            participant.has("topics") ? participant.get("topics").getAsInt() : 0,
                            participant.get("posts").getAsInt(),
                            participant.get("replies").getAsInt(),
                            participant.get("score").getAsInt()
                    ));
                }
                participantsTable.setItems(rows);
            });
        }, () -> Platform.runLater(this::loadEmptyState))).start();
    }

    private void loadEmptyState() {
        myGroupsLabel.setText("0");
        myTopicsLabel.setText("0");
        participantsCountLabel.setText("0");
        populateGroupAdminCard(GroupAdminDashboardSupport.rowsFromApi(null));
        participantsTable.setItems(FXCollections.observableArrayList());
    }

    private void populateGroupAdminCard(ObservableList<GroupAdminSummaryRow> rows) {
        GroupAdminDashboardSupport.populateTable(groupAdminTable, groupAdminCard, rows);
    }
}
