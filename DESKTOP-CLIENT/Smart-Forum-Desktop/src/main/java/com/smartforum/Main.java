package com.smartforum;

import com.smartforum.util.SessionManager;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class Main extends Application {

    @Override
    public void start(Stage stage) throws Exception {
        // TODO: replace with real login flow once colleague pushes login screen
        // For now set a test session so the chat screen loads
        SessionManager.getInstance().setSession(
                System.getProperty("sf.token", ""),
                Integer.parseInt(System.getProperty("sf.userId", "1")),
                System.getProperty("sf.userName", "Test User")
        );

        FXMLLoader loader = new FXMLLoader(
                getClass().getResource("/com/smartforum/chat.fxml"));
        Scene scene = new Scene(loader.load());

        stage.setTitle("SmartForum — Desktop");
        stage.setScene(scene);
        stage.setMinWidth(700);
        stage.setMinHeight(500);
        stage.show();
    }

    public static void main(String[] args) {
        launch();
    }
}
