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
                        <option value="" v-if="subjectsList.length > 0">-- Seleziona soggetto recente (@{{ subjectsList.length }} rilevati) --</option>
                        <option value="" v-else>-- Nessun soggetto nei log (inserisci manualmente a destra) --</option>
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
                        <span v-if="event.subject_action === 'created' || event.classification?.category === 'create'">+</span>
                        <span v-else-if="event.subject_action === 'deleted' || event.classification?.category === 'delete'">−</span>
                        <span v-else-if="event.classification?.category === 'error'">✕</span>
                        <span v-else-if="event.classification?.category === 'checkpoint'">🚩</span>
                        <span v-else>●</span>
                    </div>

                    <!-- Header dell'evento: Metodo, Azione, Rotta, Status, Durata e Data -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <!-- Verbo HTTP -->
                            <span :class="['badge', getVerbClass(event.verbo || event.verb)]" style="font-weight: 700; font-size: 11px; text-transform: uppercase;">
                                @{{ (event.verbo || event.verb || 'GET').toUpperCase() }}
                            </span>

                            <!-- Azione / Categoria -->
                            <span class="badge" :style="{ background: (event.classification?.badge_color || '#3b82f6') + '22', color: event.classification?.badge_color || '#93c5fd', border: '1px solid ' + (event.classification?.badge_color || '#3b82f6') + '55', fontWeight: '600', fontSize: '11.5px' }">
                                @{{ event.classification?.label || event.classification?.badge_label || 'Operazione' }}
                            </span>

                            <!-- Endpoint / Rotta -->
                            <span class="mono" style="font-weight: 600; color: var(--text-primary); font-size: 13.5px; background: var(--surface-secondary); padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border-subtle);">
                                @{{ event.rotta || event.route || '—' }}
                            </span>

                            <!-- Status HTTP -->
                            <span class="badge" :class="getStatusClass(event.codicehttp || event.status_code)" style="font-weight: 700; font-size: 11px;">
                                HTTP @{{ event.codicehttp || event.status_code }}
                            </span>

                            <!-- Durata -->
                            <span v-if="event.duration_ms != null" style="font-size: 11.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                                ⏱️ @{{ event.duration_ms }} ms
                            </span>
                        </div>

                        <!-- Data & Ora e Pulsante Dettaglio -->
                        <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--text-subtle); font-family: 'JetBrains Mono', monospace;">
                            <span style="color: var(--text-secondary); font-weight: 500;">@{{ event.time_human || formatTimestamp(event.dataoperazione) }}</span>
                            <span style="opacity: 0.6; font-size: 11px;">(@{{ event.time_formatted || event.dataoperazione }})</span>
                            <button class="btn-action" style="padding: 3px 10px; font-size: 11px; background: var(--surface-secondary); border: 1px solid var(--border-strong); color: var(--text-primary); cursor: pointer; border-radius: 4px;" @click="openDetail(event)" title="Visualizza tutti i dettagli del log">
                                🔍 Dettagli Log #@{{ event.id }}
                            </button>
                        </div>
                    </div>

                    <!-- Banner Contestuale: Soggetto Primario vs Entità Correlata -->
                    <div style="margin-bottom: 10px; padding: 7px 12px; border-radius: 6px; font-size: 12px; display: flex; align-items: center; gap: 8px;"
                         :style="{
                             background: event.is_primary_subject ? 'var(--primary-subtle)' : 'var(--surface-secondary)',
                             border: '1px solid ' + (event.is_primary_subject ? 'var(--primary-border)' : 'var(--border-subtle)'),
                             color: event.is_primary_subject ? '#93c5fd' : 'var(--text-secondary)'
                         }">
                        <span v-if="event.is_primary_subject">
                            🎯 <strong>Soggetto Primario:</strong> L'operazione è stata eseguita direttamente su questo record (azione: <strong>@{{ event.subject_action || 'operazione' }}</strong>).
                        </span>
                        <span v-else>
                            🔗 <strong>Entità Correlata:</strong> Questo record è stato coinvolto (azione: <strong>@{{ event.subject_action || 'aggiornato' }}</strong>) durante un'operazione su <strong>@{{ event.primary_subject_label || event.subject_label || (event.subject_type + ' #' + event.subject_id) }}</strong>.
                        </span>
                    </div>

                    <!-- Metadati Sintetici: Autore, IP e Controller/Handler -->
                    <div style="display: flex; gap: 18px; font-size: 12px; color: var(--text-muted); margin-bottom: 8px; flex-wrap: wrap;">
                        <div><strong style="color: var(--text-secondary);">👤 Autore:</strong> @{{ event.user_label || event.user?.name || event.user?.email || 'Anonimo / Ospite' }}</div>
                        <div><strong style="color: var(--text-secondary);">🌐 IP Client:</strong> @{{ event.client_ip || event.ip_address || '—' }}</div>
                        <div v-if="event.controllermethod"><strong style="color: var(--text-secondary);">⚙️ Controller:</strong> <span class="mono" style="font-size: 11.5px; color: #a5b4fc;">@{{ event.controllermethod }}</span></div>
                    </div>

                    <!-- Modelli Coinvolti nell'Operazione (Multi-Subject) -->
                    <div v-if="event.touched_models && event.touched_models.length > 0" style="background: var(--surface-secondary); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 8px 12px; margin-bottom: 8px;">
                        <div style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                            🧩 Entità modificate in questa operazione (@{{ event.touched_models.reduce((s, g) => s + g.count, 0) }} totali):
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                            <template v-for="g in event.touched_models" :key="g.type">
                                <span v-for="it in g.items" :key="it.id" 
                                      style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; font-size: 11px; border-radius: 4px; cursor: pointer;"
                                      :style="{
                                          background: it.action === 'created' ? 'var(--success-subtle)' : it.action === 'deleted' ? 'var(--danger-subtle)' : 'var(--warning-subtle)',
                                          border: '1px solid ' + (it.action === 'created' ? 'var(--success-border)' : it.action === 'deleted' ? 'var(--danger-border)' : 'var(--warning-border)'),
                                          color: it.action === 'created' ? 'var(--success-text)' : it.action === 'deleted' ? 'var(--danger-text)' : 'var(--warning-text)'
                                      }"
                                      @click="openStoryboardForSubject(g.type, it.id)"
                                      :title="'Apri Storyboard di ' + g.label + ' #' + it.id">
                                    <strong>@{{ g.label }} #@{{ it.id }}</strong>
                                    <span style="opacity: 0.7; font-size: 9.5px; text-transform: uppercase;">@{{ it.action }}</span>
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Steps ($model->logStep) -->
                    <div v-if="event.custom_traces?.steps?.length" style="background: var(--bg-base); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 10px; margin-top: 8px;">
                        <div style="font-size: 11.5px; font-weight: 600; color: #38bdf8; margin-bottom: 6px;">🚩 Passaggi Applicativi Registrati ($model->logStep):</div>
                        <div v-for="(st, sI) in event.custom_traces.steps" :key="sI" style="font-size: 12px; color: var(--text-secondary); margin-bottom: 4px;">
                            • <strong>@{{ st.label }}</strong>
                            <pre v-if="st.context && Object.keys(st.context).length" style="background: var(--surface-primary); padding: 6px; border-radius: 4px; font-size: 11px; margin-top: 4px; color: var(--text-muted); border: 1px solid var(--border-subtle);">@{{ JSON.stringify(st.context, null, 2) }}</pre>
                        </div>
                    </div>

                    <!-- Parametri Inviati (Accordion) -->
                    <div v-if="event.parametri && Object.keys(event.parametri).length" style="margin-top: 8px;">
                        <details style="background: var(--surface-secondary); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 6px 10px; font-size: 12px;">
                            <summary style="cursor: pointer; color: var(--text-secondary); font-weight: 500;">
                                📦 Parametri / Payload Inviati
                            </summary>
                            <pre style="margin-top: 8px; background: var(--bg-base); padding: 8px; border-radius: 4px; font-size: 11.5px; font-family: 'JetBrains Mono', monospace; color: var(--text-secondary); overflow-x: auto; max-height: 200px;">@{{ JSON.stringify(event.parametri, null, 2) }}</pre>
                        </details>
                    </div>

                    <!-- Error Alert -->
                    <div v-if="event.error" style="background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: 6px; padding: 8px 12px; margin-top: 8px; color: var(--danger-text); font-size: 12px;">
                        <strong>Errore:</strong> @{{ event.error }}
                    </div>
                </div>
            </div>

            <div v-else-if="!storyboardLoading" style="text-align: center; padding: 36px; color: var(--text-muted);">
                Nessun evento Storyboard trovato per il modello selezionato. Seleziona un modello in alto per visualizzare la cronologia.
            </div>
        </div>

