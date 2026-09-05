<script setup>
/**
 * LogDetailModal.vue
 *
 * Modal di dettaglio per una singola operazione loggata.
 * Include:
 * - Informazioni generali (utente, rotta, verbo, status, IP, durata)
 * - Toggle Stack a 2 Livelli: Core (solo app/) vs Full (tutto)
 * - Visualizzatore JSON interattivo per i parametri
 * - Stack trace errore con formattazione e copia
 * - Badge avviso per transazioni pendenti
 * - Step e trace personalizzati (LogOperations::step() / trace())
 */
import { ref, computed } from 'vue'

const props = defineProps({
  log: { type: Object, required: true },
})

const emit = defineEmits(['close'])

// Stack view toggle: 'core' o 'full'
const stackView = ref('core')

// Tab attiva: 'info', 'params', 'error', 'stack', 'custom'
const activeTab = ref('info')

// Copia feedback
const copyFeedback = ref('')

/* ------------------------------------------------------------------ */
/*  Computed                                                           */
/* ------------------------------------------------------------------ */

const logData = computed(() => props.log.data || props.log)

const coreStack = computed(() => props.log.core_stack || [])
const fullStack = computed(() => props.log.full_stack || [])

const currentStack = computed(() =>
  stackView.value === 'core' ? coreStack.value : fullStack.value
)

const hasParams = computed(() => {
  const p = logData.value.parametri
  return p && (typeof p === 'object' ? Object.keys(p).length > 0 : !!p)
})

const hasError = computed(() => !!logData.value.error)
const hasStack = computed(() => coreStack.value.length > 0 || fullStack.value.length > 0)
const hasCustom = computed(() => {
  const ct = logData.value.custom_traces
  return ct && (ct.steps?.length || ct.traces?.length || ct.db_callers?.length)
})

const parametriFormatted = computed(() => {
  const p = logData.value.parametri
  if (!p) return ''
  if (typeof p === 'string') {
    try { return JSON.stringify(JSON.parse(p), null, 2) }
    catch { return p }
  }
  return JSON.stringify(p, null, 2)
})

const verbColor = computed(() => {
  const v = (logData.value.verbo || '').toLowerCase()
  const map = {
    get: '#10b981', post: '#3b82f6', put: '#f59e0b',
    patch: '#8b5cf6', delete: '#f43f5e',
  }
  return map[v] || '#9ca3af'
})

const statusColor = computed(() => {
  const c = logData.value.codicehttp
  if (c >= 500) return '#ef4444'
  if (c >= 400) return '#f59e0b'
  if (c >= 300) return '#6366f1'
  return '#10b981'
})

/* ------------------------------------------------------------------ */
/*  Methods                                                            */
/* ------------------------------------------------------------------ */

async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text)
    copyFeedback.value = 'Copiato!'
    setTimeout(() => copyFeedback.value = '', 2000)
  } catch {
    copyFeedback.value = 'Errore copia'
    setTimeout(() => copyFeedback.value = '', 2000)
  }
}

function formatDuration(ms) {
  if (ms == null) return '—'
  if (ms < 1000) return ms + ' ms'
  return (ms / 1000).toFixed(2) + ' s'
}

function formatDate(isoStr) {
  if (!isoStr) return '—'
  return new Date(isoStr).toLocaleString('it-IT')
}
</script>

