package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.dao.QuizCategoryDAO;
import com.smartforum.model.QuizCategory;
import com.smartforum.util.ApiSupport;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;

import java.net.URL;
import java.util.ResourceBundle;

public class QuizCategoryController implements Initializable {

    @FXML private TextField txtCategoryName;
    @FXML private TextArea txtDescription;
    @FXML private Button btnSave;
    @FXML private Button btnUpdate;
    @FXML private Button btnDelete;
    @FXML private Button btnClear;
    @FXML private TableView<QuizCategory> tblCategories;
    @FXML private TableColumn<QuizCategory, Integer> colId;
    @FXML private TableColumn<QuizCategory, String> colCategory;
    @FXML private TableColumn<QuizCategory, String> colDescription;
    @FXML private TableColumn<QuizCategory, String> colCreatedBy;

    private final QuizCategoryDAO dao = new QuizCategoryDAO();
    private final ObservableList<QuizCategory> categoryList = FXCollections.observableArrayList();
    private QuizCategory selectedCategory;

    @Override
    public void initialize(URL url, ResourceBundle resourceBundle) {
        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colCategory.setCellValueFactory(new PropertyValueFactory<>("categoryName"));
        colDescription.setCellValueFactory(new PropertyValueFactory<>("description"));
        colCreatedBy.setCellValueFactory(new PropertyValueFactory<>("createdBy"));

        loadCategories();

        tblCategories.getSelectionModel().selectedItemProperty().addListener(
            (observable, oldValue, newValue) -> {
                if (newValue != null) {
                    selectedCategory = newValue;
                    txtCategoryName.setText(newValue.getCategoryName());
                    txtDescription.setText(newValue.getDescription());
                }
            });
    }

    public void reload() {
        loadCategories();
    }

    private void loadCategories() {
        if (ApiSupport.useApi()) {
            new Thread(() -> ApiClient.getQuizCategories().ifPresentOrElse(json -> {
                ObservableList<QuizCategory> loaded = FXCollections.observableArrayList();
                JsonArray array = json.getAsJsonArray("categories");
                if (array != null) {
                    for (JsonElement element : array) {
                        JsonObject item = element.getAsJsonObject();
                        QuizCategory category = new QuizCategory();
                        category.setId(item.get("id").getAsInt());
                        category.setCategoryName(item.get("name").getAsString());
                        if (item.has("description") && !item.get("description").isJsonNull()) {
                            category.setDescription(item.get("description").getAsString());
                        }
                        category.setCreatedBy("You");
                        loaded.add(category);
                    }
                }
                Platform.runLater(() -> {
                    categoryList.setAll(loaded);
                    tblCategories.setItems(categoryList);
                });
            }, () -> Platform.runLater(this::loadOfflineCategories)), "quiz-categories-load").start();
            return;
        }
        loadOfflineCategories();
    }

    private void loadOfflineCategories() {
        categoryList.clear();
        categoryList.addAll(dao.getAllCategories());
        tblCategories.setItems(categoryList);
    }

    @FXML
    private void saveCategory() {
        String name = txtCategoryName.getText().trim();
        String description = txtDescription.getText().trim();

        if (name.isEmpty()) {
            showAlert("Validation", "Category name is required.");
            return;
        }

        if (ApiSupport.useApi()) {
            new Thread(() -> {
                ApiClient.MutationResult result = ApiClient.createQuizCategory(name, description);
                Platform.runLater(() -> {
                    if (result.success()) {
                        showAlert("Success", result.message().isBlank()
                                ? "Quiz title created successfully."
                                : result.message());
                        clearFields();
                        loadCategories();
                    } else {
                        showAlert("Error", result.message().isBlank()
                                ? "Failed to save quiz title on the server."
                                : result.message());
                    }
                });
            }, "quiz-category-create").start();
            return;
        }

        QuizCategory category = new QuizCategory(name, description, "Lecturer");
        if (dao.saveCategory(category)) {
            showAlert("Success", "Category saved successfully.");
            clearFields();
            loadCategories();
        } else {
            showAlert("Error", "Failed to save category.");
        }
    }

    @FXML
    private void updateCategory() {
        if (selectedCategory == null) {
            showAlert("Warning", "Please select a category first.");
            return;
        }

        if (ApiSupport.useApi()) {
            showAlert("Online Mode", "Editing quiz titles online is available on the web app for now. Create a new title here if needed.");
            return;
        }

        String name = txtCategoryName.getText().trim();
        String description = txtDescription.getText().trim();
        if (name.isEmpty()) {
            showAlert("Validation", "Category name is required.");
            return;
        }

        selectedCategory.setCategoryName(name);
        selectedCategory.setDescription(description);
        selectedCategory.setCreatedBy("Lecturer");

        if (dao.updateCategory(selectedCategory)) {
            showAlert("Success", "Category updated successfully.");
            loadCategories();
            clearFields();
        } else {
            showAlert("Error", "Failed to update category.");
        }
    }

    @FXML
    private void deleteCategory() {
        if (selectedCategory == null) {
            showAlert("Warning", "Please select a category first.");
            return;
        }

        if (ApiSupport.useApi()) {
            showAlert("Online Mode", "Deleting quiz titles online is available on the web app for now.");
            return;
        }

        if (dao.deleteCategory(selectedCategory.getId())) {
            showAlert("Success", "Category deleted successfully.");
            loadCategories();
            clearFields();
        } else {
            showAlert("Error", "Failed to delete category.");
        }
    }

    @FXML
    private void clearFields() {
        txtCategoryName.clear();
        txtDescription.clear();
        tblCategories.getSelectionModel().clearSelection();
        selectedCategory = null;
    }

    private void showAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}
