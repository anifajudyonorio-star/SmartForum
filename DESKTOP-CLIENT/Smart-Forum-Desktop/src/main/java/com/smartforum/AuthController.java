package com.smartforum;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.canvas.Canvas;
import javafx.stage.Stage;
import javafx.scene.canvas.GraphicsContext;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.scene.paint.Color;
import javafx.scene.shape.ArcType;
import javafx.scene.text.Font;
import javafx.scene.text.FontWeight;

import com.smartforum.model.ForumUser;
import com.smartforum.service.AppSession;

import java.awt.Desktop;
import java.net.URI;

public class AuthController {

    @FXML private Button signInTab, registerTab;
    @FXML private VBox signInPane, registerPane;
    @FXML private Label errorLabel;
    @FXML private Button googleSignInBtn, googleRegisterBtn;

    // Sign in fields
    @FXML private TextField loginEmail;
    @FXML private PasswordField loginPassword;
    @FXML private TextField loginPasswordVisible;
    @FXML private Button loginEyeBtn;

    // Register fields
    @FXML private TextField regFname, regLname, regEmail;
    @FXML private PasswordField regPassword, regPasswordConfirm;
    @FXML private TextField regPasswordVisible, regPasswordConfirmVisible;
    @FXML private Button regEyeBtn, regConfirmEyeBtn;
    @FXML private CheckBox termsCheck;

    // Track visibility state
    private boolean loginPassVisible = false;
    private boolean regPassVisible = false;
    private boolean regConfirmVisible = false;

    @FXML
    public void initialize() {
        setGoogleButtonGraphic(googleSignInBtn, "Continue with Google");
        setGoogleButtonGraphic(googleRegisterBtn, "Continue with Google");
    }

    private void setGoogleButtonGraphic(Button btn, String label) {
        // Draw the Google multicolor "G" on a canvas
        Canvas canvas = new Canvas(18, 18);
        GraphicsContext gc = canvas.getGraphicsContext2D();

        // White circle background
        gc.setFill(Color.WHITE);
        gc.fillOval(0, 0, 18, 18);

        // Draw the 4-color G arc segments
        double cx = 9, cy = 9, r = 8;
        // Blue (top-right)
        gc.setFill(Color.web("#4285F4"));
        gc.fillArc(cx - r, cy - r, r * 2, r * 2, -10, 100, ArcType.ROUND);
        // Red (top-left)
        gc.setFill(Color.web("#EA4335"));
        gc.fillArc(cx - r, cy - r, r * 2, r * 2, 90, 110, ArcType.ROUND);
        // Yellow (bottom-left)
        gc.setFill(Color.web("#FBBC05"));
        gc.fillArc(cx - r, cy - r, r * 2, r * 2, 200, 80, ArcType.ROUND);
        // Green (bottom-right)
        gc.setFill(Color.web("#34A853"));
        gc.fillArc(cx - r, cy - r, r * 2, r * 2, 280, 80, ArcType.ROUND);

        // White inner circle (donut hole)
        gc.setFill(Color.WHITE);
        gc.fillOval(cx - 5, cy - 5, 10, 10);

        // White bar for the G crossbar
        gc.fillRect(cx, cy - 2, r - 1, 4);

        HBox graphic = new HBox(8, canvas, new javafx.scene.control.Label(label) {{
            setStyle("-fx-text-fill: white; -fx-font-weight: bold; -fx-font-size: 12;");
        }});
        graphic.setAlignment(javafx.geometry.Pos.CENTER);
        btn.setGraphic(graphic);
        btn.setText("");
    }

    @FXML
    public void showSignIn() {
        signInPane.setVisible(true);
        signInPane.setManaged(true);
        registerPane.setVisible(false);
        registerPane.setManaged(false);
        signInTab.setStyle("-fx-background-color: #16a34a; -fx-text-fill: white; -fx-background-radius: 6; -fx-font-weight: bold; -fx-font-size: 12; -fx-cursor: hand;");
        registerTab.setStyle("-fx-background-color: transparent; -fx-text-fill: #9ca3af; -fx-background-radius: 6; -fx-font-weight: bold; -fx-font-size: 12; -fx-cursor: hand; -fx-border-color: #374151; -fx-border-radius: 6;");
        clearError();
    }