<template>
<!-- BACKDROP -->
<div class="modal-backdrop" @click.self="$emit('close')">
  <div class="modal-container">
    <!-- MODAL HEADER -->
    <div class="modal-header">
      <div class="modal-header__left">
        <span class="modal-verb" :style="{ background: verbColor }">
          {{ (logData.verbo || '').toUpperCase() }}
        </span>
        <span class="modal-status" :style="{ background: statusColor }">
          {{ logData.codicehttp }}
        </span>
        <span class="modal-route">{{ logData.rotta }}</span>
      </div>
      <button class="modal-close" @click="$emit('close')">✕</button>
    </div>

    <!-- TABS -->
    <div class="modal-tabs">
      <button
        :class="['modal-tab', { active: activeTab === 'info' }]"
        @click="activeTab = 'info'"
      >
        ℹ️ Info
      </button>
      <button
        v-if="hasParams"
        :class="['modal-tab', { active: activeTab === 'params' }]"
        @click="activeTab = 'params'"
      >
        📋 Parametri
      </button>
      <button
        v-if="hasError"
        :class="['modal-tab', { active: activeTab === 'error' }]"
        @click="activeTab = 'error'"
      >
        🐛 Errore
      </button>
      <button
        v-if="hasStack"
        :class="['modal-tab', { active: activeTab === 'stack' }]"
        @click="activeTab = 'stack'"
      >
        📚 Stack ({{ coreStack.length }}/{{ fullStack.length }})
      </button>
      <button
        v-if="hasCustom"
        :class="['modal-tab', { active: activeTab === 'custom' }]"
        @click="activeTab = 'custom'"
      >
        🏷️ Traces
      </button>
    </div>

    <!-- TAB CONTENT -->
    <div class="modal-content">

      <!-- INFO TAB -->
      <div v-if="activeTab === 'info'" class="tab-info">
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Utente</span>
            <span class="info-value">{{ logData.user_label || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Tipo Utente</span>
            <span class="info-value info-value--mono">{{ logData.user_type || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Rotta</span>
            <span class="info-value info-value--mono">{{ logData.rotta }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Controller</span>
            <span class="info-value info-value--mono">{{ logData.controllermethod || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">IP Client</span>
            <span class="info-value info-value--mono">{{ logData.client_ip || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Durata</span>
            <span class="info-value">{{ formatDuration(logData.duration_ms) }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Data Operazione</span>
            <span class="info-value">{{ formatDate(logData.dataoperazione) }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Applicazione</span>
            <span class="info-value">{{ logData.nomeapplicazione || '—' }}</span>
          </div>
        </div>

        <!-- TRANSACTION WARNING -->
        <div v-if="logData.transaction_status" class="transaction-alert">
          <span class="transaction-alert__icon">⚡</span>
          <div>
            <strong>Transazione Pendente Rilevata</strong>
            <div class="transaction-alert__details">
              Livello: {{ logData.transaction_level }} |
              Azione: <code>{{ logData.transaction_status }}</code>
            </div>
          </div>
        </div>
      </div>

      <!-- PARAMS TAB -->
      <div v-if="activeTab === 'params'" class="tab-params">
        <div class="code-header">
          <span>Parametri della Richiesta</span>
          <button class="copy-btn" @click="copyToClipboard(parametriFormatted)">
            {{ copyFeedback || '📋 Copia' }}
          </button>
        </div>
        <pre class="code-block"><code>{{ parametriFormatted }}</code></pre>
      </div>

      <!-- ERROR TAB -->
      <div v-if="activeTab === 'error'" class="tab-error">
        <div class="code-header code-header--error">
          <span>Errore / Stack Trace Eccezione</span>
          <button class="copy-btn" @click="copyToClipboard(logData.error)">
            {{ copyFeedback || '📋 Copia Errore' }}
          </button>
        </div>
        <pre class="code-block code-block--error"><code>{{ logData.error }}</code></pre>
      </div>

      <!-- STACK TAB -->
      <div v-if="activeTab === 'stack'" class="tab-stack">
        <!-- STACK LEVEL TOGGLE -->
        <div class="stack-toggle">
          <button
            :class="['stack-toggle__btn', { active: stackView === 'core' }]"
            @click="stackView = 'core'"
          >
            🎯 Solo Codice Core ({{ coreStack.length }})
          </button>
          <button
            :class="['stack-toggle__btn', { active: stackView === 'full' }]"
            @click="stackView = 'full'"
          >
            🔍 Stack Completo ({{ fullStack.length }})
          </button>
        </div>

        <div class="stack-hint" v-if="stackView === 'core'">
          Visualizzazione delle sole funzioni del codice proprietario dell'applicazione
        </div>
        <div class="stack-hint" v-else>
          Stack completo incluse le chiamate interne di Laravel, Symfony e vendor
        </div>

        <!-- STACK FRAMES -->
        <div class="stack-frames">
          <div
            v-for="(frame, idx) in currentStack"
            :key="idx"
            :class="['stack-frame', { 'stack-frame--core': frame.is_core }]"
          >
            <div class="stack-frame__order">{{ frame.order }}</div>
            <div class="stack-frame__content">
              <div class="stack-frame__method">
                <span v-if="frame.class" class="stack-frame__class">{{ frame.class }}</span>
                <span v-if="frame.type" class="stack-frame__type">{{ frame.type }}</span>
                <span class="stack-frame__function">{{ frame.function || '—' }}</span>
              </div>
              <div class="stack-frame__file">
                {{ frame.file || '—' }}
                <span v-if="frame.line" class="stack-frame__line">:{{ frame.line }}</span>
              </div>
            </div>
            <span v-if="frame.is_core" class="stack-frame__badge">CORE</span>
          </div>

          <div v-if="currentStack.length === 0" class="stack-empty">
            Nessun frame disponibile per questo livello
          </div>
        </div>
      </div>

      <!-- CUSTOM TRACES TAB -->
      <div v-if="activeTab === 'custom'" class="tab-custom">
        <!-- STEPS -->
        <div v-if="logData.custom_traces?.steps?.length" class="custom-section">
          <h4>🏷️ Step Personalizzati</h4>
          <div
            v-for="(step, idx) in logData.custom_traces.steps"
            :key="'step-' + idx"
            class="custom-item"
          >
            <div class="custom-item__label">{{ step.label }}</div>
            <div class="custom-item__meta">
              <span v-if="step.class">{{ step.class }}{{ step.function ? '::' + step.function + '()' : '' }}</span>
              <span v-if="step.file">{{ step.file }}:{{ step.line }}</span>
            </div>
          </div>
        </div>

        <!-- TRACED FUNCTIONS -->
        <div v-if="logData.custom_traces?.traces?.length" class="custom-section">
          <h4>⏱️ Funzioni Tracciate</h4>
          <div
            v-for="(trace, idx) in logData.custom_traces.traces"
            :key="'trace-' + idx"
            class="custom-item"
          >
            <div class="custom-item__label">
              {{ trace.label }}
              <span class="custom-item__duration">{{ trace.duration_ms }} ms</span>
              <span v-if="trace.error" class="custom-item__error">❌ {{ trace.error }}</span>
            </div>
            <div class="custom-item__meta">
              <span v-if="trace.class">{{ trace.class }}::{{ trace.function }}()</span>
            </div>
          </div>
        </div>

        <!-- DB CALLERS -->
        <div v-if="logData.custom_traces?.db_callers?.length" class="custom-section">
          <h4>🗄️ Query DB (Origine nel Codice)</h4>
          <div
            v-for="(db, idx) in logData.custom_traces.db_callers"
            :key="'db-' + idx"
            class="custom-item custom-item--db"
          >
            <div class="custom-item__label custom-item__sql">{{ db.sql }}</div>
            <div class="custom-item__meta">
              <span>{{ db.time_ms }} ms</span>
              <span v-if="db.caller">
                → {{ db.caller.class ? db.caller.class + '::' : '' }}{{ db.caller.function }}()
                <em>({{ db.caller.file }}:{{ db.caller.line }})</em>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<style scoped>
/* ================================================================== */
/*  Modal                                                              */
/* ================================================================== */

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 24px;
  animation: fadeIn 0.2s ease;
}

.modal-container {
  background: var(--lo-surface, #1e293b);
  border: 1px solid var(--lo-border, #334155);
  border-radius: var(--lo-radius-lg, 14px);
  width: 100%;
  max-width: 900px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
  animation: modalSlide 0.3s ease;
}

@keyframes modalSlide {
  from { opacity: 0; transform: translateY(20px) scale(0.97); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

/* ------------------------------------------------------------------ */
/*  Header                                                             */
/* ------------------------------------------------------------------ */

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--lo-border, #334155);
}

.modal-header__left {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow: hidden;
}

.modal-verb, .modal-status {
  padding: 3px 10px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 700;
  color: white;
  white-space: nowrap;
}

.modal-route {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.8125rem;
  color: var(--lo-text-muted, #94a3b8);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.modal-close {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: 1px solid var(--lo-border, #334155);
  border-radius: 8px;
  color: var(--lo-text-muted, #94a3b8);
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.2s;
}
.modal-close:hover { background: #ef4444; color: white; border-color: #ef4444; }

/* ------------------------------------------------------------------ */
/*  Tabs                                                               */
/* ------------------------------------------------------------------ */

.modal-tabs {
  display: flex;
  gap: 4px;
  padding: 8px 20px;
  border-bottom: 1px solid var(--lo-border, #334155);
  overflow-x: auto;
}

.modal-tab {
  padding: 8px 14px;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: var(--lo-text-dim, #64748b);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  font-family: inherit;
}
.modal-tab:hover { color: var(--lo-text, #f1f5f9); background: var(--lo-surface-2, #334155); }
.modal-tab.active {
  background: var(--lo-primary, #6366f1);
  color: white;
}

/* ------------------------------------------------------------------ */
/*  Content                                                            */
/* ------------------------------------------------------------------ */

.modal-content {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
}

/* ------------------------------------------------------------------ */
/*  Info Tab                                                           */
/* ------------------------------------------------------------------ */

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--lo-text-dim, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-value {
  font-size: 0.875rem;
  color: var(--lo-text, #f1f5f9);
  font-weight: 500;
}

.info-value--mono {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.8125rem;
  word-break: break-all;
}

/* ------------------------------------------------------------------ */
/*  Transaction Alert                                                  */
/* ------------------------------------------------------------------ */

.transaction-alert {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: 20px;
  padding: 14px 16px;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 8px;
  color: #fbbf24;
  font-size: 0.8125rem;
}

.transaction-alert__icon { font-size: 1.25rem; }

.transaction-alert__details {
  margin-top: 4px;
  font-size: 0.75rem;
  color: #d97706;
}

.transaction-alert__details code {
  background: rgba(245, 158, 11, 0.15);
  padding: 1px 6px;
  border-radius: 3px;
}

/* ------------------------------------------------------------------ */
/*  Code Blocks                                                        */
/* ------------------------------------------------------------------ */

.code-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--lo-text-muted, #94a3b8);
}

.code-header--error { color: #fca5a5; }

.copy-btn {
  padding: 4px 10px;
  background: var(--lo-surface-2, #334155);
  border: 1px solid var(--lo-border, #334155);
  border-radius: 4px;
  color: var(--lo-text-muted, #94a3b8);
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}
.copy-btn:hover { border-color: var(--lo-primary, #6366f1); color: var(--lo-primary, #6366f1); }

.code-block {
  background: var(--lo-bg, #0f172a);
  border: 1px solid var(--lo-border, #334155);
  border-radius: 8px;
  padding: 16px;
  overflow: auto;
  max-height: 400px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.75rem;
  line-height: 1.6;
  color: #a5f3fc;
  margin: 0;
  white-space: pre-wrap;
  word-break: break-all;
}

.code-block--error { color: #fca5a5; }

/* ------------------------------------------------------------------ */
/*  Stack Tab                                                          */
/* ------------------------------------------------------------------ */

.stack-toggle {
  display: flex;
  gap: 4px;
  margin-bottom: 12px;
  background: var(--lo-bg, #0f172a);
  padding: 4px;
  border-radius: 8px;
}

.stack-toggle__btn {
  flex: 1;
  padding: 8px 14px;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: var(--lo-text-dim, #64748b);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}
.stack-toggle__btn:hover { color: var(--lo-text, #f1f5f9); }
.stack-toggle__btn.active {
  background: var(--lo-primary, #6366f1);
  color: white;
}

.stack-hint {
  font-size: 0.75rem;
  color: var(--lo-text-dim, #64748b);
  margin-bottom: 12px;
  font-style: italic;
}

.stack-frames {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stack-frame {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 12px;
  background: var(--lo-bg, #0f172a);
  border-radius: 6px;
  border-left: 3px solid var(--lo-border, #334155);
  transition: all 0.15s;
}
.stack-frame:hover { background: var(--lo-surface-2, #334155); }

.stack-frame--core {
  border-left-color: var(--lo-primary, #6366f1);
  background: rgba(99, 102, 241, 0.06);
}

.stack-frame__order {
  min-width: 24px;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--lo-text-dim, #64748b);
  text-align: right;
  padding-top: 2px;
}

.stack-frame__content { flex: 1; overflow: hidden; }

.stack-frame__method {
  font-size: 0.8125rem;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stack-frame__class {
  color: #a78bfa;
}

.stack-frame__type { color: var(--lo-text-dim, #64748b); }

.stack-frame__function { color: #67e8f9; }

.stack-frame__file {
  font-size: 0.6875rem;
  color: var(--lo-text-dim, #64748b);
  font-family: 'JetBrains Mono', monospace;
  margin-top: 2px;
}

.stack-frame__line {
  color: #fbbf24;
  font-weight: 600;
}

.stack-frame__badge {
  padding: 2px 6px;
  background: var(--lo-primary, #6366f1);
  color: white;
  border-radius: 3px;
  font-size: 0.5625rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  white-space: nowrap;
}

.stack-empty {
  text-align: center;
  padding: 24px;
  color: var(--lo-text-dim, #64748b);
  font-size: 0.875rem;
}

/* ------------------------------------------------------------------ */
/*  Custom Traces Tab                                                  */
/* ------------------------------------------------------------------ */

.custom-section {
  margin-bottom: 20px;
}

.custom-section h4 {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--lo-text, #f1f5f9);
  margin: 0 0 10px;
}

.custom-item {
  padding: 8px 12px;
  background: var(--lo-bg, #0f172a);
  border-radius: 6px;
  margin-bottom: 4px;
}

.custom-item--db {
  border-left: 3px solid #3b82f6;
}

.custom-item__label {
  font-size: 0.8125rem;
  color: var(--lo-text, #f1f5f9);
  font-weight: 500;
}

.custom-item__sql {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.75rem;
  color: #67e8f9;
  word-break: break-all;
}

.custom-item__duration {
  font-size: 0.75rem;
  color: var(--lo-text-dim, #64748b);
  margin-left: 8px;
}

.custom-item__error {
  font-size: 0.75rem;
  color: #ef4444;
  margin-left: 8px;
}

.custom-item__meta {
  font-size: 0.6875rem;
  color: var(--lo-text-dim, #64748b);
  margin-top: 4px;
  font-family: 'JetBrains Mono', monospace;
}

.custom-item__meta em {
  color: var(--lo-text-dim, #64748b);
  font-style: normal;
}

/* ------------------------------------------------------------------ */
/*  Animations                                                         */
/* ------------------------------------------------------------------ */

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* ------------------------------------------------------------------ */
/*  Responsive                                                         */
/* ------------------------------------------------------------------ */

@media (max-width: 640px) {
  .modal-container { max-width: 100%; max-height: 95vh; }
  .modal-header__left { flex-wrap: wrap; }
  .info-grid { grid-template-columns: 1fr; }
  .stack-toggle { flex-direction: column; }
}
</style>
