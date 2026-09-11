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
