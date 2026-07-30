@echo off
set DESK=C:\Users\DELL\OneDrive\Desktop\SmartForum\DESKTOP-CLIENT\Smart-Forum-Desktop
set LIBS=%DESK%\target\libs
set CLASSES=%DESK%\target\classes
set SRC=%DESK%\src\main\java\com\smartforum

javac --module-path "%LIBS%\javafx-base-25.0.2-win.jar;%LIBS%\javafx-controls-25.0.2-win.jar;%LIBS%\javafx-fxml-25.0.2-win.jar;%LIBS%\javafx-graphics-25.0.2-win.jar" --add-modules javafx.controls,javafx.fxml -cp "%CLASSES%;%LIBS%\gson-2.10.1.jar;%LIBS%\javafx-base-25.0.2-win.jar;%LIBS%\javafx-controls-25.0.2-win.jar;%LIBS%\javafx-fxml-25.0.2-win.jar;%LIBS%\javafx-graphics-25.0.2-win.jar" -d "%CLASSES%" "%SRC%\AuthController.java"

if %ERRORLEVEL% == 0 (
    echo Recompile SUCCESS
    cd /d "%DESK%\target"
    jar uf Smart-Forum-Desktop-1.0-SNAPSHOT.jar -C classes com/smartforum/AuthController.class -C classes "com/smartforum/AuthController$1.class"
    echo JAR updated
) else (
    echo Recompile FAILED
)
pause
