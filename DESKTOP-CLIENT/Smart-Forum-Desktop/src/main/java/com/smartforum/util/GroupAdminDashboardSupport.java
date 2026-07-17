package com.smartforum.util;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.model.GroupAdminSummaryRow;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.collections.ListChangeListener;
import javafx.collections.ObservableList;
import javafx.geometry.Pos;
import javafx.scene.Node;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TableCell;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableRow;
import javafx.scene.control.TableView;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import org.kordamp.ikonli.bootstrapicons.BootstrapIcons;
import org.kordamp.ikonli.javafx.FontIcon;

import java.util.function.IntConsumer;

/**
 * Mirrors web {@code dashboard/partials/group-admin-groups.blade.php}.
 */
public final class GroupAdminDashboardSupport {

    private static final double ROW_HEIGHT = 34;
    private static final double HEADER_HEIGHT = 30;

    private static final String TEXT_DARK = "#111827";
    private static final String TEXT_HEADER = "#374151";
    private static final String TABLE_LIGHT = "#f9fafb";
    private static final String ROW_HOVER = "#f3f4f6";
    private static final String PRIMARY_GREEN = "#16a34a";
    private static final String PRIMARY_GREEN_HOVER = "#15803d";

    private GroupAdminDashboardSupport() {
    }

    public static ObservableList<GroupAdminSummaryRow> rowsFromApi(JsonObject dashboardJson) {
        if (dashboardJson == null || !dashboardJson.has("group_admin_stats")
                || dashboardJson.get("group_admin_stats").isJsonNull()) {
            return FXCollections.observableArrayList();
        }

        JsonArray stats = dashboardJson.getAsJsonArray("group_admin_stats");
        ObservableList<GroupAdminSummaryRow> rows = FXCollections.observableArrayList();
        for (JsonElement element : stats) {
            JsonObject summary = element.getAsJsonObject();
            rows.add(new GroupAdminSummaryRow(
                    summary.get("group_id").getAsInt(),
                    summary.get("group_name").getAsString(),
                    summary.get("members_count").getAsInt(),
                    summary.get("topics_count").getAsInt(),
                    summary.get("posts_count").getAsInt()
            ));
        }
        return rows;
    }

    /** Web: shield-check icon (text-primary) + semibold title on white header. */
    public static void configureHeader(HBox titleBox) {
        if (titleBox == null) {
            return;
        }
        titleBox.getChildren().clear();

        FontIcon icon = FontIcon.of(BootstrapIcons.SHIELD_CHECK);
        icon.getStyleClass().add("group-admin-shield-icon");

        Label title = new Label("My Groups (Group Admin)");
        title.getStyleClass().add("dashboard-card-title");

        titleBox.getChildren().addAll(icon, title);
    }

    /** Web: btn btn-primary btn-sm in group-admin card header. */
    public static void configureViewStatisticsButton(Button button) {
        if (button == null) {
            return;
        }
        button.getStyleClass().addAll("btn-primary", "btn-sm");
        applyPrimaryButtonStyle(button, PRIMARY_GREEN);
        button.hoverProperty().addListener((obs, wasHover, isHover) ->
                applyPrimaryButtonStyle(button, isHover ? PRIMARY_GREEN_HOVER : PRIMARY_GREEN));
    }

    private static void applyPrimaryButtonStyle(Button button, String background) {
        button.setStyle(
                "-fx-background-color: " + background + ";"
                        + "-fx-text-fill: white;"
                        + "-fx-font-weight: bold;"
                        + "-fx-font-size: 11px;"
                        + "-fx-background-radius: 6;"
                        + "-fx-padding: 4 10 4 10;"
                        + "-fx-cursor: hand;"
        );
    }

