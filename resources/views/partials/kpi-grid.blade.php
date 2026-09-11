<!-- KPI Grid -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Richieste Totali</span>
            <span class="kpi-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
            </span>
        </div>
        <div class="kpi-value">@{{ stats.total_requests || pagination.total || logs.length }}</div>
        <div class="kpi-sub">Tracciate nel periodo</div>
    </div>
    <div class="kpi-card kpi-error">
        <div class="kpi-top">
            <span class="kpi-label">Errori (4xx / 5xx)</span>
            <span class="kpi-icon" style="color: var(--danger-text);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </span>
        </div>
        <div class="kpi-value">@{{ stats.total_errors || 0 }}</div>
        <div class="kpi-sub">@{{ stats.error_rate || 0 }}% tasso di errore</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Latenza Media Server</span>
            <span class="kpi-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </span>
        </div>
        <div class="kpi-value">@{{ stats.avg_duration_ms ? stats.avg_duration_ms + 'ms' : '—' }}</div>
        <div class="kpi-sub">Tempo di risposta calcolato</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Rollback / Anomalie DB</span>
            <span class="kpi-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </span>
        </div>
        <div class="kpi-value">@{{ stats.pending_transactions || 0 }}</div>
        <div class="kpi-sub">Transazioni ripristinate</div>
    </div>
</div>
