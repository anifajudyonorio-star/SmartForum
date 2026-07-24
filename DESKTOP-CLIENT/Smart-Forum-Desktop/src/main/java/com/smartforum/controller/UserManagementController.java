package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.*;

import java.util.ArrayList;
import java.util.List;

public class UserManagementController {

    @FXML private VBox userListBox;
    @FXML private Label statusLabel;
    @FXML private Button addLecturerBtn;

    // Add lecturer form fields (inline panel)
    @FXML private VBox addLecturerPanel;
    @FXML private TextField fnameField;
    @FXML private TextField lnameField;
    @FXML private TextField emailField;
    @FXML private PasswordField passwordField;
    @FXML private PasswordField passwordConfirmField;
    @FXML private Label addLecturerError;

    private record UserRow(int id, String fname, String lname, String email,
                           String role, int warnings, boolean blacklisted) {}

    private final List<UserRow> users = new ArrayList<>();

    @FXML
    private void initialize() {
        addLecturerPanel.setVisible(false);
        addLecturerPanel.setManaged(false);
        loadUsers();
    }

    private void loadUsers() {
        statusLabel.setText("Loading…");
        userListBox.getChildren().clear();
        new Thread(() -> ApiClient.getAdminUsers().ifPresentOrElse(json -> {
            JsonArray arr = json.getAsJsonArray("users");
            users.clear();
            for (var el : arr) {
                JsonObject u = el.getAsJsonObject();
                users.add(new UserRow(
                        u.get("id").getAsInt(),
                        u.get("Fname").getAsString(),
                        u.get("Lname").getAsString(),
                        u.get("email").getAsString(),
                        u.get("role").getAsString(),
                        u.get("warnings").getAsInt(),
                        u.get("is_blacklisted").getAsBoolean()
                ));
            }
            Platform.runLater(() -> {
                statusLabel.setText("");
                renderUsers();
            });
        }, () -> Platform.runLater(() -> statusLabel.setText("Failed to load users.")))).start();
    }

    private void renderUsers() {
        userListBox.getChildren().clear();
        if (users.isEmpty()) {
            Label empty = new Label("No users found.");
            empty.getStyleClass().add("empty-label");
            userListBox.getChildren().add(empty);
            return;
        }
        for (UserRow u : users) {
            userListBox.getChildren().add(buildUserCard(u));
        }
    }

    private VBox buildUserCard(UserRow u) {
        VBox card = new VBox(8);
        card.getStyleClass().add("um-user-card");
        if (u.blacklisted()) card.getStyleClass().add("um-user-card-blacklisted");

        // Top row: avatar + info
        Label avatar = new Label(
                u.fname() == null || u.fname().isBlank()
                        ? "?"
                        : u.fname().substring(0, 1).toUpperCase()
        );
        avatar.getStyleClass().add("um-avatar");

        Label name = new Label(u.fname() + " " + u.lname());
        name.getStyleClass().add("um-user-name");

        Label email = new Label(u.email());
        email.getStyleClass().add("um-user-email");

        VBox info = new VBox(2, name, email);
        info.setAlignment(Pos.CENTER_LEFT);
        HBox.setHgrow(info, Priority.ALWAYS);

        // Badges
        Label roleBadge = new Label(formatRole(u.role()));
        roleBadge.getStyleClass().addAll("um-badge", "um-badge-role");

        String warnStyle = u.warnings() >= 2 ? "um-badge-danger" : u.warnings() == 1 ? "um-badge-warning" : "um-badge-muted";
        Label warnBadge = new Label(u.warnings() + "/2 warns");
        warnBadge.getStyleClass().addAll("um-badge", warnStyle);

        Label statusBadge = new Label(u.blacklisted() ? "Blacklisted" : "Active");
        statusBadge.getStyleClass().addAll("um-badge", u.blacklisted() ? "um-badge-danger" : "um-badge-success");

        HBox badges = new HBox(6, roleBadge, warnBadge, statusBadge);
        badges.setAlignment(Pos.CENTER_LEFT);

        HBox topRow = new HBox(10, avatar, info);
        topRow.setAlignment(Pos.CENTER_LEFT);

        // Action buttons
        HBox actions = buildActions(u);

        card.getChildren().addAll(topRow, badges, actions);
        return card;
    }

