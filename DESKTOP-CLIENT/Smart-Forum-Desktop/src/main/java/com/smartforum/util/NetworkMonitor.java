package com.smartforum.util;

import com.smartforum.api.ApiClient;

public class NetworkMonitor {
    // null = no override, true/false = forced state
    private static Boolean manualOverride = null;
    private static volatile long lastCheckMs;
    private static volatile boolean lastOnline;
    private static final long CACHE_MS = 10_000;

    public static void setOverride(Boolean value) {
        manualOverride = value;
        lastCheckMs = 0;
    }

    public static Boolean getOverride() {
        return manualOverride;
    }

    public static boolean isOnline() {
        if (manualOverride != null) {
            return manualOverride;
        }
        long now = System.currentTimeMillis();
        if (now - lastCheckMs < CACHE_MS) {
            return lastOnline;
        }
        lastOnline = ApiClient.pingServer();
        lastCheckMs = now;
        return lastOnline;
    }
}
