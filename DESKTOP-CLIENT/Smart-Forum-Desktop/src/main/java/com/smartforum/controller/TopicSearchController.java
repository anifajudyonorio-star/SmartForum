package com.smartforum.controller;

import com.smartforum.api.ApiClient;
import com.smartforum.model.RecommendedTopic;
import com.smartforum.model.TopicSearchResult;
import com.smartforum.model.Group;
import com.smartforum.service.AppSession;
import com.smartforum.service.GroupService;
import com.smartforum.service.TopicService;
import javafx.application.Platform;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ButtonBar;
import javafx.scene.control.ButtonType;
import javafx.scene.control.Label;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.List;
import java.util.Optional;
import java.util.function.Consumer;

/**
 * Mirrors web {@code TopicController@search} and {@code topics/search.blade.php}.
 */
public class TopicSearchController {

    @FXML private TextField searchField;
    @FXML private Label resultsSummaryLabel;
    @FXML private VBox recommendationsBox;
    @FXML private FlowPane recommendationsGrid;
    @FXML private FlowPane resultsGrid;
    @FXML private VBox emptyStateBox;
    @FXML private Label emptyStateLabel;
    @FXML private Button clearSearchBtn;
    @FXML private Button exploreGroupsBtn;

    private String currentQuery = "";
    private ShellNavigator navigator;
    private Consumer<String> pageTitleUpdater;
    private Region rootNode;

    private final TopicService topicService = TopicService.getInstance();
    private final GroupService groupService = GroupService.getInstance();

    public Region getRootNode() {
        return rootNode;
    }

    public void setRootNode(Region rootNode) {
        this.rootNode = rootNode;
    }

    public void setNavigator(ShellNavigator navigator) {
        this.navigator = navigator;
    }

    public void setPageTitleUpdater(Consumer<String> pageTitleUpdater) {
        this.pageTitleUpdater = pageTitleUpdater;
    }

    @FXML
    private void initialize() {
        // Search runs after the shell wires the navigator.
    }

    /** Web: search() initial page */
    public void index() {
        updateTitle("Search Topics");
        loadRecommendations();
        search("");
    }

    private void loadRecommendations() {


        new Thread(() -> {
            List<RecommendedTopic> recommendations = ApiClient.fetchRecommendations();
            Platform.runLater(() -> renderRecommendations(recommendations));
        }).start();
    }

    private void renderRecommendations(List<RecommendedTopic> recommendations) {
        recommendationsGrid.getChildren().clear();
        if (recommendations == null || recommendations.isEmpty()) {
            recommendationsBox.setVisible(false);
            recommendationsBox.setManaged(false);
            return;
        }

        recommendationsBox.setVisible(true);
        recommendationsBox.setManaged(true);
        for (RecommendedTopic topic : recommendations) {
            recommendationsGrid.getChildren().add(buildRecommendationCard(topic));
        }
    }

