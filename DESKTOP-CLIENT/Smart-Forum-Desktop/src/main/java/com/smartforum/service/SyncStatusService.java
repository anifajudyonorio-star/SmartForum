package com.smartforum.service;

import com.smartforum.util.NetworkMonitor;
import com.smartforum.util.OfflineQueue;
import javafx.application.Platform;
import javafx.beans.property.IntegerProperty;
import javafx.beans.property.SimpleIntegerProperty;
import javafx.beans.property.SimpleStringProperty;
import javafx.beans.property.StringProperty;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.function.BiConsumer;

public class SyncStatusService {

    private static final SyncStatusService INSTANCE = new SyncStatusService();
    private static final DateTimeFormatter FMT = DateTimeFormatter.ofPattern("dd MMM yyyy HH:mm:ss");

    private final IntegerProperty pendingCount = new SimpleIntegerProperty(0);
    private final StringProperty statusText = new SimpleStringProperty("● Online");
    private final StringProperty lastSyncText = new SimpleStringProperty("Never");

    private BiConsumer<String, String> bannerCallback;
    private Boolean lastKnownOnline = null;
    private ScheduledExecutorService scheduler;
    private volatile boolean flushing = false;

    private SyncStatusService() {}

    public static SyncStatusService getInstance() { return INSTANCE; }

    public IntegerProperty pendingCountProperty() { return pendingCount; }
    public StringProperty statusTextProperty()    { return statusText; }
    public StringProperty lastSyncTextProperty()  { return lastSyncText; }

    public void setBannerCallback(BiConsumer<String, String> callback) {
        this.bannerCallback = callback;
    }

    private void showBanner(String message, String type) {
        if (bannerCallback != null) Platform.runLater(() -> bannerCallback.accept(message, type));
    }

    public void start() {
        if (scheduler != null && !scheduler.isShutdown()) return;
        scheduler = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r, "sync-monitor");
            t.setDaemon(true);
            return t;
        });
        scheduler.scheduleAtFixedRate(this::tick, 0, 5, TimeUnit.SECONDS);
    }

    public void stop() {
        if (scheduler != null) scheduler.shutdownNow();
    }

    public void refreshNow() {
        Platform.runLater(() -> {
            refresh();
            if (NetworkMonitor.isOnline() && OfflineQueue.size() > 0) {
                autoSync(false, false);
            }
        });
    }

    private void tick() {
        boolean online = NetworkMonitor.isOnline();
        int pending = OfflineQueue.size();

        if (lastKnownOnline == null) {
            if (!online) {
                showBanner("You're offline. Actions will be saved and synced when you reconnect.", "warning");
            } else if (pending > 0) {
                autoSync(false, false);
            }
        } else if (!lastKnownOnline && online) {
            if (pending > 0) {
                showBanner("Reconnected. Syncing…", "info");
                autoSync(true, true);
            } else {
                showBanner("Back online!", "success");
            }
        } else if (lastKnownOnline && !online) {
            showBanner("You're offline. Actions will be saved and synced when you reconnect.", "warning");
        } else if (online && pending > 0) {
            autoSync(false, false);
        }

        lastKnownOnline = online;

        Platform.runLater(() -> {
            pendingCount.set(OfflineQueue.size());
            if (!online) {
                statusText.set("● Offline");
            } else if (OfflineQueue.size() > 0) {
                statusText.set("⏳ " + OfflineQueue.size() + " pending");
            } else {
                statusText.set("● Online");
            }
        });
    }

    private void autoSync(boolean fromReconnect, boolean announceResult) {
        if (flushing || !NetworkMonitor.isOnline() || OfflineQueue.size() == 0) {
            return;
        }

        flushing = true;
        OfflineQueue.flush(
            () -> Platform.runLater(() -> {
                flushing = false;
                updateLastSync();
                refresh();
                if (announceResult) {
                    showBanner("Back online — offline actions synced!", "success");
                }
            }),
            () -> Platform.runLater(() -> {
                flushing = false;
                refresh();
                if (announceResult) {
                    showBanner("Sync failed. Will retry when connection is stable.", "danger");
                }
            })
        );
    }

    private void refresh() {
        boolean online = NetworkMonitor.isOnline();
        pendingCount.set(OfflineQueue.size());
        if (!online) {
            statusText.set("● Offline");
        } else if (OfflineQueue.size() > 0) {
            statusText.set("⏳ " + OfflineQueue.size() + " pending");
        } else {
            statusText.set("● Online");
        }
    }

    private void updateLastSync() {
        lastSyncText.set(LocalDateTime.now().format(FMT));
    }
}
