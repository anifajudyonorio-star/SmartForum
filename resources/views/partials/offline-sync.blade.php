@if(auth()->check())
<div id="offlineBanner" class="offline-banner" aria-live="polite"></div>

<div id="syncStatusCard" class="sync-status-card">
    <div class="d-flex justify-content-between align-items-center gap-2">
        <div>
            <strong id="sync-status">Online</strong>
            <span class="small text-muted ms-2"><span id="pending-count">0</span> pending</span>
        </div>
        <button type="button" id="sync-now-btn" class="btn btn-sm btn-outline-primary">Sync now</button>
    </div>
    <div class="small text-muted mt-1" id="last-sync"></div>
</div>
@endif
