package com.smartforum.service;

import com.smartforum.util.NetworkMonitor;
import com.smartforum.util.OfflineQueue;
import javafx.application.Platform;
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

    private final StringProperty statusText = new SimpleStringProperty("● Online");
    private final StringProperty lastSyncText = new SimpleStringProperty("Never");

    private BiConsumer<String, String> bannerCallback;
    private Runnable onSyncSuccessCallback;
    private ScheduledExecutorService scheduler;
    private volatile boolean flushing = false;
    private volatile boolean started = false;

    private SyncStatusService() {}

    public static SyncStatusService getInstance() { return INSTANCE; }

    public StringProperty statusTextProperty()    { return statusText; }
    public StringProperty lastSyncTextProperty()  { return lastSyncText; }

    public void setBannerCallback(BiConsumer<String, String> callback) {
        this.bannerCallback = callback;
    }

    public void setOnSyncSuccess(Runnable callback) {
        this.onSyncSuccessCallback = callback;
    }

    private void notifySyncSuccess() {
        if (onSyncSuccessCallback != null) Platform.runLater(onSyncSuccessCallback);
    }

    private void showBanner(String message, String type) {
        if (bannerCallback != null) Platform.runLater(() -> bannerCallback.accept(message, type));
    }

    public void start() {
        if (started) return;
        started = true;

        NetworkMonitor.addStableStateListener(this::onStableConnectivityChanged);

        if (scheduler != null && !scheduler.isShutdown()) return;
        scheduler = Executors.newSingleThreadScheduledExecutor(r -> {
            Thread t = new Thread(r, "sync-monitor");
            t.setDaemon(true);
            return t;
        });
        scheduler.scheduleAtFixedRate(this::tick, 5, 5, TimeUnit.SECONDS);
        Platform.runLater(this::refresh);
    }

    public void stop() {
        started = false;
        NetworkMonitor.removeStableStateListener(this::onStableConnectivityChanged);
        if (scheduler != null) scheduler.shutdownNow();
    }

    public void refreshNow() {
        Platform.runLater(this::refresh);
        if (NetworkMonitor.isOnline() && OfflineQueue.size() > 0) {
            autoSync(false, false);
        }
    }

    public void syncNow(Runnable onDone) {
        NetworkMonitor.probeNow();
        new Thread(() -> OfflineQueue.flush(
            () -> Platform.runLater(() -> {
                updateLastSync();
                refresh();
                PostService.getInstance().clearCache();
                showBanner("Back online — offline actions synced!", "success");
                notifySyncSuccess();
                if (onDone != null) onDone.run();
            }),
            () -> Platform.runLater(() -> {
                refresh();
                String detail = OfflineQueue.getLastFlushMessage();
                String message = detail == null || detail.isBlank()
                        ? "Sync failed. Will retry when connection is stable."
                        : detail;
                showBanner(message, "danger");
                if (onDone != null) onDone.run();
            })
        )).start();
    }

    private void onStableConnectivityChanged(boolean online) {
        int pending = OfflineQueue.size();
        if (!online) {
            showBanner("You're offline. Actions will be saved and synced when you reconnect.", "warning");
        } else {
            if (pending > 0) {
                showBanner("Reconnected. Syncing…", "info");
                autoSync(true, true);
            } else {
                showBanner("You're back online.", "success");
            }
        }
        Platform.runLater(this::refresh);
    }

    private void tick() {
        if (!NetworkMonitor.isOnline()) {
            NetworkMonitor.probeNow();
            return;
        }
        if (OfflineQueue.size() > 0) {
            autoSync(false, false);
        }
    }

    private void autoSync(boolean fromReconnect, boolean announceResult) {
        if (flushing || !NetworkMonitor.isOnline() || OfflineQueue.size() == 0) {
            return;
        }

        flushing = true;
        new Thread(() -> OfflineQueue.flush(
            () -> Platform.runLater(() -> {
                flushing = false;
                updateLastSync();
                refresh();
                PostService.getInstance().clearCache();
                if (announceResult) {
                    showBanner("Back online — offline actions synced!", "success");
                }
                notifySyncSuccess();
            }),
            () -> Platform.runLater(() -> {
                flushing = false;
                refresh();
                if (announceResult) {
                    String detail = OfflineQueue.getLastFlushMessage();
                    String message = detail == null || detail.isBlank()
                            ? "Sync failed. Will retry when connection is stable."
                            : detail;
                    showBanner(message, "danger");
                }
            })
        ), "offline-flush").start();
    }

    private void refresh() {
        boolean online = NetworkMonitor.isOnline();
        statusText.set(online ? "● Online" : "● Offline");
    }

    private void updateLastSync() {
        lastSyncText.set(LocalDateTime.now().format(FMT));
    }
}