    @FXML
    public void showRegister() {
        registerPane.setVisible(true);
        registerPane.setManaged(true);
        signInPane.setVisible(false);
        signInPane.setManaged(false);
        registerTab.setStyle("-fx-background-color: #16a34a; -fx-text-fill: white; -fx-background-radius: 6; -fx-font-weight: bold; -fx-font-size: 12; -fx-cursor: hand;");
        signInTab.setStyle("-fx-background-color: transparent; -fx-text-fill: #9ca3af; -fx-background-radius: 6; -fx-font-weight: bold; -fx-font-size: 12; -fx-cursor: hand; -fx-border-color: #374151; -fx-border-radius: 6;");
        clearError();
    }

    // --- Eye toggle handlers ---

    @FXML
    public void toggleLoginPassword() {
        loginPassVisible = !loginPassVisible;
        togglePasswordField(loginPassword, loginPasswordVisible, loginPassVisible);
        loginEyeBtn.setText(loginPassVisible ? "🙈" : "👁");
    }

    @FXML
    public void toggleRegPassword() {
        regPassVisible = !regPassVisible;
        togglePasswordField(regPassword, regPasswordVisible, regPassVisible);
        regEyeBtn.setText(regPassVisible ? "🙈" : "👁");
    }

    @FXML
    public void toggleRegConfirmPassword() {
        regConfirmVisible = !regConfirmVisible;
        togglePasswordField(regPasswordConfirm, regPasswordConfirmVisible, regConfirmVisible);
        regConfirmEyeBtn.setText(regConfirmVisible ? "🙈" : "👁");
    }

    private void togglePasswordField(PasswordField hidden, TextField visible, boolean show) {
        if (show) {
            visible.setText(hidden.getText());
            hidden.setVisible(false);
            hidden.setManaged(false);
            visible.setVisible(true);
            visible.setManaged(true);
        } else {
            hidden.setText(visible.getText());
            visible.setVisible(false);
            visible.setManaged(false);
            hidden.setVisible(true);
            hidden.setManaged(true);
        }
    }

    // Returns the actual password text regardless of which field is active
    private String getPassword(PasswordField hidden, TextField visible, boolean isVisible) {
        return isVisible ? visible.getText() : hidden.getText();
    }

    // --- Auth handlers ---

    @FXML
    public void handleSignIn() {
        String email = loginEmail.getText().trim();
        String password = getPassword(loginPassword, loginPasswordVisible, loginPassVisible);

        if (email.isEmpty() || password.isEmpty()) {
            showError("Please enter your email and password.");
            return;
        }

        setLoading(true);
        new Thread(() -> {
            ApiService.ApiResponse response = ApiService.login(email, password);
            Platform.runLater(() -> {
                setLoading(false);
                if (response.isSuccess()) {
                    try {
                        var user = response.body().getAsJsonObject("user");
                        UserSession.getInstance().setUser(
                            user.get("id").getAsInt(),
                            user.get("Fname").getAsString(),
                            user.get("Lname").getAsString(),
                            user.get("email").getAsString(),
                            user.get("role").getAsString(),
                            response.body().get("token").getAsString()
                        );
                        showSuccess("Welcome back, " + UserSession.getInstance().getFname() + "!");
                        navigateToDashboard();
                    } catch (Exception e) {
                        showError("Login successful but failed to load user data.");
                    }
                } else {
                    showError(response.getMessage());
                }
            });
        }).start();
    }

