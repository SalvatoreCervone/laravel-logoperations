<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogOperations — {{ $appName }} Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <style>
        :root {
            --bg-base: #0b0f17;
            --surface-primary: #111622;
            --surface-secondary: #161d2a;
            --surface-tertiary: #1b2434;
            --surface-hover: #1f293b;
            --border-subtle: #1e2638;
            --border-strong: #2a364d;
            
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --primary-subtle: rgba(59, 130, 246, 0.12);
            --primary-border: rgba(59, 130, 246, 0.25);
            
            --accent: #6366f1;
            --accent-subtle: rgba(99, 102, 241, 0.12);
            
            --success: #10b981;
            --success-subtle: rgba(16, 185, 129, 0.1);
            --success-border: rgba(16, 185, 129, 0.25);
            --success-text: #34d399;
            
            --warning: #f59e0b;
            --warning-subtle: rgba(245, 158, 11, 0.1);
            --warning-border: rgba(245, 158, 11, 0.25);
            --warning-text: #fbbf24;
            
            --danger: #ef4444;
            --danger-subtle: rgba(239, 68, 68, 0.1);
            --danger-border: rgba(239, 68, 68, 0.25);
            --danger-text: #f87171;
            
            --info: #0ea5e9;
            --info-subtle: rgba(14, 165, 233, 0.1);
            --info-text: #38bdf8;
            
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --text-subtle: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Header */
        .dash-header {
            background: var(--surface-primary);
            border-bottom: 1px solid var(--border-subtle);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .dash-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-badge {
            background: var(--primary-subtle);
            color: #60a5fa;
            border: 1px solid var(--primary-border);
            font-weight: 700;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 5px;
            letter-spacing: 0.06em;
            font-family: 'JetBrains Mono', monospace;
        }

        .brand-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .app-badge {
            font-size: 11px;
            color: var(--text-muted);
            background: var(--bg-base);
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid var(--border-subtle);
            font-weight: 500;
            font-family: 'JetBrains Mono', monospace;
        }

        .dash-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-action {
            background: var(--surface-secondary);
            color: var(--text-secondary);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 6px 13px;
            font-size: 12.5px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action:hover {
            background: var(--surface-hover);
            color: var(--text-primary);
            border-color: var(--border-strong);
        }
        .btn-action.btn-primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            font-weight: 600;
        }
        .btn-action.btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        /* Main Container */
        .dash-container {
            max-width: 1480px;
            margin: 0 auto;
            padding: 20px 24px;
        }

        /* Nav Switcher Tabs */
        .main-nav {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            background: var(--surface-primary);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid var(--border-subtle);
            overflow-x: auto;
        }

        .main-tab {
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-muted);
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .main-tab:hover {
            color: var(--text-primary);
            background: var(--surface-secondary);
        }
        .main-tab.active {
            background: var(--surface-secondary);
            color: var(--text-primary);
            border-color: var(--border-strong);
            font-weight: 600;
        }

        .tab-icon {
            display: inline-flex;
            align-items: center;
            opacity: 0.7;
        }
        .main-tab.active .tab-icon {
            opacity: 1;
            color: var(--primary);
        }

        .session-pulse {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 6px var(--success);
            display: inline-block;
            animation: pulse 1.8s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 0.7; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.7; }
        }

        /* KPI Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .kpi-card {
            background: var(--surface-primary);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 16px 18px;
            position: relative;
            transition: border-color 0.15s ease;
        }
        .kpi-card:hover {
            border-color: var(--border-strong);
        }

        .kpi-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .kpi-label {
            font-size: 11px;
            color: var(--text-subtle);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }

        .kpi-icon {
            color: var(--text-subtle);
            display: flex;
            align-items: center;
        }

        .kpi-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            font-feature-settings: 'tnum';
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .kpi-card.kpi-error .kpi-value {
            color: var(--danger-text);
        }

        .kpi-sub {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        /* Content Card */
        .content-card {
            background: var(--surface-primary);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .content-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Filter Toolbar */
        .filter-toolbar {
            background: var(--bg-base);
            border: 1px solid var(--border-subtle);
            border-radius: 7px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .filter-pills-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            padding-bottom: 12px;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .filter-pill {
            background: var(--surface-primary);
            border: 1px solid var(--border-subtle);
            color: var(--text-muted);
            border-radius: 6px;
            padding: 4px 11px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .filter-pill:hover {
            color: var(--text-primary);
            background: var(--surface-secondary);
            border-color: var(--border-strong);
        }
        .filter-pill.active {
            background: var(--primary-subtle);
            color: #60a5fa;
            border-color: var(--primary-border);
            font-weight: 600;
        }
        .filter-pill.pill-danger.active {
            background: var(--danger-subtle);
            border-color: var(--danger-border);
            color: var(--danger-text);
            font-weight: 600;
        }
        .filter-pill.pill-warning.active {
            background: var(--warning-subtle);
            border-color: var(--warning-border);
            color: var(--warning-text);
            font-weight: 600;
        }

        .filter-fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: flex-end;
        }
        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-field-label {
            font-size: 11px;
            color: var(--text-subtle);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .filter-input-ctrl {
            background: var(--surface-primary);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 7px 11px;
            color: var(--text-primary);
            font-size: 12.5px;
            outline: none;
            width: 100%;
            transition: border-color 0.15s ease;
        }
        .filter-input-ctrl:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }
        .filter-input-ctrl option {
            background: var(--surface-primary);
            color: var(--text-primary);
        }

        .btn-reset-filters {
            background: transparent;
            border: 1px solid var(--border-subtle);
            color: var(--text-muted);
            padding: 7px 11px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.15s ease;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-reset-filters:hover {
            background: var(--danger-subtle);
            color: var(--danger-text);
            border-color: var(--danger-border);
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            position: relative;
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            background: var(--surface-primary);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 12.5px;
        }
        .data-table th {
            background: var(--surface-secondary);
            color: var(--text-subtle);
            padding: 10px 14px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-subtle);
        }
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.035);
            color: var(--text-secondary);
        }
        .data-table tr:hover td {
            background: var(--surface-hover);
        }

        /* Badges */
        .badge {
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 600;
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.02em;
        }
        .badge-get { background: var(--success-subtle); color: var(--success-text); border: 1px solid var(--success-border); }
        .badge-post { background: var(--primary-subtle); color: #60a5fa; border: 1px solid var(--primary-border); }
        .badge-put, .badge-patch { background: var(--warning-subtle); color: var(--warning-text); border: 1px solid var(--warning-border); }
        .badge-delete { background: var(--danger-subtle); color: var(--danger-text); border: 1px solid var(--danger-border); }

        .status-200 { color: var(--success-text); font-weight: 600; font-family: 'JetBrains Mono', monospace; }
        .status-400 { color: var(--warning-text); font-weight: 600; font-family: 'JetBrains Mono', monospace; }
        .status-500 { color: var(--danger-text); font-weight: 600; background: var(--danger-subtle); border: 1px solid var(--danger-border); padding: 1px 6px; border-radius: 4px; font-family: 'JetBrains Mono', monospace; }

        .mono { font-family: 'JetBrains Mono', monospace; font-size: 12px; }

        /* Pagination Bar */
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 2px 2px 2px;
            margin-top: 14px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-btn {
            background: var(--surface-primary);
            border: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .page-btn:hover:not(:disabled) {
            background: var(--surface-hover);
            color: var(--text-primary);
            border-color: var(--border-strong);
        }
        .page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
        .page-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            font-weight: 600;
        }

        /* Toggle Switches */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 32px;
            height: 18px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--surface-hover);
            transition: .2s;
            border-radius: 20px;
            border: 1px solid var(--border-subtle);
        }
        .slider:before {
            position: absolute; content: ""; height: 12px; width: 12px; left: 2px; bottom: 2px;
            background-color: #94a3b8;
            transition: .2s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: var(--success);
            border-color: var(--success);
        }
        input:checked + .slider:before {
            transform: translateX(14px);
            background-color: #ffffff;
        }

        /* Storyboard Timeline Styles */
        .storyboard-timeline {
            position: relative;
            margin: 18px 0 18px 18px;
            padding-left: 24px;
            border-left: 1px solid var(--border-strong);
        }
        .timeline-event-card {
            position: relative;
            background: var(--surface-primary);
            border: 1px solid var(--border-subtle);
            border-radius: 7px;
            padding: 14px 16px;
            margin-bottom: 14px;
            transition: border-color 0.15s ease;
        }
        .timeline-event-card:hover {
            border-color: var(--border-strong);
        }
        .timeline-node-marker {
            position: absolute;
            left: -35px;
            top: 14px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--surface-secondary);
            border: 2px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            z-index: 2;
        }
        .timeline-node-marker.node-create { border-color: var(--success); color: var(--success-text); }
        .timeline-node-marker.node-update { border-color: var(--info); color: var(--info-text); }
        .timeline-node-marker.node-delete { border-color: var(--danger); color: var(--danger-text); }
        .timeline-node-marker.node-error { border-color: var(--danger); color: var(--danger-text); }
        .timeline-node-marker.node-step { border-color: var(--accent); color: #a5b4fc; }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 20px;
        }
        .modal-body {
            background: var(--surface-primary);
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.6);
        }
        .modal-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            background: var(--surface-primary);
            z-index: 10;
        }
        .modal-content { padding: 18px; }

        .guide-banner {
            background: var(--primary-subtle);
            border: 1px solid var(--primary-border);
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: 12.5px;
            color: var(--text-secondary);
        }

        .loading-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(11, 15, 23, 0.75);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .spinner {
            width: 28px; height: 28px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div id="app">
    <!-- Header -->
    <header class="dash-header">
        <div class="dash-brand">
            <span class="brand-badge">LOGOPERATIONS</span>
            <span class="brand-title">
                Dashboard Operazioni
                <span class="app-badge">{{ $appName }}</span>
            </span>
        </div>
        <div class="dash-controls">
            <button class="btn-action" @click="refreshCurrentTab">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                Aggiorna Dati
            </button>
        </div>
    </header>

    <div class="dash-container">
        <!-- Main Navigation Tabs -->
        <div class="main-nav">
            <button :class="['main-tab', { active: currentTab === 'logs' }]" @click="currentTab = 'logs'">
                <span class="tab-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"></line>
                        <line x1="12" y1="20" x2="12" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="14"></line>
                    </svg>
                </span>
                Log Operazioni (@{{ pagination.total || logs.length }})
            </button>
            <button :class="['main-tab', { active: currentTab === 'storyboard' }]" @click="currentTab = 'storyboard'; fetchStoryboard();">
                <span class="tab-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                </span>
                Storyboard Record & Audit Trail
            </button>
            <button :class="['main-tab', { active: currentTab === 'studio_routes' }]" @click="currentTab = 'studio_routes'">
                <span class="tab-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                </span>
                Studio Rotte
            </button>
            <button :class="['main-tab', { active: currentTab === 'studio_classes' }]" @click="currentTab = 'studio_classes'">
                <span class="tab-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 18 22 12 16 6"></polyline>
                        <polyline points="8 6 2 12 8 18"></polyline>
                    </svg>
                </span>
                Studio Funzioni (Metodi)
            </button>
            <button :class="['main-tab', { active: currentTab === 'studio_users' }]" @click="currentTab = 'studio_users'">
                <span class="tab-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </span>
                Monitor Utente Live <span v-if="activeSessions.length" class="session-pulse" style="margin-left: 4px;"></span>
            </button>
        </div>

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

        <!-- ============================================================= -->
        <!-- TAB 1: REGISTRO OPERAZIONI                                    -->
        <!-- ============================================================= -->
        <div v-if="currentTab === 'logs'" class="content-card">
            <!-- Filter Toolbar -->
            <div class="filter-toolbar">
                <div class="filter-pills-row" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                        <span class="filter-field-label" style="margin-right: 4px;">Filtri Rapidi:</span>
                        <button :class="['filter-pill', { active: quickFilter === 'all' }]" @click="setQuickFilter('all')">Tutti</button>
                        <button :class="['filter-pill', 'pill-danger', { active: quickFilter === 'errors' }]" @click="setQuickFilter('errors')">Solo Errori</button>
                        <button :class="['filter-pill', 'pill-warning', { active: quickFilter === 'tx' }]" @click="setQuickFilter('tx')">Rollback DB</button>
                        <button :class="['filter-pill', { active: quickFilter === 'slow' }]" @click="setQuickFilter('slow')">Lenti (&gt;1s)</button>
                        <button :class="['filter-pill', { active: quickFilter === '500' }]" @click="setQuickFilter('500')">HTTP 500</button>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button class="btn-action" @click="exportData('csv')" title="Esporta i log filtrati in CSV">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Esporta CSV
                        </button>
                        <button class="btn-action" @click="exportData('json')" title="Esporta i log filtrati in JSON">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Esporta JSON
                        </button>
                    </div>
                </div>

                <div class="filter-fields-grid">
                    <div class="filter-field">
                        <label class="filter-field-label">Cerca testo</label>
                        <input type="text" class="filter-input-ctrl" v-model="filterText" @input="debounceFetchLogs" placeholder="Rotta, controller, errore...">
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Verbo HTTP</label>
                        <select class="filter-input-ctrl" v-model="filterVerb" @change="onFilterChange">
                            <option value="">Tutti i verbi</option>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Utente / Autore</label>
                        <input type="text" class="filter-input-ctrl" v-model="filterUser" @input="debounceFetchLogs" placeholder="Nome, email o ID...">
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Data Inizio</label>
                        <input type="date" class="filter-input-ctrl" v-model="filterDateFrom" @change="onFilterChange">
                    </div>
                    <div class="filter-field">
                        <label class="filter-field-label">Data Fine</label>
                        <input type="date" class="filter-input-ctrl" v-model="filterDateTo" @change="onFilterChange">
                    </div>
                    <div>
                        <button class="btn-reset-filters" @click="resetFilters">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Azzera Filtri
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table Responsive -->
            <div class="table-responsive">
                <div v-if="loading" class="loading-overlay">
                    <div class="spinner"></div>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Metodo</th>
                            <th style="width: 70px;">Status</th>
                            <th>Utente</th>
                            <th>Rotta</th>
                            <th>Controller</th>
                            <th>IP</th>
                            <th>Durata</th>
                            <th>Data</th>
                            <th>Dettagli / Storyboard</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs" :key="log.id" style="cursor: pointer;" @click="openDetail(log)">
                            <td><span :class="['badge', getVerbClass(log.verbo)]">@{{ (log.verbo || '').toUpperCase() }}</span></td>
                            <td><span :class="getStatusClass(log.codicehttp)">@{{ log.codicehttp }}</span></td>
                            <td><span style="font-weight: 500; color: var(--text-primary);">@{{ log.user_label }}</span></td>
                            <td class="mono" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #93c5fd;">@{{ log.rotta }}</td>
                            <td style="color: #a5b4fc; font-size: 11.5px;">@{{ log.controllermethod || '—' }}</td>
                            <td class="mono" style="font-size: 11px; color: var(--text-muted);">@{{ log.client_ip }}</td>
                            <td class="mono" style="font-size: 11px;">@{{ log.duration_ms }}ms</td>
                            <td style="color: var(--text-subtle); font-size: 11px;">@{{ formatTimestamp(log.dataoperazione) }}</td>
                            <td>
                                <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                    <span v-if="log.subject_id"
                                          class="badge"
                                          style="background: var(--accent-subtle); color: #c7d2fe; border: 1px solid rgba(99, 102, 241, 0.3); cursor: pointer;"
                                          @click.stop="openStoryboardForSubject(log.subject_type, log.subject_id)"
                                          title="Visualizza Storyboard di questo record">
                                        Audit @{{ log.subject_label || ('#' + log.subject_id) }}
                                    </span>
                                    <span v-if="log.transaction_status" class="badge" style="background: var(--warning-subtle); color: var(--warning-text); border: 1px solid var(--warning-border);">TX</span>
                                    <span v-if="log.error" class="badge" style="background: var(--danger-subtle); color: var(--danger-text); border: 1px solid var(--danger-border);">ERR</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0 && !loading">
                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                Nessuna operazione registrata corrispondente ai filtri.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-bar">
                <div style="font-size: 12px; color: var(--text-subtle);">
                    Mostrati da <strong style="color: var(--text-secondary);">@{{ pagination.from || 0 }}</strong> a <strong style="color: var(--text-secondary);">@{{ pagination.to || 0 }}</strong> di <strong style="color: var(--text-secondary);">@{{ pagination.total || 0 }}</strong> log
                </div>
                <div style="display: flex; gap: 4px; align-items: center;">
                    <button class="page-btn" :disabled="currentPage <= 1" @click="goToPage(1)">«</button>
                    <button class="page-btn" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">‹ Prec</button>
                    <template v-for="p in visiblePages" :key="p">
                        <span v-if="p === '...'" style="padding: 0 4px; color: var(--text-subtle);">...</span>
                        <button v-else :class="['page-btn', { active: p === currentPage }]" @click="goToPage(p)">@{{ p }}</button>
                    </template>
                    <button class="page-btn" :disabled="currentPage >= pagination.last_page" @click="goToPage(currentPage + 1)">Succ ›</button>
                    <button class="page-btn" :disabled="currentPage >= pagination.last_page" @click="goToPage(pagination.last_page)">»</button>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 12px; color: var(--text-subtle);">Per pagina:</label>
                    <select class="filter-input-ctrl" style="width: 70px; padding: 4px 8px;" v-model="perPage" @change="onPerPageChange">
                        <option v-for="opt in perPageOptions" :key="opt" :value="opt">@{{ opt }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 2: STORYBOARD DEL RECORD (AUDIT TRAIL)                    -->
        <!-- ============================================================= -->
        <div v-if="currentTab === 'storyboard'" class="content-card">
            <div class="content-header">
                <div>
                    <div class="content-title">Storyboard del Record (Audit Trail & Cronologia di Vita)</div>
                    <span style="font-size: 12px; color: var(--text-muted);">Tracciamento cronologico completo di ogni modello Eloquent collegato come soggetto dell'operazione</span>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button class="btn-action" @click="toggleStoryboardSort">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="7 11 12 6 17 11"></polyline>
                            <polyline points="17 13 12 18 7 13"></polyline>
                        </svg>
                        @{{ storyboardSort === 'asc' ? 'Cronologico (Meno recenti prima)' : 'Recenti prima' }}
                    </button>
                </div>
            </div>

            <div class="guide-banner">
                <div style="margin-top: 1px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary);">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
                <div>
                    <strong style="color: var(--text-primary);">Audit Trail Completo:</strong>
                    <p>Fornisce la storia completa di qualsiasi entità aziendale (Ordini, Contratti, Ticket, Utenti). Mostra chi l'ha creata, quali chiamate l'hanno modificata, quali checkpoint applicativi sono stati registrati con <code>$model->logStep()</code> ed eventuali errori verificatisi.</p>
                </div>
            </div>

            <!-- Subject Selector Toolbar -->
            <div style="background: var(--bg-base); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 14px; margin-bottom: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <label class="filter-field-label" style="display: block; margin-bottom: 4px;">Soggetti Recenti Rilevati nei Log:</label>
                    <select v-model="selectedSubjectKey" class="filter-input-ctrl" @change="onSubjectSelectChange">
                        <option value="">-- Seleziona o inserisci manualmente sotto --</option>
                        <option v-for="s in subjectsList" :key="s.type + '_' + s.id" :value="s.type + '::' + s.id">
                            @{{ s.label }} (@{{ s.type }})
                        </option>
                    </select>
                </div>

                <div style="display: flex; gap: 8px; align-items: flex-end;">
                    <div>
                        <label class="filter-field-label" style="display: block; margin-bottom: 4px;">Classe Modello (Subject Type):</label>
                        <input type="text" class="filter-input-ctrl" v-model="customSubjectType" placeholder="Es. App\Models\Order" style="width: 220px;">
                    </div>
                    <div>
                        <label class="filter-field-label" style="display: block; margin-bottom: 4px;">ID Record:</label>
                        <input type="text" class="filter-input-ctrl" v-model="customSubjectId" placeholder="Es. 1" style="width: 90px;">
                    </div>
                    <button class="btn-action btn-primary" @click="fetchStoryboard" style="height: 35px;">
                        Carica Storyboard
                    </button>
                </div>
            </div>

            <!-- Storyboard Category Filter Pills -->
            <div v-if="storyboardData && storyboardData.events" style="display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap;">
                <button :class="['filter-pill', { active: storyboardCategory === 'all' }]" @click="storyboardCategory = 'all'">Tutti (@{{ storyboardData.events.length }})</button>
                <button :class="['filter-pill', { active: storyboardCategory === 'create' }]" @click="storyboardCategory = 'create'">Creazione</button>
                <button :class="['filter-pill', { active: storyboardCategory === 'update' }]" @click="storyboardCategory = 'update'">Modifiche</button>
                <button :class="['filter-pill', { active: storyboardCategory === 'step' }]" @click="storyboardCategory = 'step'">Checkpoint</button>
                <button :class="['filter-pill', 'pill-danger', { active: storyboardCategory === 'error' }]" @click="storyboardCategory = 'error'">Errori</button>
            </div>

            <!-- Storyboard Loading -->
            <div v-if="storyboardLoading" style="text-align: center; padding: 36px; color: var(--text-muted);">
                <div class="spinner" style="margin: 0 auto 10px auto;"></div>
                Caricamento linea del tempo in corso...
            </div>

            <!-- Storyboard Timeline -->
            <div v-else-if="filteredStoryboardEvents.length" class="storyboard-timeline">
                <div v-for="(event, idx) in filteredStoryboardEvents" :key="event.id" class="timeline-event-card">
                    <div :class="['timeline-node-marker', 'node-' + (event.classification?.category || 'default')]">
                        •
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span class="badge" :style="{ background: event.classification?.badge_bg || '#1e2638', color: event.classification?.badge_color || '#fff', marginRight: '8px' }">
                                @{{ event.classification?.label || 'EVENTO' }}
                            </span>
                            <span class="mono" style="font-weight: 600; color: var(--text-primary); font-size: 13.5px;">@{{ event.route }}</span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--text-subtle); font-family: 'JetBrains Mono', monospace;">
                            @{{ event.time_human }} (@{{ event.time_iso }})
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; flex-wrap: wrap;">
                        <div><strong style="color: var(--text-secondary);">Autore:</strong> @{{ event.user?.name || event.user?.email || 'Ospite' }}</div>
                        <div><strong style="color: var(--text-secondary);">Durata:</strong> @{{ event.duration_ms }}ms</div>
                        <div><strong style="color: var(--text-secondary);">IP:</strong> @{{ event.ip_address }}</div>
                        <div><strong style="color: var(--text-secondary);">Status:</strong> <span :class="getStatusClass(event.status_code)">@{{ event.status_code }}</span></div>
                    </div>

                    <!-- Steps ($model->logStep) -->
                    <div v-if="event.custom_traces?.steps?.length" style="background: var(--bg-base); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 10px; margin-top: 8px;">
                        <div style="font-size: 11.5px; font-weight: 600; color: #38bdf8; margin-bottom: 6px;">Passaggi Applicativi Registrati:</div>
                        <div v-for="(st, sI) in event.custom_traces.steps" :key="sI" style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">
                            • <strong>@{{ st.label }}</strong>
                            <pre v-if="st.context && Object.keys(st.context).length" style="background: var(--surface-primary); padding: 6px; border-radius: 4px; font-size: 11px; margin-top: 4px; color: var(--text-muted); border: 1px solid var(--border-subtle);">@{{ JSON.stringify(st.context, null, 2) }}</pre>
                        </div>
                    </div>

                    <!-- Error Alert -->
                    <div v-if="event.error" style="background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: 6px; padding: 8px; margin-top: 8px; color: var(--danger-text); font-size: 12px;">
                        <strong>Errore:</strong> @{{ event.error }}
                    </div>
                </div>
            </div>

            <div v-else-if="!storyboardLoading" style="text-align: center; padding: 36px; color: var(--text-muted);">
                Nessun evento Storyboard trovato per il modello selezionato. Seleziona un modello in alto per visualizzare la cronologia.
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 3: STUDIO ROTTE                                           -->
        <!-- ============================================================= -->
        <div v-if="currentTab === 'studio_routes'" class="content-card">
            <div class="content-header">
                <div>
                    <div class="content-title">Studio Rotte (Controllo Pagine & API Senza Codice)</div>
                    <span style="font-size: 12px; color: var(--text-muted);">Riconoscimento automatico delle rotte applicative con attivazione del tracciamento a 1 click</span>
                </div>
                <input v-model="routeFilter" type="text" class="filter-input-ctrl" placeholder="Filtra rotta o controller..." style="max-width: 250px;">
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Tracciamento</th>
                            <th style="width: 130px;">Metodi</th>
                            <th>URI Rotta</th>
                            <th>Controller & Metodo</th>
                            <th style="width: 150px;">Livello Dettaglio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in filteredRoutes" :key="r.uri">
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" :checked="r.is_tracked" @change="toggleRouteTracking(r)">
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>
                                <span v-for="m in r.methods" :key="m" :class="['badge', 'badge-' + m.toLowerCase()]" style="margin-right: 4px;">
                                    @{{ m }}
                                </span>
                            </td>
                            <td class="mono" style="color: #93c5fd;">/@{{ r.clean_uri }}</td>
                            <td style="color: #a5b4fc; font-size: 12px;">@{{ r.controller }}@{{ r.controller_method ? '@' + r.controller_method : '' }}</td>
                            <td>
                                <select v-model="r.stack_level" class="filter-input-ctrl" style="padding: 4px 8px; width: auto;" @change="updateRouteLevel(r)">
                                    <option value="base">Base</option>
                                    <option value="core">Core Stack</option>
                                    <option value="full">Completo</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 4: STUDIO FUNZIONI & METODI PHP                           -->
        <!-- ============================================================= -->
        <div v-if="currentTab === 'studio_classes'" class="content-card">
            <div class="content-header">
                <div>
                    <div class="content-title">Studio Funzioni (Dynamic Proxy & Interceptor Metodi)</div>
                    <span style="font-size: 12px; color: var(--text-muted);">Intercetta l'esecuzione dei metodi di business logic senza alterare il codice dell'applicazione</span>
                </div>
            </div>

            <div v-for="cls in classes" :key="cls.class" style="margin-bottom: 14px; background: var(--bg-base); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 14px;">
                <div style="font-weight: 600; color: var(--text-primary); font-size: 13.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                    <span class="mono">@{{ cls.class }}</span>
                    <span v-if="cls.has_traceable_attribute" class="badge" style="background: var(--accent-subtle); color: #c7d2fe; border: 1px solid rgba(99, 102, 241, 0.3);">#[Traceable]</span>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <div v-for="m in cls.methods" :key="m.name" style="background: var(--surface-primary); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 6px 10px; display: flex; align-items: center; gap: 8px;">
                        <label class="toggle-switch">
                            <input type="checkbox" :checked="m.is_tracked" @change="toggleMethodTracking(cls.class, m)">
                            <span class="slider"></span>
                        </label>
                        <span class="mono" style="font-size: 12px; color: var(--text-secondary);">@{{ m.name }}()</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 5: MONITOR UTENTE LIVE                                    -->
        <!-- ============================================================= -->
        <div v-if="currentTab === 'studio_users'" class="content-card">
            <div class="content-header">
                <div>
                    <div class="content-title">Monitor Utente Live a Tempo</div>
                    <span style="font-size: 12px; color: var(--text-muted);">Traccia in tempo reale tutte le attività di un utente specifico per una finestra temporale controllata</span>
                </div>
            </div>

            <!-- Avvio Sessione Form -->
            <div style="background: var(--bg-base); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 14px; margin-bottom: 16px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label class="filter-field-label" style="display: block; margin-bottom: 4px;">Seleziona Utente:</label>
                    <select v-model="targetUserId" class="filter-input-ctrl" style="width: 250px;">
                        <option value="">-- Seleziona Utente --</option>
                        <option v-for="u in usersList" :key="u.id" :value="u.id">
                            @{{ u.name }} (@{{ u.email }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="filter-field-label" style="display: block; margin-bottom: 4px;">Durata Sessione:</label>
                    <select v-model="userDuration" class="filter-input-ctrl" style="width: 140px;">
                        <option :value="5">5 Minuti</option>
                        <option :value="15">15 Minuti</option>
                        <option :value="30">30 Minuti</option>
                        <option :value="60">60 Minuti</option>
                    </select>
                </div>
                <button class="btn-action btn-primary" @click="startLiveSession" :disabled="!targetUserId" style="height: 35px;">
                    Avvia Monitoraggio
                </button>
            </div>

            <!-- Sessioni Attive -->
            <div v-if="activeSessions.length">
                <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 8px; font-size: 13px;">Sessioni Live Attive:</div>
                <div v-for="s in activeSessions" :key="s.id" style="background: var(--bg-base); border: 1px solid var(--success-border); border-radius: 6px; padding: 10px 14px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <span class="session-pulse"></span>
                        <strong style="color: var(--text-primary); margin-left: 8px; font-size: 13px;">Utente ID: @{{ s.target }}</strong>
                        <span style="color: var(--text-muted); font-size: 12px; margin-left: 8px;">
                            (Tempo residuo: @{{ s.seconds_left ? formatSeconds(s.seconds_left) : 'In corso' }})
                        </span>
                    </div>
                    <button class="page-btn" style="border-color: var(--danger-border); color: var(--danger-text);" @click="stopLiveSession(s.id)">
                        Arresta
                    </button>
                </div>
            </div>
            <div v-else style="color: var(--text-muted); font-size: 12.5px;">
                Nessuna sessione di monitoraggio utente attiva al momento.
            </div>
        </div>
    </div>

    <!-- Modal Dettaglio Singolo Log -->
    <div v-if="activeLog" class="modal-overlay" @click.self="closeDetail">
        <div class="modal-body">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span :class="['badge', getVerbClass(activeLog.verbo)]">@{{ (activeLog.verbo || '').toUpperCase() }}</span>
                    <span :class="['badge', getStatusClass(activeLog.codicehttp)]">@{{ activeLog.codicehttp }}</span>
                    <span class="mono" style="font-weight: 600; font-size: 13.5px; color: var(--text-primary);">@{{ activeLog.rotta }}</span>
                </div>
                <button style="background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer;" @click="closeDetail">✕</button>
            </div>

            <div class="modal-content">
                <div style="display: flex; gap: 8px; margin-bottom: 14px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 8px;">
                    <button :class="['page-btn', { active: modalTab === 'overview' }]" @click="modalTab = 'overview'">Riepilogo & Dati</button>
                    <button :class="['page-btn', { active: modalTab === 'stack' }]" @click="modalTab = 'stack'">
                        Stack Trace (@{{ modalStackView === 'core' ? (activeLog.core_stack ? activeLog.core_stack.length : 0) : (activeLog.stack_trace ? activeLog.stack_trace.length : 0) }})
                    </button>
                </div>

                <!-- Overview -->
                <div v-if="modalTab === 'overview'">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; font-size: 13px;">
                        <div><span style="color: var(--text-subtle);">Utente:</span> <strong style="color: var(--text-primary);">@{{ activeLog.user_label }}</strong></div>
                        <div><span style="color: var(--text-subtle);">IP:</span> <span class="mono">@{{ activeLog.client_ip }}</span></div>
                        <div><span style="color: var(--text-subtle);">Durata:</span> <span class="mono">@{{ activeLog.duration_ms }} ms</span></div>
                        <div><span style="color: var(--text-subtle);">Data:</span> @{{ formatTimestamp(activeLog.dataoperazione) }}</div>
                        <div><span style="color: var(--text-subtle);">Controller:</span> <span style="color: #a5b4fc;">@{{ activeLog.controllermethod || 'N/A' }}</span></div>
                        <div><span style="color: var(--text-subtle);">Transazione:</span> @{{ activeLog.transaction_status || 'Nessuna anomalia' }}</div>
                    </div>

                    <div v-if="activeLog.subject_id" style="background: var(--accent-subtle); border: 1px solid var(--accent); border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--text-primary);">Soggetto Storyboard:</strong> @{{ activeLog.subject_label || (activeLog.subject_type + ' #' + activeLog.subject_id) }}
                        </div>
                        <button class="btn-action" style="background: var(--accent); color: #ffffff;" @click="openStoryboardForSubject(activeLog.subject_type, activeLog.subject_id)">
                            Apri Storyboard
                        </button>
                    </div>

                    <div v-if="activeLog.error" style="background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; color: var(--danger-text);">
                        <strong>Errore:</strong>
                        <pre style="margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; white-space: pre-wrap;">@{{ activeLog.error }}</pre>
                    </div>

                    <div>
                        <strong style="font-size: 12.5px; color: var(--text-secondary);">Parametri Richiesta:</strong>
                        <pre style="background: var(--bg-base); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 10px; margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 11.5px; max-height: 220px; overflow: auto; color: var(--text-muted);">@{{ JSON.stringify(activeLog.parametri, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Stack Trace -->
                <div v-if="modalTab === 'stack'">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 12px; color: var(--text-subtle);">Visualizzazione:</span>
                        <div style="display: flex; gap: 6px;">
                            <button :class="['page-btn', { active: modalStackView === 'core' }]" @click="modalStackView = 'core'">Solo Codice Core</button>
                            <button :class="['page-btn', { active: modalStackView === 'full' }]" @click="modalStackView = 'full'">Stack Completo</button>
                        </div>
                    </div>

                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-subtle); border-radius: 6px;">
                        <div v-for="(frame, fIdx) in displayedStackFrames" :key="fIdx" style="padding: 8px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.035); font-size: 12px;">
                            <span style="color: var(--text-subtle); font-family: 'JetBrains Mono', monospace;">#@{{ fIdx + 1 }}</span>
                            <span style="color: var(--text-primary); font-weight: 500; margin: 0 6px;">@{{ frame.class ? frame.class + '::' + frame.function : frame.function }}</span>
                            <span v-if="frame.is_core" class="badge" style="background: var(--primary-subtle); color: #93c5fd; border: 1px solid var(--primary-border);">CORE</span>
                            <div style="color: var(--text-subtle); font-size: 11px; margin-top: 2px; font-family: 'JetBrains Mono', monospace;">
                                @{{ frame.file }}:@{{ frame.line }}
                            </div>
                        </div>
                        <div v-if="displayedStackFrames.length === 0" style="color: var(--text-muted); padding: 12px; text-align: center;">
                            Nessun frame disponibile per questa visualizzazione.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const { createApp, ref, computed, onMounted } = Vue;

    createApp({
        setup() {
            const apiPrefix = '{{ $apiPrefix }}';
            const currentTab = ref('logs');
            const logs = ref([]);
            const stats = ref({});
            const loading = ref(false);

            // Paginazione
            const currentPage = ref(1);
            const perPage = ref({{ $perPage }});
            const perPageOptions = ref(@json($perPageOptions));
            const pagination = ref({ total: 0, last_page: 1, from: 0, to: 0 });

            // Filtri
            const quickFilter = ref('all');
            const filterText = ref('');
            const filterVerb = ref('');
            const filterUser = ref('');
            const filterDateFrom = ref('');
            const filterDateTo = ref('');

            // Modal
            const activeLog = ref(null);
            const modalTab = ref('overview');
            const modalStackView = ref('core');

            // Tracking Studio State
            const routes = ref([]);
            const routeFilter = ref('');
            const classes = ref([]);
            const usersList = ref([]);
            const activeSessions = ref([]);
            const targetUserId = ref('');
            const userDuration = ref(15);

            // Storyboard State
            const subjectsList = ref([]);
            const selectedSubjectKey = ref('');
            const customSubjectType = ref('');
            const customSubjectId = ref('');
            const storyboardData = ref({ events: [] });
            const storyboardLoading = ref(false);
            const storyboardCategory = ref('all');
            const storyboardSort = ref('asc');

            let debounceTimer = null;

            function debounceFetchLogs() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    currentPage.value = 1;
                    fetchLogs(1);
                }, 300);
            }

            function onFilterChange() {
                currentPage.value = 1;
                fetchLogs(1);
            }

            function setQuickFilter(key) {
                quickFilter.value = key;
                currentPage.value = 1;
                fetchLogs(1);
            }

            function resetFilters() {
                quickFilter.value = 'all';
                filterText.value = '';
                filterVerb.value = '';
                filterUser.value = '';
                filterDateFrom.value = '';
                filterDateTo.value = '';
                currentPage.value = 1;
                fetchLogs(1);
            }

            function getExportUrl(format) {
                const params = new URLSearchParams();
                params.set('format', format);
                if (filterText.value) params.set('text', filterText.value);
                if (filterVerb.value) params.set('verb', filterVerb.value);
                if (filterUser.value) params.set('user', filterUser.value);
                if (filterDateFrom.value) params.set('date_from', filterDateFrom.value);
                if (filterDateTo.value) params.set('date_to', filterDateTo.value);

                if (quickFilter.value === 'errors') params.set('has_error', '1');
                if (quickFilter.value === 'tx') params.set('has_unfinished_transaction', '1');
                if (quickFilter.value === 'slow') params.set('min_duration', '1000');
                if (quickFilter.value === '500') params.set('status_codes[]', '500');

                return `/${apiPrefix}/export?${params.toString()}`;
            }

            function exportData(format) {
                window.open(getExportUrl(format), '_blank');
            }

            async function fetchLogs(page = 1) {
                loading.value = true;
                try {
                    const params = new URLSearchParams();
                    params.append('page', page.toString());
                    params.append('per_page', perPage.value.toString());

                    if (filterText.value) params.append('text', filterText.value);
                    if (filterVerb.value) params.append('verb', filterVerb.value);
                    if (filterUser.value) params.append('user', filterUser.value);
                    if (filterDateFrom.value) params.append('date_from', filterDateFrom.value);
                    if (filterDateTo.value) params.append('date_to', filterDateTo.value);

                    if (quickFilter.value === 'errors') params.append('has_error', '1');
                    if (quickFilter.value === 'tx') params.append('has_unfinished_transaction', '1');
                    if (quickFilter.value === 'slow') params.append('min_duration', '1000');
                    if (quickFilter.value === '500') params.append('status_codes[]', '500');

                    const res = await fetch(`/${apiPrefix}?${params.toString()}`);
                    const data = await res.json();
                    logs.value = data.data || [];
                    currentPage.value = data.current_page || 1;
                    pagination.value = {
                        total: data.total || 0,
                        last_page: data.last_page || 1,
                        from: data.from || 0,
                        to: data.to || 0,
                    };
                } catch (e) {
                } finally {
                    loading.value = false;
                }

                try {
                    const sRes = await fetch(`/${apiPrefix}/stats`);
                    stats.value = await sRes.json();
                } catch (e) {}
            }

            function goToPage(page) {
                if (page >= 1 && page <= pagination.value.last_page && page !== currentPage.value) {
                    currentPage.value = page;
                    fetchLogs(page);
                }
            }

            function onPerPageChange() {
                currentPage.value = 1;
                fetchLogs(1);
            }

            const visiblePages = computed(() => {
                const total = pagination.value.last_page || 1;
                const current = currentPage.value;
                const pages = [];
                const delta = 2;

                for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
                    pages.push(i);
                }
                if (current - delta > 2) pages.unshift('...');
                pages.unshift(1);
                if (current + delta < total - 1) pages.push('...');
                if (total > 1) pages.push(total);
                return pages;
            });

            function getVerbClass(verbo) {
                switch ((verbo || '').toLowerCase()) {
                    case 'get': return 'badge-get';
                    case 'post': return 'badge-post';
                    case 'put': case 'patch': return 'badge-put';
                    case 'delete': return 'badge-delete';
                    default: return 'badge-get';
                }
            }

            function getStatusClass(status) {
                if (status >= 500) return 'status-500';
                if (status >= 400) return 'status-400';
                return 'status-200';
            }

            function formatTimestamp(ts) {
                if (!ts) return '—';
                const d = new Date(ts);
                return d.toLocaleDateString('it-IT') + ' ' + d.toLocaleTimeString('it-IT');
            }

            function formatSeconds(secs) {
                const m = Math.floor(secs / 60);
                const s = secs % 60;
                return `${m}m ${s < 10 ? '0' : ''}${s}s`;
            }

            async function openDetail(log) {
                try {
                    const res = await fetch(`/${apiPrefix}/${log.id}`);
                    const detail = await res.json();
                    activeLog.value = {
                        ...detail.data,
                        core_stack: detail.core_stack,
                        full_stack: detail.full_stack,
                    };
                    modalTab.value = 'overview';
                    modalStackView.value = 'core';
                } catch (e) {
                    activeLog.value = log;
                }
            }

            function closeDetail() { activeLog.value = null; }

            const displayedStackFrames = computed(() => {
                if (!activeLog.value) return [];
                if (modalStackView.value === 'core') {
                    if (activeLog.value.core_stack && activeLog.value.core_stack.length) {
                        return activeLog.value.core_stack;
                    }
                    return (activeLog.value.stack_trace || []).filter(f => f.is_core);
                }
                return activeLog.value.full_stack || activeLog.value.stack_trace || [];
            });

            // Tracking Studio Logic
            async function fetchStudioData() {
                try {
                    const [rRes, cRes, ruRes, uRes] = await Promise.all([
                        fetch(`/${apiPrefix}/studio/routes`),
                        fetch(`/${apiPrefix}/studio/classes`),
                        fetch(`/${apiPrefix}/studio/rules`),
                        fetch(`/${apiPrefix}/studio/users`),
                    ]);
                    routes.value = await rRes.json();
                    classes.value = await cRes.json();
                    const rules = await ruRes.json();
                    usersList.value = await uRes.json();
                    activeSessions.value = rules.filter(r => r.type === 'user_session' && r.is_active);
                } catch (e) {}
            }

            const filteredRoutes = computed(() => {
                if (!routeFilter.value) return routes.value;
                const f = routeFilter.value.toLowerCase();
                return routes.value.filter(r => r.uri.toLowerCase().includes(f) || (r.controller && r.controller.toLowerCase().includes(f)));
            });

            async function toggleRouteTracking(route) {
                const newState = !route.is_tracked;
                route.is_tracked = newState;
                await fetch(`/${apiPrefix}/studio/rules`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ type: 'route', target: route.clean_uri, is_active: newState, stack_level: route.stack_level || 'core' })
                });
            }

            async function updateRouteLevel(route) {
                await fetch(`/${apiPrefix}/studio/rules`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ type: 'route', target: route.clean_uri, is_active: route.is_tracked, stack_level: route.stack_level })
                });
            }

            async function toggleMethodTracking(className, method) {
                const newState = !method.is_tracked;
                method.is_tracked = newState;
                await fetch(`/${apiPrefix}/studio/rules`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ type: 'method', target: className + '@' + method.name, is_active: newState })
                });
            }

            async function startLiveSession() {
                if (!targetUserId.value) return;
                await fetch(`/${apiPrefix}/studio/user-session`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: targetUserId.value, duration_minutes: userDuration.value })
                });
                await fetchStudioData();
            }

            async function stopLiveSession(ruleId) {
                await fetch(`/${apiPrefix}/studio/user-session/${ruleId}`, { method: 'DELETE' });
                await fetchStudioData();
            }

            // Storyboard Logic
            async function fetchStoryboardSubjects() {
                try {
                    const res = await fetch(`/${apiPrefix}/storyboard/subjects`);
                    if (res.ok) {
                        subjectsList.value = await res.json();
                        if (subjectsList.value.length && !selectedSubjectKey.value) {
                            selectedSubjectKey.value = subjectsList.value[0].type + '::' + subjectsList.value[0].id;
                            customSubjectType.value = subjectsList.value[0].type;
                            customSubjectId.value = subjectsList.value[0].id;
                        }
                    }
                } catch (e) {}
            }

            function onSubjectSelectChange() {
                if (selectedSubjectKey.value) {
                    const parts = selectedSubjectKey.value.split('::');
                    customSubjectType.value = parts[0];
                    customSubjectId.value = parts[1];
                    fetchStoryboard();
                }
            }

            async function fetchStoryboard() {
                const type = customSubjectType.value;
                const id = customSubjectId.value;
                if (!type || !id) {
                    if (!subjectsList.value.length) {
                        await fetchStoryboardSubjects();
                    }
                }
                if (!customSubjectType.value || !customSubjectId.value) return;

                storyboardLoading.value = true;
                try {
                    const url = new URL(`/${apiPrefix}/storyboard`, window.location.origin);
                    url.searchParams.set('subject_type', customSubjectType.value);
                    url.searchParams.set('subject_id', customSubjectId.value);
                    url.searchParams.set('order', storyboardSort.value);

                    const res = await fetch(url.toString(), {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        storyboardData.value = await res.json();
                    }
                } catch (e) {
                } finally {
                    storyboardLoading.value = false;
                }
            }

            function toggleStoryboardSort() {
                storyboardSort.value = storyboardSort.value === 'asc' ? 'desc' : 'asc';
                fetchStoryboard();
            }

            const filteredStoryboardEvents = computed(() => {
                let list = storyboardData.value.events || [];
                if (storyboardCategory.value !== 'all') {
                    list = list.filter(e => e.classification?.category === storyboardCategory.value);
                }
                return list;
            });

            async function openStoryboardForSubject(subjectType, subjectId) {
                if (activeLog.value) activeLog.value = null;
                customSubjectType.value = subjectType;
                customSubjectId.value = subjectId;
                selectedSubjectKey.value = subjectType + '::' + subjectId;
                currentTab.value = 'storyboard';
                await fetchStoryboard();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function refreshCurrentTab() {
                if (currentTab.value === 'logs') fetchLogs(currentPage.value);
                else if (currentTab.value === 'storyboard') fetchStoryboard();
                else fetchStudioData();
            }

            onMounted(() => {
                fetchLogs(1);
                fetchStudioData();
                fetchStoryboardSubjects().then(() => fetchStoryboard());
                setInterval(() => {
                    activeSessions.value.forEach(s => { if (s.seconds_left > 0) s.seconds_left--; });
                }, 1000);
            });

            return {
                currentTab, logs, stats, loading, currentPage, perPage, perPageOptions, pagination, visiblePages,
                quickFilter, filterText, filterVerb, filterUser, filterDateFrom, filterDateTo, activeLog, modalTab,
                modalStackView, displayedStackFrames,
                // Studio
                routes, routeFilter, filteredRoutes, classes, usersList, activeSessions, targetUserId, userDuration,
                toggleRouteTracking, updateRouteLevel, toggleMethodTracking, startLiveSession, stopLiveSession,
                // Storyboard
                subjectsList, selectedSubjectKey, customSubjectType, customSubjectId, storyboardData, storyboardLoading,
                storyboardCategory, storyboardSort, filteredStoryboardEvents, onSubjectSelectChange, fetchStoryboard,
                toggleStoryboardSort, openStoryboardForSubject, refreshCurrentTab,
                // Actions
                fetchLogs, goToPage, onPerPageChange, debounceFetchLogs, onFilterChange, setQuickFilter, resetFilters,
                getVerbClass, getStatusClass, formatTimestamp, formatSeconds, openDetail, closeDetail, exportData,
            };
        }
    }).mount('#app');
</script>
</body>
</html>
