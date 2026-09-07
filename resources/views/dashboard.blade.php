<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogOperations — Standalone Dashboard</title>
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
            padding: 16px 24px;
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

        .refresh-btn {
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .refresh-btn:hover { background: #6366f1; transform: translateY(-1px); }

        /* Container */
        .dash-container {
            max-width: 1440px;
            margin: 24px auto;
            padding: 0 20px;
        }

        /* KPI Cards */
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
            padding: 18px 20px;
            position: relative;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--primary);
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .kpi-card.kpi-error::before { background: var(--danger); }
        .kpi-card.kpi-time::before { background: var(--warning); }
        .kpi-card.kpi-tx::before { background: #ec4899; }

        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-value { font-size: 28px; font-weight: 800; color: #fff; margin-top: 4px; }
        .kpi-sub { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        /* Content Card */
        .content-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        /* Filter Toolbar */
        .filter-toolbar {
            background: #10192d;
            border-bottom: 1px solid var(--border-color);
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .filter-pills-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-pill {
            background: #17233d;
            border: 1px solid var(--border-color);
            color: #cbd5e1;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            user-select: none;
        }
        .filter-pill:hover { background: #24355a; color: #fff; border-color: #6366f1; }
        .filter-pill.active {
            background: #4f46e5;
            color: #fff;
            border-color: #818cf8;
            box-shadow: 0 2px 10px rgba(79, 70, 229, 0.45);
        }
        .filter-pill.active.pill-danger { background: #dc2626; border-color: #f87171; }
        .filter-pill.active.pill-warning { background: #d97706; border-color: #fbbf24; }

        .filter-fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: end;
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-field-label {
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-input-ctrl {
            background: #0b1326;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            width: 100%;
            color-scheme: dark;
        }
        .filter-input-ctrl:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
        }

        .btn-reset-filters {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            height: 38px;
        }
        .btn-reset-filters:hover { background: #dc2626; color: #fff; }

        /* Table */
        .table-responsive {
            overflow-x: auto;
            position: relative;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .data-table th {
            background: #131d31;
            padding: 12px 16px;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(51, 65, 85, 0.5);
            vertical-align: middle;
        }

        .data-table tr:hover { background: var(--surface-hover); cursor: pointer; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .badge-get { background: #064e3b; color: #34d399; }
        .badge-post { background: #1e3a8a; color: #60a5fa; }
        .badge-put { background: #78350f; color: #fbbf24; }
        .badge-delete { background: #7f1d1d; color: #f87171; }

        .status-200 { background: #064e3b; color: #34d399; }
        .status-400 { background: #78350f; color: #fbbf24; }
        .status-500 { background: #7f1d1d; color: #f87171; }

        .mono { font-family: 'JetBrains Mono', monospace; }

        .subject-chip {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 6px;
            font-size: 10px;
            border-radius: 4px;
            background: rgba(59, 130, 246, 0.15);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        /* Pagination Bar */
        .pagination-bar {
            background: #10192d;
            border-top: 1px solid var(--border-color);
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .pagination-info {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }
        .pagination-info strong { color: #fff; }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .page-btn {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            color: #cbd5e1;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            min-width: 34px;
            text-align: center;
        }
        .page-btn:hover:not(:disabled) {
            background: var(--surface-hover);
            color: #fff;
            border-color: #6366f1;
        }
        .page-btn.active {
            background: #4f46e5;
            color: #fff;
            border-color: #818cf8;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.4);
        }
        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .page-ellipsis {
            color: var(--text-muted);
            padding: 0 4px;
            font-size: 13px;
        }

        .pagination-options {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .per-page-select {
            background: #0b1326;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            outline: none;
        }

        .jump-page-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .jump-page-input {
            width: 48px;
            background: #0b1326;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 5px 6px;
            border-radius: 6px;
            font-size: 12px;
            text-align: center;
            outline: none;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid rgba(99, 102, 241, 0.2);
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Modal Dettaglio */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            backdrop-filter: blur(4px);
            padding: 20px;
        }

        .modal-body {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            width: 100%;
            max-width: 900px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-content {
            padding: 20px;
            overflow-y: auto;
        }

        .stack-tree {
            background: #090d16;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            max-height: 350px;
            overflow-y: auto;
        }

        .stack-item {
            padding: 6px 10px;
            border-left: 3px solid #334155;
            margin-bottom: 6px;
        }

        .stack-item.core {
            border-left-color: #10b981;
            background: rgba(16, 185, 129, 0.05);
        }

        .core-badge {
            background: #064e3b;
            color: #34d399;
            font-size: 9px;
            font-weight: 800;
            padding: 2px 5px;
            border-radius: 4px;
            margin-left: 6px;
        }
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
            <button class="refresh-btn" @click="fetchLogs(currentPage)">
                🔄 Aggiorna Log
            </button>
        </div>
    </header>

    <div class="dash-container">
        <!-- KPI Row -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Richieste Totali</div>
                <div class="kpi-value">@{{ stats.total_requests || 0 }}</div>
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

        <!-- Table Card -->
        <div class="content-card">
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
                        <button class="filter-pill" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 600;" @click="exportData('csv')" title="Esporta i log filtrati in CSV (compatibile con Excel)">
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
                            <th>Flags</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="logs.length === 0 && !loading">
                            <td colspan="9" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                Nessun log trovato per i criteri selezionati.
                            </td>
                        </tr>
                        <tr v-for="log in logs" :key="log.id" @click="openDetail(log)">
                            <td>
                                <span :class="['badge', getVerbClass(log.verbo)]">
                                    @{{ (log.verbo || '').toUpperCase() }}
                                </span>
                            </td>
                            <td>
                                <span :class="['badge', getStatusClass(log.codicehttp)]">
                                    @{{ log.codicehttp }}
                                </span>
                            </td>
                            <td>@{{ log.user_label || '—' }}</td>
                            <td class="mono">
                                @{{ log.rotta }}
                                <span v-if="log.subject_type && log.subject_id" class="subject-chip">
                                    📦 @{{ log.subject_label || (log.subject_type.split('\\').pop() + ' #' + log.subject_id) }}
                                </span>
                            </td>
                            <td style="color: var(--text-muted);">@{{ log.controllermethod || '—' }}</td>
                            <td class="mono">@{{ log.client_ip || '—' }}</td>
                            <td class="mono">@{{ log.duration_ms ? log.duration_ms + 'ms' : '—' }}</td>
                            <td style="color: var(--text-muted); font-size: 11px;">@{{ formatTimestamp(log.dataoperazione) }}</td>
                            <td>
                                <span v-if="log.error" title="Contiene errore">🐛</span>
                                <span v-if="log.transaction_status" title="Rollback DB">⚡</span>
                                <span v-if="log.stack_trace && log.stack_trace.length" title="Stack trace">📚</span>
                                <span v-if="log.subject_type && log.subject_id" title="Entità tracciata">📖</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-bar">
                <div class="pagination-info">
                    Mostrati da <strong>@{{ pagination.from || 0 }}</strong> a <strong>@{{ pagination.to || 0 }}</strong> di <strong>@{{ pagination.total || 0 }}</strong> record totali
                </div>

                <div class="pagination-nav">
                    <button class="page-btn" :disabled="currentPage <= 1" @click="goToPage(1)" title="Prima pagina">«</button>
                    <button class="page-btn" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)" title="Pagina precedente">‹</button>

                    <template v-for="(p, idx) in visiblePages" :key="idx">
                        <span v-if="p === '...'" class="page-ellipsis">...</span>
                        <button v-else :class="['page-btn', { active: p === currentPage }]" @click="goToPage(p)">
                            @{{ p }}
                        </button>
                    </template>

                    <button class="page-btn" :disabled="currentPage >= pagination.last_page" @click="goToPage(currentPage + 1)" title="Pagina successiva">›</button>
                    <button class="page-btn" :disabled="currentPage >= pagination.last_page" @click="goToPage(pagination.last_page)" title="Ultima pagina">»</button>
                </div>

                <div class="pagination-options">
                    <label style="font-size: 12px; color: var(--text-muted);">Per pagina:</label>
                    <select class="per-page-select" v-model="perPage" @change="onPerPageChange">
                        <option v-for="opt in perPageOptions" :key="opt" :value="opt">@{{ opt }}</option>
                    </select>

                    <div class="jump-page-wrap">
                        <span>Vai a:</span>
                        <input type="number" class="jump-page-input" min="1" :max="pagination.last_page || 1" v-model.number="jumpPageNumber" @keyup.enter="jumpToPage">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Dettaglio -->
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

                <!-- Tab Overview -->
                <div v-if="modalTab === 'overview'">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div><strong>Utente:</strong> @{{ activeLog.user_label }}</div>
                        <div><strong>IP:</strong> @{{ activeLog.client_ip }}</div>
                        <div><strong>Durata:</strong> @{{ activeLog.duration_ms }} ms</div>
                        <div><strong>Data:</strong> @{{ formatTimestamp(activeLog.dataoperazione) }}</div>
                        <div><strong>Controller:</strong> @{{ activeLog.controllermethod || 'N/A' }}</div>
                        <div><strong>Transazione:</strong> @{{ activeLog.transaction_status || 'Nessuna anomalia' }}</div>
                    </div>

                    <div v-if="activeLog.error" style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); border-radius: 8px; padding: 12px; margin-bottom: 16px; color: #fca5a5;">
                        <strong>Errore:</strong>
                        <pre style="margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; white-space: pre-wrap;">@{{ activeLog.error }}</pre>
                    </div>

                    <div>
                        <strong>Parametri Richiesta:</strong>
                        <pre style="background: #090d16; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; max-height: 250px; overflow: auto;">@{{ JSON.stringify(activeLog.parametri, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Tab Stack Trace -->
                <div v-if="modalTab === 'stack'">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 12px; color: var(--text-muted);">Visualizzazione:</span>
                        <div style="display: flex; gap: 6px;">
                            <button :class="['page-btn', { active: modalStackView === 'core' }]" @click="modalStackView = 'core'">Solo Codice Core</button>
                            <button :class="['page-btn', { active: modalStackView === 'full' }]" @click="modalStackView = 'full'">Stack Completo</button>
                        </div>
                    </div>

                    <div class="stack-tree">
                        <div v-for="(frame, fIdx) in displayedStackFrames" :key="fIdx" :class="['stack-item', { core: frame.is_core }]">
                            <span style="color: var(--text-muted);">#@{{ fIdx + 1 }}</span>
                            <span style="color: #fff; font-weight: 600; margin: 0 6px;">@{{ frame.class ? frame.class + '::' + frame.function : frame.function }}</span>
                            <span v-if="frame.is_core" class="core-badge">CORE</span>
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
            const logs = ref([]);
            const stats = ref({});
            const loading = ref(false);

            // Paginazione
            const currentPage = ref(1);
            const perPage = ref({{ $perPage }});
            const perPageOptions = ref(@json($perPageOptions));
            const pagination = ref({
                total: 0,
                last_page: 1,
                from: 0,
                to: 0,
            });
            const jumpPageNumber = ref(1);

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
                    jumpPageNumber.value = currentPage.value;
                } catch (e) {
                    console.error('Errore recupero log:', e);
                } finally {
                    loading.value = false;
                }

                try {
                    const sRes = await fetch(`/${apiPrefix}/stats`);
                    stats.value = await sRes.json();
                } catch (e) {}
            }

            function goToPage(page) {
                if (page >= 1 && page <= (pagination.value.last_page || 1) && page !== currentPage.value) {
                    currentPage.value = page;
                    fetchLogs(page);
                }
            }

            function onPerPageChange() {
                currentPage.value = 1;
                fetchLogs(1);
            }

            function jumpToPage() {
                const target = parseInt(jumpPageNumber.value, 10);
                if (!isNaN(target)) {
                    goToPage(Math.max(1, Math.min(target, pagination.value.last_page)));
                }
            }

            const visiblePages = computed(() => {
                const total = pagination.value.last_page || 1;
                const current = currentPage.value;
                const delta = 2;
                const pages = [];

                for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
                    pages.push(i);
                }

                if (current - delta > 2) {
                    pages.unshift('...');
                }
                pages.unshift(1);

                if (current + delta < total - 1) {
                    pages.push('...');
                }
                if (total > 1) {
                    pages.push(total);
                }

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

            function closeDetail() {
                activeLog.value = null;
            }

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

            onMounted(() => {
                fetchLogs(1);
            });

            return {
                logs,
                stats,
                loading,
                currentPage,
                perPage,
                perPageOptions,
                pagination,
                jumpPageNumber,
                visiblePages,
                quickFilter,
                filterText,
                filterVerb,
                filterUser,
                filterDateFrom,
                filterDateTo,
                activeLog,
                modalTab,
                modalStackView,
                displayedStackFrames,
                fetchLogs,
                goToPage,
                onPerPageChange,
                jumpToPage,
                debounceFetchLogs,
                onFilterChange,
                setQuickFilter,
                resetFilters,
                getVerbClass,
                getStatusClass,
                formatTimestamp,
                openDetail,
                closeDetail,
                exportData,
            };
        }
    }).mount('#app');
</script>
</body>
</html>
