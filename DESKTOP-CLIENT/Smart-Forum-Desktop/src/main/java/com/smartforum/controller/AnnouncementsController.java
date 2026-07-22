package com.smartforum.controller;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.smartforum.api.ApiClient;
import com.smartforum.dao.AnnouncementDAO;
import com.smartforum.dao.CategoryStudentDAO;
import com.smartforum.dao.QuizCategoryDAO;
import com.smartforum.model.Announcement;
import com.smartforum.model.QuizCategory;
import com.smartforum.service.AppSession;
import com.smartforum.util.ApiSupport;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;

public class AnnouncementsController {

    @FXML private Label pageSubtitleLabel;
    @FXML private Button btnAvailableQuizzes;
    @FXML private HBox enrollmentAlertBox;
    @FXML private Label enrollmentAlertLabel;
    @FXML private HBox lecturerLayout;
    @FXML private FlowPane studentCardsBox;
    @FXML private ComboBox<QuizCategory> cmbCategory;
    @FXML private TextField txtTitle;
    @FXML private TextArea txtMessage;
    @FXML private Label announcementCountLabel;
    @FXML private TableView<Announcement> tblAnnouncements;
    @FXML private TableColumn<Announcement, String> colCategory;
    @FXML private TableColumn<Announcement, String> colTitle;
    @FXML private TableColumn<Announcement, String> colMessage;
    @FXML private TableColumn<Announcement, String> colBy;
    @FXML private TableColumn<Announcement, String> colAt;
    @FXML private TableColumn<Announcement, Void> colAction;

    private final AnnouncementDAO announcementDAO = new AnnouncementDAO();
    private final CategoryStudentDAO categoryStudentDAO = new CategoryStudentDAO();
    private final QuizCategoryDAO quizCategoryDAO = new QuizCategoryDAO();

    private Runnable openQuizzesHandler;

