<!-- Toast Notification -->
<div class="toast-container">
    <transition name="toast-fade">
        <div v-if="toast.show" :class="['toast-notification', 'toast-' + toast.type]">
            <span v-if="toast.type === 'success'" style="font-size: 16px; font-weight: bold;">✓</span>
            <span v-else-if="toast.type === 'error'" style="font-size: 16px; font-weight: bold;">✕</span>
            <span v-else style="font-size: 16px; font-weight: bold;">ℹ</span>
            <span>@{{ toast.message }}</span>
        </div>
    </transition>
</div>

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
