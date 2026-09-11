    <!-- Modal Dettaglio Singolo Log -->
    <div v-if="activeLog" class="modal-overlay" v-cloak @click.self="closeDetail">
        <div class="modal-body">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span :class="['badge', getVerbClass(activeLog.verbo)]">@{{ (activeLog.verbo || '').toUpperCase() }}</span>
                    <span :class="['badge', getStatusClass(activeLog.codicehttp)]">@{{ activeLog.codicehttp }}</span>
                    <span class="mono" style="font-weight: 600; font-size: 13.5px; color: var(--text-primary);">@{{ activeLog.rotta }}</span>
                </div>
                <button style="background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer;" @click="closeDetail">✕</button>
            </div>

            <div class="modal-content">
                <div style="display: flex; gap: 8px; margin-bottom: 14px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 8px;">
                    <button :class="['page-btn', { active: modalTab === 'overview' }]" @click="modalTab = 'overview'">Riepilogo & Dati</button>
                    <button :class="['page-btn', { active: modalTab === 'stack' }]" @click="modalTab = 'stack'">
                        Stack Trace (@{{ modalStackView === 'core' ? (activeLog.core_stack ? activeLog.core_stack.length : 0) : (activeLog.stack_trace ? activeLog.stack_trace.length : 0) }})
                    </button>
                </div>

                <!-- Overview -->
                <div v-if="modalTab === 'overview'">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; font-size: 13px;">
                        <div><span style="color: var(--text-subtle);">Utente:</span> <strong style="color: var(--text-primary);">@{{ activeLog.user_label }}</strong></div>
                        <div><span style="color: var(--text-subtle);">IP:</span> <span class="mono">@{{ activeLog.client_ip }}</span></div>
                        <div><span style="color: var(--text-subtle);">Durata:</span> <span class="mono">@{{ activeLog.duration_ms }} ms</span></div>
                        <div><span style="color: var(--text-subtle);">Data:</span> @{{ formatTimestamp(activeLog.dataoperazione) }}</div>
                        <div><span style="color: var(--text-subtle);">Controller:</span> <span style="color: #a5b4fc;">@{{ activeLog.controllermethod || 'N/A' }}</span></div>
                        <div><span style="color: var(--text-subtle);">Transazione:</span> @{{ activeLog.transaction_status || 'Nessuna anomalia' }}</div>
                    </div>

                    <div v-if="activeLog.subject_id" style="background: var(--accent-subtle); border: 1px solid var(--accent); border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--text-primary);">Soggetto Storyboard:</strong> @{{ activeLog.subject_label || (activeLog.subject_type + ' #' + activeLog.subject_id) }}
                        </div>
                        <button class="btn-action" style="background: var(--accent); color: #ffffff;" @click="openStoryboardForSubject(activeLog.subject_type, activeLog.subject_id)">
                            Apri Storyboard
                        </button>
                    </div>

                    <!-- Modelli Coinvolti nell'Operazione (Multi-Subject) -->
                    <div v-if="activeLog.touched_models && activeLog.touched_models.length > 0" style="background: var(--surface-secondary); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary);">
                                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                            </svg>
                            <strong style="color: var(--text-primary); font-size: 12.5px;">Modelli Coinvolti nell'Operazione</strong>
                            <span class="badge" style="background: var(--primary-subtle); color: #93c5fd; border: 1px solid var(--primary-border); font-size: 11px;">@{{ activeLog.touched_models.reduce((sum, g) => sum + g.count, 0) }} entità</span>
                        </div>
                        <div v-for="(group, gIdx) in activeLog.touched_models" :key="gIdx" style="margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span style="font-weight: 600; font-size: 12px; color: var(--text-secondary);">@{{ group.label }}</span>
                                <span style="font-size: 11px; color: var(--text-subtle);">(@{{ group.count }} record)</span>
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                <template v-for="(item, iIdx) in (expandedModelGroups[gIdx] ? group.items : group.items.slice(0, 5))" :key="iIdx">
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; font-size: 11px; border-radius: 4px; cursor: pointer;"
                                          :style="{
                                              background: item.action === 'created' ? 'var(--success-subtle)' : item.action === 'deleted' ? 'var(--danger-subtle)' : 'var(--warning-subtle)',
                                              border: '1px solid ' + (item.action === 'created' ? 'var(--success-border)' : item.action === 'deleted' ? 'var(--danger-border)' : 'var(--warning-border)'),
                                              color: item.action === 'created' ? 'var(--success-text)' : item.action === 'deleted' ? 'var(--danger-text)' : 'var(--warning-text)'
                                          }"
                                          @click="openStoryboardForSubject(group.type, item.id)">
                                        <span style="font-weight: 600;">#@{{ item.id }}</span>
                                        <span style="opacity: 0.7; font-size: 10px; text-transform: uppercase;">@{{ item.action }}</span>
                                    </span>
                                </template>
                                <button v-if="!expandedModelGroups[gIdx] && group.items.length > 5"
                                        @click="expandedModelGroups[gIdx] = true"
                                        style="background: var(--surface-tertiary); border: 1px solid var(--border-strong); color: var(--primary); font-size: 11px; padding: 2px 10px; border-radius: 4px; cursor: pointer;">
                                    Mostra tutti i @{{ group.count }} record...
                                </button>
                                <button v-if="expandedModelGroups[gIdx] && group.items.length > 5"
                                        @click="expandedModelGroups[gIdx] = false"
                                        style="background: var(--surface-tertiary); border: 1px solid var(--border-strong); color: var(--text-muted); font-size: 11px; padding: 2px 10px; border-radius: 4px; cursor: pointer;">
                                    Mostra solo i primi 5
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeLog.error" style="background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; color: var(--danger-text);">
                        <strong>Errore:</strong>
                        <pre style="margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; white-space: pre-wrap;">@{{ activeLog.error }}</pre>
                    </div>

                    <div>
                        <strong style="font-size: 12.5px; color: var(--text-secondary);">Parametri Richiesta:</strong>
                        <pre style="background: var(--bg-base); border: 1px solid var(--border-subtle); border-radius: 6px; padding: 10px; margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 11.5px; max-height: 220px; overflow: auto; color: var(--text-muted);">@{{ JSON.stringify(activeLog.parametri, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Stack Trace -->
                <div v-if="modalTab === 'stack'">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 12px; color: var(--text-subtle);">Visualizzazione:</span>
                        <div style="display: flex; gap: 6px;">
                            <button :class="['page-btn', { active: modalStackView === 'core' }]" @click="modalStackView = 'core'">Solo Codice Core</button>
                            <button :class="['page-btn', { active: modalStackView === 'full' }]" @click="modalStackView = 'full'">Stack Completo</button>
                        </div>
                    </div>

                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-subtle); border-radius: 6px;">
                        <div v-for="(frame, fIdx) in displayedStackFrames" :key="fIdx" style="padding: 8px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.035); font-size: 12px;">
                            <span style="color: var(--text-subtle); font-family: 'JetBrains Mono', monospace;">#@{{ fIdx + 1 }}</span>
                            <span style="color: var(--text-primary); font-weight: 500; margin: 0 6px;">@{{ frame.class ? frame.class + '::' + frame.function : frame.function }}</span>
                            <span v-if="frame.is_core" class="badge" style="background: var(--primary-subtle); color: #93c5fd; border: 1px solid var(--primary-border);">CORE</span>
                            <div style="color: var(--text-subtle); font-size: 11px; margin-top: 2px; font-family: 'JetBrains Mono', monospace;">
                                @{{ frame.file }}:@{{ frame.line }}
                            </div>
                        </div>
                        <div v-if="displayedStackFrames.length === 0" style="color: var(--text-muted); padding: 12px; text-align: center;">
                            Nessun frame disponibile per questa visualizzazione.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