    @FXML
    public void initialize() {
        colCategory.setCellValueFactory(new PropertyValueFactory<>("categoryName"));
        colTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colMessage.setCellValueFactory(cell -> new javafx.beans.property.SimpleStringProperty(
                preview(cell.getValue() == null ? null : cell.getValue().getMessage(),
                        cell.getValue() == null ? null : cell.getValue().getMessagePreview())));
        colBy.setCellValueFactory(new PropertyValueFactory<>("createdBy"));
        colAt.setCellValueFactory(new PropertyValueFactory<>("createdAt"));
        colAction.setCellValueFactory(param -> null);
        colAction.setCellFactory(column -> actionCell());
        tblAnnouncements.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY_FLEX_LAST_COLUMN);
    }

    public void setOpenQuizzesHandler(Runnable handler) {
        this.openQuizzesHandler = handler;
    }

    /** Adapt UI for the signed-in role (called by MainShell after load). */
    public void configureForCurrentUser() {
        boolean isStudent = AppSession.getInstance().isStudent();

        if (lecturerLayout != null) {
            lecturerLayout.setVisible(!isStudent);
            lecturerLayout.setManaged(!isStudent);
        }
        if (studentCardsBox != null) {
            studentCardsBox.setVisible(isStudent);
            studentCardsBox.setManaged(isStudent);
        }
        if (btnAvailableQuizzes != null) {
            btnAvailableQuizzes.setVisible(isStudent);
            btnAvailableQuizzes.setManaged(isStudent);
        }

        if (isStudent) {
            loadStudentFeed();
        } else {
            if (pageSubtitleLabel != null) {
                pageSubtitleLabel.setText("Share updates with students enrolled in your quiz titles.");
            }
            hideEnrollmentAlert();
            loadLecturerFeed();
        }
    }

    @FXML
    private void openAvailableQuizzes() {
        if (openQuizzesHandler != null) {
            openQuizzesHandler.run();
        }
    }

    @FXML
    private void postAnnouncement() {
        QuizCategory category = cmbCategory.getValue();
        String title = txtTitle.getText() == null ? "" : txtTitle.getText().trim();
        String message = txtMessage.getText() == null ? "" : txtMessage.getText().trim();

        if (category == null || title.isEmpty() || message.isEmpty()) {
            alert("Validation", "Quiz title, title, and message are required.");
            return;
        }

        if (ApiSupport.useApi()) {
            new Thread(() -> {
                ApiClient.MutationResult result = ApiClient.postQuizAnnouncement(category.getId(), title, message);
                Platform.runLater(() -> {
                    if (result.success()) {
                        txtTitle.clear();
                        txtMessage.clear();
                        loadLecturerFeed();
                        alert("Success", result.message());
                    } else {
                        alert("Error", result.message().isBlank()
                                ? "Could not post announcement."
                                : result.message());
                    }
                });
            }).start();
            return;
        }

        Announcement announcement = new Announcement();
        announcement.setCategoryId(category.getId());
        announcement.setTitle(title);
        announcement.setMessage(message);
        announcement.setCreatedBy(AppSession.getInstance().getCurrentUser() != null
                ? AppSession.getInstance().getCurrentUser().getName()
                : "Lecturer");
        announcement.setCreatedAt(LocalDateTime.now().format(DateTimeFormatter.ofPattern("MMM d, yyyy h:mm a")));

        if (announcementDAO.save(announcement)) {
            txtTitle.clear();
            txtMessage.clear();
            loadLecturerFeed();
            alert("Success", "Announcement posted to \"" + category.getCategoryName() + "\".");
        }
    }

    private void loadLecturerFeed() {
        if (ApiSupport.useApi()) {
            new Thread(() -> ApiClient.getQuizAnnouncements().ifPresentOrElse(json -> Platform.runLater(() -> {
                populateCategories(json.getAsJsonArray("categories"));
                List<Announcement> announcements = parseAnnouncements(json.getAsJsonArray("announcements"));
                tblAnnouncements.setItems(FXCollections.observableArrayList(announcements));
                announcementCountLabel.setText(String.valueOf(json.get("count").getAsInt()));
            }), () -> Platform.runLater(this::loadLecturerOffline))).start();
            return;
        }

        loadLecturerOffline();
    }

    private void loadLecturerOffline() {
        cmbCategory.setItems(FXCollections.observableArrayList(quizCategoryDAO.getAllCategories()));
        List<Announcement> announcements = announcementDAO.getAll();
        announcements.forEach(announcement -> announcement.setCanDelete(true));
        tblAnnouncements.setItems(FXCollections.observableArrayList(announcements));
        announcementCountLabel.setText(String.valueOf(announcements.size()));
    }

    private void loadStudentFeed() {
        if (ApiSupport.useApi()) {
            new Thread(() -> ApiClient.getStudentAnnouncements().ifPresentOrElse(json -> Platform.runLater(() -> {
                JsonObject enrolled = json.has("enrolled_category") && !json.get("enrolled_category").isJsonNull()
                        ? json.getAsJsonObject("enrolled_category")
                        : null;

                if (enrolled != null) {
                    pageSubtitleLabel.setText("Updates for your enrolled quiz title: "
                            + enrolled.get("name").getAsString() + ".");
                    hideEnrollmentAlert();
                } else {
                    pageSubtitleLabel.setText("Enroll in a quiz title to receive announcements.");
                    showEnrollmentAlert("You are not enrolled in a quiz title yet. Open Available Quizzes to enroll.");
                }

                renderStudentCards(parseAnnouncements(json.getAsJsonArray("announcements")), enrolled != null);
            }), () -> Platform.runLater(this::loadStudentOffline))).start();
            return;
        }

        loadStudentOffline();
    }

    private void loadStudentOffline() {
        var user = AppSession.getInstance().getCurrentUser();
        if (user == null) {
            pageSubtitleLabel.setText("Enroll in a quiz title to receive announcements.");
            showEnrollmentAlert("You are not enrolled in a quiz title yet.");
            renderStudentCards(List.of(), false);
            return;
        }

        int categoryId = categoryStudentDAO.getCategoryForStudent(user.getId(), user.getName());
        if (categoryId == -1) {
            pageSubtitleLabel.setText("Enroll in a quiz title to receive announcements.");
            showEnrollmentAlert("You are not enrolled in a quiz title yet. Open Available Quizzes to enroll.");
            renderStudentCards(List.of(), false);
            return;
        }

        quizCategoryDAO.getAllCategories().stream()
                .filter(category -> category.getId() == categoryId)
                .findFirst()
                .ifPresentOrElse(category -> {
                    pageSubtitleLabel.setText("Updates for your enrolled quiz title: "
                            + category.getCategoryName() + ".");
                    hideEnrollmentAlert();
                }, this::hideEnrollmentAlert);

        List<Announcement> announcements = announcementDAO.getByCategory(categoryId);
        quizCategoryDAO.getAllCategories().stream()
                .filter(category -> category.getId() == categoryId)
                .findFirst()
                .ifPresent(category -> announcements.forEach(item -> item.setCategoryName(category.getCategoryName())));

        renderStudentCards(announcements, true);
    }

    private void populateCategories(JsonArray categories) {
        List<QuizCategory> items = new ArrayList<>();
        for (JsonElement element : categories) {
            JsonObject category = element.getAsJsonObject();
            QuizCategory quizCategory = new QuizCategory();
            quizCategory.setId(category.get("id").getAsInt());
            quizCategory.setCategoryName(category.get("name").getAsString());
            items.add(quizCategory);
        }
        cmbCategory.setItems(FXCollections.observableArrayList(items));
    }

    private List<Announcement> parseAnnouncements(JsonArray array) {
        List<Announcement> announcements = new ArrayList<>();
        for (JsonElement element : array) {
            JsonObject item = element.getAsJsonObject();
            Announcement announcement = new Announcement();
            announcement.setId(item.get("id").getAsInt());
            if (item.has("category_id") && !item.get("category_id").isJsonNull()) {
                announcement.setCategoryId(item.get("category_id").getAsInt());
            }
            if (item.has("category_name") && !item.get("category_name").isJsonNull()) {
                announcement.setCategoryName(item.get("category_name").getAsString());
            }
            announcement.setTitle(item.get("title").getAsString());
            announcement.setMessage(item.get("message").getAsString());
            if (item.has("message_preview") && !item.get("message_preview").isJsonNull()) {
                announcement.setMessagePreview(item.get("message_preview").getAsString());
            }
            if (item.has("author_name") && !item.get("author_name").isJsonNull()) {
                announcement.setCreatedBy(item.get("author_name").getAsString());
            }
            if (item.has("created_at") && !item.get("created_at").isJsonNull()) {
                announcement.setCreatedAt(item.get("created_at").getAsString());
            }
            announcement.setCanDelete(item.has("can_delete") && item.get("can_delete").getAsBoolean());
            announcements.add(announcement);
        }
        return announcements;
    }

    private void renderStudentCards(List<Announcement> announcements, boolean enrolled) {
        studentCardsBox.getChildren().clear();

        if (announcements.isEmpty()) {
            VBox emptyCard = new VBox(8);
            emptyCard.setAlignment(Pos.CENTER);
            emptyCard.getStyleClass().add("announcement-empty-card");
            emptyCard.setMaxWidth(Double.MAX_VALUE);

            Label emptyText = new Label(enrolled
                    ? "No announcements have been posted for your quiz title yet."
                    : "No announcements to show.");
            emptyText.getStyleClass().add("dashboard-subtitle");
            emptyCard.getChildren().add(emptyText);
            studentCardsBox.getChildren().add(emptyCard);
            return;
        }

        for (Announcement announcement : announcements) {
            studentCardsBox.getChildren().add(buildStudentCard(announcement));
        }
    }

    private VBox buildStudentCard(Announcement announcement) {
        VBox card = new VBox(0);
        card.getStyleClass().add("announcement-card");
        card.setMinWidth(320);
        card.setPrefWidth(320);
        card.setMaxWidth(420);

        VBox header = new VBox(4);
        header.getStyleClass().add("announcement-card-header");

        Label title = new Label(announcement.getTitle());
        title.getStyleClass().add("announcement-card-title");
        title.setWrapText(true);

        String meta = String.join(" · ",
                safe(announcement.getCategoryName()),
                safe(announcement.getCreatedBy()),
                safe(announcement.getCreatedAt()));
        Label metaLabel = new Label(meta);
        metaLabel.getStyleClass().add("announcement-card-meta");
        metaLabel.setWrapText(true);

        header.getChildren().addAll(title, metaLabel);

        Label message = new Label(announcement.getMessage());
        message.getStyleClass().add("announcement-card-message");
        message.setWrapText(true);

        VBox body = new VBox(message);
        body.getStyleClass().add("announcement-card-body");

        card.getChildren().addAll(header, body);
        return card;
    }

    private TableCell<Announcement, Void> actionCell() {
        return new TableCell<>() {
            private final Button deleteButton = new Button("Delete");

            {
                deleteButton.getStyleClass().addAll("btn-danger", "btn-sm");
                deleteButton.setOnAction(event -> {
                    Announcement row = getTableView().getItems().get(getIndex());
                    if (row != null) {
                        confirmDelete(row);
                    }
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                    return;
                }

                Announcement row = getTableView().getItems().get(getIndex());
                setGraphic(row != null && row.isCanDelete() ? deleteButton : null);
                setAlignment(Pos.CENTER);
            }
        };
    }

    private void confirmDelete(Announcement announcement) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                "Delete this announcement?", ButtonType.YES, ButtonType.NO);
        confirm.setHeaderText(null);
        if (confirm.showAndWait().orElse(ButtonType.NO) != ButtonType.YES) {
            return;
        }

        if (ApiSupport.useApi()) {
            new Thread(() -> {
                ApiClient.MutationResult result = ApiClient.deleteQuizAnnouncement(announcement.getId());
                Platform.runLater(() -> {
                    if (result.success()) {
                        loadLecturerFeed();
                    } else {
                        alert("Error", result.message().isBlank()
                                ? "Could not delete announcement."
                                : result.message());
                    }
                });
            }).start();
            return;
        }

        announcementDAO.delete(announcement.getId());
        loadLecturerFeed();
    }

    private void showEnrollmentAlert(String message) {
        enrollmentAlertLabel.setText(message);
        enrollmentAlertBox.setVisible(true);
        enrollmentAlertBox.setManaged(true);
    }

    private void hideEnrollmentAlert() {
        enrollmentAlertBox.setVisible(false);
        enrollmentAlertBox.setManaged(false);
    }

    private static String preview(String message, String messagePreview) {
        if (messagePreview != null && !messagePreview.isBlank()) {
            return messagePreview;
        }
        if (message == null) {
            return "";
        }
        return message.length() <= 120 ? message : message.substring(0, 117) + "...";
    }

    private static String safe(String value) {
        return value == null || value.isBlank() ? "—" : value;
    }

    private void alert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}
