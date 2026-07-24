package com.smartforum.util;

import com.smartforum.api.ApiClient;

import java.io.IOException;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.util.concurrent.CopyOnWriteArrayList;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.function.Consumer;

/**
 * Connectivity monitor with asymmetric debouncing:
 * - Reconnect: one successful probe → online + sync immediately
 * - Disconnect: three failed probes → offline (avoids flicker on WiFi/mobile)
 */
public final class NetworkMonitor {
    private static final int FAILURES_FOR_OFFLINE = 3;
    private static final long ONLINE_PROBE_SEC = 8;
    private static final long OFFLINE_PROBE_SEC = 2;

    private static volatile boolean stableOnline = true;
    private static int consecutiveFailures = 0;

    private static Boolean manualOverride = null;
    private static ScheduledExecutorService prober;
    private static final CopyOnWriteArrayList<Consumer<Boolean>> listeners = new CopyOnWriteArrayList<>();

    private NetworkMonitor() {}

    public static void setOverride(Boolean value) {
        manualOverride = value;
        if (value != null) {
            notifyStable(value);
        }
    }

    public static Boolean getOverride() {
        return manualOverride;
    }

    public static void addStableStateListener(Consumer<Boolean> listener) {
        if (listener != null) {
            listeners.add(listener);
        }
        startProberIfNeeded();
    }

    public static void removeStableStateListener(Consumer<Boolean> listener) {
        listeners.remove(listener);
    }

    public static boolean isOnline() {
        if (manualOverride != null) {
            return manualOverride;
        }
        startProberIfNeeded();
        return stableOnline;
    }

    /** Force an immediate probe (e.g. user toggled online or app regained focus). */
    public static void probeNow() {
        if (manualOverride != null) {
            return;
        }
        startProberIfNeeded();
        applyProbeResult(probeConnectivity());
    }

    private static void startProberIfNeeded() {
        if (prober != null && !prober.isShutdown()) {
            return;
        }
        synchronized (NetworkMonitor.class) {
            if (prober != null && !prober.isShutdown()) {
                return;
            }
            prober = Executors.newSingleThreadScheduledExecutor(r -> {
                Thread t = new Thread(r, "network-prober");
                t.setDaemon(true);
                return t;
            });
            scheduleProbe(0);
        }
    }

    private static void scheduleProbe(long delaySec) {
        if (prober == null || prober.isShutdown()) {
            return;
        }
        prober.schedule(() -> {
            if (manualOverride == null) {
                applyProbeResult(probeConnectivity());
            }
            long next = stableOnline ? ONLINE_PROBE_SEC : OFFLINE_PROBE_SEC;
            scheduleProbe(next);
        }, delaySec, TimeUnit.SECONDS);
    }

    private static void applyProbeResult(boolean reachable) {
        boolean previous = stableOnline;

        if (reachable) {
            consecutiveFailures = 0;
            if (!stableOnline) {
                stableOnline = true;
            }
        } else {
            consecutiveFailures++;
            if (stableOnline && consecutiveFailures >= FAILURES_FOR_OFFLINE) {
                stableOnline = false;
            }
        }

        if (previous != stableOnline) {
            notifyStable(stableOnline);
        }
    }

    private static void notifyStable(boolean online) {
        for (Consumer<Boolean> listener : listeners) {
            try {
                listener.accept(online);
            } catch (Exception ignored) {
            }
        }
    }

    /** Works on WiFi, mobile hotspot, and ethernet. */
    private static boolean hasNetworkLink() {
        for (String host : new String[] {"1.1.1.1", "8.8.8.8"}) {
            try (Socket socket = new Socket()) {
                socket.connect(new InetSocketAddress(host, 53), 2000);
                return true;
            } catch (IOException ignored) {
            }
        }
        return false;
    }

    private static boolean probeConnectivity() {
        if (!hasNetworkLink()) {
            return false;
        }
        return ApiClient.pingServer();
    }
}
