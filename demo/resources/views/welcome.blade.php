<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogOperations — Live Playground & Testbench</title>
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

        /* Top Simulation Bar */
        .simulator-bar {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 2px solid #4f46e5;
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

        .simulator-brand {
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
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }

        .simulator-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .user-switcher {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #0f172a;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .user-switcher select {
            background: #0f172a;
            color: #f8fafc;
            border: none;
            font-size: 13px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
            color-scheme: dark;
        }

        select {
            color-scheme: dark;
        }

        select option {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            padding: 8px 12px;
        }

        select option:hover,
        select option:focus,
        select option:checked {
            background-color: #4f46e5 !important;
            color: #ffffff !important;
        }

        .sim-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .sim-btn:hover { transform: translateY(-1px); }
        .sim-btn:active { transform: translateY(1px); }

        .btn-success { background: #059669; color: #fff; }
        .btn-success:hover { background: #10b981; }

        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #ef4444; }

        .btn-warning { background: #d97706; color: #fff; }
        .btn-warning:hover { background: #f59e0b; }

        .btn-info { background: #4f46e5; color: #fff; }
        .btn-info:hover { background: #6366f1; }

        /* Sim Feedback Toast */
        .sim-feedback {
            font-size: 12px;
            font-family: 'JetBrains Mono', monospace;
            padding: 4px 10px;
            border-radius: 6px;
            background: #022c22;
            color: #34d399;
            border: 1px solid #059669;
        }

        /* App Container */
        .playground-container {
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 20px;
        }

        /* Main View Switcher Tabs */
        .main-nav {
            display: flex;
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            padding: 6px;
            border-radius: 12px;
            margin-bottom: 24px;
            gap: 8px;
        }

        .main-tab {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .main-tab:hover { color: #fff; background: rgba(255, 255, 255, 0.03); }
        .main-tab.active {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
        }

        /* KPI Banner */
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

        .kpi-label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; }
        .kpi-value { font-size: 28px; font-weight: 800; color: #fff; margin-top: 4px; }
        .kpi-sub { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        /* Tables & Studio Panels */
        .content-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .content-header {
            padding: 16px 20px;
            background: var(--surface-dark);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .content-title { font-size: 16px; font-weight: 700; color: #fff; }

        /* Search & Filters */
        .search-input {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            width: 280px;
            outline: none;
        }
        .search-input:focus { border-color: var(--primary); }

        /* Modern Filter Toolbar */
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

        .filter-pill-label {
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
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

        .filter-pill:hover {
            background: #24355a;
            color: #fff;
            border-color: #6366f1;
        }

        .filter-pill.active {
            background: #4f46e5;
            color: #fff;
            border-color: #818cf8;
            box-shadow: 0 2px 10px rgba(79, 70, 229, 0.45);
        }

        .filter-pill.active.pill-danger {
            background: #dc2626;
            border-color: #f87171;
            box-shadow: 0 2px 10px rgba(220, 38, 38, 0.45);
        }

        .filter-pill.active.pill-warning {
            background: #d97706;
            border-color: #fbbf24;
            box-shadow: 0 2px 10px rgba(217, 119, 6, 0.45);
        }

        .filter-pill.active.pill-purple {
            background: #9333ea;
            border-color: #c084fc;
            box-shadow: 0 2px 10px rgba(147, 51, 234, 0.45);
        }

        .filter-pill.active.pill-cyan {
            background: #0891b2;
            border-color: #22d3ee;
            box-shadow: 0 2px 10px rgba(8, 145, 178, 0.45);
        }

        .filter-fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            display: flex;
            align-items: center;
            gap: 4px;
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
            transition: all 0.2s;
        }

        .filter-input-ctrl:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
            background: #0f1a34;
        }

        .filter-input-ctrl option {
            background: #0b1326;
            color: #fff;
            padding: 8px 12px;
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
            transition: all 0.2s;
            white-space: nowrap;
            height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-reset-filters:hover {
            background: #dc2626;
            color: #fff;
            border-color: #ef4444;
        }

        .filter-status-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-muted);
            padding-top: 4px;
            border-top: 1px dashed rgba(51, 65, 85, 0.5);
        }

        /* Table */
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

        .data-table tr:hover { background: var(--surface-hover); }

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

        .tx-badge {
            background: #831843;
            color: #f472b6;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 6px;
        }

        .mono { font-family: 'JetBrains Mono', monospace; }

        /* Switch Toggle */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 24px;
            cursor: pointer;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #334155;
            transition: .3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(24px); }

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

        .btn-view-detail {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-view-detail:hover { color: #fff; border-color: var(--primary); }

        .session-pulse {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            animation: pulse-dot 1s infinite;
        }
        @keyframes pulse-dot { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.4); opacity: 0.5; } }

        /* Tooltip & Guida Intuitiva per l'Utente */
        .info-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 17px;
            height: 17px;
            background: #334155;
            color: #94a3b8;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 700;
            cursor: help;
            transition: all 0.2s;
            user-select: none;
            flex-shrink: 0;
            vertical-align: middle;
            margin-left: 4px;
        }
        .info-pill:hover {
            background: var(--primary);
            color: #fff;
            transform: scale(1.15);
        }

        [data-tooltip] {
            cursor: help;
        }

        /* Universal Floating Tooltip Container */
        .global-floating-tooltip {
            position: fixed;
            display: none;
            opacity: 0;
            background: #090d16;
            color: #f8fafc;
            padding: 9px 13px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.45;
            max-width: 310px;
            border: 1px solid #475569;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.9);
            z-index: 2147483647;
            pointer-events: none;
            transition: opacity 0.15s ease-in-out;
            box-sizing: border-box;
        }

        /* Banner Guida Contestuale */
        .guide-banner {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(168, 85, 247, 0.07) 100%);
            border: 1px solid rgba(99, 102, 241, 0.35);
            border-radius: 10px;
            padding: 12px 18px;
            margin: 16px 20px 0 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.5;
        }
        .guide-banner .guide-icon {
            font-size: 20px;
            flex-shrink: 0;
            line-height: 1;
        }
        .guide-banner strong {
            color: #fff;
        }
        .guide-banner p {
            margin: 0;
        }
    </style>
</head>
<body>
    <div id="app">
        <!-- TOP SIMULATION BAR -->
        <header class="simulator-bar">
            <div class="simulator-brand">
                <span class="brand-badge">LIVE TESTBENCH</span>
                <span class="brand-title">Laravel LogOperations Demo</span>
            </div>

            <div class="simulator-controls">
                <!-- Selettore Utente -->
                <div class="user-switcher">
                    <span>👤 Utente Attivo:</span>
                    <select v-model="selectedUserId" @change="switchUser">
                        <option value="">Ospite (Non autenticato)</option>
                        <option v-for="u in usersList" :key="u.id" :value="u.id">
                            @{{ u.name }} (@{{ u.email }})
                        </option>
                    </select>
                    <span
                        class="info-pill"
                        data-tooltip="<strong>Simula l'Utente Autenticato:</strong><br>Le richieste inviate con i bottoni a lato verranno associate all'utente selezionato (supporto polimorfico)."
                        title="Simula l'Utente Autenticato: le richieste inviate verranno associate a questo utente"
                    >ℹ️</span>
                </div>

                <!-- Pulsanti di Simulazione Casistiche con Tooltip -->
                <button
                    class="sim-btn btn-success"
                    :disabled="simulating"
                    @click="runSimulation('order')"
                    data-tooltip="<strong>Simula Ordine a Buon Fine (200 OK):</strong><br>Esegue POST /api/demo/orders che richiama OrderService, calcola sconti e registra i parametri e la durata esatta."
                    title="Simula Ordine a Buon Fine (200 OK) con OrderService"
                >
                    🛒 Simula Ordine (200 OK)
                </button>

                <button
                    class="sim-btn btn-danger"
                    :disabled="simulating"
                    @click="runSimulation('error')"
                    data-tooltip="<strong>Simula Eccezione PHP & Fallimento (500):</strong><br>Genera un'eccezione critica in PaymentService per vedere la cattura dello Stack Trace Core pulito e l'evidenziazione rossa."
                    title="Simula Errore 500 con eccezione in PaymentService"
                >
                    💥 Simula Errore 500
                </button>

                <button
                    class="sim-btn btn-warning"
                    :disabled="simulating"
                    @click="runSimulation('transaction')"
                    data-tooltip="<strong>Simula Transazione DB Incompleta:</strong><br>Simula un controller con transazione SQL non chiusa a causa di un errore. Il middleware esegue il Rollback automatico per non bloccare il database."
                    title="Simula Transazione DB Incompleta con Rollback di sicurezza"
                >
                    ⚡ Transazione Aperta
                </button>

                <button
                    class="sim-btn btn-info"
                    :disabled="simulating"
                    @click="runSimulation('slow')"
                    data-tooltip="<strong>Simula Latenza Elevata (1.2s):</strong><br>Esegue una richiesta rallentata artificialmente per testare il monitoraggio dei tempi di risposta e l'avviso arancione di lentezza."
                    title="Simula Richiesta Lenta (1.2s) per testare le performance"
                >
                    ⏱️ Richiesta Lenta (1.2s)
                </button>

                <span v-if="lastSimResult" class="sim-feedback">
                    @{{ lastSimResult }}
                </span>
            </div>
        </header>

        <!-- APP CONTAINER -->
        <main class="playground-container">
            <!-- NAV SWITCHER -->
            <div class="main-nav">
                <button :class="['main-tab', { active: currentTab === 'logs' }]" @click="currentTab = 'logs'">
                    📊 Registro Operazioni (@{{ logs.length }})
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

            <!-- KPI STATS -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">
                        Totale Chiamate
                        <span class="info-pill" data-tooltip="Numero totale di richieste HTTP o eventi intercettati e salvati nel database." title="Numero totale di operazioni registrate">ℹ️</span>
                    </div>
                    <div class="kpi-value">@{{ stats.total_requests || logs.length }}</div>
                    <div class="kpi-sub">Operazioni registrate</div>
                </div>
                <div class="kpi-card kpi-error">
                    <div class="kpi-label">
                        Tasso Errori
                        <span class="info-pill" data-tooltip="Percentuale di risposte con codice HTTP 4xx o 500 rispetto al totale." title="Percentuale di risposte con codice 4xx o 500">ℹ️</span>
                    </div>
                    <div class="kpi-value">@{{ stats.error_rate || 0 }}%</div>
                    <div class="kpi-sub">@{{ stats.total_errors || 0 }} anomalie rilevate</div>
                </div>
                <div class="kpi-card kpi-time">
                    <div class="kpi-label">
                        Latenza Media
                        <span class="info-pill" data-tooltip="Tempo medio impiegato dal server per elaborare la richiesta completa." title="Tempo medio di risposta del server in ms">ℹ️</span>
                    </div>
                    <div class="kpi-value">@{{ stats.avg_duration_ms || 0 }} ms</div>
                    <div class="kpi-sub">Tempo di risposta medio</div>
                </div>
                <div class="kpi-card kpi-tx">
                    <div class="kpi-label">
                        Transazioni Anomale
                        <span class="info-pill" data-tooltip="Transazioni SQL lasciate aperte a causa di errore che il pacchetto ha annullato (Rollback) automaticamente per salvaguardare il DB." title="Rollback automatici di sicurezza eseguiti">ℹ️</span>
                    </div>
                    <div class="kpi-value">@{{ stats.pending_transactions || 0 }}</div>
                    <div class="kpi-sub">Rollback di sicurezza eseguiti</div>
                </div>
            </div>

            <!-- TAB 1: REGISTRO LOG -->
            <div v-if="currentTab === 'logs'" class="content-card">
                <div class="content-header">
                    <div>
                        <div class="content-title">📊 Registro Operazioni (Storico Log)</div>
                        <span style="font-size: 12px; color: var(--text-muted);">Consultazione e analisi di tutti gli eventi storici dell'applicazione</span>
                    </div>
                    <button class="sim-btn btn-info" @click="fetchLogs">🔄 Aggiorna Log</button>
                </div>

                <div class="guide-banner">
                    <span class="guide-icon">💡</span>
                    <div>
                        <strong>Come funziona il Registro Operazioni:</strong>
                        <p>Mostra la sequenza cronologica delle richieste. Usa la barra filtri per isolare errori 500, specifici utenti o percorsi, oppure clicca su <strong>"🔍 Ispeziona"</strong> per visualizzare payload, controller e Stack Trace pulito.</p>
                    </div>
                </div>

                <!-- BARRA FILTRI DINAMICI -->
                <div class="filter-toolbar">
                    <!-- Quick Pills (1-Click) -->
                    <div class="filter-pills-row">
                        <span class="filter-pill-label">⚡ Filtro Rapido:</span>
                        <button
                            :class="['filter-pill', { active: activeQuickFilter === 'all' }]"
                            @click="setQuickFilter('all')"
                        >
                            Tutti i Log (@{{ stats.total_requests || logs.length }})
                        </button>
                        <button
                            :class="['filter-pill pill-danger', { active: activeQuickFilter === '500' }]"
                            @click="setQuickFilter('500')"
                            data-tooltip="<strong>Solo Errori Critici (HTTP 500):</strong><br>Mostra solo le richieste terminate con crash del server o eccezione non gestita."
                            title="Filtra solo errori 500"
                        >
                            🚨 Solo 500
                        </button>
                        <button
                            :class="['filter-pill pill-warning', { active: activeQuickFilter === 'errors' }]"
                            @click="setQuickFilter('errors')"
                            data-tooltip="<strong>Tutti gli Errori (4xx & 500):</strong><br>Mostra sia gli errori client/validazione (400, 404, 422) che i fallimenti server (500)."
                            title="Filtra errori 4xx e 500"
                        >
                            ⚠️ Errori (4xx & 500)
                        </button>
                        <button
                            :class="['filter-pill pill-purple', { active: activeQuickFilter === 'tx' }]"
                            @click="setQuickFilter('tx')"
                            data-tooltip="<strong>Transazioni SQL con Rollback:</strong><br>Mostra le richieste in cui una transazione interrotta ha richiesto il Rollback di emergenza."
                            title="Filtra transazioni con rollback"
                        >
                            ⚡ Solo Rollback DB
                        </button>
                        <button
                            :class="['filter-pill pill-cyan', { active: activeQuickFilter === 'slow' }]"
                            @click="setQuickFilter('slow')"
                            data-tooltip="<strong>Richieste Lente (>1 secondo):</strong><br>Mostra solo le chiamate che hanno impiegato più di 1000ms a rispondere."
                            title="Filtra richieste lente"
                        >
                            ⏱️ Richieste Lente (>1s)
                        </button>
                    </div>

                    <!-- Griglia Filtri Avanzati Combinabili -->
                    <div class="filter-fields-grid">
                        <!-- Filtro Utente -->
                        <div class="filter-field">
                            <label class="filter-field-label">
                                👤 Utente Esecutore
                                <span class="info-pill" data-tooltip="Filtra le richieste registrate per un determinato utente oppure solo per visitatori ospiti non autenticati." title="Filtra per utente specifico">ℹ️</span>
                            </label>
                            <select v-model="filterUser" class="filter-input-ctrl" @change="onFilterChange">
                                <option value="">👥 Tutti gli Utenti</option>
                                <option value="guest">👤 Solo Ospite (Non autenticato)</option>
                                <option v-for="u in usersList" :key="u.id" :value="u.id">
                                    👤 @{{ u.name }} (@{{ u.email }})
                                </option>
                            </select>
                        </div>

                        <!-- Filtro Esito HTTP -->
                        <div class="filter-field">
                            <label class="filter-field-label">
                                🎯 Esito HTTP
                                <span class="info-pill" data-tooltip="Filtra per codice HTTP esatto: 500 (Errore Server), 404 (Not Found), 200 (Successo), ecc." title="Filtra per codice HTTP">ℹ️</span>
                            </label>
                            <select v-model="filterStatus" class="filter-input-ctrl" @change="onFilterChange">
                                <option value="">🎯 Tutti i codici</option>
                                <option value="500">💥 500 (Internal Server Error)</option>
                                <option value="404">🔍 404 (Not Found)</option>
                                <option value="400">⚠️ 400 (Bad Request)</option>
                                <option value="422">📝 422 (Unprocessable Entity)</option>
                                <option value="200">✅ 200 (OK Success)</option>
                                <option value="201">✨ 201 (Created)</option>
                            </select>
                        </div>

                        <!-- Filtro Verbo HTTP -->
                        <div class="filter-field">
                            <label class="filter-field-label">
                                🌐 Metodo HTTP
                                <span class="info-pill" data-tooltip="Filtra per verbo HTTP: GET (lettura), POST (scrittura), PUT, DELETE." title="Filtra per verbo HTTP">ℹ️</span>
                            </label>
                            <select v-model="filterVerb" class="filter-input-ctrl" @change="onFilterChange">
                                <option value="">🌐 Tutti i verbi</option>
                                <option value="GET">GET (Lettura)</option>
                                <option value="POST">POST (Creazione/Azione)</option>
                                <option value="PUT">PUT (Modifica)</option>
                                <option value="DELETE">DELETE (Eliminazione)</option>
                            </select>
                        </div>

                        <!-- Filtro Testo / Rotta / Metodo -->
                        <div class="filter-field" style="grid-column: span 2;">
                            <label class="filter-field-label">
                                🔎 Cerca Rotta, Funzione o Errore
                                <span class="info-pill" data-tooltip="Ricerca parziale nel percorso URL (es. /api/orders), nel nome del controller o nel testo dell'eccezione." title="Cerca per percorso URL o testo">ℹ️</span>
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input
                                    type="text"
                                    v-model="filterText"
                                    class="filter-input-ctrl"
                                    placeholder="Es: /api/orders, PaymentService, Eccezione..."
                                    @input="debounceFetchLogs"
                                />
                                <button
                                    v-if="hasActiveFilters"
                                    class="btn-reset-filters"
                                    @click="resetAllFilters"
                                    title="Ripristina tutti i filtri e mostra tutti i log"
                                >
                                    ✖️ Azzera Filtri
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Riepilogo Risultati -->
                    <div class="filter-status-summary">
                        <div>
                            <span>Risultati filtrati: </span>
                            <strong style="color: #fff; font-size: 13px;">@{{ logs.length }} log trovati</strong>
                            <span v-if="hasActiveFilters" style="color: #38bdf8; margin-left: 8px; font-weight: 600;">
                                • Filtri attivi applicati
                            </span>
                        </div>
                        <div v-if="hasActiveFilters">
                            <a href="#" @click.prevent="resetAllFilters" style="color: #f87171; text-decoration: none; font-weight: 700;">
                                ✖️ Rimuovi tutti i filtri
                            </a>
                        </div>
                    </div>
                </div>

                <table class="data-table" style="margin-top: 0;">
                    <thead>
                        <tr>
                            <th>
                                Verbo
                                <span class="info-pill" data-tooltip="<strong>Metodo HTTP della richiesta:</strong><br>GET (lettura), POST (invio dati), PUT/PATCH (modifica), DELETE (eliminazione)." title="Metodo HTTP: GET, POST, PUT, DELETE, PATCH">ℹ️</span>
                            </th>
                            <th>
                                Rotta / Endpoint (URI)
                                <span class="info-pill" data-tooltip="<strong>Percorso della rotta:</strong><br>L'indirizzo URL o endpoint API richiamato dal client." title="Indirizzo URL o endpoint API">ℹ️</span>
                            </th>
                            <th>
                                Esito HTTP
                                <span class="info-pill" data-tooltip="<strong>Codice di stato HTTP:</strong><br>200 = Successo | 4xx = Errore Client / Validazione | 500 = Errore Server o Eccezione PHP." title="200 = Successo | 4xx = Errore Client | 500 = Errore Server">ℹ️</span>
                            </th>
                            <th>
                                Utente Esecutore
                                <span class="info-pill" data-tooltip="<strong>Utente Autenticato:</strong><br>L'utente che ha compiuto l'operazione. Se non loggato, compare come Ospite." title="Utente esecutore dell'operazione">ℹ️</span>
                            </th>
                            <th>
                                Durata (ms)
                                <span class="info-pill" data-tooltip="<strong>Tempo di Elaborazione:</strong><br>Durata totale della richiesta impiegata dal server. Verde: veloce (&lt;500ms), Giallo/Rosso: lento." title="Tempo di risposta del server in millisecondi">ℹ️</span>
                            </th>
                            <th>Data & Ora</th>
                            <th>Dettaglio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs" :key="log.id">
                            <td>
                                <span :class="['badge', 'badge-' + log.verbo.toLowerCase()]">
                                    @{{ log.verbo.toUpperCase() }}
                                </span>
                            </td>
                            <td>
                                <span class="mono">@{{ log.rotta }}</span>
                                <span v-if="log.transaction_status === 'rollback'" class="tx-badge" data-tooltip="<strong>Transazione SQL non chiusa:</strong><br>A causa dell'errore, il pacchetto ha annullato (Rollback) automaticamente le modifiche per proteggere il database." title="Rollback automatico di sicurezza transazione SQL">
                                    ⚡ ROLLBACK DB
                                </span>
                            </td>
                            <td>
                                <span :class="['badge', 'status-' + log.codicehttp]">
                                    @{{ log.codicehttp }}
                                </span>
                            </td>
                            <td>
                                <span>@{{ log.user_label || (log.user ? log.user.name : (log.user_id ? 'Utente #' + log.user_id : 'Ospite')) }}</span>
                            </td>
                            <td class="mono">@{{ log.duration_ms }} ms</td>
                            <td class="mono">@{{ formatDate(log.dataoperazione) }}</td>
                            <td>
                                <button class="btn-view-detail" @click="openLogDetail(log)">🔍 Ispeziona</button>
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0">
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                Nessuna operazione registrata. Usa i pulsanti di simulazione in alto per scatenare una chiamata!
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 2: STUDIO ROTTE -->
            <div v-if="currentTab === 'studio_routes'" class="content-card">
                <div class="content-header">
                    <div>
                        <div class="content-title">🗺️ Studio Rotte (Controllo Pagine & API Senza Codice)</div>
                        <span style="font-size: 12px; color: var(--text-muted);">Riconoscimento automatico delle rotte Laravel con attivazione permanente a 1 click</span>
                    </div>
                    <input v-model="routeFilter" type="text" class="search-input" placeholder="Filtra rotta o controller...">
                </div>

                <div class="guide-banner">
                    <span class="guide-icon">💡</span>
                    <div>
                        <strong>Cosa fa lo Studio Rotte:</strong>
                        <p>Non serve toccare i file di routing o fare deploy. Attiva l'interruttore <strong>(ON)</strong> sulla rotta desiderata per iniziare a registrarla sempre. Seleziona il <strong>Livello Dettaglio</strong>: <em>Base</em> per salvare solo i parametri, <em>🎯 Core</em> per evidenziare i tuoi file PHP, o <em>🔍 Completo</em> per l'intero albero di esecuzione.</p>
                    </div>
                </div>

                <table class="data-table" style="margin-top: 16px;">
                    <thead>
                        <tr>
                            <th style="width: 110px;">
                                Stato Log
                                <span class="info-pill" data-tooltip="<strong>Stato Tracciamento:</strong><br>Attiva (ON) o disattiva (OFF) il salvataggio dei log su questa rotta in modo permanente e immediato." title="Attiva o disattiva il tracciamento su questa rotta">ℹ️</span>
                            </th>
                            <th>
                                Metodi HTTP
                                <span class="info-pill" data-tooltip="<strong>Verbi HTTP Accettati:</strong><br>Metodi supportati da questa rotta (GET, POST, PUT, DELETE, PATCH)." title="Metodi HTTP accettati">ℹ️</span>
                            </th>
                            <th>Percorso Rotta (URI)</th>
                            <th>Controller & Metodo PHP</th>
                            <th>
                                Livello Dettaglio
                                <span class="info-pill" data-tooltip="<strong>Livello di Profondità:</strong><br><strong>Base:</strong> Salva richiesta e tempi.<br><strong>🎯 Core (Consigliato):</strong> Evidenzia solo i tuoi file in app/.<br><strong>🔍 Completo:</strong> Include l'intero framework Laravel." title="Profondità dello Stack Trace da registrare">ℹ️</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in filteredRoutes" :key="r.uri">
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label class="toggle-switch">
                                        <input type="checkbox" :checked="r.is_tracked" @change="toggleRouteTracking(r)">
                                        <span class="slider"></span>
                                    </label>
                                    <span :style="{ fontSize: '11px', fontWeight: '700', color: r.is_tracked ? '#10b981' : '#64748b' }">
                                        @{{ r.is_tracked ? 'ON' : 'OFF' }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span v-for="m in r.methods" :key="m" :class="['badge', 'badge-' + m.toLowerCase()]" style="margin-right: 4px;">
                                    @{{ m }}
                                </span>
                            </td>
                            <td class="mono">/@{{ r.clean_uri }}</td>
                            <td style="color: var(--accent); font-weight: 600;">@{{ r.controller }}@{{ r.controller_method ? '@' + r.controller_method : '' }}</td>
                            <td>
                                <select v-model="r.stack_level" style="background: #0f172a; color: #fff; border: 1px solid var(--border-color); padding: 5px 10px; border-radius: 6px; font-size: 12px;" @change="updateRouteLevel(r)">
                                    <option value="base">Base (Parametri e Tempi)</option>
                                    <option value="core">🎯 Core (Consigliato: file in app/)</option>
                                    <option value="full">🔍 Completo (Tecnico: include Vendor)</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 3: STUDIO FUNZIONI & SERVIZI -->
            <div v-if="currentTab === 'studio_classes'" class="content-card">
                <div class="content-header">
                    <div>
                        <div class="content-title">⚙️ Studio Funzioni & Servizi (Proxy Dinamico Metodi PHP)</div>
                        <span style="font-size: 12px; color: var(--text-muted);">Monitora singole funzioni di calcolo o logica interna senza toccare il codice sorgente</span>
                    </div>
                    <span style="font-size: 12px; color: #38bdf8; font-weight: 600;">Scansione automatica directory app/</span>
                </div>

                <div class="guide-banner">
                    <span class="guide-icon">💡</span>
                    <div>
                        <strong>Cosa fa lo Studio Funzioni:</strong>
                        <p>Il sistema rileva le classi di servizio e logica business in <code>app/</code>. Cliccando sull'interruttore di un metodo, il pacchetto attiva un <strong>Proxy Dinamico</strong>: ogni volta che l'applicazione richiama quel metodo, registra automaticamente i parametri in ingresso, il tempo di esecuzione in millisecondi ed eventuali eccezioni.</p>
                    </div>
                </div>

                <div style="padding: 20px;">
                    <div v-for="c in classes" :key="c.class_name" style="background: #131d31; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 16px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <div>
                                <span style="background: #4f46e5; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; margin-right: 8px;">@{{ c.category }}</span>
                                <strong style="font-size: 15px; color: #fff;">@{{ c.short_name }}</strong>
                                <span class="mono" style="font-size: 12px; color: var(--text-muted); margin-left: 8px;">@{{ c.class_name }}</span>
                            </div>
                        </div>

                        <table class="data-table" style="background: transparent;">
                            <thead>
                                <tr>
                                    <th style="width: 110px;">
                                        Intercetta
                                        <span class="info-pill" data-tooltip="<strong>Proxy Dinamico:</strong><br>Attiva o disattiva l'intercettazione e il log per questo specifico metodo PHP." title="Attiva intercettazione per questo metodo">ℹ️</span>
                                    </th>
                                    <th>Metodo & Argomenti</th>
                                    <th>Tipo Ritorno</th>
                                    <th>Stato Interceptor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in c.methods" :key="m.target">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <label class="toggle-switch">
                                                <input type="checkbox" :checked="m.is_tracked" @change="toggleMethodTracking(m, c)">
                                                <span class="slider"></span>
                                            </label>
                                            <span :style="{ fontSize: '11px', fontWeight: '700', color: m.is_tracked ? '#10b981' : '#64748b' }">
                                                @{{ m.is_tracked ? 'LOG' : 'OFF' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="mono" style="color: #38bdf8; font-weight: 700;">@{{ m.name }}</span>
                                        <span class="mono" style="color: var(--text-muted);">(...)</span>
                                    </td>
                                    <td class="mono" style="color: #a855f7;">: @{{ m.return_type }}</td>
                                    <td>
                                        <span v-if="m.is_tracked" style="color: #34d399; font-weight: 700; font-size: 12px;">⚡ Proxy Dinamico Attivo</span>
                                        <span v-else style="color: var(--text-muted); font-size: 12px;">Non monitorato</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: MONITOR UTENTE LIVE -->
            <div v-if="currentTab === 'studio_users'" class="content-card">
                <div class="content-header">
                    <div>
                        <div class="content-title">👤 Monitoraggio Utente Live a Tempo</div>
                        <span style="font-size: 12px; color: var(--text-muted);">Session Tracker mirato per assistenza clienti con disattivazione automatica</span>
                    </div>
                    <span style="font-size: 12px; color: #f43f5e; font-weight: 700;">⏱️ Disattivazione Automatica al termine</span>
                </div>

                <div class="guide-banner">
                    <span class="guide-icon">💡</span>
                    <div>
                        <strong>Cosa fa il Monitoraggio Utente Live:</strong>
                        <p>Ideale per il supporto tecnico: se un singolo cliente segnala un problema, puoi monitorare tutte le sue azioni per <strong>5, 15, 30 o 60 minuti</strong> con Stack Trace completo. Traccia solo lui senza riempire il database con i log di altri utenti, e si disattiva automaticamente allo scadere del timer.</p>
                    </div>
                </div>

                <div style="padding: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <!-- Avvio Sessione -->
                    <div style="background: #131d31; padding: 20px; border-radius: 10px; border: 1px solid var(--border-color);">
                        <h4 style="margin-bottom: 8px;">1. Seleziona Utente da Monitorare:</h4>
                        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Cerca per nome o email l'utente che sta riscontrando il problema:</p>
                        <div style="margin-bottom: 20px;">
                            <select v-model="targetUserId" style="width: 100%; background: #0f172a; color: #fff; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13px;">
                                <option v-for="u in usersList" :key="u.id" :value="u.id">
                                    @{{ u.name }} (@{{ u.email }})
                                </option>
                            </select>
                        </div>

                        <h4 style="margin-bottom: 8px;">2. Durata Finestra di Monitoraggio:</h4>
                        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Al termine dei minuti scelti, il monitoraggio si arresterà da solo:</p>
                        <div style="display: flex; gap: 8px; margin-bottom: 24px;">
                            <button v-for="d in [5, 15, 30, 60]" :key="d" :class="['sim-btn', userDuration === d ? 'btn-info' : '']" style="border: 1px solid var(--border-color);" @click="userDuration = d">
                                ⏱️ @{{ d }} Minuti
                            </button>
                        </div>

                        <button class="sim-btn btn-success" style="width: 100%; justify-content: center; font-size: 14px; padding: 12px;" @click="startLiveSession">
                            ⚡ Avvia Monitoraggio Live (@{{ userDuration }} min)
                        </button>
                    </div>

                    <!-- Sessioni Attive -->
                    <div style="background: #131d31; padding: 20px; border-radius: 10px; border: 1px solid var(--border-color);">
                        <h4 style="margin-bottom: 8px;">🔴 Sessioni Attive in Tempo Reale:</h4>
                        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">Utenti attualmente sotto lente d'ingrandimento con countdown live:</p>
                        
                        <div v-for="s in activeSessions" :key="s.id" style="display: flex; justify-content: space-between; align-items: center; background: #0f172a; padding: 14px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #4f46e5;">
                            <div>
                                <div style="font-weight: 700; font-size: 14px;">@{{ s.metadata?.user_name || 'Utente #' + s.target }}</div>
                                <div style="font-size: 12px; color: var(--text-muted);">@{{ s.metadata?.user_email }}</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="text-align: right;">
                                    <span style="font-size: 10px; color: var(--text-muted); display: block;">TEMPO RESIDUO</span>
                                    <span class="mono" style="color: #f43f5e; font-weight: 800; font-size: 14px;">⏱️ @{{ formatSeconds(s.seconds_left) }}</span>
                                </div>
                                <button class="sim-btn btn-danger" style="padding: 6px 12px; font-size: 11px;" @click="stopLiveSession(s.id)" title="Interrompe anticipatamente il monitoraggio di questo utente">
                                    ⏹️ Interrompi Ora
                                </button>
                            </div>
                        </div>

                        <div v-if="activeSessions.length === 0" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Nessun utente sotto osservazione attiva al momento.
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- MODAL DETTAGLIO LOG -->
        <div v-if="activeModalLog" class="modal-overlay" @click.self="activeModalLog = null">
            <div class="modal-body">
                <div class="modal-header">
                    <div>
                        <span :class="['badge', 'badge-' + activeModalLog.verbo.toLowerCase()]">@{{ activeModalLog.verbo }}</span>
                        <strong class="mono" style="margin-left: 8px;">@{{ activeModalLog.rotta }}</strong>
                    </div>
                    <button style="background: none; border: none; color: #fff; font-size: 18px; cursor: pointer;" @click="activeModalLog = null">✕</button>
                </div>

                <div class="modal-content">
                    <h4 style="margin-bottom: 8px;">Parametri Registrati (con protezione dati sensibili):</h4>
                    <pre class="stack-tree" style="margin-bottom: 16px;">@{{ activeModalLog.parametri ? JSON.stringify(activeModalLog.parametri, null, 2) : 'Nessun parametro inviato (richiesta senza body o query string)' }}</pre>

                    <!-- Funzioni & Step Applicativi Intercettati -->
                    <div v-if="activeModalLog.custom_traces && ((activeModalLog.custom_traces.steps && activeModalLog.custom_traces.steps.length) || (activeModalLog.custom_traces.traces && activeModalLog.custom_traces.traces.length))" style="margin-bottom: 16px;">
                        <h4 style="color: #34d399; margin-bottom: 8px;">⚙️ Step & Metodi Interni Intercettati:</h4>
                        
                        <!-- Traces con durata -->
                        <div v-for="tr in (activeModalLog.custom_traces.traces || [])" :key="tr.label" style="background: #131d31; border-left: 3px solid #38bdf8; padding: 8px 12px; border-radius: 6px; margin-bottom: 6px; font-size: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong>⚡ @{{ tr.label }}</strong>
                                <span class="mono" style="color: #f59e0b; font-weight: bold;">@{{ tr.duration_ms }} ms</span>
                            </div>
                            <div v-if="tr.file" style="color: var(--text-muted); font-size: 11px; margin-top: 2px;">@{{ tr.file }}:@{{ tr.line }}</div>
                        </div>

                        <!-- Steps e Checkpoint -->
                        <div v-for="step in (activeModalLog.custom_traces.steps || [])" :key="step.label" style="background: #131d31; border-left: 3px solid #10b981; padding: 8px 12px; border-radius: 6px; margin-bottom: 6px; font-size: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong>📌 @{{ step.label }}</strong>
                                <span v-if="step.context?.duration_ms" class="mono" style="color: #f59e0b;">@{{ step.context.duration_ms }} ms</span>
                            </div>
                            <div v-if="step.context && Object.keys(step.context).length" style="color: #94a3b8; font-size: 11px; margin-top: 3px; font-family: monospace;">
                                @{{ JSON.stringify(step.context) }}
                            </div>
                        </div>
                    </div>

                    <!-- Stack Trace a 2 Livelli -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h4 style="margin: 0;">
                            Analisi Chiamate Stack Trace 
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: normal;">
                                (Vista: <strong :style="{ color: stackViewMode === 'core' ? '#10b981' : '#38bdf8' }">@{{ stackViewMode === 'core' ? 'Solo Codice Core (app/)' : 'Stack Completo (Vendor)' }}</strong>)
                            </span>
                        </h4>
                        <button class="sim-btn" :class="stackViewMode === 'core' ? 'btn-info' : 'btn-success'" style="padding: 4px 12px; font-size: 11px;" @click="stackViewMode = stackViewMode === 'core' ? 'full' : 'core'">
                            @{{ stackViewMode === 'core' ? '🔍 Passa a Stack Completo (Vendor)' : '🎯 Passa a Solo Codice Core' }}
                        </button>
                    </div>
                    <div class="stack-tree">
                        <div v-for="(frame, idx) in displayedStackTrace" :key="idx" :class="['stack-item', { core: frame.is_core }]">
                            <span>#@{{ idx }} </span>
                            <span style="color: #38bdf8;">@{{ frame.class }}@{{ frame.type }}@{{ frame.function }}()</span>
                            <span v-if="frame.is_core" class="core-badge">CORE</span>
                            <div style="color: var(--text-muted); font-size: 11px;">@{{ frame.file }}:@{{ frame.line }}</div>
                        </div>
                        <div v-if="!displayedStackTrace.length" style="color: var(--text-muted); padding: 12px; line-height: 1.6;">
                            <div v-if="stackViewMode === 'core'">
                                ℹ️ <strong>Nessun frame nel codice core dell'applicazione</strong>: l'operazione non ha sollevato eccezioni runtime in <code>app/</code>.<br>
                                Clicca su <button class="sim-btn btn-info" style="padding: 2px 8px; font-size: 11px; margin: 4px 0;" @click="stackViewMode = 'full'">🔍 Mostra Stack Completo (Vendor)</button> per visualizzare la catena di esecuzione del framework.
                            </div>
                            <div v-else>
                                Nessun frame nello stack trace.
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
                const currentTab = ref('logs');
                const logs = ref([]);
                const routes = ref([]);
                const classes = ref([]);
                const activeSessions = ref([]);
                const usersList = ref(@json($users));
                const selectedUserId = ref('');
                const targetUserId = ref(usersList.value[0]?.id || 1);
                const userDuration = ref(15);
                const simulating = ref(false);
                const lastSimResult = ref('');
                const routeFilter = ref('');
                const activeModalLog = ref(null);
                const stackViewMode = ref('core');
                const stats = ref({});

                // Filtri di ricerca reattivi
                const activeQuickFilter = ref('all');
                const filterStatus = ref('');
                const filterUser = ref('');
                const filterVerb = ref('');
                const filterText = ref('');
                const filterSlow = ref(false);
                const filterUnfinishedTx = ref(false);
                const filterOnlyErrors = ref(false);

                const hasActiveFilters = computed(() => {
                    return activeQuickFilter.value !== 'all' ||
                        filterStatus.value !== '' ||
                        filterUser.value !== '' ||
                        filterVerb.value !== '' ||
                        (filterText.value && filterText.value.trim() !== '') ||
                        filterSlow.value ||
                        filterUnfinishedTx.value ||
                        filterOnlyErrors.value;
                });

                let debounceTimer = null;
                function debounceFetchLogs() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        activeQuickFilter.value = 'custom';
                        fetchLogs();
                    }, 300);
                }

                function setQuickFilter(type) {
                    activeQuickFilter.value = type;
                    if (type === 'all') {
                        filterStatus.value = '';
                        filterOnlyErrors.value = false;
                        filterUnfinishedTx.value = false;
                        filterSlow.value = false;
                    } else if (type === '500') {
                        filterStatus.value = '500';
                        filterOnlyErrors.value = false;
                        filterUnfinishedTx.value = false;
                        filterSlow.value = false;
                    } else if (type === 'errors') {
                        filterStatus.value = '';
                        filterOnlyErrors.value = true;
                        filterUnfinishedTx.value = false;
                        filterSlow.value = false;
                    } else if (type === 'tx') {
                        filterStatus.value = '';
                        filterOnlyErrors.value = false;
                        filterUnfinishedTx.value = true;
                        filterSlow.value = false;
                    } else if (type === 'slow') {
                        filterStatus.value = '';
                        filterOnlyErrors.value = false;
                        filterUnfinishedTx.value = false;
                        filterSlow.value = true;
                    }
                    fetchLogs();
                }

                function onFilterChange() {
                    activeQuickFilter.value = 'custom';
                    fetchLogs();
                }

                function resetAllFilters() {
                    activeQuickFilter.value = 'all';
                    filterStatus.value = '';
                    filterUser.value = '';
                    filterVerb.value = '';
                    filterText.value = '';
                    filterSlow.value = false;
                    filterUnfinishedTx.value = false;
                    filterOnlyErrors.value = false;
                    fetchLogs();
                }

                const filteredRoutes = computed(() => {
                    if (!routeFilter.value) return routes.value;
                    const q = routeFilter.value.toLowerCase();
                    return routes.value.filter(r => r.uri.toLowerCase().includes(q) || (r.controller || '').toLowerCase().includes(q));
                });

                const displayedStackTrace = computed(() => {
                    if (!activeModalLog.value || !activeModalLog.value.stack_trace) return [];
                    if (stackViewMode.value === 'core') {
                        return activeModalLog.value.stack_trace.filter(f => f.is_core);
                    }
                    return activeModalLog.value.stack_trace;
                });

                async function fetchLogs() {
                    try {
                        const params = new URLSearchParams();
                        params.append('per_page', '50');

                        if (activeQuickFilter.value === '500' || filterStatus.value === '500') {
                            params.append('status_codes[]', '500');
                        } else if (filterStatus.value) {
                            params.append('status_codes[]', filterStatus.value);
                        }

                        if (activeQuickFilter.value === 'errors' || filterOnlyErrors.value) {
                            params.append('has_error', '1');
                        }

                        if (activeQuickFilter.value === 'tx' || filterUnfinishedTx.value) {
                            params.append('has_unfinished_transaction', '1');
                        }

                        if (activeQuickFilter.value === 'slow' || filterSlow.value) {
                            params.append('min_duration', '1000');
                        }

                        if (filterUser.value) {
                            params.append('user', filterUser.value);
                        }

                        if (filterVerb.value) {
                            params.append('verb', filterVerb.value);
                        }

                        if (filterText.value && filterText.value.trim()) {
                            params.append('text', filterText.value.trim());
                        }

                        const res = await fetch(`/api/logoperations?${params.toString()}`);
                        const data = await res.json();
                        logs.value = data.data || [];
                    } catch (e) {}

                    try {
                        const sRes = await fetch('/api/logoperations/stats');
                        const sData = await sRes.json();
                        stats.value = sData.data || {};
                    } catch (e) {}
                }

                async function fetchStudioData() {
                    try {
                        const rRes = await fetch('/api/logoperations/studio/routes', { headers: { 'Accept': 'application/json' } });
                        const rData = await rRes.json();
                        routes.value = rData.data || [];

                        const cRes = await fetch('/api/logoperations/studio/classes', { headers: { 'Accept': 'application/json' } });
                        const cData = await cRes.json();
                        classes.value = cData.data || [];

                        const rulesRes = await fetch('/api/logoperations/studio/rules', { headers: { 'Accept': 'application/json' } });
                        const rulesData = await rulesRes.json();
                        activeSessions.value = (rulesData.data || [])
                            .filter(r => r.type === 'user_session' && !r.is_expired)
                            .map(s => ({
                                ...s,
                                seconds_left: s.seconds_remaining != null ? s.seconds_remaining : Math.max(0, Math.floor((new Date(s.expires_at) - new Date()) / 1000))
                            }));
                    } catch (e) {
                        console.error('Error in fetchStudioData:', e);
                    }
                }

                async function switchUser() {
                    await fetch('/api/demo/login-as', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: selectedUserId.value || null })
                    });
                    lastSimResult.value = selectedUserId.value ? 'Utente attivo impostato' : 'Sessione Ospite impostata';
                }

                async function runSimulation(type) {
                    simulating.value = true;
                    lastSimResult.value = 'Esecuzione...';
                    try {
                        let endpoint = '/api/demo/orders';
                        let method = 'POST';
                        let body = null;
                        const headers = { 'Accept': 'application/json' };

                        if (type === 'order') {
                            headers['Content-Type'] = 'application/json';
                            body = JSON.stringify({
                                cliente_id: selectedUserId.value || 1,
                                articoli: [
                                    { prodotto_id: 10, nome: 'Laptop Pro 16"', prezzo: 1499.00, quantita: 1 },
                                    { prodotto_id: 22, nome: 'Mouse Wireless', prezzo: 49.90, quantita: 2 }
                                ],
                                metodo_pagamento: 'carta_credito',
                                numero_carta: '4000123456789010', // campo sensibile da mascherare
                                cvv_sicurezza: '883',              // campo sensibile da mascherare
                                indirizzo_spedizione: 'Via Garibaldi 42, Roma'
                            });
                        } else if (type === 'error') {
                            endpoint = '/api/demo/payment-fail';
                        } else if (type === 'transaction') {
                            endpoint = '/api/demo/unfinished-transaction';
                        } else if (type === 'slow') {
                            endpoint = '/api/demo/slow-request';
                            method = 'GET';
                        }

                        const res = await fetch(endpoint, { method, headers, body });
                        const data = await res.json().catch(() => ({}));
                        lastSimResult.value = `Risposta: HTTP ${res.status}`;
                        await fetchLogs();
                    } catch (e) {
                        lastSimResult.value = 'Chiamata eseguita!';
                        await fetchLogs();
                    } finally {
                        simulating.value = false;
                    }
                }

                async function toggleRouteTracking(r) {
                    r.is_tracked = !r.is_tracked;
                    await fetch('/api/logoperations/studio/rules', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            type: 'route',
                            target: r.uri,
                            name: r.controller || r.uri,
                            stack_level: r.stack_level || 'base',
                            is_active: r.is_tracked
                        })
                    });
                }

                async function updateRouteLevel(r) {
                    if (r.is_tracked) {
                        await fetch('/api/logoperations/studio/rules', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                type: 'route',
                                target: r.uri,
                                stack_level: r.stack_level,
                                is_active: true
                            })
                        });
                    }
                }

                async function toggleMethodTracking(m, cls) {
                    m.is_tracked = !m.is_tracked;
                    await fetch('/api/logoperations/studio/rules', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            type: 'method',
                            target: m.target,
                            name: `${cls.short_name}::${m.name}`,
                            stack_level: 'core',
                            is_active: m.is_tracked
                        })
                    });
                }

                async function startLiveSession() {
                    const u = usersList.value.find(x => String(x.id) === String(targetUserId.value));
                    try {
                        const res = await fetch('/api/logoperations/studio/user-session', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                user_id: String(targetUserId.value),
                                duration_minutes: Number(userDuration.value),
                                user_name: u ? u.name : ('Utente #' + targetUserId.value),
                                user_email: u ? u.email : ''
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            lastSimResult.value = `Monitoraggio live avviato per ${u ? u.name : targetUserId.value} (${userDuration.value} min)`;
                        }
                    } catch (e) {
                        console.error('Errore avvio live session:', e);
                    }
                    await fetchStudioData();
                }

                async function stopLiveSession(id) {
                    try {
                        await fetch(`/api/logoperations/studio/user-session/${id}`, {
                            method: 'DELETE',
                            headers: { 'Accept': 'application/json' }
                        });
                        lastSimResult.value = 'Monitoraggio live interrotto.';
                    } catch (e) {
                        console.error('Errore arresto live session:', e);
                    }
                    await fetchStudioData();
                }

                function openLogDetail(log) {
                    activeModalLog.value = log;
                    const hasCore = log.stack_trace && log.stack_trace.some(f => f.is_core);
                    stackViewMode.value = hasCore ? 'core' : 'full';
                }

                function formatDate(iso) {
                    if (!iso) return '—';
                    const d = new Date(iso);
                    return d.toLocaleTimeString('it-IT');
                }

                function formatSeconds(sec) {
                    if (sec <= 0) return '00:00';
                    const m = Math.floor(sec / 60);
                    const s = sec % 60;
                    return `${m}:${s < 10 ? '0' : ''}${s}`;
                }

                onMounted(() => {
                    fetchLogs();
                    fetchStudioData();
                    setInterval(() => {
                        activeSessions.value.forEach(s => { if (s.seconds_left > 0) s.seconds_left--; });
                    }, 1000);
                });

                return {
                    currentTab, logs, routes, classes, activeSessions, usersList, selectedUserId,
                    targetUserId, userDuration, simulating, lastSimResult, routeFilter,
                    filteredRoutes, activeModalLog, stackViewMode, displayedStackTrace, stats,
                    activeQuickFilter, filterStatus, filterUser, filterVerb, filterText,
                    filterSlow, filterUnfinishedTx, filterOnlyErrors, hasActiveFilters,
                    setQuickFilter, onFilterChange, resetAllFilters, debounceFetchLogs,
                    fetchLogs, switchUser, runSimulation, toggleRouteTracking, updateRouteLevel,
                    toggleMethodTracking, startLiveSession, stopLiveSession, openLogDetail,
                    formatDate, formatSeconds
                };
            }
        }).mount('#app');

        // Universal Floating Tooltip System (Zero-Dependency & Immune to Stacking/Overflow)
        (function() {
            const tip = document.createElement('div');
            tip.id = 'global-tooltip';
            tip.className = 'global-floating-tooltip';
            document.body.appendChild(tip);

            let activeEl = null;

            function showTip(target) {
                activeEl = target;
                const html = target.getAttribute('data-tooltip') || target.getAttribute('title');
                if (!html) return;

                tip.innerHTML = html;
                tip.style.display = 'block';
                tip.style.opacity = '1';

                const rect = target.getBoundingClientRect();
                const tipW = tip.offsetWidth;
                const tipH = tip.offsetHeight;

                // Position below element by default
                let top = rect.bottom + 8;
                let left = rect.left + (rect.width / 2) - (tipW / 2);

                // If going off screen bottom, show above
                if (top + tipH > window.innerHeight - 10) {
                    top = rect.top - tipH - 8;
                }

                // Keep within viewport horizontal bounds
                if (left < 10) left = 10;
                if (left + tipW > window.innerWidth - 10) {
                    left = window.innerWidth - tipW - 10;
                }

                tip.style.top = `${top}px`;
                tip.style.left = `${left}px`;
            }

            function hideTip() {
                tip.style.display = 'none';
                tip.style.opacity = '0';
                activeEl = null;
            }

            document.addEventListener('mouseover', (e) => {
                const target = e.target.closest ? e.target.closest('[data-tooltip]') : null;
                if (target) {
                    showTip(target);
                }
            }, true);

            document.addEventListener('mouseout', (e) => {
                const target = e.target.closest ? e.target.closest('[data-tooltip]') : null;
                if (target && target === activeEl) {
                    hideTip();
                }
            }, true);
        })();
    </script>
</body>
</html>