    @FXML
    public void handleRegister() {
        String fname = regFname.getText().trim();
        String lname = regLname.getText().trim();
        String email = regEmail.getText().trim();
        String password = getPassword(regPassword, regPasswordVisible, regPassVisible);
        String confirm = getPassword(regPasswordConfirm, regPasswordConfirmVisible, regConfirmVisible);

        if (fname.isEmpty() || lname.isEmpty() || email.isEmpty() || password.isEmpty()) {
            showError("Please fill in all fields.");
            return;
        }
        if (!password.equals(confirm)) {
            showError("Passwords do not match.");
            return;
        }
        if (!termsCheck.isSelected()) {
            showError("You must agree to the Terms & Conditions.");
            return;
        }

        setLoading(true);
        new Thread(() -> {
            ApiService.ApiResponse response = ApiService.register(fname, lname, email, password, confirm);
            Platform.runLater(() -> {
                setLoading(false);
                if (response.isSuccess()) {
                    showSuccess("Account created! You can now sign in.");
                    showSignIn();
                } else {
                    showError(response.getMessage());
                }
            });
        }).start();
    }

    @FXML
    public void handleGoogleSignIn() {
        new OAuthCallbackServer().start(params -> Platform.runLater(() -> {
            try {
                UserSession.getInstance().setUser(
                    Integer.parseInt(params.get("id")),
                    params.get("fname"),
                    params.get("lname"),
                    params.get("email"),
                    params.get("role"),
                    params.get("token")
                );
                showSuccess("Welcome, " + UserSession.getInstance().getFname() + "!");
                navigateToDashboard();
            } catch (Exception e) {
                showError("Google sign in failed: " + e.getMessage());
            }
        }));
        try {
            Desktop.getDesktop().browse(new URI("http://127.0.0.1:8000/auth/google/redirect?desktop=1"));
            showInfo("Browser opened. Complete sign in with Google...");
        } catch (Exception e) {
            showError("Could not open browser. Make sure the Laravel server is running.");
        }
    }

    @FXML
    public void handleForgotPassword() {
        String email = loginEmail.getText().trim();
        if (email.isEmpty()) {
            showError("Enter your email first, then click Forgot password.");
            return;
        }
        try {
            Desktop.getDesktop().browse(new URI("http://127.0.0.1:8000/forgot-password?email=" + email));
        } catch (Exception e) {
            showError("Could not open browser.");
        }
    }

    private void showError(String msg) {
        errorLabel.setStyle("-fx-text-fill: #f87171; -fx-font-size: 11; -fx-padding: 4 16 0 16;");
        errorLabel.setText(msg);
    }

    private void showSuccess(String msg) {
        errorLabel.setStyle("-fx-text-fill: #4ade80; -fx-font-size: 11; -fx-padding: 4 16 0 16;");
        errorLabel.setText(msg);
    }

    private void showInfo(String msg) {
        errorLabel.setStyle("-fx-text-fill: #60a5fa; -fx-font-size: 11; -fx-padding: 4 16 0 16;");
        errorLabel.setText(msg);
    }

    private void clearError() { errorLabel.setText(""); }

    private void setLoading(boolean loading) {
        signInPane.setDisable(loading);
        registerPane.setDisable(loading);
    }

    private void navigateToDashboard() {
        try {
            UserSession us = UserSession.getInstance();

            // Sync AppSession so MainShellController gets the real logged-in user
            AppSession.getInstance().setCurrentUser(new ForumUser(
                us.getId(),
                us.getFname() + " " + us.getLname(),
                us.getEmail(),
                us.getRole()
            ));

            // Students go straight to the student dashboard inside the shell;
            // admins and lecturers use the main shell which picks the right dashboard via AppSession
            String fxml = "/com/smartforum/view/main-shell.fxml";
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxml));
            Scene scene = new Scene(loader.load());
            Stage stage = (Stage) signInPane.getScene().getWindow();
            stage.setScene(scene);
            stage.setMaximized(true);
            stage.setResizable(true);
        } catch (Exception e) {
            e.printStackTrace();
            showError("Failed to load dashboard: " + e.getMessage());
        }
    }
}
