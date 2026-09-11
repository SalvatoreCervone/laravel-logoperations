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
            --bg-dark: #0b0f17;
            --surface-dark: #111622;
            --surface-card: #161d2a;
            --surface-hover: #1f293b;
            --border-color: #1e2638;
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
            
            --text-main: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --text-subtle: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Simulation Bar */
        .simulator-bar {
            background: var(--surface-dark);
            border-bottom: 1px solid var(--border-color);
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .simulator-brand {
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
            color: var(--text-main);
        }

        .simulator-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .user-switcher {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-dark);
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 12.5px;
        }

        .user-switcher select {
            background: transparent;
            color: var(--text-main);
            border: none;
            font-size: 12.5px;
            font-weight: 500;
            outline: none;
            cursor: pointer;
            color-scheme: dark;
        }

        select {
            color-scheme: dark;
        }

        select option {
            background-color: var(--surface-dark) !important;
            color: var(--text-main) !important;
            padding: 8px 12px;
        }

        .sim-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .sim-btn:hover { opacity: 0.9; }

        .btn-success { background: var(--success-subtle); color: var(--success-text); border-color: var(--success-border); }
        .btn-success:hover { background: rgba(16, 185, 129, 0.2); }

        .btn-danger { background: var(--danger-subtle); color: var(--danger-text); border-color: var(--danger-border); }
        .btn-danger:hover { background: rgba(239, 68, 68, 0.2); }

        .btn-warning { background: var(--warning-subtle); color: var(--warning-text); border-color: var(--warning-border); }
        .btn-warning:hover { background: rgba(245, 158, 11, 0.2); }

        .btn-info { background: var(--primary-subtle); color: #60a5fa; border-color: var(--primary-border); }
        .btn-info:hover { background: rgba(59, 130, 246, 0.2); }

        /* Sim Feedback Toast */
        .sim-feedback {
            font-size: 11.5px;
            font-family: 'JetBrains Mono', monospace;
            padding: 4px 8px;
            border-radius: 5px;
            background: var(--success-subtle);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }

        /* App Container */
        .playground-container {
            max-width: 1480px;
            margin: 20px auto;
            padding: 0 24px;
        }

        /* Main View Switcher Tabs */
        .main-nav {
            display: flex;
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            padding: 4px;
            border-radius: 8px;
            margin-bottom: 20px;
            gap: 4px;
            overflow-x: auto;
        }

        .main-tab {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 14px;
            border: 1px solid transparent;
            background: transparent;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .main-tab:hover { color: var(--text-main); background: var(--surface-card); }
        .main-tab.active {
            background: var(--surface-card);
            color: var(--text-main);
            border-color: var(--border-strong);
            font-weight: 600;
        }

        /* KPI Banner */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px 18px;
            position: relative;
            transition: border-color 0.15s ease;
        }
        .kpi-card:hover { border-color: var(--border-strong); }

        .kpi-label { font-size: 11px; font-weight: 600; color: var(--text-subtle); text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-value { font-size: 24px; font-weight: 700; color: var(--text-main); margin-top: 4px; font-family: 'JetBrains Mono', monospace; }
        .kpi-card.kpi-error .kpi-value { color: var(--danger-text); }
        .kpi-sub { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }

        /* Tables & Studio Panels */
        .content-card {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .content-header {
            padding: 14px 20px;
            background: var(--surface-card);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .content-title { font-size: 15px; font-weight: 600; color: var(--text-main); }

        /* Search & Filters */
        .search-input {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 7px 12px;
            border-radius: 6px;
            font-size: 12.5px;
            width: 280px;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .search-input:focus { border-color: var(--primary); }

        /* Modern Filter Toolbar */
        .filter-toolbar {
            background: var(--bg-dark);
            border-bottom: 1px solid var(--border-color);
            padding: 14px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .filter-pills-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .filter-pill-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-subtle);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .filter-pill {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 4px 11px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            user-select: none;
        }

        .filter-pill:hover {
            background: var(--surface-card);
            color: var(--text-main);
            border-color: var(--border-strong);
        }

        .filter-pill.active {
            background: var(--primary-subtle);
            color: #60a5fa;
            border-color: var(--primary-border);
            font-weight: 600;
        }

        .filter-pill.active.pill-danger {
            background: var(--danger-subtle);
            border-color: var(--danger-border);
            color: var(--danger-text);
            font-weight: 600;
        }

        .filter-pill.active.pill-warning {
            background: var(--warning-subtle);
            border-color: var(--warning-border);
            color: var(--warning-text);
            font-weight: 600;
        }

        .filter-pill.active.pill-purple {
            background: var(--accent-subtle);
            border-color: rgba(99, 102, 241, 0.3);
            color: #c7d2fe;
            font-weight: 600;
        }

        .filter-pill.active.pill-cyan {
            background: var(--info-subtle);
            border-color: rgba(14, 165, 233, 0.3);
            color: #38bdf8;
            font-weight: 600;
        }

        .filter-fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: end;
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-field-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-subtle);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .filter-input-ctrl {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 7px 11px;
            border-radius: 6px;
            font-size: 12.5px;
            outline: none;
            width: 100%;
            color-scheme: dark;
            transition: border-color 0.15s ease;
        }

        .filter-input-ctrl:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }

        .filter-input-ctrl option {
            background: var(--surface-dark);
            color: var(--text-main);
            padding: 8px 12px;
        }

        .btn-reset-filters {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 7px 11px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
            height: 35px;
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

        .filter-status-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-subtle);
            padding-top: 6px;
            border-top: 1px solid var(--border-color);
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            text-align: left;
        }

        .data-table th {
            background: var(--surface-card);
            padding: 10px 14px;
            color: var(--text-subtle);
            font-weight: 600;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.035);
            vertical-align: middle;
            color: var(--text-secondary);
        }

        .data-table tr:hover { background: var(--surface-hover); }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.02em;
        }

        .badge-get { background: var(--success-subtle); color: var(--success-text); border: 1px solid var(--success-border); }
        .badge-post { background: var(--primary-subtle); color: #60a5fa; border: 1px solid var(--primary-border); }
        .badge-put { background: var(--warning-subtle); color: var(--warning-text); border: 1px solid var(--warning-border); }
        .badge-delete { background: var(--danger-subtle); color: var(--danger-text); border: 1px solid var(--danger-border); }

        .status-200 { color: var(--success-text); font-weight: 600; }
        .status-400 { color: var(--warning-text); font-weight: 600; }
        .status-500 { color: var(--danger-text); font-weight: 600; background: var(--danger-subtle); border: 1px solid var(--danger-border); padding: 1px 6px; border-radius: 4px; }

        .tx-badge {
            background: var(--warning-subtle);
            color: var(--warning-text);
            border: 1px solid var(--warning-border);
            font-size: 10px;
            font-weight: 600;
            padding: 1px 5px;
            border-radius: 4px;
            margin-left: 6px;
        }

        .mono { font-family: 'JetBrains Mono', monospace; }

        /* Switch Toggle */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 32px;
            height: 18px;
            cursor: pointer;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--surface-hover);
            transition: .2s;
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 12px; width: 12px;
            left: 2px; bottom: 2px;
            background-color: #94a3b8;
            transition: .2s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--success); border-color: var(--success); }
        input:checked + .slider:before { transform: translateX(14px); background-color: #fff; }

        /* Modal Dettaglio */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            backdrop-filter: blur(4px);
            padding: 20px;
        }

        .modal-body {
            background: var(--surface-dark);
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            width: 100%;
            max-width: 900px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.6);
        }

        .modal-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-content {
            padding: 18px;
            overflow-y: auto;
        }

        .stack-tree {
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            max-height: 350px;
            overflow-y: auto;
        }

        .stack-item {
            padding: 6px 10px;
            border-left: 2px solid var(--border-color);
            margin-bottom: 6px;
        }

        .stack-item.core {
            border-left-color: var(--success);
            background: var(--success-subtle);
        }

        .core-badge {
            background: var(--success-subtle);
            color: var(--success-text);
            border: 1px solid var(--success-border);
            font-size: 9px;
            font-weight: 700;
            padding: 1px 5px;
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

        .page-btn {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            min-width: 32px;
            text-align: center;
        }
        .page-btn:hover:not(:disabled) {
            background: var(--surface-hover);
            color: var(--text-main);
            border-color: var(--border-strong);
        }
        .page-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            font-weight: 600;
        }
        .page-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        .session-pulse {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 6px var(--success);
            animation: pulse-dot 1.8s infinite;
        }
        @keyframes pulse-dot { 0%, 100% { transform: scale(0.9); opacity: 0.7; } 50% { transform: scale(1.2); opacity: 1; } }

        /* Tooltip & Guida Intuitiva per l'Utente */
        .info-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 17px;
            height: 17px;
            background: var(--surface-hover);
            color: var(--text-muted);
            border-radius: 50%;
            font-size: 11px;
            font-weight: 600;
            cursor: help;
            transition: all 0.15s ease;
            user-select: none;
            flex-shrink: 0;
            vertical-align: middle;
            margin-left: 4px;
        }
        .info-pill:hover {
            background: var(--primary);
            color: #fff;
        }

        button, .sim-btn, .tab-btn, a, select, [role="button"] {
            cursor: pointer !important;
        }

        .global-floating-tooltip {
            position: fixed;
            display: none;
            opacity: 0;
            background: var(--surface-dark);
            color: var(--text-main);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.45;
            max-width: 310px;
            border: 1px solid var(--border-strong);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.7);
            z-index: 2147483647;
            pointer-events: none;
            transition: opacity 0.15s ease-in-out;
            box-sizing: border-box;
        }

        /* Banner Guida Contestuale */
        .guide-banner {
            background: var(--primary-subtle);
            border: 1px solid var(--primary-border);
            border-radius: 6px;
            padding: 12px 14px;
            margin: 14px 20px 0 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 12.5px;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        .guide-banner .guide-icon {
            font-size: 18px;
            flex-shrink: 0;
            line-height: 1;
        }
        .guide-banner strong { color: var(--text-main); }
        .guide-banner p { margin: 0; }

        /* Storyboard Timeline Styles */
        .storyboard-toolbar {
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 14px 16px;
            margin: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .storyboard-timeline-wrap {
            padding: 10px 20px 24px;
            display: flex;
            flex-direction: column;
        }

        .timeline-item {
            display: flex;
            gap: 14px;
            position: relative;
        }

        .timeline-stem-line {
            width: 1px;
            background: var(--border-strong);
            flex-grow: 1;
            margin: 6px 0;
        }

        .timeline-bullet-node {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            z-index: 2;
            flex-shrink: 0;
            background: var(--surface-card);
            border: 2px solid var(--primary);
        }

        .timeline-event-card {
            flex: 1;
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 7px;
            margin-bottom: 14px;
            overflow: hidden;
            transition: border-color 0.15s ease;
        }

        .timeline-event-card:hover {
            border-color: var(--border-strong);
        }

        .timeline-event-header {
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            gap: 12px;
            user-select: none;
        }

        .timeline-event-body {
            padding: 12px 14px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-dark);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .pill-btn {
            padding: 4px 11px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--surface-dark);
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .pill-btn:hover {
            color: var(--text-main);
            background: var(--surface-card);
        }

        .pill-btn.active {
            background: var(--primary-subtle);
            color: #60a5fa;
            border-color: var(--primary-border);
            font-weight: 600;
        }

        .pill-btn-error.active {
            background: var(--danger-subtle);
            border-color: var(--danger-border);
            color: var(--danger-text);
            font-weight: 600;
        }

        .pill-btn-checkpoint.active {
            background: var(--info-subtle);
            border-color: rgba(14, 165, 233, 0.3);
            color: #38bdf8;
            font-weight: 600;
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
                    class="sim-btn"
                    style="background: #8b5cf6; color: #fff;"
                    :disabled="simulating"
                    @click="simulateOrderAction('checkpoint')"
                    data-tooltip="<strong>Simula Checkpoint Storyboard:</strong><br>Chiama $order->logStep() per registrare un passaggio di business (es. presa in carico, spedizione) sulla timeline dell'ordine selezionato."
                    title="Simula Checkpoint applicativo sulla Storyboard ($order->logStep)"
                >
                    🚩 Checkpoint Storyboard
                </button>

                <button
                    class="sim-btn"
                    style="background: #0284c7; color: #fff;"
                    :disabled="simulating"
                    @click="runSimulation('multimodel')"
                    data-tooltip="<strong>Simula Operazione Multi-Modello (Auto-Discovery):</strong><br>Esegue una richiesta che crea Order, Invoice e aggiorna User. LogOperations registra 1 operazione principale e collega 3 modelli distinti nella tabella relazionale."
                    title="Simula Operazione Multi-Modello con Auto-Discovery (Order + Invoice + User)"
                >
                    🧩 Multi-Modello (Auto-Discovery)
                </button>

                <button
                    class="sim-btn"
                    style="background: #0d9488; color: #fff;"
                    :disabled="simulating"
                    @click="runSimulation('bulk10')"
                    data-tooltip="<strong>Simula Ordine con 10 Articoli Connessi (Nested-5):</strong><br>Crea 1 Order e 10 OrderItem collegati in un'unica richiesta HTTP. Dimostra la visualizzazione compatta 'nested a 5' con pulsante per espandere tutti i record."
                    title="Simula Ordine con 10 OrderItem connessi (Test visualizzazione Nested-5)"
                >
                    📦 Simula 10 Articoli (Nested-5)
                </button>

                <button
                    v-if="selectedOrderId"
                    class="sim-btn"
                    style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid #6366f1;"
                    @click="currentTab = 'storyboard'; fetchStoryboard();"
                    title="Visualizza la timeline completa dell'ordine selezionato"
                >
                    📖 Vai a Storyboard
                </button>

                <span
                    v-if="lastSimResult"
                    class="sim-feedback"
                    style="cursor: pointer;"
                    @click="lastCreatedOrderId ? openStoryboardForSubject('App\\Models\\Order', lastCreatedOrderId) : null"
                    :title="lastCreatedOrderId ? 'Clicca per aprire la Storyboard di questo ordine' : ''"
                >
                    @{{ lastSimResult }}
                    <span v-if="lastCreatedOrderId" style="text-decoration: underline; margin-left: 4px; font-weight: 700;">(Vedi Storyboard →)</span>
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
                <button :class="['main-tab', { active: currentTab === 'storyboard' }]" @click="currentTab = 'storyboard'; fetchStoryboard();">
                    📖 Storyboard Record & Audit Trail (Fase 5)
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

                    <!-- Riepilogo Risultati & Azioni Export Streaming (Fase 9) -->
                    <div class="filter-status-summary" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span>Risultati visualizzati: </span>
                            <strong style="color: #fff; font-size: 13px;">@{{ logs.length }} log</strong>
                            <span v-if="pagination.total" style="color: var(--text-muted); font-size: 12px; margin-left: 4px;">
                                (di @{{ pagination.total }} totali)
                            </span>
                            <span v-if="hasActiveFilters" style="color: #38bdf8; margin-left: 8px; font-weight: 600;">
                                • Filtri attivi applicati
                            </span>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button
                                class="btn-action-primary"
                                style="background: rgba(16, 185, 129, 0.18); border-color: rgba(16, 185, 129, 0.4); color: #34d399; font-size: 12px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border-radius: 6px;"
                                @click="exportData('csv')"
                                title="Esporta tutti i log filtrati in streaming CSV O(1) memoria (compatibile con Microsoft Excel)"
                            >
                                📥 Esporta CSV
                            </button>
                            <button
                                class="btn-action-primary"
                                style="background: rgba(59, 130, 246, 0.18); border-color: rgba(59, 130, 246, 0.4); color: #60a5fa; font-size: 12px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border-radius: 6px;"
                                @click="exportData('json')"
                                title="Esporta tutti i log filtrati in streaming JSON O(1) memoria"
                            >
                                📥 Esporta JSON
                            </button>
                            <a v-if="hasActiveFilters" href="#" @click.prevent="resetAllFilters" style="color: #f87171; text-decoration: none; font-weight: 700; margin-left: 10px; font-size: 12px;">
                                ✖️ Rimuovi filtri
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
                                <!-- Badge Entità Collegata (Subject) -->
                                <span v-if="log.subject_type && log.subject_id"
                                      class="subject-tag"
                                      style="display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; font-size: 11px; padding: 2px 8px; border-radius: 6px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.35); color: #93c5fd; cursor: pointer; font-weight: 600;"
                                      @click.stop="openStoryboardForSubject(log.subject_type, log.subject_id)"
                                      :data-tooltip="'<strong>Entità Collegata:</strong><br>' + (log.subject_label || (log.subject_type + ' #' + log.subject_id)) + '<br><em>Clicca per aprire la Storyboard di questa entità</em>'"
                                      :title="'Clicca per aprire la Storyboard di ' + (log.subject_label || log.subject_type)">
                                    📦 @{{ log.subject_label || (log.subject_type.split('\\').pop() + ' #' + log.subject_id) }}
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
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <button class="btn-view-detail" @click="openLogDetail(log)">🔍 Ispeziona</button>
                                    <button v-if="log.subject_type && log.subject_id"
                                            class="sim-btn btn-info"
                                            style="padding: 4px 8px; font-size: 11px; white-space: nowrap; border-radius: 4px;"
                                            @click.stop="openStoryboardForSubject(log.subject_type, log.subject_id)"
                                            title="Apri Storyboard del record collegato">
                                        📖 Storyboard
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0">
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                Nessuna operazione registrata. Usa i pulsanti di simulazione in alto per scatenare una chiamata!
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- PAGINATION BAR (FASE 8) -->
                <div class="pagination-bar" style="background: #10192d; border-top: 1px solid var(--border-color); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div class="pagination-info" style="font-size: 12px; color: var(--text-muted);">
                        Mostrati da <strong style="color: #fff;">@{{ pagination.from || 0 }}</strong> a <strong style="color: #fff;">@{{ pagination.to || 0 }}</strong> di <strong style="color: #fff;">@{{ pagination.total || 0 }}</strong> record totali
                    </div>

                    <div class="pagination-nav" style="display: flex; align-items: center; gap: 6px;">
                        <button class="page-btn" :disabled="currentPage <= 1" @click="goToPage(1)" title="Prima pagina">«</button>
                        <button class="page-btn" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)" title="Pagina precedente">‹</button>

                        <template v-for="(p, idx) in visiblePages" :key="idx">
                            <span v-if="p === '...'" style="color: var(--text-muted); padding: 0 4px;">...</span>
                            <button v-else :class="['page-btn', { active: p === currentPage }]" @click="goToPage(p)">
                                @{{ p }}
                            </button>
                        </template>

                        <button class="page-btn" :disabled="currentPage >= pagination.last_page" @click="goToPage(currentPage + 1)" title="Pagina successiva">›</button>
                        <button class="page-btn" :disabled="currentPage >= pagination.last_page" @click="goToPage(pagination.last_page)" title="Ultima pagina">»</button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label style="font-size: 12px; color: var(--text-muted);">Per pagina:</label>
                        <select v-model="perPage" @change="onPerPageChange" style="background: #0b1326; border: 1px solid var(--border-color); color: #fff; padding: 6px 10px; border-radius: 6px; font-size: 12px; outline: none;">
                            <option v-for="opt in perPageOptions" :key="opt" :value="opt">@{{ opt }}</option>
                        </select>

                        <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted);">
                            <span>Vai a:</span>
                            <input type="number" min="1" :max="pagination.last_page || 1" v-model.number="jumpPageNumber" @keyup.enter="jumpToPage" style="width: 48px; background: #0b1326; border: 1px solid var(--border-color); color: #fff; padding: 5px 6px; border-radius: 6px; font-size: 12px; text-align: center; outline: none;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: STORYBOARD RECORD (FASE 5) -->
            <div v-if="currentTab === 'storyboard'" class="content-card">
                <div class="content-header">
                    <div>
                        <div class="content-title">📖 Storyboard del Record & Audit Trail (Fase 5)</div>
                        <span style="font-size: 12px; color: var(--text-muted);">
                            Tracciamento cronologico automatico del ciclo di vita dei modelli Eloquent (<code style="color: #a5b4fc;">App\Models\Order</code>) con Route Model Binding e checkpoint applicativi
                        </span>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button class="sim-btn btn-info" style="padding: 6px 12px; font-size: 12px;" @click="toggleStoryboardSort">
                            ⇅ @{{ storyboardSort === 'asc' ? 'Cronologico (Dal più vecchio)' : 'Inverso (Dal più recente)' }}
                        </button>
                        <button class="sim-btn btn-info" style="padding: 6px 12px; font-size: 12px;" :disabled="storyboardLoading" @click="fetchStoryboard">
                            🔄 Ricarica Timeline
                        </button>
                    </div>
                </div>

                <!-- ORDER SELECTOR & SIMULATOR ACTIONS -->
                <div class="storyboard-toolbar">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="font-size: 13px; font-weight: 700; color: #fff;">Seleziona Ordine Target:</span>
                        <select v-model="selectedOrderId" @change="fetchStoryboard" class="sim-input" style="padding: 6px 12px; font-size: 13px; font-weight: 600; border-radius: 8px; border: 1px solid var(--border-color); background: #0f172a; color: #fff;">
                            <option v-for="ord in ordersList" :key="ord.id" :value="ord.id">
                                @{{ ord.reference }} — @{{ ord.customer_name }} (€@{{ ord.amount }}) [@{{ ord.status }}]
                            </option>
                        </select>
                        <button class="sim-btn" style="background: #8b5cf6; color: #fff; padding: 6px 12px; font-size: 12px;" @click="simulateOrderAction('create')">
                            ➕ Crea Nuovo Ordine (POST)
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">Simula Azioni su questo Ordine:</span>
                        <button class="sim-btn btn-info" style="padding: 6px 10px; font-size: 12px;" :disabled="!selectedOrderId" @click="simulateOrderAction('view')" title="Esegue GET /api/demo/orders/{id}">
                            👁️ Leggi (GET)
                        </button>
                        <button class="sim-btn btn-warning" style="padding: 6px 10px; font-size: 12px;" :disabled="!selectedOrderId" @click="simulateOrderAction('update')" title="Esegue PUT /api/demo/orders/{id}">
                            ✏️ Modifica (PUT)
                        </button>
                        <button class="sim-btn btn-success" style="padding: 6px 10px; font-size: 12px;" :disabled="!selectedOrderId" @click="simulateOrderAction('checkpoint')" title="Chiama $order->logStep()">
                            🚩 Checkpoint (logStep)
                        </button>
                        <button class="sim-btn btn-danger" style="padding: 6px 10px; font-size: 12px;" :disabled="!selectedOrderId" @click="simulateOrderAction('fail')" title="Simula errore HTTP 400 associato al record">
                            ⚠️ Errore (400)
                        </button>
                    </div>
                </div>

                <!-- KPI BAR PER IL RECORD -->
                <div v-if="storyboardData.kpis" style="display: flex; gap: 12px; padding: 0 20px 14px; flex-wrap: wrap;">
                    <div class="kpi-card" style="flex: 1; min-width: 140px; padding: 10px 14px;">
                        <div class="kpi-label">Eventi Totali</div>
                        <div class="kpi-value" style="font-size: 20px;">@{{ storyboardData.kpis.total_events || 0 }}</div>
                    </div>
                    <div class="kpi-card kpi-error" style="flex: 1; min-width: 140px; padding: 10px 14px;">
                        <div class="kpi-label">Errori Rilevati</div>
                        <div class="kpi-value" style="font-size: 20px; color: #ef4444;">@{{ storyboardData.kpis.total_errors || 0 }}</div>
                    </div>
                    <div class="kpi-card" style="flex: 1; min-width: 140px; padding: 10px 14px;">
                        <div class="kpi-label">Rollback DB</div>
                        <div class="kpi-value" style="font-size: 20px; color: #f59e0b;">@{{ storyboardData.kpis.total_rollbacks || 0 }}</div>
                    </div>
                    <div class="kpi-card" style="flex: 1; min-width: 140px; padding: 10px 14px;">
                        <div class="kpi-label">Checkpoint logStep()</div>
                        <div class="kpi-value" style="font-size: 20px; color: #3b82f6;">@{{ storyboardData.kpis.total_checkpoints || 0 }}</div>
                    </div>
                    <div v-if="storyboardData.kpis.last_activity" class="kpi-card" style="flex: 1; min-width: 140px; padding: 10px 14px;">
                        <div class="kpi-label">Ultima Attività</div>
                        <div style="font-size: 13px; font-weight: 700; color: #cbd5e1; margin-top: 6px;">@{{ formatDate(storyboardData.kpis.last_activity) }}</div>
                    </div>
                </div>

                <!-- CONTROLS & FILTER PILLS -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 20px 16px; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <button :class="['pill-btn', { active: storyboardCategory === 'all' }]" @click="storyboardCategory = 'all'">
                            Tutti (@{{ storyboardData.events?.length || 0 }})
                        </button>
                        <button :class="['pill-btn pill-btn-error', { active: storyboardCategory === 'error' }]" @click="storyboardCategory = 'error'">
                            Errori & Rollback (@{{ (storyboardData.kpis?.total_errors || 0) + (storyboardData.kpis?.total_rollbacks || 0) }})
                        </button>
                        <button :class="['pill-btn', { active: storyboardCategory === 'create' }]" @click="storyboardCategory = 'create'">
                            Creazione
                        </button>
                        <button :class="['pill-btn', { active: storyboardCategory === 'update' }]" @click="storyboardCategory = 'update'">
                            Modifiche
                        </button>
                        <button :class="['pill-btn pill-btn-checkpoint', { active: storyboardCategory === 'checkpoint' }]" @click="storyboardCategory = 'checkpoint'">
                            Checkpoint (@{{ storyboardData.kpis?.total_checkpoints || 0 }})
                        </button>
                    </div>

                    <input v-model="storyboardSearch" type="text" placeholder="Cerca rotta, autore, payload, errore..." class="search-input" style="max-width: 320px;">
                </div>

                <!-- VERTICAL TIMELINE -->
                <div class="storyboard-timeline-wrap">
                    <div v-if="storyboardLoading" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        Caricamento timeline del record in corso...
                    </div>

                    <div v-else-if="filteredStoryboardEvents.length === 0" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        Nessun evento registrato per questo ordine con i filtri selezionati. Usa i pulsanti di simulazione in alto per registrare chiamate e checkpoint!
                    </div>

                    <div v-else style="display: flex; flex-direction: column;">
                        <div
                            v-for="(event, eIdx) in filteredStoryboardEvents"
                            :key="event.id"
                            class="timeline-item"
                        >
                            <!-- Stem + Bullet Node -->
                            <div style="display: flex; flex-direction: column; align-items: center; width: 28px; flex-shrink: 0;">
                                <div
                                    class="timeline-bullet-node"
                                    :style="{ backgroundColor: event.classification?.badge_color || '#6b7280' }"
                                >
                                    <span v-if="event.classification?.category === 'error'">✕</span>
                                    <span v-else-if="event.classification?.category === 'create'">+</span>
                                    <span v-else-if="event.classification?.category === 'checkpoint'">★</span>
                                    <span v-else-if="event.classification?.category === 'delete'">−</span>
                                    <span v-else>●</span>
                                </div>
                                <div v-if="eIdx < filteredStoryboardEvents.length - 1" class="timeline-stem-line"></div>
                            </div>

                            <!-- Timeline Card -->
                            <div class="timeline-event-card">
                                <div class="timeline-event-header" @click="toggleTimelineEvent(event.id)">
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <span
                                            style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;"
                                            :style="{ backgroundColor: (event.classification?.badge_color || '#6b7280') + '30', color: event.classification?.badge_color || '#6b7280' }"
                                        >
                                            @{{ event.classification?.badge_label || event.verbo }}
                                        </span>

                                        <span style="font-size: 14px; font-weight: 600; color: #fff;">
                                            @{{ event.classification?.title }}
                                        </span>

                                        <span v-if="event.codicehttp" class="status-badge" :class="`status-${event.codicehttp}`">
                                            HTTP @{{ event.codicehttp }}
                                        </span>

                                        <span v-if="event.duration_ms != null && event.duration_ms > 0" style="font-size: 11px; color: var(--text-muted); font-family: monospace;">
                                            ⏱️ @{{ event.duration_ms }} ms
                                        </span>
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 14px; font-size: 12px; color: var(--text-muted); flex-shrink: 0;">
                                        <span>👤 @{{ event.user_label }}</span>
                                        <span>🕒 @{{ formatDate(event.dataoperazione) }}</span>
                                        <button class="sim-btn" style="padding: 2px 8px; font-size: 11px; background: #1e293b; color: #93c5fd; border: 1px solid #334155; border-radius: 4px;" @click.stop="openLogDetail(event)">
                                            🔍 Log #@{{ event.id }}
                                        </button>
                                        <span style="font-size: 12px; color: #a5b4fc;">@{{ expandedTimelineEvents.has(event.id) ? '▲' : '▼' }}</span>
                                    </div>
                                </div>

                                <!-- Banner Ruolo Soggetto (Primario vs Correlato) -->
                                <div style="margin: 0 16px 8px; padding: 6px 10px; border-radius: 6px; font-size: 12px; display: flex; align-items: center; gap: 8px;"
                                     :style="{
                                         background: event.is_primary_subject ? 'rgba(59, 130, 246, 0.12)' : 'rgba(255, 255, 255, 0.04)',
                                         border: '1px solid ' + (event.is_primary_subject ? 'rgba(59, 130, 246, 0.3)' : 'rgba(255, 255, 255, 0.08)'),
                                         color: event.is_primary_subject ? '#93c5fd' : '#cbd5e1'
                                     }">
                                    <span v-if="event.is_primary_subject">🎯 <strong>Soggetto Primario:</strong> Azione diretta su questo record (azione: <strong>@{{ event.subject_action }}</strong>).</span>
                                    <span v-else>🔗 <strong>Entità Correlata:</strong> Modificato (azione: <strong>@{{ event.subject_action }}</strong>) durante un'operazione su <strong>@{{ event.primary_subject_label || event.subject_label }}</strong>.</span>
                                </div>

                                <!-- Card Collapsible Details -->
                                <div v-if="expandedTimelineEvents.has(event.id)" class="timeline-event-body">
                                    <div style="display: flex; align-items: center; gap: 10px; font-size: 13px;">
                                        <span style="width: 90px; font-weight: 600; color: var(--text-muted); font-size: 12px;">Endpoint:</span>
                                        <span style="background: #1e293b; padding: 2px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #38bdf8;">
                                            <strong>@{{ (event.verbo || '').toUpperCase() }}</strong> @{{ event.rotta }}
                                        </span>
                                    </div>

                                    <div v-if="event.controllermethod" style="display: flex; align-items: center; gap: 10px; font-size: 13px;">
                                        <span style="width: 90px; font-weight: 600; color: var(--text-muted); font-size: 12px;">Handler:</span>
                                        <span style="background: #1e293b; padding: 2px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #a5b4fc;">
                                            @{{ event.controllermethod }}
                                        </span>
                                    </div>

                                    <!-- Modelli Coinvolti nell'Operazione -->
                                    <div v-if="event.touched_models && event.touched_models.length > 0" style="background: #0f172a; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 12px; margin-top: 4px;">
                                        <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px;">
                                            🧩 Entità modificate in questa operazione:
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <template v-for="g in event.touched_models" :key="g.type">
                                                <span v-for="it in g.items" :key="it.id" 
                                                      style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; font-size: 11px; border-radius: 4px; cursor: pointer;"
                                                      :style="{
                                                          background: it.action === 'created' ? 'var(--success-subtle)' : it.action === 'deleted' ? 'var(--danger-subtle)' : 'var(--warning-subtle)',
                                                          border: '1px solid ' + (it.action === 'created' ? 'var(--success-border)' : it.action === 'deleted' ? 'var(--danger-border)' : 'var(--warning-border)'),
                                                          color: it.action === 'created' ? 'var(--success-text)' : it.action === 'deleted' ? 'var(--danger-text)' : 'var(--warning-text)'
                                                      }"
                                                      @click="openStoryboardForSubject(g.type, it.id)">
                                                    <strong>@{{ g.label }} #@{{ it.id }}</strong>
                                                    <span style="opacity: 0.7; font-size: 9.5px; text-transform: uppercase;">@{{ it.action }}</span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Custom Steps ($order->logStep) -->
                                    <div v-if="event.custom_traces?.steps?.length" style="background: #1e293b; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; margin-top: 4px;">
                                        <div style="font-size: 12px; font-weight: 700; color: #38bdf8; margin-bottom: 8px;">
                                            🚩 Passaggi Chiave & Dati Contestuali ($order->logStep):
                                        </div>
                                        <div v-for="(st, sI) in event.custom_traces.steps" :key="sI" style="margin-bottom: 6px;">
                                            <div style="font-size: 13px; font-weight: 600; color: #fff;">@{{ st.label }}</div>
                                            <pre v-if="st.context && Object.keys(st.context).length" style="margin-top: 4px; background: #090d16; padding: 8px; border-radius: 6px; font-size: 11px; font-family: monospace; color: #94a3b8; overflow-x: auto;">@{{ JSON.stringify(st.context, null, 2) }}</pre>
                                        </div>
                                    </div>

                                    <!-- Parametri / Payload -->
                                    <div v-if="event.parametri" style="margin-top: 4px;">
                                        <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">Payload Richiesta / Parametri:</div>
                                        <pre style="background: #090d16; padding: 10px; border-radius: 6px; font-size: 11px; font-family: monospace; color: #f8fafc; border: 1px solid var(--border-color); max-height: 180px; overflow-y: auto;">@{{ JSON.stringify(event.parametri, null, 2) }}</pre>
                                    </div>

                                    <!-- Errore Dettagliato -->
                                    <div v-if="event.error" style="margin-top: 4px; background: #450a0a; border: 1px solid #7f1d1d; border-radius: 6px; padding: 10px;">
                                        <div style="font-size: 12px; font-weight: 700; color: #fecaca; margin-bottom: 4px;">Dettaglio Errore:</div>
                                        <pre style="margin: 0; font-size: 11px; font-family: monospace; color: #fca5a5; white-space: pre-wrap;">@{{ event.error }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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

                <!-- Banner Entità Collegata (Subject) -->
                <div v-if="activeModalLog.subject_type && activeModalLog.subject_id" 
                     style="margin: 14px 20px 0; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.35); border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #93c5fd; font-weight: 700;">🎯 Entità Collegata (Subject):</div>
                        <div style="font-size: 14px; font-weight: 600; color: #fff; margin-top: 2px;">
                            📦 @{{ activeModalLog.subject_label || (activeModalLog.subject_type + ' #' + activeModalLog.subject_id) }}
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: normal; margin-left: 6px;">(@{{ activeModalLog.subject_type }})</span>
                        </div>
                    </div>
                    <button class="sim-btn btn-info" style="padding: 6px 14px; font-size: 12px; display: flex; align-items: center; gap: 6px; white-space: nowrap; border-radius: 6px;" @click="openStoryboardForSubject(activeModalLog.subject_type, activeModalLog.subject_id); activeModalLog = null">
                        📖 Apri Storyboard Completa &rarr;
                    </button>
                </div>

                <!-- Modelli Coinvolti nell'Operazione (Multi-Subject) -->
                <div v-if="activeModalLog.touched_models && activeModalLog.touched_models.length > 0" 
                     style="margin: 14px 20px 0; background: var(--surface-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary);">
                            <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        </svg>
                        <strong style="color: var(--text-main); font-size: 13px;">Modelli Coinvolti nell'Operazione (Auto-Discovery)</strong>
                        <span class="badge" style="background: var(--primary-subtle); color: #93c5fd; border: 1px solid var(--primary-border); font-size: 11px;">@{{ activeModalLog.touched_models.reduce((sum, g) => sum + g.count, 0) }} entità</span>
                    </div>
                    <div v-for="(group, gIdx) in activeModalLog.touched_models" :key="gIdx" style="margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                            <span style="font-weight: 600; font-size: 12px; color: var(--text-secondary);">@{{ group.label }}</span>
                            <span style="font-size: 11px; color: var(--text-muted);">(@{{ group.count }} record)</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <template v-for="(item, iIdx) in (expandedModelGroups[gIdx] ? group.items : group.items.slice(0, 5))" :key="iIdx">
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; font-size: 11px; border-radius: 4px; cursor: pointer;"
                                      :style="{
                                          background: item.action === 'created' ? 'var(--success-subtle)' : item.action === 'deleted' ? 'var(--danger-subtle)' : 'var(--warning-subtle)',
                                          border: '1px solid ' + (item.action === 'created' ? 'var(--success-border)' : item.action === 'deleted' ? 'var(--danger-border)' : 'var(--warning-border)'),
                                          color: item.action === 'created' ? 'var(--success-text)' : item.action === 'deleted' ? 'var(--danger-text)' : 'var(--warning-text)'
                                      }"
                                      @click="openStoryboardForSubject(group.type, item.id); activeModalLog = null"
                                      title="Clicca per aprire la Storyboard di questa entità">
                                    <span style="font-weight: 700;">#@{{ item.id }}</span>
                                    <span style="opacity: 0.8; font-size: 10px; text-transform: uppercase;">@{{ item.action }}</span>
                                </span>
                            </template>
                            <button v-if="!expandedModelGroups[gIdx] && group.items.length > 5"
                                    @click="expandedModelGroups[gIdx] = true"
                                    style="background: var(--surface-hover); border: 1px solid var(--border-strong); color: var(--primary); font-size: 11px; padding: 2px 10px; border-radius: 4px; cursor: pointer;">
                                Mostra tutti i @{{ group.count }} record...
                            </button>
                            <button v-if="expandedModelGroups[gIdx] && group.items.length > 5"
                                    @click="expandedModelGroups[gIdx] = false"
                                    style="background: var(--surface-hover); border: 1px solid var(--border-strong); color: var(--text-muted); font-size: 11px; padding: 2px 10px; border-radius: 4px; cursor: pointer;">
                                Mostra solo i primi 5
                            </button>
                        </div>
                    </div>
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
                        <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                            Analisi Chiamate Stack Trace 
                            <span v-if="hasVendorFrames" style="font-size: 11px; color: var(--text-muted); font-weight: normal;">
                                (Vista: <strong :style="{ color: stackViewMode === 'core' ? '#10b981' : '#38bdf8' }">@{{ stackViewMode === 'core' ? 'Solo Codice Core (app/)' : 'Stack Completo (Vendor)' }}</strong>)
                            </span>
                            <span v-else style="font-size: 11px; color: #10b981; font-weight: normal;">
                                (Livello salvato: <strong>Solo Codice Core</strong> — frame vendor esclusi a monte)
                            </span>
                        </h4>
                        <div>
                            <button v-if="hasVendorFrames" class="sim-btn" :class="stackViewMode === 'core' ? 'btn-info' : 'btn-success'" style="padding: 4px 12px; font-size: 11px;" @click="stackViewMode = stackViewMode === 'core' ? 'full' : 'core'">
                                @{{ stackViewMode === 'core' ? '🔍 Passa a Stack Completo (Vendor)' : '🎯 Passa a Solo Codice Core' }}
                            </button>
                            <span v-else style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 500;">
                                🛡️ Regola Rotta: Solo Core (Vendor non catturato)
                            </span>
                        </div>
                    </div>
                    <div class="stack-tree">
                        <div v-for="(frame, idx) in displayedStackTrace" :key="idx" :class="['stack-item', { core: frame.is_core }]">
                            <span>#@{{ idx }} </span>
                            <span style="color: #38bdf8; font-weight: 600;">@{{ frame.class }}@{{ frame.type }}@{{ frame.function }}()</span>
                            <span v-if="frame.is_core" class="core-badge">CORE</span>
                            <span v-if="frame.label && frame.label !== frame.function" style="color: var(--text-muted); font-size: 11px; margin-left: 8px; font-style: italic;">// @{{ frame.label }}</span>
                            <div style="color: var(--text-muted); font-size: 11px; font-family: monospace;">@{{ frame.file }}:@{{ frame.line }}</div>
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
                const lastCreatedOrderId = ref(null);
                const routeFilter = ref('');
                const activeModalLog = ref(null);
                const expandedModelGroups = ref({});

                // Paginazione (Fase 8)
                const currentPage = ref(1);
                const perPage = ref(20);
                const perPageOptions = ref([15, 20, 25, 50, 100]);
                const pagination = ref({
                    total: 0,
                    last_page: 1,
                    from: 0,
                    to: 0,
                });
                const jumpPageNumber = ref(1);
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

                const hasVendorFrames = computed(() => {
                    if (!activeModalLog.value || !activeModalLog.value.stack_trace) return false;
                    return activeModalLog.value.stack_trace.some(f => !f.is_core);
                });

                async function fetchLogs(page = 1) {
                    try {
                        const params = new URLSearchParams();
                        params.append('page', page.toString());
                        params.append('per_page', perPage.value.toString());

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
                        currentPage.value = data.current_page || 1;
                        pagination.value = {
                            total: data.total || 0,
                            last_page: data.last_page || 1,
                            from: data.from || 0,
                            to: data.to || 0,
                        };
                        jumpPageNumber.value = currentPage.value;
                    } catch (e) {}

                    try {
                        const sRes = await fetch('/api/logoperations/stats');
                        const sData = await sRes.json();
                        stats.value = sData.data || {};
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

                function getExportUrl(format) {
                    const params = new URLSearchParams();
                    params.append('format', format);

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

                    return `/api/logoperations/export?${params.toString()}`;
                }

                function exportData(format) {
                    window.open(getExportUrl(format), '_blank');
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
                        } else if (type === 'multimodel') {
                            endpoint = '/api/demo/multi-model-checkout';
                            headers['Content-Type'] = 'application/json';
                            body = JSON.stringify({
                                customer_name: 'Acme Enterprise Spa'
                            });
                        } else if (type === 'bulk10') {
                            endpoint = '/api/demo/orders-with-10-items';
                            headers['Content-Type'] = 'application/json';
                            body = JSON.stringify({
                                customer_name: 'Fornitore Elettronica Spa'
                            });
                        }

                        const res = await fetch(endpoint, { method, headers, body });
                        const data = await res.json().catch(() => ({}));

                        if (type === 'bulk10' && data.order_id) {
                            lastCreatedOrderId.value = data.order_id;
                            selectedOrderId.value = data.order_id;
                            lastSimResult.value = `✅ Creato ${data.order_reference} con 10 OrderItem connessi (Nested-5)!`;
                            await fetchOrders();
                        } else if (type === 'multimodel' && data.order_id) {
                            lastCreatedOrderId.value = data.order_id;
                            selectedOrderId.value = data.order_id;
                            lastSimResult.value = `✅ Toccati 3 Modelli (Order #${data.order_id}, Invoice #${data.invoice_id}, User #${data.user_id})!`;
                            await fetchOrders();
                        } else if (type === 'order' && data.order) {
                            lastCreatedOrderId.value = data.order.id;
                            selectedOrderId.value = data.order.id;
                            lastSimResult.value = `✅ Creato ${data.order.reference} (€${data.totale}) con Storyboard!`;
                            await fetchOrders();
                        } else {
                            lastCreatedOrderId.value = null;
                            lastSimResult.value = `Risposta: HTTP ${res.status}`;
                        }

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

                async function openLogDetail(log) {
                    try {
                        const res = await fetch(`/api/logoperations/${log.id}`, { headers: { 'Accept': 'application/json' } });
                        const detail = await res.json();
                        const detailData = detail.data || log;
                        activeModalLog.value = {
                            ...detailData,
                            core_stack: detail.core_stack || detailData.core_stack,
                            full_stack: detail.full_stack || detailData.full_stack,
                            touched_models: detail.touched_models || [],
                        };
                    } catch (e) {
                        activeModalLog.value = log;
                    }
                    expandedModelGroups.value = {};
                    const hasCore = activeModalLog.value.stack_trace && activeModalLog.value.stack_trace.some(f => f.is_core);
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

                // ==========================================
                // STATO & METODI STORYBOARD (FASE 5)
                // ==========================================
                const ordersList = ref([]);
                const selectedOrderId = ref(null);
                const storyboardData = ref({ subject: null, kpis: {}, events: [] });
                const storyboardLoading = ref(false);
                const storyboardCategory = ref('all');
                const storyboardSearch = ref('');
                const storyboardSort = ref('asc');
                const expandedTimelineEvents = ref(new Set());

                async function fetchOrders() {
                    try {
                        const res = await fetch('/api/demo/orders-list');
                        if (res.ok) {
                            ordersList.value = await res.json();
                            if (ordersList.value.length && !selectedOrderId.value) {
                                selectedOrderId.value = ordersList.value[0].id;
                            }
                        }
                    } catch (e) {}
                }

                async function fetchStoryboard() {
                    if (!selectedOrderId.value) {
                        await fetchOrders();
                    }
                    if (!selectedOrderId.value) return;

                    storyboardLoading.value = true;
                    try {
                        const url = new URL('/api/logoperations/storyboard', window.location.origin);
                        url.searchParams.set('subject_type', 'App\\Models\\Order');
                        url.searchParams.set('subject_id', selectedOrderId.value);
                        url.searchParams.set('order', storyboardSort.value);

                        const res = await fetch(url.toString(), {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            storyboardData.value = await res.json();
                            if (storyboardData.value.events?.length) {
                                const lastEvt = storyboardData.value.events[storyboardData.value.events.length - 1];
                                if (lastEvt) expandedTimelineEvents.value.add(lastEvt.id);
                            }
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

                function toggleTimelineEvent(id) {
                    if (expandedTimelineEvents.value.has(id)) {
                        expandedTimelineEvents.value.delete(id);
                    } else {
                        expandedTimelineEvents.value.add(id);
                    }
                }

                const filteredStoryboardEvents = computed(() => {
                    let list = storyboardData.value.events || [];
                    if (storyboardCategory.value !== 'all') {
                        list = list.filter(e => {
                            const cat = e.classification?.category;
                            if (storyboardCategory.value === 'error') return cat === 'error';
                            if (storyboardCategory.value === 'checkpoint') return cat === 'checkpoint';
                            if (storyboardCategory.value === 'create') return cat === 'create';
                            if (storyboardCategory.value === 'update') return cat === 'update';
                            return true;
                        });
                    }
                    if (storyboardSearch.value.trim()) {
                        const q = storyboardSearch.value.toLowerCase().trim();
                        list = list.filter(e => {
                            return (
                                (e.rotta && e.rotta.toLowerCase().includes(q)) ||
                                (e.controllermethod && e.controllermethod.toLowerCase().includes(q)) ||
                                (e.user_label && e.user_label.toLowerCase().includes(q)) ||
                                (e.classification?.title && e.classification.title.toLowerCase().includes(q)) ||
                                (e.error && e.error.toLowerCase().includes(q)) ||
                                (JSON.stringify(e.parametri || {}).toLowerCase().includes(q))
                            );
                        });
                    }
                    return list;
                });

                async function simulateOrderAction(action) {
                    if (!selectedOrderId.value && action !== 'create') return;

                    simulating.value = true;
                    try {
                        let res;
                        if (action === 'view') {
                            res = await fetch(`/api/demo/orders/${selectedOrderId.value}`);
                            lastSimResult.value = `GET /api/demo/orders/${selectedOrderId.value} eseguita (HTTP ${res.status})`;
                        } else if (action === 'update') {
                            const statuses = ['in_preparazione', 'spedito', 'consegnato', 'in_attesa'];
                            const newStatus = statuses[Math.floor(Math.random() * statuses.length)];
                            res = await fetch(`/api/demo/orders/${selectedOrderId.value}`, {
                                method: 'PUT',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                body: JSON.stringify({ status: newStatus, amount: (Math.random() * 200 + 50).toFixed(2) })
                            });
                            lastSimResult.value = `PUT /api/demo/orders/${selectedOrderId.value} -> Stato: ${newStatus}`;
                            await fetchOrders();
                        } else if (action === 'checkpoint') {
                            res = await fetch(`/api/demo/orders/${selectedOrderId.value}/checkpoint`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                body: JSON.stringify({
                                    label: 'Spedizione affidata all\'hub logistico',
                                    payload: { corriere: 'BRT Express', collo_id: 'COLLO-' + Math.floor(Math.random() * 89999 + 10000) }
                                })
                            });
                            lastSimResult.value = `Checkpoint registrato con $order->logStep()!`;
                        } else if (action === 'fail') {
                            res = await fetch(`/api/demo/orders/${selectedOrderId.value}/fail`, {
                                method: 'POST',
                                headers: { 'Accept': 'application/json' }
                            });
                            lastSimResult.value = `Simulato errore HTTP ${res.status} legato all'ordine #${selectedOrderId.value}`;
                        } else if (action === 'create') {
                            res = await fetch(`/api/demo/orders-create`, {
                                method: 'POST',
                                headers: { 'Accept': 'application/json' }
                            });
                            const created = await res.json();
                            if (created.order) {
                                await fetchOrders();
                                selectedOrderId.value = created.order.id;
                                lastSimResult.value = `Creato nuovo ordine ${created.order.reference}`;
                            }
                        }

                        // Ricarica la storyboard dell'ordine e il registro generale
                        await fetchStoryboard();
                        await fetchLogs();
                        await fetchStats();
                    } catch (e) {
                        lastSimResult.value = `Errore simulazione: ${e.message}`;
                    } finally {
                        simulating.value = false;
                    }
                }

                async function openStoryboardForSubject(subjectType, subjectId) {
                    if (activeModalLog.value) {
                        activeModalLog.value = null;
                    }
                    await fetchOrders();
                    selectedOrderId.value = subjectId;
                    currentTab.value = 'storyboard';
                    await fetchStoryboard();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }

                onMounted(() => {
                    fetchLogs();
                    fetchStudioData();
                    fetchOrders().then(() => fetchStoryboard());
                    setInterval(() => {
                        activeSessions.value.forEach(s => { if (s.seconds_left > 0) s.seconds_left--; });
                    }, 1000);
                });

                return {
                    currentTab, logs, routes, classes, activeSessions, usersList, selectedUserId,
                    targetUserId, userDuration, simulating, lastSimResult, routeFilter,
                    filteredRoutes, activeModalLog, stackViewMode, displayedStackTrace, hasVendorFrames, stats,
                    expandedModelGroups,
                    activeQuickFilter, filterStatus, filterUser, filterVerb, filterText,
                    filterSlow, filterUnfinishedTx, filterOnlyErrors, hasActiveFilters,
                    setQuickFilter, onFilterChange, resetAllFilters, debounceFetchLogs,
                    fetchLogs, switchUser, runSimulation, toggleRouteTracking, updateRouteLevel,
                    toggleMethodTracking, startLiveSession, stopLiveSession, openLogDetail,
                    formatDate, formatSeconds,
                    // Paginazione (Fase 8)
                    currentPage, perPage, perPageOptions, pagination, jumpPageNumber, visiblePages,
                    goToPage, onPerPageChange, jumpToPage,
                    // Storyboard exports
                    ordersList, selectedOrderId, storyboardData, storyboardLoading,
                    storyboardCategory, storyboardSearch, storyboardSort, expandedTimelineEvents,
                    fetchOrders, fetchStoryboard, toggleStoryboardSort, toggleTimelineEvent,
                    filteredStoryboardEvents, simulateOrderAction, openStoryboardForSubject,
                    lastCreatedOrderId, exportData
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

            let tipTimer = null;

            function scheduleTip(target) {
                clearTimeout(tipTimer);
                tipTimer = setTimeout(() => showTip(target), 200);
            }

            function cancelTip() {
                clearTimeout(tipTimer);
                hideTip();
            }

            document.addEventListener('mouseover', (e) => {
                const target = e.target.closest ? e.target.closest('[data-tooltip]') : null;
                if (target) {
                    scheduleTip(target);
                }
            }, true);

            document.addEventListener('mouseout', (e) => {
                const target = e.target.closest ? e.target.closest('[data-tooltip]') : null;
                if (target && target === activeEl) {
                    cancelTip();
                }
            }, true);

            document.addEventListener('click', () => {
                cancelTip();
            }, true);
        })();
    </script>
</body>
</html>
