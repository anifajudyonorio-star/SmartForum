package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.ParticipantRow;
import com.smartforum.util.ApiSupport;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import javafx.scene.control.cell.PropertyValueFactory;

public class LecturerDashboardController {

    @FXML private Label myGroupsLabel;
    @FXML private Label myTopicsLabel;
    @FXML private Label participantsCountLabel;
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
    private void initialize() {
        nameColumn.setCellValueFactory(new PropertyValueFactory<>("name"));
        topicsColumn.setCellValueFactory(new PropertyValueFactory<>("topics"));
        postsColumn.setCellValueFactory(new PropertyValueFactory<>("posts"));
        repliesColumn.setCellValueFactory(new PropertyValueFactory<>("replies"));
        scoreColumn.setCellValueFactory(new PropertyValueFactory<>("score"));
        participantsTable.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);

        if (ApiSupport.useApi()) {
            loadFromApi();
        } else {
            loadPreviewData();
        }
    }

    private void loadFromApi() {
        new Thread(() -> ApiClient.getDashboard().ifPresentOrElse(json -> {
            if (!"lecturer".equals(json.get("role").getAsString())) {
                Platform.runLater(this::loadPreviewData);
                return;
            }
            JsonObject stats = json.getAsJsonObject("stats");
            Platform.runLater(() -> {
                myGroupsLabel.setText(String.valueOf(stats.get("my_groups").getAsInt()));
                myTopicsLabel.setText(String.valueOf(stats.get("my_topics").getAsInt()));

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
        }, () -> Platform.runLater(this::loadPreviewData))).start();
    }

    private void loadPreviewData() {
        myGroupsLabel.setText("3");
        myTopicsLabel.setText("7");
        participantsCountLabel.setText("5");

        participantsTable.setItems(FXCollections.observableArrayList(
                new ParticipantRow("Anifa Onorio", 2, 14, 9, 23),
                new ParticipantRow("James Okello", 1, 10, 6, 16),
                new ParticipantRow("Sarah Nakato", 3, 8, 11, 19),
                new ParticipantRow("Peter Musoke", 0, 6, 4, 10),
                new ParticipantRow("Grace Achieng", 1, 5, 3, 8)
        ));
    }
}