    private VBox buildRecommendationCard(RecommendedTopic topic) {
        Label title = new Label(topic.title());
        title.getStyleClass().add("group-card-title");
        title.setWrapText(true);

        Label scoreBadge = new Label(String.format("%.2f", topic.score()));
        scoreBadge.getStyleClass().add("badge-primary");

        HBox titleRow = new HBox(8, title);
        titleRow.setAlignment(Pos.CENTER_LEFT);
        HBox.setHgrow(title, Priority.ALWAYS);
        titleRow.getChildren().add(scoreBadge);

        Label desc = new Label(truncate(topic.description(), 100));
        desc.getStyleClass().add("group-card-desc");
        desc.setWrapText(true);

        Label groupLabel = new Label(topic.groupName());
        groupLabel.getStyleClass().add("list-item-meta");

        HBox footer = new HBox(8);
        footer.setAlignment(Pos.CENTER_LEFT);

        if (topic.canView() || AppSession.getInstance().isSystemAdmin()) {
            Button openBtn = new Button(AppSession.getInstance().isSystemAdmin() && !topic.canView()
                    ? "Open group"
                    : "View recommendation");
            openBtn.getStyleClass().add("btn-outline-primary");
            openBtn.setOnAction(event -> {
                if (navigator == null) {
                    return;
                }
                if (topic.canView()) {
                    navigator.showTopic(topic.id());
                } else {
                    navigator.showGroup(topic.groupId());
                }
            });
            footer.getChildren().add(openBtn);
        } else {
            Button joinBtn = new Button("Request to Join");
            joinBtn.getStyleClass().add("btn-outline-secondary");
            if ("pending".equalsIgnoreCase(topic.joinStatus())) {
                joinBtn.setText("Pending approval");
                joinBtn.setDisable(true);
            } else if ("blocked".equalsIgnoreCase(topic.joinStatus())) {
                joinBtn.setText("Cannot join");
                joinBtn.setDisable(true);
            } else {
                joinBtn.setOnAction(event -> handleRecommendationJoin(topic, joinBtn));
            }
            footer.getChildren().add(joinBtn);
        }

        VBox card = new VBox(8, titleRow, desc, groupLabel, footer);
        card.getStyleClass().add("group-card-modern");
        card.setMinWidth(280);
        card.setMaxWidth(340);
        if (topic.canView() || AppSession.getInstance().isSystemAdmin()) {
            card.setOnMouseClicked(event -> {
                if (navigator == null) {
                    return;
                }
                if (topic.canView()) {
                    navigator.showTopic(topic.id());
                } else {
                    navigator.showGroup(topic.groupId());
                }
            });
        }
        return card;
    }

    private void handleRecommendationJoin(RecommendedTopic topic, Button joinBtn) {
        Group group = groupService.findExploreGroup(topic.groupId())
                .or(() -> groupService.getGroup(topic.groupId()))
                .orElse(null);
        if (group == null) {
            showInfo("Group unavailable", "Could not load group details.");
            return;
        }
        if (group.hasJoinRules()) {
            Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
            alert.setTitle("Group rules — " + group.getName());
            alert.setHeaderText("Please read the rules below. You must agree before your join request can be sent to the group admin.");
            TextArea rulesArea = new TextArea(group.getJoinRules());
            rulesArea.setEditable(false);
            rulesArea.setWrapText(true);
            rulesArea.setPrefRowCount(8);
            rulesArea.setMaxWidth(Double.MAX_VALUE);
            alert.getDialogPane().setContent(rulesArea);
            ButtonType accept = new ButtonType("Agree & Request to Join", ButtonBar.ButtonData.OK_DONE);
            alert.getButtonTypes().setAll(ButtonType.CANCEL, accept);
            Optional<ButtonType> choice = alert.showAndWait();
            if (choice.isEmpty() || choice.get() != accept) {
                return;
            }
        }

        if (groupService.requestJoinGroup(topic.groupId(), true)) {
            joinBtn.setText("Pending approval");
            joinBtn.setDisable(true);
            showInfo("Request sent", "Your request to join \"" + group.getName() + "\" was sent for admin approval.");
        } else {
            showInfo("Request failed", "Could not send your join request.");
        }
    }