    private HBox buildActions(UserRow u) {
        HBox actions = new HBox(6);
        actions.setAlignment(Pos.CENTER_LEFT);

        if (!u.blacklisted()) {
            Button warnBtn = new Button("⚠ Warn");
            warnBtn.getStyleClass().addAll("btn-warning", "btn-sm");
            warnBtn.setOnAction(e -> showReasonDialog("Warn " + u.fname() + " (" + u.warnings() + "/2 warnings)",
                    u.warnings() == 1 ? "⚠ Final warning — user will be blacklisted." : null,
                    reason -> doAction(() -> ApiClient.adminWarnUser(u.id(), reason))));

            Button blacklistBtn = new Button("⛔ Blacklist");
            blacklistBtn.getStyleClass().addAll("btn-danger", "btn-sm");
            blacklistBtn.setOnAction(e -> showReasonDialog("Blacklist " + u.fname(),
                    "This will immediately block the user from accessing the platform.",
                    reason -> doAction(() -> ApiClient.adminBlacklistUser(u.id(), reason))));

            Button roleBtn = new Button("🛡 Role");
            roleBtn.getStyleClass().addAll("btn-outline", "btn-sm");
            roleBtn.setOnAction(e -> showRoleDialog(u));

            actions.getChildren().addAll(warnBtn, blacklistBtn, roleBtn);
        } else {
            Button reinstateBtn = new Button("✔ Reinstate");
            reinstateBtn.getStyleClass().addAll("btn-success", "btn-sm");
            reinstateBtn.setOnAction(e -> doAction(() -> ApiClient.adminUnblacklistUser(u.id())));
            actions.getChildren().add(reinstateBtn);
        }
        return actions;
    }

    private void showReasonDialog(String title, String warning, java.util.function.Consumer<String> onConfirm) {
        Dialog<String> dialog = new Dialog<>();
        dialog.setTitle(title);
        dialog.setHeaderText(null);

        VBox content = new VBox(8);
        content.setPrefWidth(320);
        if (warning != null) {
            Label warn = new Label(warning);
            warn.getStyleClass().add("um-dialog-warning");
            warn.setWrapText(true);
            content.getChildren().add(warn);
        }
        Label reasonLabel = new Label("Reason (optional)");
        reasonLabel.getStyleClass().add("form-label");
        TextArea reasonArea = new TextArea();
        reasonArea.setPrefRowCount(2);
        reasonArea.getStyleClass().add("form-textarea");
        content.getChildren().addAll(reasonLabel, reasonArea);

        dialog.getDialogPane().setContent(content);
        dialog.getDialogPane().getButtonTypes().addAll(ButtonType.OK, ButtonType.CANCEL);
        dialog.setResultConverter(bt -> bt == ButtonType.OK ? reasonArea.getText() : null);
        dialog.showAndWait().ifPresent(onConfirm);
    }

    private void showRoleDialog(UserRow u) {
        ChoiceDialog<String> dialog = new ChoiceDialog<>(u.role(), "student", "lecturer", "admin");
        dialog.setTitle("Change Role — " + u.fname());
        dialog.setHeaderText("Select new role for " + u.fname() + " " + u.lname());
        dialog.setContentText("Role:");
        dialog.showAndWait().ifPresent(role -> doAction(() -> ApiClient.adminChangeRole(u.id(), role)));
    }

    private void doAction(java.util.function.Supplier<ApiClient.MutationResult> action) {
        new Thread(() -> {
            var result = action.get();
            Platform.runLater(() -> {
                statusLabel.setText(result.message());
                if (result.success()) loadUsers();
            });
        }).start();
    }

    @FXML
    private void toggleAddLecturer() {
        boolean show = !addLecturerPanel.isVisible();
        addLecturerPanel.setVisible(show);
        addLecturerPanel.setManaged(show);
        addLecturerError.setText("");
        if (!show) clearAddForm();
    }

    @FXML
    private void submitAddLecturer() {
        String fname = fnameField.getText().trim();
        String lname = lnameField.getText().trim();
        String email = emailField.getText().trim();
        String pass = passwordField.getText();
        String confirm = passwordConfirmField.getText();

        if (fname.isEmpty() || lname.isEmpty() || email.isEmpty() || pass.isEmpty()) {
            addLecturerError.setText("All fields are required.");
            return;
        }
        if (!pass.equals(confirm)) {
            addLecturerError.setText("Passwords do not match.");
            return;
        }
        addLecturerError.setText("");
        new Thread(() -> {
            var result = ApiClient.adminCreateLecturer(fname, lname, email, pass);
            Platform.runLater(() -> {
                if (result.success()) {
                    clearAddForm();
                    addLecturerPanel.setVisible(false);
                    addLecturerPanel.setManaged(false);
                    statusLabel.setText(result.message());
                    loadUsers();
                } else {
                    addLecturerError.setText(result.message());
                }
            });
        }).start();
    }

    @FXML
    private void cancelAddLecturer() {
        addLecturerPanel.setVisible(false);
        addLecturerPanel.setManaged(false);
        clearAddForm();
    }

    private void clearAddForm() {
        fnameField.clear();
        lnameField.clear();
        emailField.clear();
        passwordField.clear();
        passwordConfirmField.clear();
    }

    private String formatRole(String role) {
        return switch (role.toLowerCase()) {
            case "admin" -> "Super Admin";
            case "lecturer" -> "Lecturer";
            default -> "Student";
        };
    }
}
