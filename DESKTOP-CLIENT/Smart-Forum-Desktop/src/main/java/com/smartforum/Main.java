package com.smartforum;

import com.smartforum.util.FontLoader;
import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class Main extends Application {

    @Override
    public void init() {
        FontLoader.loadAppFonts();
    }

    @Override
    public void start(Stage stage) throws Exception {
        FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/smartforum/auth-view.fxml"));
        Scene scene = new Scene(loader.load(), 480, 600);
        scene.setFill(javafx.scene.paint.Color.web("#0a0f1e"));
        stage.setTitle("Smart Discussion");
        stage.setScene(scene);
        stage.setResizable(false);
        stage.show();
    }

    public static void main(String[] args) {
        launch(args);
    }
}
