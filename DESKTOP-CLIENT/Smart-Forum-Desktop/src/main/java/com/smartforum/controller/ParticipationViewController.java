package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.ParticipationCard;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ComboBox;
import javafx.scene.control.ContentDisplay;
import javafx.scene.control.Label;
import javafx.scene.control.ProgressBar;
import javafx.scene.control.TextField;
import javafx.scene.layout.ColumnConstraints;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import org.kordamp.ikonli.bootstrapicons.BootstrapIcons;
import org.kordamp.ikonli.javafx.FontIcon;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class ParticipationViewController {

    @FXML private Label headerSubtitleLabel;
    @FXML private Label topParticipantLabel;
    @FXML private ComboBox<String> groupFilterCombo;
    @FXML private VBox criteriaBox;
    @FXML private GridPane criteriaGrid;
    @FXML private FlowPane participantsBox;

    private Region rootNode;
    private final List<Integer> groupIds = new ArrayList<>();
    private Integer selectedGroupId;
    private boolean canManage;
    private int manualMarksMax = 20;
    private final Map<String, TextField> criteriaFields = new HashMap<>();

    public Region getRootNode() {
        return rootNode;
    }

    public void setRootNode(Region rootNode) {
        this.rootNode = rootNode;
    }

    @FXML
    private void initialize() {
        groupFilterCombo.setOnAction(event -> {
            int index = groupFilterCombo.getSelectionModel().getSelectedIndex();
            Integer groupId = index <= 0 ? null : groupIds.get(index - 1);
            loadParticipation(groupId);
        });


        loadParticipation(null);

    }

    public void loadParticipation(Integer groupId) {


        new Thread(() -> ApiClient.getParticipation(groupId).ifPresentOrElse(json -> Platform.runLater(() -> {
            populateGroupFilter(json, groupId);
            renderParticipation(json);
        }), () -> Platform.runLater(this::loadEmptyState))).start();
    }

    private void renderParticipation(JsonObject json) {
        canManage = json.has("can_manage") && json.get("can_manage").getAsBoolean();
        selectedGroupId = json.has("selected_group") && !json.get("selected_group").isJsonNull()
                ? json.getAsJsonObject("selected_group").get("id").getAsInt()
                : null;

        if (json.has("selected_group") && !json.get("selected_group").isJsonNull()) {
            JsonObject selected = json.getAsJsonObject("selected_group");
            headerSubtitleLabel.setText("Engagement in " + selected.get("name").getAsString()
                    + " using configurable criteria and manual marks.");
        } else {
            headerSubtitleLabel.setText("Track student engagement across topics, posts, and replies.");
        }

        renderCriteriaForm(json);
        participantsBox.getChildren().clear();

        JsonArray participants = json.getAsJsonArray("participants");
        if (participants.isEmpty()) {
            participantsBox.getChildren().add(buildEmptyState());
            topParticipantLabel.setGraphic(null);
            topParticipantLabel.setText("");
            return;
        }

        JsonObject first = participants.get(0).getAsJsonObject();
        topParticipantLabel.setGraphic(createHeaderStarIcon());
        topParticipantLabel.setContentDisplay(ContentDisplay.LEFT);
        topParticipantLabel.setGraphicTextGap(4);
        topParticipantLabel.setText("Top: " + first.get("name").getAsString());

        int highestScore = json.get("highest_score").getAsInt();
        for (JsonElement element : participants) {
            JsonObject participant = element.getAsJsonObject();
            int score = participant.get("score").getAsInt();
            int progress = highestScore <= 0 ? 0 : Math.round((score * 100f) / highestScore);
            participantsBox.getChildren().add(buildCard(new ParticipationCard(
                    participant.has("user_id") ? participant.get("user_id").getAsInt() : 0,
                    participant.get("name").getAsString(),
                    participant.get("topics_count").getAsInt(),
                    participant.get("posts_count").getAsInt(),
                    participant.get("replies_count").getAsInt(),
                    score,
                    participant.get("rank").getAsString(),
                    progress,
                    participant.has("auto_score") ? participant.get("auto_score").getAsInt() : score,
                    participant.has("manual_marks") ? participant.get("manual_marks").getAsInt() : 0,
                    participant.has("grade_notes") && !participant.get("grade_notes").isJsonNull()
                            ? participant.get("grade_notes").getAsString()
                            : ""
            )));
        }
    }

    private void renderCriteriaForm(JsonObject json) {
        criteriaGrid.getChildren().clear();
        criteriaFields.clear();

        if (!canManage || selectedGroupId == null || !json.has("criteria") || json.get("criteria").isJsonNull()) {
            criteriaBox.setVisible(false);
            criteriaBox.setManaged(false);
            return;
        }

        JsonObject criteria = json.getAsJsonObject("criteria");
        manualMarksMax = criteria.has("manual_marks_max") ? criteria.get("manual_marks_max").getAsInt() : 20;

        addCriteriaField(0, "Points per topic", "topic_points", criteria);
        addCriteriaField(1, "Points per post", "post_points", criteria);
        addCriteriaField(2, "Points per reply", "reply_points", criteria);
        addCriteriaField(3, "Gold min", "gold_min", criteria);
        addCriteriaField(4, "Silver min", "silver_min", criteria);
        addCriteriaField(5, "Bronze min", "bronze_min", criteria);
        addCriteriaField(6, "Max manual marks", "manual_marks_max", criteria);

        Button saveBtn = new Button("Save criteria");
        saveBtn.getStyleClass().add("btn-primary");
        saveBtn.setOnAction(event -> saveCriteria());
        criteriaGrid.add(saveBtn, 0, 2, 7, 1);

        if (criteriaGrid.getColumnConstraints().isEmpty()) {
            for (int i = 0; i < 7; i++) {
                ColumnConstraints col = new ColumnConstraints();
                col.setPercentWidth(100.0 / 7);
                col.setHgrow(Priority.SOMETIMES);
                criteriaGrid.getColumnConstraints().add(col);
            }
        }

        criteriaBox.setVisible(true);
        criteriaBox.setManaged(true);
    }

    private void addCriteriaField(int column, String labelText, String key, JsonObject criteria) {
        VBox fieldBox = new VBox(4);
        Label label = new Label(labelText);
        label.getStyleClass().add("form-label");
        TextField field = new TextField(String.valueOf(criteria.get(key).getAsInt()));
        field.setMaxWidth(Double.MAX_VALUE);
        fieldBox.getChildren().addAll(label, field);
        criteriaGrid.add(fieldBox, column, 0);
        criteriaFields.put(key, field);
    }

    private void saveCriteria() {
        if (selectedGroupId == null) {
            return;
        }

        try {
            JsonObject body = new JsonObject();
            for (Map.Entry<String, TextField> entry : criteriaFields.entrySet()) {
                body.addProperty(entry.getKey(), Integer.parseInt(entry.getValue().getText().trim()));
            }
            ApiClient.MutationResult result = ApiClient.updateParticipationCriteria(selectedGroupId, body);
            if (!result.success()) {
                showAlert(Alert.AlertType.ERROR, "Save failed", result.message());
                return;
            }
            showAlert(Alert.AlertType.INFORMATION, "Saved", "Participation criteria updated.");
            loadParticipation(selectedGroupId);
        } catch (NumberFormatException ex) {
            showAlert(Alert.AlertType.WARNING, "Invalid values", "All criteria fields must be whole numbers.");
        }
    }

    private void populateGroupFilter(JsonObject json, Integer requestedGroupId) {
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

        if (requestedGroupId != null) {
            int index = groupIds.indexOf(requestedGroupId);
            if (index >= 0) {
                groupFilterCombo.getSelectionModel().select(index + 1);
                return;
            }
        }

        if (json.has("selected_group") && !json.get("selected_group").isJsonNull()) {
            JsonObject selected = json.getAsJsonObject("selected_group");
            groupFilterCombo.getSelectionModel().select(selected.get("name").getAsString());
        } else {
            groupFilterCombo.getSelectionModel().selectFirst();
        }
    }

    private VBox buildCard(ParticipationCard card) {
        VBox cardBox = new VBox(0);
        cardBox.getStyleClass().add("participant-card");
        cardBox.setMinWidth(280);
        cardBox.setPrefWidth(280);
        cardBox.setMaxWidth(340);

        HBox top = new HBox(12);
        top.setAlignment(Pos.CENTER_LEFT);
        top.getStyleClass().add("participant-card-top");

        Label avatar = new Label(card.getInitials());
        avatar.getStyleClass().add("participant-avatar");

        VBox nameBox = new VBox(4);
        Label name = new Label(card.getName());
        name.getStyleClass().add("participant-name");
        name.setMaxWidth(Double.MAX_VALUE);

        Label rank = new Label(card.getRank());
        rank.getStyleClass().add(rankBadgeStyle(card.getRank()));
        nameBox.getChildren().addAll(name, rank);
        HBox.setHgrow(nameBox, Priority.ALWAYS);

        FontIcon trendIcon = FontIcon.of(BootstrapIcons.GRAPH_UP);
        trendIcon.getStyleClass().add("participant-trend-icon");

        top.getChildren().addAll(avatar, nameBox, trendIcon);

        HBox stats = new HBox();
        stats.setAlignment(Pos.CENTER);
        stats.getStyleClass().add("participant-stats");
        HBox.setHgrow(stats, Priority.ALWAYS);

        VBox topicsStat = createStatBlock(String.valueOf(card.getTopics()), BootstrapIcons.BOOK, "Topics");
        VBox postsStat = createStatBlock(String.valueOf(card.getPosts()), BootstrapIcons.CHAT_LEFT_TEXT, "Posts");
        VBox repliesStat = createStatBlock(String.valueOf(card.getReplies()), BootstrapIcons.REPLY, "Replies");
        HBox.setHgrow(topicsStat, Priority.ALWAYS);
        HBox.setHgrow(postsStat, Priority.ALWAYS);
        HBox.setHgrow(repliesStat, Priority.ALWAYS);
        stats.getChildren().addAll(topicsStat, postsStat, repliesStat);

        HBox scoreRow = new HBox(4);
        scoreRow.setAlignment(Pos.CENTER_LEFT);
        scoreRow.getStyleClass().add("participant-score-row");

        Label scorePrefix = new Label("Score:");
        scorePrefix.getStyleClass().add("participant-score-muted");

        Label scoreValue = new Label(String.valueOf(card.getScore()));
        scoreValue.getStyleClass().add("participant-score-value");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Label percentLabel = new Label(card.getProgress() + "%");
        percentLabel.getStyleClass().add("participant-score-muted");

        scoreRow.getChildren().addAll(scorePrefix, scoreValue, spacer, percentLabel);

        Label breakdown = new Label(
                "Auto: " + card.getAutoScore() + " · Manual: " + card.getManualMarks() + " · Total: " + card.getScore());
        breakdown.getStyleClass().add("participant-score-muted");

        ProgressBar progressBar = new ProgressBar(card.getProgress() / 100.0);
        progressBar.setMaxWidth(Double.MAX_VALUE);
        progressBar.getStyleClass().add("participant-progress");

        cardBox.getChildren().addAll(top, stats, scoreRow, breakdown, progressBar);

        if (canManage && selectedGroupId != null && card.getUserId() > 0) {
            cardBox.getChildren().add(buildManualMarksForm(card));
        }

        return cardBox;
    }

    private VBox buildManualMarksForm(ParticipationCard card) {
        VBox form = new VBox(8);
        form.getStyleClass().add("participant-manual-form");

        Label manualLabel = new Label("Manual marks");
        manualLabel.getStyleClass().add("form-label");
        TextField marksField = new TextField(String.valueOf(card.getManualMarks()));
        marksField.setPromptText("0-" + manualMarksMax);

        Label notesLabel = new Label("Notes");
        notesLabel.getStyleClass().add("form-label");
        TextField notesField = new TextField(card.getGradeNotes());
        notesField.setPromptText("Optional lecturer note");

        Button saveBtn = new Button("Save marks");
        saveBtn.getStyleClass().addAll("btn-outline-primary", "btn-sm");
        saveBtn.setOnAction(event -> {
            try {
                int marks = Integer.parseInt(marksField.getText().trim());
                JsonObject body = new JsonObject();
                body.addProperty("manual_marks", marks);
                body.addProperty("notes", notesField.getText() == null ? "" : notesField.getText().trim());
                ApiClient.MutationResult result = ApiClient.updateParticipationGrade(
                        selectedGroupId,
                        card.getUserId(),
                        body
                );
                if (!result.success()) {
                    showAlert(Alert.AlertType.ERROR, "Save failed", result.message());
                    return;
                }
                loadParticipation(selectedGroupId);
            } catch (NumberFormatException ex) {
                showAlert(Alert.AlertType.WARNING, "Invalid marks", "Manual marks must be a whole number.");
            }
        });

        form.getChildren().addAll(manualLabel, marksField, notesLabel, notesField, saveBtn);
        return form;
    }

    private VBox createStatBlock(String value, BootstrapIcons icon, String label) {
        VBox block = new VBox(2);
        block.setAlignment(Pos.CENTER);
        block.setMaxWidth(Double.MAX_VALUE);

        Label valueLabel = new Label(value);
        valueLabel.getStyleClass().add("participant-stat-value");
        valueLabel.setMaxWidth(Double.MAX_VALUE);
        valueLabel.setAlignment(Pos.CENTER);

        FontIcon statIcon = FontIcon.of(icon);
        statIcon.getStyleClass().add("participant-stat-icon");

        Label textLabel = new Label(label);
        textLabel.getStyleClass().add("participant-stat-label");

        HBox labelRow = new HBox(4, statIcon, textLabel);
        labelRow.setAlignment(Pos.CENTER);

        block.getChildren().addAll(valueLabel, labelRow);
        return block;
    }

    private String rankBadgeStyle(String rank) {
        if (rank.contains("Gold")) {
            return "badge-rank-gold";
        }
        if (rank.contains("Silver")) {
            return "badge-rank-silver";
        }
        if (rank.contains("Bronze")) {
            return "badge-rank-bronze";
        }
        return "badge-primary";
    }

    private FontIcon createHeaderStarIcon() {
        FontIcon star = FontIcon.of(BootstrapIcons.STAR_FILL);
        star.getStyleClass().add("participant-header-star");
        return star;
    }

    private VBox buildEmptyState() {
        FontIcon usersIcon = FontIcon.of(BootstrapIcons.PEOPLE);
        usersIcon.getStyleClass().add("participant-empty-icon");

        Label empty = new Label("No participation data available.");
        empty.getStyleClass().add("participant-empty-text");

        VBox box = new VBox(8, usersIcon, empty);
        box.setAlignment(Pos.CENTER);
        box.getStyleClass().add("participant-empty-card");
        box.setMaxWidth(Double.MAX_VALUE);
        return box;
    }

    private void loadEmptyState() {
        headerSubtitleLabel.setText("Track student engagement across topics, posts, and replies.");
        topParticipantLabel.setGraphic(null);
        topParticipantLabel.setText("");
        groupFilterCombo.setVisible(false);
        groupFilterCombo.setManaged(false);
        criteriaBox.setVisible(false);
        criteriaBox.setManaged(false);
        participantsBox.getChildren().clear();
        participantsBox.getChildren().add(buildEmptyState());
    }

    private void showAlert(Alert.AlertType type, String title, String message) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}
