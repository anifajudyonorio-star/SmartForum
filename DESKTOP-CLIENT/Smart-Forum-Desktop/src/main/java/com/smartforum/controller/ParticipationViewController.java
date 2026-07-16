package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.ParticipationCard;
import com.smartforum.util.ApiSupport;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.ComboBox;
import javafx.scene.control.Label;
import javafx.scene.control.ProgressBar;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.ArrayList;
import java.util.List;

public class ParticipationViewController {

    @FXML private Label headerSubtitleLabel;
    @FXML private Label topParticipantLabel;
    @FXML private ComboBox<String> groupFilterCombo;
    @FXML private VBox participantsBox;

    private final List<Integer> groupIds = new ArrayList<>();

    @FXML
    private void initialize() {
        groupFilterCombo.setOnAction(event -> {
            int index = groupFilterCombo.getSelectionModel().getSelectedIndex();
            Integer groupId = index <= 0 ? null : groupIds.get(index - 1);
            loadParticipation(groupId);
        });

        if (ApiSupport.useApi()) {
            loadParticipation(null);
        } else {
            loadPreviewData();
        }
    }

    private void loadParticipation(Integer groupId) {
        new Thread(() -> ApiClient.getParticipation(groupId).ifPresentOrElse(json -> Platform.runLater(() -> {
            populateGroupFilter(json);

            if (json.has("selected_group") && !json.get("selected_group").isJsonNull()) {
                JsonObject selected = json.getAsJsonObject("selected_group");
                headerSubtitleLabel.setText("Engagement in " + selected.get("name").getAsString() + ".");
            } else {
                headerSubtitleLabel.setText("Track student engagement across topics, posts, and replies.");
            }

            participantsBox.getChildren().clear();
            JsonArray participants = json.getAsJsonArray("participants");
            if (participants.isEmpty()) {
                Label empty = new Label("No participation data available.");
                empty.getStyleClass().add("dashboard-subtitle");
                participantsBox.getChildren().add(empty);
                topParticipantLabel.setText("");
                return;
            }

            JsonObject first = participants.get(0).getAsJsonObject();
            topParticipantLabel.setText("⭐ Top: " + first.get("name").getAsString());

            int highestScore = json.get("highest_score").getAsInt();
            for (JsonElement element : participants) {
                JsonObject participant = element.getAsJsonObject();
                int score = participant.get("score").getAsInt();
                int progress = highestScore <= 0 ? 0 : Math.round((score * 100f) / highestScore);
                participantsBox.getChildren().add(buildCard(new ParticipationCard(
                        participant.get("name").getAsString(),
                        participant.get("topics_count").getAsInt(),
                        participant.get("posts_count").getAsInt(),
                        participant.get("replies_count").getAsInt(),
                        score,
                        participant.get("rank").getAsString(),
                        progress
                )));
            }
        }), () -> Platform.runLater(this::loadPreviewData))).start();
    }

    private void populateGroupFilter(JsonObject json) {
        groupIds.clear();
        groupFilterCombo.getItems().clear();
        groupFilterCombo.getItems().add("All my groups");

        JsonArray groups = json.getAsJsonArray("available_groups");
        if (groups.size() <= 1) {
            groupFilterCombo.setVisible(false);
            groupFilterCombo.setManaged(false);
            return;
        }

        groupFilterCombo.setVisible(true);
        groupFilterCombo.setManaged(true);
        for (JsonElement element : groups) {
            JsonObject group = element.getAsJsonObject();
            groupIds.add(group.get("id").getAsInt());
            groupFilterCombo.getItems().add(group.get("name").getAsString());
        }

        if (json.has("selected_group") && !json.get("selected_group").isJsonNull()) {
            JsonObject selected = json.getAsJsonObject("selected_group");
            groupFilterCombo.getSelectionModel().select(selected.get("name").getAsString());
        } else {
            groupFilterCombo.getSelectionModel().selectFirst();
        }
    }

    private VBox buildCard(ParticipationCard card) {
        VBox cardBox = new VBox(10);
        cardBox.getStyleClass().add("participant-card");
        cardBox.setPadding(new Insets(14));

        HBox top = new HBox(12);
        top.setAlignment(Pos.CENTER_LEFT);

        Label avatar = new Label(card.getInitials());
        avatar.getStyleClass().add("participant-avatar");

        VBox nameBox = new VBox(4);
        Label name = new Label(card.getName());
        name.getStyleClass().add("participant-name");
        Label rank = new Label(card.getRank());
        rank.getStyleClass().add("badge-primary");
        nameBox.getChildren().addAll(name, rank);
        HBox.setHgrow(nameBox, Priority.ALWAYS);

        top.getChildren().addAll(avatar, nameBox);

        GridPane stats = new GridPane();
        stats.setHgap(16);
        stats.getStyleClass().add("participant-stats");
        stats.add(createStatBlock(String.valueOf(card.getTopics()), "Topics"), 0, 0);
        stats.add(createStatBlock(String.valueOf(card.getPosts()), "Posts"), 1, 0);
        stats.add(createStatBlock(String.valueOf(card.getReplies()), "Replies"), 2, 0);

        HBox scoreRow = new HBox(8);
        scoreRow.setAlignment(Pos.CENTER_LEFT);
        Label scoreLabel = new Label("Score: " + card.getScore());
        scoreLabel.getStyleClass().add("list-item-meta");
        Label percentLabel = new Label(card.getProgress() + "%");
        percentLabel.getStyleClass().add("list-item-meta");
        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        scoreRow.getChildren().addAll(scoreLabel, spacer, percentLabel);

        ProgressBar progressBar = new ProgressBar(card.getProgress() / 100.0);
        progressBar.setMaxWidth(Double.MAX_VALUE);
        progressBar.getStyleClass().add("participant-progress");

        cardBox.getChildren().addAll(top, stats, scoreRow, progressBar);
        return cardBox;
    }

    private VBox createStatBlock(String value, String label) {
        Label valueLabel = new Label(value);
        valueLabel.getStyleClass().add("participant-stat-value");
        Label textLabel = new Label(label);
        textLabel.getStyleClass().add("participant-stat-label");
        return new VBox(2, valueLabel, textLabel);
    }

    private void loadPreviewData() {
        headerSubtitleLabel.setText("Track student engagement across topics, posts, and replies.");
        topParticipantLabel.setText("⭐ Top: Anifa Onorio");
        groupFilterCombo.setVisible(false);
        groupFilterCombo.setManaged(false);
        participantsBox.getChildren().clear();
        participantsBox.getChildren().add(buildCard(new ParticipationCard(
                "Anifa Onorio", 2, 14, 9, 25, "🥈 Silver", 100)));
        participantsBox.getChildren().add(buildCard(new ParticipationCard(
                "James Okello", 1, 10, 6, 17, "⭐ Beginner", 68)));
    }
}
