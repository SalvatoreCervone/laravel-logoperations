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
