<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogOperations — {{ $appName }} Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <style>
        :root {
            --bg-dark: #090d16;
            --surface-dark: #0f172a;
            --surface-card: #1e293b;
            --surface-hover: #273549;
            --border-color: #334155;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --accent: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.5;
        }

        /* Top Header */
        .dash-header {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 2px solid var(--primary);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .dash-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-badge {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #fff;
            font-weight: 800;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            letter-spacing: 0.05em;
        }

        .brand-title {
            font-size: 17px;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .app-badge {
            font-size: 12px;
            color: var(--text-muted);
            background: #090d16;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .dash-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-refresh {
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-refresh:hover { background: #4338ca; }

        /* Main Container */
        .dash-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Nav Switcher Tabs */
        .main-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            background: var(--surface-dark);
            padding: 6px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
        }

        .main-tab {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .main-tab:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }
        .main-tab.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .session-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px #10b981;
            display: inline-block;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.8; }
        }

        /* KPI Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        .kpi-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: var(--primary);
        }
        .kpi-card.kpi-error::after { background: var(--danger); }
        .kpi-card.kpi-time::after { background: #38bdf8; }
        .kpi-card.kpi-tx::after { background: #a855f7; }

        .kpi-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .kpi-value {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            margin: 6px 0 2px 0;
            font-family: 'JetBrains Mono', monospace;
        }
        .kpi-sub {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Content Card */
        .content-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .content-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Filter Toolbar */
        .filter-toolbar {
            background: #0f172a;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .filter-pills-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            padding-bottom: 14px;
            margin-bottom: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .filter-pill {
            background: #1e293b;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .filter-pill:hover { color: #fff; background: var(--surface-hover); }
        .filter-pill.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .filter-pill.pill-danger.active { background: var(--danger); border-color: var(--danger); }
        .filter-pill.pill-warning.active { background: var(--warning); border-color: var(--warning); color: #000; }

        .filter-fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: flex-end;
        }
        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-field-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
        }
        .filter-input-ctrl {
            background: #090d16;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 8px 12px;
            color: #fff;
            font-size: 13px;
            outline: none;
            width: 100%;
        }
        .filter-input-ctrl:focus { border-color: var(--primary); }

        .btn-reset-filters {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
            width: 100%;
        }
        .btn-reset-filters:hover { background: rgba(239, 68, 68, 0.15); color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            position: relative;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }
        .data-table th {
            background: #0f172a;
            color: var(--text-muted);
            padding: 12px 14px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
        }
        .data-table tr:hover td {
            background: var(--surface-hover);
        }

        /* Badges */
        .badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
        }
        .badge-get { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-post { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-put, .badge-patch { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-delete { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .status-200 { color: #34d399; font-weight: 700; }
        .status-400 { color: #fbbf24; font-weight: 700; }
        .status-500 { color: #f87171; font-weight: 700; background: rgba(239, 68, 68, 0.15); padding: 2px 6px; border-radius: 4px; }

        .mono { font-family: 'JetBrains Mono', monospace; font-size: 12px; }

        /* Pagination Bar */
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0 4px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-btn {
            background: #0f172a;
            border: 1px solid var(--border-color);
            color: #fff;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .page-btn:hover:not(:disabled) { background: var(--surface-hover); }
        .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .page-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        /* Toggle Switches */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #334155;
            transition: .3s;
            border-radius: 20px;
        }
        .slider:before {
            position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #10b981; }
        input:checked + .slider:before { transform: translateX(16px); }

        /* Storyboard Timeline Styles */
        .storyboard-timeline {
            position: relative;
            margin: 20px 0 20px 20px;
            padding-left: 28px;
            border-left: 2px dashed rgba(99, 102, 241, 0.4);
        }
        .timeline-event-card {
            position: relative;
            background: #0f172a;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }
        .timeline-event-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.15);
        }
        .timeline-node-marker {
            position: absolute;
            left: -43px;
            top: 14px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #1e293b;
            border: 2px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            z-index: 2;
        }
        .timeline-node-marker.node-create { border-color: #10b981; background: #064e3b; }
        .timeline-node-marker.node-update { border-color: #38bdf8; background: #0c4a6e; }
        .timeline-node-marker.node-delete { border-color: #f87171; background: #7f1d1d; }
        .timeline-node-marker.node-error { border-color: #ef4444; background: #991b1b; }
        .timeline-node-marker.node-step { border-color: #8b5cf6; background: #4c1d95; }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 20px;
        }
        .modal-body {
            background: #0f172a;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            background: #0f172a;
            z-index: 10;
        }
        .modal-content { padding: 20px; }

        /* Floating Tooltip */
        .global-floating-tooltip {
            position: fixed;
            background: #0f172a;
            color: #f8fafc;
            border: 1px solid #475569;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            line-height: 1.4;
            max-width: 320px;
            z-index: 99999;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.6);
            pointer-events: none;
            display: none;
        }

        .guide-banner {
            background: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: 13px;
        }
        .guide-icon { font-size: 20px; }

        .loading-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .spinner {
            width: 36px; height: 36px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
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
            <button class="btn-refresh" @click="refreshCurrentTab">
                🔄 Aggiorna Dati
            </button>
        </div>
    </header>

    <div class="dash-container">
        <!-- Main Navigation Tabs -->
        <div class="main-nav">
            <button :class="['main-tab', { active: currentTab === 'logs' }]" @click="currentTab = 'logs'">
                📊 Log Operazioni (@{{ pagination.total || logs.length }})
            </button>
            <button :class="['main-tab', { active: currentTab === 'storyboard' }]" @click="currentTab = 'storyboard'; fetchStoryboard();">
                📖 Storyboard Record & Audit Trail
            </button>
            <button :class="['main-tab', { active: currentTab === 'studio_routes' }]" @click="currentTab = 'studio_routes'">
                🗺️ Studio Rotte (Pagine & API)
            </button>
            <button :class="['main-tab', { active: currentTab === 'studio_classes' }]" @click="currentTab = 'studio_classes'">
                ⚙️ Studio Funzioni (Metodi PHP)
            </button>
            <button :class="['main-tab', { active: currentTab === 'studio_users' }]" @click="currentTab = 'studio_users'">
                👤 Monitor Utente Live <span v-if="activeSessions.length" class="session-pulse"></span>
            </button>
        </div>

        <!-- KPI Grid -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Richieste Totali</div>
                <div class="kpi-value">@{{ stats.total_requests || pagination.total || logs.length }}</div>
                <div class="kpi-sub">Tracciate nel periodo</div>
            </div>
            <div class="kpi-card kpi-error">
                <div class="kpi-label">Errori (4xx / 5xx)</div>
                <div class="kpi-value">@{{ stats.total_errors || 0 }}</div>
                <div class="kpi-sub">@{{ stats.error_rate || 0 }}% tasso di errore</div>
            </div>
            <div class="kpi-card kpi-time">
                <div class="kpi-label">Latenza Media Server</div>
                <div class="kpi-value">@{{ stats.avg_duration_ms ? stats.avg_duration_ms + 'ms' : '—' }}</div>
                <div class="kpi-sub">Tempo di risposta</div>
            </div>
            <div class="kpi-card kpi-tx">
                <div class="kpi-label">Rollback / Anomalie DB</div>
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
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <span class="filter-field-label">Filtri Rapidi:</span>
                        <button :class="['filter-pill', { active: quickFilter === 'all' }]" @click="setQuickFilter('all')">Tutti</button>
                        <button :class="['filter-pill', 'pill-danger', { active: quickFilter === 'errors' }]" @click="setQuickFilter('errors')">🐛 Solo Errori</button>
                        <button :class="['filter-pill', 'pill-warning', { active: quickFilter === 'tx' }]" @click="setQuickFilter('tx')">⚡ Rollback DB</button>
                        <button :class="['filter-pill', { active: quickFilter === 'slow' }]" @click="setQuickFilter('slow')">⏱️ Richieste Lente (>1s)</button>
                        <button :class="['filter-pill', { active: quickFilter === '500' }]" @click="setQuickFilter('500')">🔥 HTTP 500</button>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button class="filter-pill" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 600;" @click="exportData('csv')" title="Esporta i log filtrati in CSV (compatibile Excel)">
                            📥 Esporta CSV
                        </button>
                        <button class="filter-pill" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); font-weight: 600;" @click="exportData('json')" title="Esporta i log filtrati in JSON">
                            📥 Esporta JSON
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
                        <button class="btn-reset-filters" @click="resetFilters">✕ Azzera Filtri</button>
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
                            <th>Metodo</th>
                            <th>Status</th>
                            <th>Utente</th>
                            <th>Rotta</th>
                            <th>Controller</th>
                            <th>IP</th>
                            <th>Durata</th>
                            <th>Data</th>
                            <th>Flags / Storyboard</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs" :key="log.id" style="cursor: pointer;" @click="openDetail(log)">
                            <td><span :class="['badge', getVerbClass(log.verbo)]">@{{ (log.verbo || '').toUpperCase() }}</span></td>
                            <td><span :class="getStatusClass(log.codicehttp)">@{{ log.codicehttp }}</span></td>
                            <td><span style="font-weight: 600;">@{{ log.user_label }}</span></td>
                            <td class="mono" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">@{{ log.rotta }}</td>
                            <td style="color: var(--accent); font-size: 11px;">@{{ log.controllermethod || '—' }}</td>
                            <td class="mono" style="font-size: 11px;">@{{ log.client_ip }}</td>
                            <td class="mono" style="font-size: 11px;">@{{ log.duration_ms }}ms</td>
                            <td style="color: var(--text-muted); font-size: 11px;">@{{ formatTimestamp(log.dataoperazione) }}</td>
                            <td>
                                <div style="display: flex; gap: 4px; align-items: center; flex-wrap: wrap;">
                                    <span v-if="log.subject_id"
                                          class="badge"
                                          style="background: #8b5cf6; color: #fff; cursor: pointer;"
                                          @click.stop="openStoryboardForSubject(log.subject_type, log.subject_id)"
                                          title="Visualizza Storyboard di questo record">
                                        📖 @{{ log.subject_label || ('#' + log.subject_id) }}
                                    </span>
                                    <span v-if="log.transaction_status" class="badge" style="background: rgba(245, 158, 11, 0.2); color: #fbbf24;">⚡ DB</span>
                                    <span v-if="log.error" class="badge" style="background: rgba(239, 68, 68, 0.2); color: #f87171;">⚠️ ERR</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0 && !loading">
                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                Nessuna operazione registrata corrispondente ai filtri.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-bar">
                <div style="font-size: 12px; color: var(--text-muted);">
                    Mostrati da <strong>@{{ pagination.from || 0 }}</strong> a <strong>@{{ pagination.to || 0 }}</strong> di <strong>@{{ pagination.total || 0 }}</strong> log
                </div>
                <div style="display: flex; gap: 4px; align-items: center;">
                    <button class="page-btn" :disabled="currentPage <= 1" @click="goToPage(1)">«</button>
                    <button class="page-btn" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">‹ Prec</button>
                    <template v-for="p in visiblePages" :key="p">
                        <span v-if="p === '...'" style="padding: 0 4px; color: var(--text-muted);">...</span>
                        <button v-else :class="['page-btn', { active: p === currentPage }]" @click="goToPage(p)">@{{ p }}</button>
                    </template>
                    <button class="page-btn" :disabled="currentPage >= pagination.last_page" @click="goToPage(currentPage + 1)">Succ ›</button>
                    <button class="page-btn" :disabled="currentPage >= pagination.last_page" @click="goToPage(pagination.last_page)">»</button>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 12px; color: var(--text-muted);">Per pagina:</label>
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
                    <div class="content-title">📖 Storyboard del Record (Audit Trail & Cronologia di Vita)</div>
                    <span style="font-size: 12px; color: var(--text-muted);">Tracciamento cronologico completo di ogni modello Eloquent collegato come soggetto dell'operazione</span>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button class="page-btn" @click="toggleStoryboardSort">
                        @{{ storyboardSort === 'asc' ? '▲ Cronologico (Vecchi prima)' : '▼ Recenti prima' }}
                    </button>
                </div>
            </div>

            <div class="guide-banner">
                <span class="guide-icon">💡</span>
                <div>
                    <strong>Cos'è la Storyboard:</strong>
                    <p>Fornisce la storia completa di qualsiasi modello di business (Ordini, Fatture, Ticket, Contratti). Mostra chi l'ha creato, quali chiamate HTTP l'hanno modificato, quali checkpoint applicativi sono stati registrati con <code>$model->logStep()</code> e ogni eventuale errore.</p>
                </div>
            </div>

            <!-- Subject Selector Toolbar -->
            <div style="background: #0f172a; border: 1px solid var(--border-color); border-radius: 8px; padding: 14px; margin-bottom: 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
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
                    <button class="btn-refresh" @click="fetchStoryboard" style="height: 38px;">
                        Carica Storyboard
                    </button>
                </div>
            </div>

            <!-- Storyboard Category Filter Pills -->
            <div v-if="storyboardData && storyboardData.events" style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
                <button :class="['filter-pill', { active: storyboardCategory === 'all' }]" @click="storyboardCategory = 'all'">Tutti (@{{ storyboardData.events.length }})</button>
                <button :class="['filter-pill', { active: storyboardCategory === 'create' }]" @click="storyboardCategory = 'create'">✨ Creazione</button>
                <button :class="['filter-pill', { active: storyboardCategory === 'update' }]" @click="storyboardCategory = 'update'">✏️ Modifiche</button>
                <button :class="['filter-pill', { active: storyboardCategory === 'step' }]" @click="storyboardCategory = 'step'">🚩 Checkpoint</button>
                <button :class="['filter-pill', 'pill-danger', { active: storyboardCategory === 'error' }]" @click="storyboardCategory = 'error'">💥 Errori</button>
            </div>

            <!-- Storyboard Loading -->
            <div v-if="storyboardLoading" style="text-align: center; padding: 40px; color: var(--text-muted);">
                <div class="spinner" style="margin: 0 auto 12px auto;"></div>
                Caricamento linea del tempo in corso...
            </div>

            <!-- Storyboard Timeline -->
            <div v-else-if="filteredStoryboardEvents.length" class="storyboard-timeline">
                <div v-for="(event, idx) in filteredStoryboardEvents" :key="event.id" class="timeline-event-card">
                    <div :class="['timeline-node-marker', 'node-' + (event.classification?.category || 'default')]">
                        @{{ event.classification?.icon || '•' }}
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <span class="badge" :style="{ background: event.classification?.badge_bg || '#1e293b', color: event.classification?.badge_color || '#fff', marginRight: '8px' }">
                                @{{ event.classification?.label || 'EVENTO' }}
                            </span>
                            <span class="mono" style="font-weight: 700; color: #fff; font-size: 14px;">@{{ event.route }}</span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                            @{{ event.time_human }} (@{{ event.time_iso }})
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px; font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
                        <div>👤 <strong>Autore:</strong> @{{ event.user?.name || event.user?.email || 'Visitatore Ospite' }}</div>
                        <div>⏱️ <strong>Durata:</strong> @{{ event.duration_ms }}ms</div>
                        <div>🌐 <strong>IP:</strong> @{{ event.ip_address }}</div>
                        <div>🎯 <strong>Status:</strong> <span :class="getStatusClass(event.status_code)">@{{ event.status_code }}</span></div>
                    </div>

                    <!-- Steps ($model->logStep) -->
                    <div v-if="event.custom_traces?.steps?.length" style="background: #1e293b; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; margin-top: 8px;">
                        <div style="font-size: 12px; font-weight: 700; color: #38bdf8; margin-bottom: 6px;">🚩 Passaggi Applicativi Registrati:</div>
                        <div v-for="(st, sI) in event.custom_traces.steps" :key="sI" style="font-size: 12px; color: #f8fafc; margin-bottom: 4px;">
                            • <strong>@{{ st.label }}</strong>
                            <pre v-if="st.context && Object.keys(st.context).length" style="background: #090d16; padding: 6px; border-radius: 4px; font-size: 11px; margin-top: 4px; color: #94a3b8;">@{{ JSON.stringify(st.context, null, 2) }}</pre>
                        </div>
                    </div>

                    <!-- Error Alert -->
                    <div v-if="event.error" style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); border-radius: 6px; padding: 8px; margin-top: 8px; color: #fca5a5; font-size: 12px;">
                        <strong>Errore:</strong> @{{ event.error }}
                    </div>
                </div>
            </div>

            <div v-else-if="!storyboardLoading" style="text-align: center; padding: 40px; color: var(--text-muted);">
                Nessun evento Storyboard trovato per il modello selezionato. Seleziona un modello in alto per visualizzare la cronologia.
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 3: STUDIO ROTTE                                           -->
        <!-- ============================================================= -->
        <div v-if="currentTab === 'studio_routes'" class="content-card">
            <div class="content-header">
                <div>
                    <div class="content-title">🗺️ Studio Rotte (Controllo Pagine & API Senza Codice)</div>
                    <span style="font-size: 12px; color: var(--text-muted);">Riconoscimento automatico delle rotte Laravel con attivazione permanente a 1 click</span>
                </div>
                <input v-model="routeFilter" type="text" class="filter-input-ctrl" placeholder="Filtra rotta o controller..." style="max-width: 250px;">
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">Stato</th>
                        <th>Metodi</th>
                        <th>URI Rotta</th>
                        <th>Controller</th>
                        <th>Livello Dettaglio</th>
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
                        <td class="mono">/@{{ r.clean_uri }}</td>
                        <td style="color: var(--accent);">@{{ r.controller }}@{{ r.controller_method ? '@' + r.controller_method : '' }}</td>
                        <td>
                            <select v-model="r.stack_level" class="filter-input-ctrl" style="padding: 4px 8px; width: auto;" @change="updateRouteLevel(r)">
                                <option value="base">Base</option>
                                <option value="core">🎯 Core</option>
                                <option value="full">🔍 Completo</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 4: STUDIO FUNZIONI & METODI PHP                           -->
        <!-- ============================================================= -->
        <div v-if="currentTab === 'studio_classes'" class="content-card">
            <div class="content-header">
                <div>
                    <div class="content-title">⚙️ Studio Funzioni (Dynamic Proxy & Interceptor Metodi)</div>
                    <span style="font-size: 12px; color: var(--text-muted);">Intercetta l'esecuzione dei metodi di business logic senza toccare il codice applicativo</span>
                </div>
            </div>

            <div v-for="cls in classes" :key="cls.class" style="margin-bottom: 20px; background: #0f172a; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px;">
                <div style="font-weight: 700; color: #fff; font-size: 14px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <span>📦 @{{ cls.class }}</span>
                    <span v-if="cls.has_traceable_attribute" class="badge" style="background: #8b5cf6; color: #fff;">#[Traceable]</span>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <div v-for="m in cls.methods" :key="m.name" style="background: #1e293b; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 12px; display: flex; align-items: center; gap: 10px;">
                        <label class="toggle-switch">
                            <input type="checkbox" :checked="m.is_tracked" @change="toggleMethodTracking(cls.class, m)">
                            <span class="slider"></span>
                        </label>
                        <span class="mono" style="font-size: 12px;">@{{ m.name }}()</span>
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
                    <div class="content-title">👤 Monitor Utente Live a Tempo</div>
                    <span style="font-size: 12px; color: var(--text-muted);">Traccia tutte le azioni di un utente specifico per una finestra temporale temporanea</span>
                </div>
            </div>

            <!-- Avvio Sessione Form -->
            <div style="background: #0f172a; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; margin-bottom: 20px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
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
                <button class="btn-refresh" @click="startLiveSession" :disabled="!targetUserId">
                    Avvia Monitoraggio
                </button>
            </div>

            <!-- Sessioni Attive -->
            <div v-if="activeSessions.length">
                <div style="font-weight: 700; color: #fff; margin-bottom: 10px;">Sessioni Live Attive:</div>
                <div v-for="s in activeSessions" :key="s.id" style="background: #0f172a; border: 1px solid #10b981; border-radius: 8px; padding: 12px 16px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span class="session-pulse"></span>
                        <strong style="color: #fff; margin-left: 8px;">Utente ID: @{{ s.target }}</strong>
                        <span style="color: var(--text-muted); font-size: 12px; margin-left: 8px;">
                            (Tempo residuo: @{{ s.seconds_left ? formatSeconds(s.seconds_left) : 'In corso' }})
                        </span>
                    </div>
                    <button class="page-btn" style="border-color: #ef4444; color: #f87171;" @click="stopLiveSession(s.id)">
                        Arresta
                    </button>
                </div>
            </div>
            <div v-else style="color: var(--text-muted); font-size: 13px;">
                Nessuna sessione di monitoraggio utente attiva al momento.
            </div>
        </div>
    </div>

    <!-- Modal Dettaglio Singolo Log -->
    <div v-if="activeLog" class="modal-overlay" @click.self="closeDetail">
        <div class="modal-body">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span :class="['badge', getVerbClass(activeLog.verbo)]">@{{ (activeLog.verbo || '').toUpperCase() }}</span>
                    <span :class="['badge', getStatusClass(activeLog.codicehttp)]">@{{ activeLog.codicehttp }}</span>
                    <span class="mono" style="font-weight: 700; font-size: 14px;">@{{ activeLog.rotta }}</span>
                </div>
                <button style="background: none; border: none; color: #fff; font-size: 20px; cursor: pointer;" @click="closeDetail">✕</button>
            </div>

            <div class="modal-content">
                <div style="display: flex; gap: 10px; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                    <button :class="['page-btn', { active: modalTab === 'overview' }]" @click="modalTab = 'overview'">Riepilogo & Dati</button>
                    <button :class="['page-btn', { active: modalTab === 'stack' }]" @click="modalTab = 'stack'">
                        Stack Trace (@{{ modalStackView === 'core' ? (activeLog.core_stack ? activeLog.core_stack.length : 0) : (activeLog.stack_trace ? activeLog.stack_trace.length : 0) }})
                    </button>
                </div>

                <!-- Overview -->
                <div v-if="modalTab === 'overview'">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div><strong>Utente:</strong> @{{ activeLog.user_label }}</div>
                        <div><strong>IP:</strong> @{{ activeLog.client_ip }}</div>
                        <div><strong>Durata:</strong> @{{ activeLog.duration_ms }} ms</div>
                        <div><strong>Data:</strong> @{{ formatTimestamp(activeLog.dataoperazione) }}</div>
                        <div><strong>Controller:</strong> @{{ activeLog.controllermethod || 'N/A' }}</div>
                        <div><strong>Transazione:</strong> @{{ activeLog.transaction_status || 'Nessuna anomalia' }}</div>
                    </div>

                    <div v-if="activeLog.subject_id" style="background: rgba(139, 92, 246, 0.1); border: 1px solid var(--accent); border-radius: 8px; padding: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>Soggetto Storyboard:</strong> @{{ activeLog.subject_label || (activeLog.subject_type + ' #' + activeLog.subject_id) }}
                        </div>
                        <button class="btn-refresh" style="background: var(--accent);" @click="openStoryboardForSubject(activeLog.subject_type, activeLog.subject_id)">
                            📖 Apri Storyboard
                        </button>
                    </div>

                    <div v-if="activeLog.error" style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); border-radius: 8px; padding: 12px; margin-bottom: 16px; color: #fca5a5;">
                        <strong>Errore:</strong>
                        <pre style="margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; white-space: pre-wrap;">@{{ activeLog.error }}</pre>
                    </div>

                    <div>
                        <strong>Parametri Richiesta:</strong>
                        <pre style="background: #090d16; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; max-height: 220px; overflow: auto;">@{{ JSON.stringify(activeLog.parametri, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Stack Trace -->
                <div v-if="modalTab === 'stack'">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 12px; color: var(--text-muted);">Visualizzazione:</span>
                        <div style="display: flex; gap: 6px;">
                            <button :class="['page-btn', { active: modalStackView === 'core' }]" @click="modalStackView = 'core'">Solo Codice Core</button>
                            <button :class="['page-btn', { active: modalStackView === 'full' }]" @click="modalStackView = 'full'">Stack Completo</button>
                        </div>
                    </div>

                    <div style="max-height: 400px; overflow-y: auto;">
                        <div v-for="(frame, fIdx) in displayedStackFrames" :key="fIdx" style="padding: 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); font-size: 12px;">
                            <span style="color: var(--text-muted);">#@{{ fIdx + 1 }}</span>
                            <span style="color: #fff; font-weight: 600; margin: 0 6px;">@{{ frame.class ? frame.class + '::' + frame.function : frame.function }}</span>
                            <span v-if="frame.is_core" class="badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;">CORE</span>
                            <div style="color: var(--text-muted); font-size: 11px; margin-top: 2px;">
                                @{{ frame.file }}:@{{ frame.line }}
                            </div>
                        </div>
                        <div v-if="displayedStackFrames.length === 0" style="color: var(--text-muted); padding: 10px;">
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
