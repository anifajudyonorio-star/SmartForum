package com.smartforum.controller;

import com.smartforum.model.TopicSearchResult;
import com.smartforum.service.TopicService;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

import java.util.List;
import java.util.function.Consumer;

/**
 * Mirrors web {@code TopicController@search} and {@code topics/search.blade.php}.
 */
public class TopicSearchController {

    @FXML private TextField searchField;
    @FXML private Label resultsSummaryLabel;
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
        search("");
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
