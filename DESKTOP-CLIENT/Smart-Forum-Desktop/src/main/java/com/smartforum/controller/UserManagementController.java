package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.model.AdminUserRow;
import com.smartforum.util.ApiSupport;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.HBox;
import javafx.stage.Window;

import java.util.ArrayList;
import java.util.List;
import java.util.Optional;
import java.util.function.Supplier;

public class UserManagementController {

    @FXML private Label userCountLabel;
    @FXML private TableView<AdminUserRow> usersTable;
    @FXML private TableColumn<AdminUserRow, String> colName;
    @FXML private TableColumn<AdminUserRow, String> colEmail;
    @FXML private TableColumn<AdminUserRow, String> colRole;
    @FXML private TableColumn<AdminUserRow, String> colWarnings;
    @FXML private TableColumn<AdminUserRow, String> colStatus;
    @FXML private TableColumn<AdminUserRow, Void> colActions;

    @FXML
    private void initialize() {
        colName.setCellValueFactory(new PropertyValueFactory<>("name"));
        colEmail.setCellValueFactory(new PropertyValueFactory<>("email"));
        colRole.setCellValueFactory(new PropertyValueFactory<>("role"));
        colWarnings.setCellValueFactory(cell -> new javafx.beans.property.SimpleStringProperty(
                cell.getValue() == null ? "" : cell.getValue().getWarningsLabel()));
        colStatus.setCellValueFactory(cell -> new javafx.beans.property.SimpleStringProperty(
                cell.getValue() == null ? "" : cell.getValue().getStatusLabel()));
        colActions.setCellFactory(column -> actionCell());
        usersTable.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);
        loadUsers();
    }

    @FXML
    private void showAddLecturerDialog() {
        Dialog<ButtonType> dialog = new Dialog<>();
        dialog.setTitle("Add Lecturer");
        dialog.setHeaderText("Create a new lecturer account");
        attachDialogOwner(dialog);

        TextField firstName = new TextField();
        firstName.setPromptText("First name");
        TextField lastName = new TextField();
        lastName.setPromptText("Last name");
        TextField email = new TextField();
        email.setPromptText("Email");
        PasswordField password = new PasswordField();
        password.setPromptText("Password");
        PasswordField confirm = new PasswordField();
        confirm.setPromptText("Confirm password");

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.add(new Label("First Name"), 0, 0);
        grid.add(firstName, 1, 0);
        grid.add(new Label("Last Name"), 0, 1);
        grid.add(lastName, 1, 1);
        grid.add(new Label("Email"), 0, 2);
        grid.add(email, 1, 2);
        grid.add(new Label("Password"), 0, 3);
        grid.add(password, 1, 3);
        grid.add(new Label("Confirm"), 0, 4);
        grid.add(confirm, 1, 4);
        dialog.getDialogPane().setContent(grid);
        dialog.getDialogPane().getButtonTypes().addAll(ButtonType.OK, ButtonType.CANCEL);

        dialog.showAndWait().ifPresent(result -> {
            if (result != ButtonType.OK) {
                return;
            }
            if (firstName.getText().isBlank() || lastName.getText().isBlank() || email.getText().isBlank()
                    || password.getText().isBlank()) {
                alert("Missing fields", "Please fill in all fields.");
                return;
            }
            if (!password.getText().equals(confirm.getText())) {
                alert("Password mismatch", "Password confirmation does not match.");
                return;
            }
            new Thread(() -> {
                ApiClient.MutationResult response = ApiClient.createAdminUser(
                        firstName.getText().trim(),
                        lastName.getText().trim(),
                        email.getText().trim(),
                        password.getText(),
                        confirm.getText());
                Platform.runLater(() -> {
                    if (response.success()) {
                        alert("Success", response.message());
                        loadUsers();
                    } else {
                        alert("Could not create user", response.message());
                    }
                });
            }).start();
        });
    }

    private void loadUsers() {
        if (!ApiSupport.useApi()) {
            usersTable.setItems(FXCollections.observableArrayList());
            userCountLabel.setText("0");
            return;
        }

        new Thread(() -> ApiClient.getAdminUsers().ifPresentOrElse(json -> Platform.runLater(() -> {
            List<AdminUserRow> rows = parseUsers(json.getAsJsonArray("users"));
            usersTable.setItems(FXCollections.observableArrayList(rows));
            userCountLabel.setText(String.valueOf(rows.size()));
        }), () -> Platform.runLater(() -> {
            usersTable.setItems(FXCollections.observableArrayList());
            userCountLabel.setText("0");
        }))).start();
    }

    private List<AdminUserRow> parseUsers(JsonArray array) {
        List<AdminUserRow> rows = new ArrayList<>();
        for (JsonElement element : array) {
            JsonObject item = element.getAsJsonObject();
            AdminUserRow row = new AdminUserRow();
            row.setId(item.get("id").getAsInt());
            row.setName(item.get("name").getAsString());
            row.setEmail(item.get("email").getAsString());
            row.setRole(item.get("role").getAsString());
            row.setWarnings(item.get("warnings").getAsInt());
            row.setBlacklisted(item.get("is_blacklisted").getAsBoolean());
            rows.add(row);
        }
        return rows;
    }

    private TableCell<AdminUserRow, Void> actionCell() {
        return new TableCell<>() {
            private final Button warnBtn = smallButton("Warn", "btn-outline");
            private final Button blacklistBtn = smallButton("Blacklist", "btn-danger");
            private final Button roleBtn = smallButton("Role", "btn-outline");
            private final Button reinstateBtn = smallButton("Reinstate", "btn-primary");
            private final HBox activeBox = new HBox(6, warnBtn, blacklistBtn, roleBtn);
            private final HBox blockedBox = new HBox(6, reinstateBtn);

            {
                activeBox.setAlignment(Pos.CENTER_LEFT);
                blockedBox.setAlignment(Pos.CENTER_LEFT);
                warnBtn.setOnAction(e -> {
                    e.consume();
                    AdminUserRow user = currentRow();
                    if (user != null) {
                        warnUser(user);
                    }
                });
                blacklistBtn.setOnAction(e -> {
                    e.consume();
                    AdminUserRow user = currentRow();
                    if (user != null) {
                        blacklistUser(user);
                    }
                });
                roleBtn.setOnAction(e -> {
                    e.consume();
                    AdminUserRow user = currentRow();
                    if (user != null) {
                        changeRole(user);
                    }
                });
                reinstateBtn.setOnAction(e -> {
                    e.consume();
                    AdminUserRow user = currentRow();
                    if (user != null) {
                        reinstateUser(user);
                    }
                });
            }

            private AdminUserRow currentRow() {
                if (getTableRow() != null && getTableRow().getItem() != null) {
                    return getTableRow().getItem();
                }
                int index = getIndex();
                if (index >= 0 && index < getTableView().getItems().size()) {
                    return getTableView().getItems().get(index);
                }
                return null;
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || currentRow() == null) {
                    setGraphic(null);
                    return;
                }
                setGraphic(currentRow().isBlacklisted() ? blockedBox : activeBox);
                setAlignment(Pos.CENTER_LEFT);
            }
        };
    }

    private Button smallButton(String text, String styleClass) {
        Button button = new Button(text);
        button.getStyleClass().addAll("button", "btn-sm", styleClass);
        button.setMinHeight(Button.USE_PREF_SIZE);
        button.setFocusTraversable(false);
        return button;
    }

    private void warnUser(AdminUserRow user) {
        Optional<String> reason = promptReason("Warn " + user.getName(), "Reason (optional)");
        if (reason.isEmpty()) {
            return;
        }
        runMutation(() -> ApiClient.warnAdminUser(user.getId(), reason.get()));
    }

    private void blacklistUser(AdminUserRow user) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION, "Blacklist " + user.getName() + "?", ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText("This will block the user from accessing the platform.");
        attachDialogOwner(confirm);
        confirm.showAndWait().ifPresent(result -> {
            if (result != ButtonType.YES) {
                return;
            }
            Optional<String> reason = promptReason("Blacklist " + user.getName(), "Reason (optional)");
            if (reason.isEmpty()) {
                return;
            }
            runMutation(() -> ApiClient.blacklistAdminUser(user.getId(), reason.get()));
        });
    }

    private void reinstateUser(AdminUserRow user) {
        runMutation(() -> ApiClient.unblacklistAdminUser(user.getId()));
    }

    private void changeRole(AdminUserRow user) {
        ChoiceDialog<String> dialog = new ChoiceDialog<>(user.getRole(), "student", "lecturer", "admin");
        dialog.setTitle("Change Role");
        dialog.setHeaderText("Change role for " + user.getName());
        dialog.setContentText("Select role:");
        attachDialogOwner(dialog);
        dialog.showAndWait().ifPresent(role -> runMutation(() -> ApiClient.promoteAdminUser(user.getId(), role)));
    }

    private Optional<String> promptReason(String title, String prompt) {
        TextInputDialog dialog = new TextInputDialog("");
        dialog.setTitle(title);
        dialog.setHeaderText(null);
        dialog.setContentText(prompt);
        attachDialogOwner(dialog);
        return dialog.showAndWait();
    }

    private void attachDialogOwner(Dialog<?> dialog) {
        Window owner = ownerWindow();
        if (owner != null) {
            dialog.initOwner(owner);
        }
    }

    private Window ownerWindow() {
        if (usersTable != null && usersTable.getScene() != null) {
            return usersTable.getScene().getWindow();
        }
        return null;
    }

    private void runMutation(Supplier<ApiClient.MutationResult> action) {
        new Thread(() -> {
            ApiClient.MutationResult result = action.get();
            Platform.runLater(() -> {
                if (result.success()) {
                    alert("Success", result.message());
                    loadUsers();
                } else {
                    alert("Action failed", result.message());
                }
            });
        }).start();
    }

    private void alert(String title, String message) {
        Alert alert = new Alert(resultAlertType(title), message, ButtonType.OK);
        alert.setTitle(title);
        alert.setHeaderText(null);
        attachDialogOwner(alert);
        alert.showAndWait();
    }

    private Alert.AlertType resultAlertType(String title) {
        return "Action failed".equals(title) || title.startsWith("Could not")
                ? Alert.AlertType.ERROR
                : Alert.AlertType.INFORMATION;
    }
}