    public static void configureTable(
            TableView<GroupAdminSummaryRow> table,
            TableColumn<GroupAdminSummaryRow, String> groupColumn,
            TableColumn<GroupAdminSummaryRow, Number> membersColumn,
            TableColumn<GroupAdminSummaryRow, Number> topicsColumn,
            TableColumn<GroupAdminSummaryRow, Number> postsColumn,
            TableColumn<GroupAdminSummaryRow, Void> actionColumn,
            IntConsumer onGroupStats
    ) {
        if (!table.getStyleClass().contains("dashboard-table")) {
            table.getStyleClass().add("dashboard-table");
        }

        groupColumn.setCellValueFactory(new PropertyValueFactory<>("groupName"));
        groupColumn.setCellFactory(column -> new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                    setStyle("");
                } else {
                    setText(item);
                    setStyle("-fx-font-weight: bold; -fx-text-fill: " + TEXT_DARK + ";");
                }
            }
        });
        membersColumn.setCellValueFactory(new PropertyValueFactory<>("membersCount"));
        topicsColumn.setCellValueFactory(new PropertyValueFactory<>("topicsCount"));
        postsColumn.setCellValueFactory(new PropertyValueFactory<>("postsCount"));
        actionColumn.setCellValueFactory(param -> null);

        actionColumn.setCellFactory(column -> new TableCell<>() {
            private final Button statsBtn = new Button("Group Stats");

            {
                statsBtn.getStyleClass().addAll("btn-outline", "btn-sm");
                statsBtn.setOnAction(event -> {
                    GroupAdminSummaryRow row = getTableView().getItems().get(getIndex());
                    if (row != null) {
                        onGroupStats.accept(row.getGroupId());
                    }
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : statsBtn);
                setAlignment(Pos.CENTER_LEFT);
            }
        });

        table.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);
        table.setFixedCellSize(ROW_HEIGHT);
        table.setRowFactory(tv -> new TableRow<>() {
            {
                hoverProperty().addListener((obs, wasHover, isHover) -> updateRowStyle(this));
            }

            @Override
            protected void updateItem(GroupAdminSummaryRow item, boolean empty) {
                super.updateItem(item, empty);
                updateRowStyle(this);
            }
        });

        table.skinProperty().addListener((obs, oldSkin, newSkin) -> {
            if (newSkin != null) {
                Platform.runLater(() -> styleTableHeader(table));
            }
        });

        ListChangeListener<GroupAdminSummaryRow> rowListener = change -> resizeTableToRows(table);
        table.itemsProperty().addListener((obs, oldItems, newItems) -> {
            if (oldItems != null) {
                oldItems.removeListener(rowListener);
            }
            if (newItems != null) {
                newItems.addListener(rowListener);
            }
            resizeTableToRows(table);
            Platform.runLater(() -> styleTableHeader(table));
        });
    }

    public static void populateTable(
            TableView<GroupAdminSummaryRow> table,
            VBox card,
            ObservableList<GroupAdminSummaryRow> rows
    ) {
        table.setItems(rows);
        resizeTableToRows(table);
        applyVisibility(card, rows);
        Platform.runLater(() -> styleTableHeader(table));
    }

    private static void styleTableHeader(TableView<?> table) {
        if (table == null) {
            return;
        }

        table.applyCss();
        table.layout();

        Node headerBackground = table.lookup(".column-header-background");
        if (headerBackground instanceof Region region) {
            region.setStyle(
                    "-fx-background-color: " + TABLE_LIGHT + ";"
                            + "-fx-border-color: #e5e7eb;"
                            + "-fx-border-width: 0 0 1 0;"
            );
        }

        for (Node node : table.lookupAll(".column-header .label")) {
            node.setStyle("-fx-text-fill: " + TEXT_HEADER + "; -fx-font-weight: bold; -fx-font-size: 12px;");
        }
    }

    private static void updateRowStyle(TableRow<GroupAdminSummaryRow> row) {
        if (row.isEmpty()) {
            row.setStyle("");
            return;
        }
        if (row.isHover()) {
            row.setStyle("-fx-background-color: " + ROW_HOVER + ";");
        } else {
            row.setStyle("-fx-background-color: white;");
        }
    }

    private static void resizeTableToRows(TableView<?> table) {
        int rows = table.getItems() == null ? 0 : table.getItems().size();
        double height = HEADER_HEIGHT + (ROW_HEIGHT * rows) + 2;
        table.setPrefHeight(height);
        table.setMinHeight(height);
        table.setMaxHeight(height);
    }

    public static void applyVisibility(VBox card, ObservableList<GroupAdminSummaryRow> rows) {
        boolean show = rows != null && !rows.isEmpty();
        card.setVisible(show);
        card.setManaged(show);
    }
}
