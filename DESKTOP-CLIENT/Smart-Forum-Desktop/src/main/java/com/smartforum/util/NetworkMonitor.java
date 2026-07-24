package com.smartforum.util;

import com.smartforum.api.ApiClient;

public class NetworkMonitor {
    // null = no override, true/false = forced state
    private static Boolean manualOverride = null;

    public static void setOverride(Boolean value) {
        manualOverride = value;
    }

    public static Boolean getOverride() {
        return manualOverride;
    }

    public static boolean isOnline() {
        if (manualOverride != null) {
            return manualOverride;
        }
        return ApiClient.pingServer();
    }
}
