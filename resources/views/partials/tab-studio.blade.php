        <div v-if="currentTab === 'studio_routes'" class="content-card">
            <div class="content-header" style="flex-direction: column; align-items: stretch; gap: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <div class="content-title">Studio Rotte (Controllo Pagine & API Senza Codice)</div>
                        <span style="font-size: 12px; color: var(--text-muted);">Riconoscimento automatico delle rotte applicative con attivazione del tracciamento e livello di log a 1 click o massivo</span>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); background: var(--surface-primary); border: 1px solid var(--border-subtle); padding: 5px 11px; border-radius: 6px;">
                        <span style="color: var(--text-primary); font-weight: 600;">@{{ filteredRoutes.length }}</span> rotte visibili su <span style="color: var(--text-primary); font-weight: 600;">@{{ routes.length }}</span> totali
                    </div>
                </div>

                <!-- Barra Filtri Studio Rotte (Verbo, Controller, Stato, Ricerca) -->
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <!-- Filtro Verbo HTTP -->
                    <div style="min-width: 130px;">
                        <select v-model="routeVerbFilter" class="filter-input-ctrl">
                            <option value="all">Tutti i verbi</option>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="PATCH">PATCH</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>

                    <!-- Filtro Controller -->
                    <div style="min-width: 190px; max-width: 280px; flex: 1;">
                        <select v-model="routeControllerFilter" class="filter-input-ctrl">
                            <option value="all">Tutti i controller (@{{ uniqueControllers.length }})</option>
                            <option v-for="ctrl in uniqueControllers" :key="ctrl" :value="ctrl">@{{ ctrl }}</option>
                        </select>
                    </div>

                    <!-- Filtro Stato Tracciamento -->
                    <div style="min-width: 150px;">
                        <select v-model="routeTrackedFilter" class="filter-input-ctrl">
                            <option value="all">Tutti gli stati</option>
                            <option value="tracked">Solo monitorate</option>
                            <option value="untracked">Non monitorate</option>
                        </select>
                    </div>

                    <!-- Ricerca testuale URI o Metodo -->
                    <div style="flex: 1; min-width: 180px;">
                        <input v-model="routeFilter" type="text" class="filter-input-ctrl" placeholder="Cerca URI o metodo...">
                    </div>

                    <!-- Pulsante Reset Filtri -->
                    <button v-if="routeFilter || routeVerbFilter !== 'all' || routeControllerFilter !== 'all' || routeTrackedFilter !== 'all'"
                            @click="resetRouteFilters" 
                            class="btn-reset-filters" 
                            title="Azzera filtri"
                            style="white-space: nowrap;">
                        ✕ Reset
                    </button>
                </div>
            </div>

            <!-- BARRA AZIONI MASSIVE (visibile quando ci sono rotte selezionate) -->
            <div v-if="selectedRouteKeys.length > 0" 
                 style="margin: 0 0 16px 0; padding: 12px 16px; background: rgba(99, 102, 241, 0.12); border: 1px solid rgba(99, 102, 241, 0.35); border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-weight: 600; color: #c7d2fe; font-size: 13px;">
                        📌 @{{ selectedRouteKeys.length }} @{{ selectedRouteKeys.length === 1 ? 'rotta selezionata' : 'rotte selezionate' }}
                    </span>
                    <span style="font-size: 11.5px; color: var(--text-muted);">(su @{{ filteredRoutes.length }} filtrate)</span>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <!-- 1. Flagga (Attiva Tracciamento) -->
                    <button @click="bulkSetTracking(true)" class="page-btn" style="background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.4); color: #6ee7b7; font-size: 12px; padding: 6px 12px; font-weight: 500;">
                        ✓ Attiva Tracciamento (@{{ selectedRouteKeys.length }})
                    </button>

                    <!-- 2. Sflagga (Disattiva Tracciamento) -->
                    <button @click="bulkSetTracking(false)" class="page-btn" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.35); color: #fca5a5; font-size: 12px; padding: 6px 12px; font-weight: 500;">
                        ✕ Disattiva Tracciamento
                    </button>

                    <!-- 3. Imposta Livello Massivo (Base, Core, Completo) -->
                    <div style="display: flex; align-items: center; gap: 6px; border-left: 1px solid rgba(255, 255, 255, 0.15); padding-left: 10px; margin-left: 4px;">
                        <span style="font-size: 12px; color: #e2e8f0;">Livello:</span>
                        <select v-model="bulkStackLevel" class="filter-input-ctrl" style="padding: 5px 8px; width: auto; font-size: 12px;">
                            <option value="base">Base</option>
                            <option value="core">Core Stack</option>
                            <option value="full">Completo</option>
                        </select>
                        <button @click="bulkSetStackLevel(bulkStackLevel)" class="page-btn" style="background: var(--primary); color: white; border-color: var(--primary); font-size: 12px; padding: 6px 12px; font-weight: 500;">
                            Applica a Tutti
                        </button>
                    </div>

                    <!-- Deseleziona tutte -->
                    <button @click="selectedRouteKeys = []" class="btn-reset-filters" style="font-size: 12px; padding: 6px 10px;" title="Deseleziona tutte">
                        Deseleziona
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 44px; text-align: center;">
                                <input type="checkbox" 
                                       :checked="isAllSelected" 
                                       :indeterminate.prop="isSomeSelected" 
                                       @change="toggleSelectAll" 
                                       title="Seleziona / deseleziona tutte le rotte filtrate"
                                       style="cursor: pointer; width: 16px; height: 16px; accent-color: var(--primary);">
                            </th>
                            <th style="width: 85px;">Tracciamento</th>
                            <th style="width: 120px;">Metodi</th>
                            <th>URI Rotta</th>
                            <th>Controller & Metodo</th>
                            <th style="width: 150px;">Livello Dettaglio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in filteredRoutes" :key="r.uri + '-' + (r.methods ? r.methods.join('-') : '')"
                            :style="selectedRouteKeys.includes(getRouteKey(r)) ? 'background: rgba(99, 102, 241, 0.08);' : ''">
                            <td style="text-align: center;">
                                <input type="checkbox" 
                                       :value="getRouteKey(r)" 
                                       v-model="selectedRouteKeys" 
                                       style="cursor: pointer; width: 16px; height: 16px; accent-color: var(--primary);">
                            </td>
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
                        <tr v-if="filteredRoutes.length === 0">
                            <td colspan="6" style="padding: 30px; text-align: center; color: var(--text-muted);">
                                Nessuna rotta trovata con i filtri selezionati.
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
                <input v-model="classFilter" type="text" class="filter-input-ctrl" placeholder="Filtra classe o metodo..." style="max-width: 250px;">
            </div>

            <div v-if="filteredClasses.length === 0" style="padding: 30px; text-align: center; color: var(--text-muted);">
                Nessuna classe o funzione trovata corrispondente ai filtri.
            </div>

            <div v-for="cls in filteredClasses" :key="cls.class_name || cls.class" style="margin-bottom: 16px; background: var(--bg-base); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span class="badge" :style="getCategoryBadgeStyle(cls.category)">@{{ cls.category }}</span>
                        <strong style="font-size: 15px; color: var(--text-primary);">@{{ cls.short_name }}</strong>
                        <span class="mono" style="font-size: 12px; color: var(--text-muted);">@{{ cls.class_name || cls.class }}</span>
                        <span v-if="cls.has_traceable_attribute" class="badge" style="background: var(--accent-subtle); color: #c7d2fe; border: 1px solid rgba(99, 102, 241, 0.3);">#[Traceable]</span>
                    </div>
                    <span style="font-size: 12px; color: var(--text-muted);">@{{ (cls.methods || []).length }} @{{ (cls.methods || []).length === 1 ? 'metodo' : 'metodi' }}</span>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <div v-for="m in cls.methods" :key="m.target || m.name" :style="{ background: m.is_tracked ? 'rgba(16, 185, 129, 0.08)' : 'var(--surface-primary)', border: m.is_tracked ? '1px solid rgba(16, 185, 129, 0.4)' : '1px solid var(--border-subtle)', borderRadius: '6px', padding: '6px 12px', display: 'flex', alignItems: 'center', gap: '10px' }">
                        <label class="toggle-switch">
                            <input type="checkbox" :checked="m.is_tracked" @change="toggleMethodTracking(cls.class_name || cls.class, m)">
                            <span class="slider"></span>
                        </label>
                        <div>
                            <span class="mono" :style="{ fontSize: '13px', fontWeight: '600', color: m.is_tracked ? '#34d399' : 'var(--text-primary)' }">@{{ m.name }}()</span>
                            <span v-if="m.return_type && m.return_type !== 'mixed'" class="mono" style="font-size: 11px; color: #a5b4fc; margin-left: 4px;">: @{{ m.return_type }}</span>
                        </div>
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
