package com.smartforum.util;

import com.smartforum.model.Quiz;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.time.format.DateTimeParseException;

public final class QuizSchedule {
    private static final DateTimeFormatter SPACE_DATE_TIME =
        DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss");

    private QuizSchedule() {}

    public static LocalDateTime parseStart(String value) {
        return parse(value, false);
    }

    public static LocalDateTime parseEnd(String value) {
        return parse(value, true);
    }

    private static LocalDateTime parse(String value, boolean endOfDay) {
        if (value == null || value.isBlank()) return null;
        String text = value.trim();
        try {
            return LocalDateTime.parse(text);
        } catch (DateTimeParseException ignored) {
            try {
                return LocalDateTime.parse(text, SPACE_DATE_TIME);
            } catch (DateTimeParseException ignoredAgain) {
                LocalDate date = LocalDate.parse(text);
                return endOfDay ? date.atTime(23, 59, 59) : date.atStartOfDay();
            }
        }
    }

    public static String availability(Quiz quiz, LocalDateTime now) {
        try {
            LocalDateTime start = parseStart(quiz.getStartDate());
            LocalDateTime end = parseEnd(quiz.getEndDate());
            if (start != null && end != null && !end.isAfter(start)) return "Invalid schedule";
            if (start != null && now.isBefore(start)) return "Upcoming";
            if (end != null && now.isAfter(end)) return "Expired";
            return "Available";
        } catch (DateTimeParseException e) {
            return "Invalid schedule";
        }
    }

    public static LocalDateTime deadline(Quiz quiz, LocalDateTime startedAt) {
        LocalDateTime durationDeadline = startedAt.plusMinutes(quiz.getDuration());
        LocalDateTime quizEnd = parseEnd(quiz.getEndDate());
        return quizEnd != null && quizEnd.isBefore(durationDeadline) ? quizEnd : durationDeadline;
    }
}