    private void showInfo(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    @FXML
    public void onSearch(ActionEvent event) {
        String query = searchField.getText() == null ? "" : searchField.getText().trim();
        search(query);
    }

    @FXML
    public void onClearSearch(ActionEvent event) {
        searchField.clear();
        search("");
    }

    @FXML
    public void onExploreGroups(ActionEvent event) {
        if (navigator != null) {
            navigator.showExploreGroups();
        }
    }

    public void search(String query) {
        currentQuery = query == null ? "" : query.trim();
        if (searchField != null) {
            searchField.setText(currentQuery);
        }

        try {
            if (!topicService.hasSearchableGroups()) {
                showNoGroupsState();
                return;
            }

            List<TopicSearchResult> results = topicService.searchTopics(currentQuery);
            resultsGrid.getChildren().clear();

            if (!currentQuery.isEmpty()) {
                resultsSummaryLabel.setText(results.size() + " result(s) for \"" + currentQuery + "\"");
                resultsSummaryLabel.setVisible(true);
                resultsSummaryLabel.setManaged(true);
            } else {
                resultsSummaryLabel.setVisible(false);
                resultsSummaryLabel.setManaged(false);
            }

            if (results.isEmpty()) {
                showEmptyResultsState();
                return;
            }

            emptyStateBox.setVisible(false);
            emptyStateBox.setManaged(false);

            for (TopicSearchResult result : results) {
                resultsGrid.getChildren().add(buildResultCard(result));
            }
        } catch (RuntimeException e) {
            resultsGrid.getChildren().clear();
            resultsSummaryLabel.setVisible(false);
            resultsSummaryLabel.setManaged(false);
            emptyStateLabel.setText("Topic search is unavailable right now. Check your connection and try again.");
            clearSearchBtn.setVisible(false);
            clearSearchBtn.setManaged(false);
            exploreGroupsBtn.setVisible(false);
            exploreGroupsBtn.setManaged(false);
            emptyStateBox.setVisible(true);
            emptyStateBox.setManaged(true);
        }
    }

    private void showNoGroupsState() {
        resultsGrid.getChildren().clear();
        resultsSummaryLabel.setVisible(false);
        resultsSummaryLabel.setManaged(false);
        emptyStateLabel.setText("Join a group to start searching its topics.");
        clearSearchBtn.setVisible(false);
        clearSearchBtn.setManaged(false);
        exploreGroupsBtn.setVisible(true);
        exploreGroupsBtn.setManaged(true);
        emptyStateBox.setVisible(true);
        emptyStateBox.setManaged(true);
    }

    private void showEmptyResultsState() {
        if (!currentQuery.isEmpty()) {
            emptyStateLabel.setText("No topics matched your search.");
            clearSearchBtn.setVisible(true);
            clearSearchBtn.setManaged(true);
            exploreGroupsBtn.setVisible(false);
            exploreGroupsBtn.setManaged(false);
        } else {
            emptyStateLabel.setText("No topics found in your groups yet.");
            clearSearchBtn.setVisible(false);
            clearSearchBtn.setManaged(false);
            exploreGroupsBtn.setVisible(false);
            exploreGroupsBtn.setManaged(false);
        }
        emptyStateBox.setVisible(true);
        emptyStateBox.setManaged(true);
    }

    private VBox buildResultCard(TopicSearchResult result) {
        Label title = new Label(result.topic().getTitle());
        title.getStyleClass().add("group-card-title");
        title.setWrapText(true);

        Label postsBadge = new Label(result.postsCount() + " posts");
        postsBadge.getStyleClass().add("badge-primary");

        HBox titleRow = new HBox(8, title);
        titleRow.setAlignment(Pos.CENTER_LEFT);
        HBox.setHgrow(title, Priority.ALWAYS);
        titleRow.getChildren().add(postsBadge);

        Label groupBadge = new Label(result.groupName());
        groupBadge.getStyleClass().add("group-topics-badge");

        Label desc = new Label(truncate(result.topic().getDescription(), 100));
        desc.getStyleClass().add("group-card-desc");
        desc.setWrapText(true);

        Label meta = new Label(result.topic().getAuthorName());
        meta.getStyleClass().add("list-item-meta");

        Button openBtn = new Button("Open");
        openBtn.getStyleClass().add("btn-primary");
        openBtn.setOnAction(event -> {
            if (navigator != null) {
                navigator.showTopic(result.topic().getId());
            }
        });

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        HBox footer = new HBox(8, meta, spacer, openBtn);
        footer.setAlignment(Pos.CENTER_LEFT);

        VBox card = new VBox(8, titleRow, groupBadge, desc, footer);
        card.getStyleClass().add("group-card-modern");
        card.setMinWidth(280);
        card.setMaxWidth(340);
        card.setOnMouseClicked(event -> {
            if (navigator != null) {
                navigator.showTopic(result.topic().getId());
            }
        });

        return card;
    }

    private void updateTitle(String title) {
        if (pageTitleUpdater != null) {
            pageTitleUpdater.accept(title);
        }
    }

    private String truncate(String text, int max) {
        if (text == null || text.isBlank()) {
            return "No description.";
        }
        return text.length() <= max ? text : text.substring(0, max - 3) + "...";
    }
}
