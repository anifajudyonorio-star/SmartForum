# Smart Discussion — Desktop Client

JavaFX desktop UI for the SmartForum Laravel system. Step 1: role-based dashboards matching the web app.

## Project structure

```
src/main/
  java/com/smartforum/
    Main.java
    controller/
      MainShellController.java      # Sidebar shell + dashboard switching
      StudentDashboardController.java
      LecturerDashboardController.java
      AdminDashboardController.java
    model/
      ParticipantRow.java
  resources/com/smartforum/
    css/app.css                     # Green theme (matches web)
    view/
      main-shell.fxml               # App shell with sidebar
      student-dashboard.fxml
      lecturer-dashboard.fxml
      admin-dashboard.fxml
```

## Open in Scene Builder

1. Install [Scene Builder](https://gluonhq.com/products/scene-builder/).
2. Open any file under `src/main/resources/com/smartforum/view/`.
3. Set the controller class in Scene Builder (e.g. `com.smartforum.controller.StudentDashboardController`).
4. Preview layout, then save — controllers and CSS are already wired.

**FXML paths for Scene Builder:**
- `main-shell.fxml` — sidebar + content area
- `student-dashboard.fxml` — student stats, recent topics, quick actions
- `lecturer-dashboard.fxml` — participation table
- `admin-dashboard.fxml` — system stats, top groups/topics

## Run the app

Requirements: **JDK 25** (you have this installed), Maven bundled with IntelliJ

### IntelliJ IDEA (recommended)

1. Open `DESKTOP-CLIENT/Smart-Forum-Desktop` as a project.
2. When prompted, **Load Maven Project** / **Import as Maven project**.
3. Set Project SDK to **JDK 25** (File → Project Structure → Project).
4. Open the **Maven** tool window → Lifecycle → click **compile** (downloads JavaFX 25).
5. Run using one of these:
   - **Smart Forum Desktop (Maven)** run config → uses `javafx:run` (most reliable)
   - Or **Smart Forum Desktop** run config → runs `Main` directly

If you see red errors on `javafx.*` imports, Maven dependencies are not loaded yet — reload the Maven project.

### Command line

```bash
cd DESKTOP-CLIENT/Smart-Forum-Desktop
mvn clean javafx:run
```

## Current status (Step 1)

- Three dashboards mirror the web layouts (student, lecturer, admin)
- Green theme aligned with the Laravel UI
- Sidebar switches between dashboards (preview mode)
- Sample data in controllers — API integration comes in a later step

## Next steps

- Login screen + Laravel API authentication
- Load real dashboard data from `/dashboard` API endpoints
- Groups, topics, chat, and notifications screens
