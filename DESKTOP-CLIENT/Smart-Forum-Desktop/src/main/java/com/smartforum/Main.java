package com.smartforum;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.scene.control.Tab;
import javafx.scene.control.TabPane;
import javafx.stage.Stage;

public class Main extends Application {

    @Override
    public void start(Stage stage) throws Exception {
        TabPane tabPane = new TabPane();

        tabPane.getTabs().addAll(
            loadTab("Quiz Categories", "/fxml/QuizCategories.fxml"),
            loadTab("Quizzes", "/fxml/quizzes.fxml"),
            loadTab("Questions", "/fxml/QuestionManagement.fxml")
        );

        stage.setTitle("Smart Forum - Quiz Management");
        stage.setScene(new Scene(tabPane));
        stage.setMaximized(true);
        stage.show();
    }

    private Tab loadTab(String title, String fxmlPath) throws Exception {
        Tab tab = new Tab(title);
        tab.setClosable(false);
        tab.setContent(new FXMLLoader(getClass().getResource(fxmlPath)).load());
        return tab;
    }

    public static void main(String[] args) {
        launch(args);
    }
}
