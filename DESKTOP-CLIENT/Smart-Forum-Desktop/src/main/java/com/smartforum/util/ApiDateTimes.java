package com.smartforum.util;

import java.time.Instant;
import java.time.LocalDateTime;
import java.time.OffsetDateTime;
import java.time.ZoneId;
import java.time.format.DateTimeParseException;

/**
 * Parses Laravel/Carbon ISO-8601 timestamps used by the quiz API.
 */
public final class ApiDateTimes {

    private ApiDateTimes() {}

    /**
     * Converts an API timestamp into the JVM's local date-time (zone-correct).
     */
    public static LocalDateTime parseLocal(String raw) {
        if (raw == null || raw.isBlank()) {
            return null;
        }
        String value = raw.trim();
        ZoneId zone = ZoneId.systemDefault();
        try {
            return OffsetDateTime.parse(value).atZoneSameInstant(zone).toLocalDateTime();
        } catch (DateTimeParseException ignored) {
            // fall through
        }
        try {
            return Instant.parse(value).atZone(zone).toLocalDateTime();
        } catch (DateTimeParseException ignored) {
            // fall through
        }
        if (value.length() >= 19) {
            return LocalDateTime.parse(value.substring(0, 19));
        }
        return LocalDateTime.parse(value);
    }
}
