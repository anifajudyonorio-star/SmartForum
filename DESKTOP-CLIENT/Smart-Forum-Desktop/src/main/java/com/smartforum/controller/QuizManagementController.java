package com.smartforum.controller;

import javafx.fxml.FXML;
import javafx.scene.control.Tab;
import javafx.scene.control.TabPane;

/**
 * Reloads nested quiz tabs when selected so API-created categories/quizzes appear.
 */
public class QuizManagementController {

    @FXML private TabPane quizTabs;
    @FXML private QuizCategoryController categoriesController;
    @FXML private QuizController quizzesController;
    @FXML private QuestionController questionsController;
    @FXML private AnnouncementsController announcementsController;

    @FXML
    private void initialize() {
        if (quizTabs == null) {
            return;
        }
        quizTabs.getSelectionModel().selectedItemProperty().addListener((obs, oldTab, newTab) -> {
            if (newTab == null) {
                return;
            }
            refreshTab(newTab);
        });
        Tab selected = quizTabs.getSelectionModel().getSelectedItem();
        if (selected != null) {
            refreshTab(selected);
        }
    }

    private void refreshTab(Tab tab) {
        String title = tab.getText() == null ? "" : tab.getText();
        switch (title) {
            case "Quiz Categories" -> {
                if (categoriesController != null) {
                    categoriesController.reload();
                }
            }
            case "Quizzes" -> {
                if (quizzesController != null) {
                    quizzesController.reload();
                }
            }
            case "Questions" -> {
                if (questionsController != null) {
                    questionsController.reload();
                }
            }
            case "Announcements" -> {
                if (announcementsController != null) {
                    announcementsController.reload();
                }
            }
            default -> {
            }
        }
    }
}
