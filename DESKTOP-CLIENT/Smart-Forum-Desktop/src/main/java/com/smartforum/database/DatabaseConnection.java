package com.smartforum.database;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.sql.Statement;

public class DatabaseConnection {

    private static final String URL = "jdbc:sqlite:smartforum.db";

    public static Connection getConnection() {
        try {
            Connection connection = DriverManager.getConnection(URL);
            createTables(connection);
            return connection;
        } catch (SQLException e) {
            e.printStackTrace();
            return null;
        }
    }

    private static void createTables(Connection connection) throws SQLException {
        Statement statement = connection.createStatement();

        statement.execute(
            "CREATE TABLE IF NOT EXISTS quiz_categories (" +
                "id INTEGER PRIMARY KEY AUTOINCREMENT, " +
                "category_name TEXT NOT NULL, " +
                "description TEXT, " +
                "created_by TEXT" +
                ")"
        );

        statement.execute(
            "CREATE TABLE IF NOT EXISTS quizzes (" +
                "id INTEGER PRIMARY KEY AUTOINCREMENT, " +
                "category_id INTEGER NOT NULL, " +
                "title TEXT NOT NULL, " +
                "description TEXT, " +
                "duration INTEGER, " +
                "total_marks INTEGER, " +
                "start_date TEXT, " +
                "end_date TEXT, " +
                "FOREIGN KEY(category_id) REFERENCES quiz_categories(id)" +
                ")"
        );

        statement.execute(
            "CREATE TABLE IF NOT EXISTS questions (" +
                "id INTEGER PRIMARY KEY AUTOINCREMENT," +
                "quiz_id INTEGER NOT NULL," +
                "question TEXT NOT NULL," +
                "option_a TEXT NOT NULL," +
                "option_b TEXT NOT NULL," +
                "option_c TEXT NOT NULL," +
                "option_d TEXT NOT NULL," +
                "correct_answer TEXT NOT NULL," +
                "FOREIGN KEY(quiz_id) REFERENCES quizzes(id)" +
                ")"
        );

        statement.close();
    }
}
