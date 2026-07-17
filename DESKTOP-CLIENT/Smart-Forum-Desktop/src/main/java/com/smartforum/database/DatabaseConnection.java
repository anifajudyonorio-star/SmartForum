package com.smartforum.database;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;

public class DatabaseConnection {

    private static final String URL = "jdbc:sqlite:smartforum.db";
    private static final int SCHEMA_VERSION = 4;
    private static boolean migrated;

    public static synchronized Connection getConnection() {
        Connection connection = null;
        try {
            connection = DriverManager.getConnection(URL);
            configure(connection);
            if (!migrated) {
                migrate(connection);
                migrated = true;
            }
            return connection;
        } catch (SQLException e) {
            if (connection != null) {
                try {
                    connection.close();
                } catch (SQLException ignored) {
                    // Preserve the original database error.
                }
            }
            e.printStackTrace();
            return null;
        }
    }

    private static void configure(Connection connection) throws SQLException {
        try (Statement statement = connection.createStatement()) {
            statement.execute("PRAGMA foreign_keys = ON");
            statement.execute("PRAGMA busy_timeout = 5000");
            try (ResultSet rs = statement.executeQuery("PRAGMA foreign_keys")) {
                if (!rs.next() || rs.getInt(1) != 1) {
                    throw new SQLException("SQLite foreign key enforcement could not be enabled");
                }
            }
        }
    }

    private static void migrate(Connection connection) throws SQLException {
        boolean oldAutoCommit = connection.getAutoCommit();
        connection.setAutoCommit(false);
        try (Statement statement = connection.createStatement()) {
            int version;
            try (ResultSet rs = statement.executeQuery("PRAGMA user_version")) {
                version = rs.next() ? rs.getInt(1) : 0;
            }
            createBaseTables(statement);
            addColumnIfMissing(connection, "quiz_results", "category_id", "INTEGER DEFAULT 0");
            addColumnIfMissing(connection, "quiz_results", "student_id", "INTEGER");
            addColumnIfMissing(connection, "quiz_results", "final_possible_marks", "INTEGER");
            addColumnIfMissing(connection, "questions", "marks", "INTEGER NOT NULL DEFAULT 1");
            addColumnIfMissing(connection, "quizzes", "participation_marks", "INTEGER NOT NULL DEFAULT 0");
            addColumnIfMissing(connection, "category_students", "student_id", "INTEGER");
            statement.execute("CREATE UNIQUE INDEX IF NOT EXISTS uq_category_students_student " +
                    "ON category_students(student_id) WHERE student_id IS NOT NULL");
            statement.execute("CREATE TABLE IF NOT EXISTS quiz_attempts (" +
                    "id INTEGER PRIMARY KEY AUTOINCREMENT, quiz_id INTEGER NOT NULL, " +
                    "student_id INTEGER NOT NULL, student_name TEXT NOT NULL, category_id INTEGER NOT NULL, " +
                    "started_at TEXT NOT NULL, deadline_at TEXT NOT NULL, completed_at TEXT, " +
                    "status TEXT NOT NULL DEFAULT 'IN_PROGRESS', answers TEXT NOT NULL DEFAULT '', " +
                    "UNIQUE(quiz_id, student_id), FOREIGN KEY(quiz_id) REFERENCES quizzes(id))");
            statement.execute("CREATE INDEX IF NOT EXISTS idx_attempts_student ON quiz_attempts(student_id, status)");
            statement.execute("CREATE INDEX IF NOT EXISTS idx_results_quiz ON quiz_results(quiz_id)");
            statement.execute("CREATE UNIQUE INDEX IF NOT EXISTS uq_results_quiz_student " +
                    "ON quiz_results(quiz_id, student_id) WHERE student_id IS NOT NULL");
            if (version < SCHEMA_VERSION) {
                statement.execute("UPDATE questions SET marks=1 WHERE marks IS NULL OR marks<=0");
                statement.execute("UPDATE quizzes SET participation_marks=0 " +
                    "WHERE participation_marks IS NULL OR participation_marks<0");
                statement.execute("UPDATE quiz_results SET final_possible_marks=" +
                    "CASE WHEN total_marks + participation_marks > 0 THEN total_marks + participation_marks ELSE total_marks END " +
                    "WHERE final_possible_marks IS NULL OR final_possible_marks<=0");
            }
            statement.execute("PRAGMA user_version = " + SCHEMA_VERSION);
            connection.commit();
        } catch (SQLException e) {
            connection.rollback();
            throw e;
        } finally {
            connection.setAutoCommit(oldAutoCommit);
        }
    }

    private static void createBaseTables(Statement statement) throws SQLException {
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
                "participation_marks INTEGER NOT NULL DEFAULT 0, " +
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
                "marks INTEGER NOT NULL DEFAULT 1," +
                "FOREIGN KEY(quiz_id) REFERENCES quizzes(id)" +
                ")"
        );

        statement.execute(
            "CREATE TABLE IF NOT EXISTS quiz_results (" +
                "id INTEGER PRIMARY KEY AUTOINCREMENT," +
                "quiz_id INTEGER NOT NULL," +
                "student_name TEXT NOT NULL," +
                "student_id INTEGER," +
                "category_id INTEGER DEFAULT 0," +
                "score INTEGER DEFAULT 0," +
                "total_marks INTEGER DEFAULT 0," +
                "participation_marks INTEGER DEFAULT 0," +
                "total_score INTEGER DEFAULT 0," +
                "final_possible_marks INTEGER," +
                "submitted_at TEXT," +
                "FOREIGN KEY(quiz_id) REFERENCES quizzes(id)" +
                ")"
        );

        statement.execute(
            "CREATE TABLE IF NOT EXISTS category_students (" +
                "id INTEGER PRIMARY KEY AUTOINCREMENT," +
                "category_id INTEGER NOT NULL," +
                "student_id INTEGER," +
                "student_name TEXT NOT NULL," +
                "UNIQUE(category_id, student_name)," +
                "FOREIGN KEY(category_id) REFERENCES quiz_categories(id)" +
                ")"
        );

        statement.execute(
            "CREATE TABLE IF NOT EXISTS announcements (" +
                "id INTEGER PRIMARY KEY AUTOINCREMENT," +
                "category_id INTEGER NOT NULL," +
                "title TEXT NOT NULL," +
                "message TEXT NOT NULL," +
                "created_by TEXT," +
                "created_at TEXT," +
                "FOREIGN KEY(category_id) REFERENCES quiz_categories(id)" +
                ")"
        );

    }

    private static void addColumnIfMissing(Connection connection, String table, String column, String definition)
            throws SQLException {
        try (Statement statement = connection.createStatement();
             ResultSet rs = statement.executeQuery("PRAGMA table_info(" + table + ")")) {
            while (rs.next()) {
                if (column.equalsIgnoreCase(rs.getString("name"))) return;
            }
        }
        try (Statement statement = connection.createStatement()) {
            statement.execute("ALTER TABLE " + table + " ADD COLUMN " + column + " " + definition);
        }
    }
}
