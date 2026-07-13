package com.smartforum;

import javafx.application.Application;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.layout.StackPane;
import javafx.stage.Stage;

public class Main extends Application {

    @Override
    public void start(Stage stage) {
        Label label = new Label("Welcome to Smart Discussion Forum Desktop");

        StackPane root = new StackPane(label);

        Scene scene = new Scene(root, 600, 400);

        stage.setTitle("Smart Discussion Forum");
        stage.setScene(scene);
        stage.show();
    }

    public static void main(String[] args) {
        launch();
    }
}
