package com.smartforum.util;

import java.io.IOException;
import java.net.InetSocketAddress;
import java.net.Socket;

public class NetworkMonitor {
    private static final String HOST = "8.8.8.8";
    private static final int PORT = 53;
    private static final int TIMEOUT_MS = 1500;

    // null = no override, true/false = forced state
    private static Boolean manualOverride = null;

    public static void setOverride(Boolean value) {
        manualOverride = value;
    }

    public static Boolean getOverride() {
        return manualOverride;
    }

    public static boolean isOnline() {
        if (manualOverride != null) return manualOverride;
        try (Socket socket = new Socket()) {
            socket.connect(new InetSocketAddress(HOST, PORT), TIMEOUT_MS);
            return true;
        } catch (IOException e) {
            return false;
        }
    }
}
