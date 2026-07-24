package com.smartforum.util;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.UUID;

public final class DeviceIdStore {
    private static final Path DEVICE_FILE = Paths.get(
            System.getProperty("user.home"), ".smartforum_device_id");

    private DeviceIdStore() {}

    public static String getDeviceId() {
        try {
            if (Files.exists(DEVICE_FILE)) {
                String stored = Files.readString(DEVICE_FILE).trim();
                if (!stored.isBlank()) {
                    return stored;
                }
            }
            String id = "desktop-" + UUID.randomUUID();
            Files.writeString(DEVICE_FILE, id);
            return id;
        } catch (IOException e) {
            return "desktop-" + UUID.randomUUID();
        }
    }
}
