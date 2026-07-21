package com.smartforum.controller;

import com.smartforum.UserSession;
import com.smartforum.api.ApiClient;
import com.smartforum.api.ApiMapper;
import com.smartforum.model.ForumUser;
import com.smartforum.service.AppSession;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.scene.control.Button;
import javafx.scene.control.ButtonType;
import javafx.scene.control.Dialog;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.layout.VBox;

public class ProfileController {

    @FXML private TextField nameField;
    @FXML private TextField emailField;
    @FXML private PasswordField currentPasswordField;
    @FXML private PasswordField newPasswordField;
    @FXML private PasswordField confirmPasswordField;
    @FXML private Label successBanner;
    @FXML private Label nameErrorLabel;
    @FXML private Label emailErrorLabel;
    @FXML private Label currentPasswordErrorLabel;
    @FXML private Label newPasswordErrorLabel;
    @FXML private Label confirmPasswordErrorLabel;

    private Runnable onUserUpdated;
    private Runnable onAccountDeleted;

    public void setOnUserUpdated(Runnable onUserUpdated) {
        this.onUserUpdated = onUserUpdated;
    }

    public void setOnAccountDeleted(Runnable onAccountDeleted) {
        this.onAccountDeleted = onAccountDeleted;
    }

    @FXML
    private void initialize() {
        ForumUser user = AppSession.getInstance().getCurrentUser();
        nameField.setText(user.getName());
        emailField.setText(user.getEmail() != null ? user.getEmail() : "");
    }

    @FXML
    private void onSaveProfile() {
        clearProfileErrors();
        ApiClient.MutationResult result = ApiClient.updateProfile(
                nameField.getText().trim(),
                emailField.getText().trim()
        );

        if (!result.success()) {
            showFieldError(nameErrorLabel, ApiClient.firstFieldError(result.body(), "name"));
            showFieldError(emailErrorLabel, ApiClient.firstFieldError(result.body(), "email"));
            if (!nameErrorLabel.isVisible() && !emailErrorLabel.isVisible()) {
                showSuccessBanner(result.message(), false);
            }
            return;
        }

        if (result.body().has("user")) {
            ForumUser updated = ApiMapper.toForumUser(result.body().getAsJsonObject("user"));
            AppSession.getInstance().setCurrentUser(updated);
            syncUserSession(updated);
            if (onUserUpdated != null) {
                onUserUpdated.run();
            }
        }

        showSuccessBanner("Profile updated successfully.", true);
    }

    @FXML
    private void onUpdatePassword() {
        clearPasswordErrors();
        ApiClient.MutationResult result = ApiClient.updatePassword(
                currentPasswordField.getText(),
                newPasswordField.getText(),
                confirmPasswordField.getText()
        );

        if (!result.success()) {
            showFieldError(currentPasswordErrorLabel, ApiClient.firstFieldError(result.body(), "current_password"));
            showFieldError(newPasswordErrorLabel, ApiClient.firstFieldError(result.body(), "password"));
            showFieldError(confirmPasswordErrorLabel, ApiClient.firstFieldError(result.body(), "password_confirmation"));
            if (!currentPasswordErrorLabel.isVisible()
                    && !newPasswordErrorLabel.isVisible()
                    && !confirmPasswordErrorLabel.isVisible()) {
                showSuccessBanner(result.message(), false);
            }
            return;
        }

        currentPasswordField.clear();
        newPasswordField.clear();
        confirmPasswordField.clear();
        showSuccessBanner("Password updated successfully.", true);
    }

    @FXML
    private void onDeleteAccount() {
        Dialog<String> dialog = new Dialog<>();
        dialog.setTitle("Delete Account");
        dialog.setHeaderText("Delete Account");
        dialog.setContentText("Enter your password to confirm permanent account deletion.");

        PasswordField passwordField = new PasswordField();
        passwordField.setPromptText("Password");
        VBox content = new VBox(8,
                new Label("Enter your password to confirm permanent account deletion."),
                passwordField
        );
        content.setPadding(new Insets(8, 0, 0, 0));
        dialog.getDialogPane().setContent(content);
        dialog.getDialogPane().getButtonTypes().addAll(ButtonType.CANCEL, ButtonType.OK);
        Button okButton = (Button) dialog.getDialogPane().lookupButton(ButtonType.OK);
        okButton.setText("Delete Account");
        okButton.getStyleClass().add("btn-danger");

        dialog.setResultConverter(button -> button == ButtonType.OK ? passwordField.getText() : null);

        dialog.showAndWait().ifPresent(password -> {
            if (password == null || password.isBlank()) {
                showSuccessBanner("Password is required to delete your account.", false);
                return;
            }

            ApiClient.MutationResult result = ApiClient.deleteAccount(password);
            if (!result.success()) {
                String fieldError = ApiClient.firstFieldError(result.body(), "password");
                showSuccessBanner(fieldError != null ? fieldError : result.message(), false);
                return;
            }

            if (onAccountDeleted != null) {
                onAccountDeleted.run();
            }
        });
    }

    private void syncUserSession(ForumUser user) {
        UserSession session = UserSession.getInstance();
        String[] parts = splitName(user.getName());
        session.setUser(
                user.getId(),
                parts[0],
                parts[1],
                user.getEmail(),
                user.getSystemRole(),
                session.getToken()
        );
    }

    private String[] splitName(String name) {
        if (name == null || name.isBlank()) {
            return new String[] {"", ""};
        }
        String trimmed = name.trim();
        int space = trimmed.indexOf(' ');
        if (space < 0) {
            return new String[] {trimmed, ""};
        }
        return new String[] {trimmed.substring(0, space), trimmed.substring(space + 1).trim()};
    }

    private void clearProfileErrors() {
        hideFieldError(nameErrorLabel);
        hideFieldError(emailErrorLabel);
    }

    private void clearPasswordErrors() {
        hideFieldError(currentPasswordErrorLabel);
        hideFieldError(newPasswordErrorLabel);
        hideFieldError(confirmPasswordErrorLabel);
    }

    private void showFieldError(Label label, String message) {
        if (message == null || message.isBlank()) {
            hideFieldError(label);
            return;
        }
        label.setText(message);
        label.setVisible(true);
        label.setManaged(true);
    }

    private void hideFieldError(Label label) {
        label.setText("");
        label.setVisible(false);
        label.setManaged(false);
    }

    private void showSuccessBanner(String message, boolean success) {
        successBanner.setText(message);
        successBanner.getStyleClass().removeAll("profile-alert-success", "profile-alert-error");
        successBanner.getStyleClass().add(success ? "profile-alert-success" : "profile-alert-error");
        successBanner.setVisible(true);
        successBanner.setManaged(true);
    }
}
