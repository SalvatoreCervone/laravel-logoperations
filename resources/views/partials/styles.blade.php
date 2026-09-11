    <style>
        [v-cloak] {
            display: none !important;
        }

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
        /* Toast Notification */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .toast-notification {
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            max-width: 450px;
            word-break: break-word;
        }
        .toast-success {
            background: rgba(16, 185, 129, 0.95);
            border-color: rgba(52, 211, 153, 0.4);
        }
        .toast-error {
            background: rgba(239, 68, 68, 0.95);
            border-color: rgba(248, 113, 113, 0.4);
        }
        .toast-info {
            background: rgba(59, 130, 246, 0.95);
            border-color: rgba(96, 165, 250, 0.4);
        }
        .toast-fade-enter-active, .toast-fade-leave-active {
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .toast-fade-enter-from {
            opacity: 0;
            transform: translateY(16px) scale(0.95);
        }
        .toast-fade-leave-to {
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
        }
    </style>
