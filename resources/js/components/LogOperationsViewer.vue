<script setup>
/**
 * LogOperationsViewer.vue
 *
 * Componente principale Vue 3 per la visualizzazione e ricerca
 * dei log operazioni. Design moderno, responsive, con KPI bar,
 * filtri rapidi, query builder avanzato e dettaglio modale.
 *
 * Props:
 *   apiBase    - URL base delle API (default: '/api/logoperations')
 *   perPage    - Elementi per pagina (default: 20)
 */
import { ref, reactive, computed, onMounted, watch } from 'vue'
import LogQueryBuilder from './LogQueryBuilder.vue'
import LogDetailModal from './LogDetailModal.vue'
import LogStatsBar from './LogStatsBar.vue'

const props = defineProps({
  apiBase: { type: String, default: '/api/logoperations' },
  perPage: { type: Number, default: 20 },
})

/* ------------------------------------------------------------------ */
/*  Stato                                                              */
/* ------------------------------------------------------------------ */

const logs = ref({ data: [], current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const showFilters = ref(true)
const showQueryBuilder = ref(false)
const selectedLog = ref(null)
const showDetail = ref(false)

// Statistiche KPI
const stats = ref({
  total_requests: 0,
  total_errors: 0,
  error_rate: 0,
  avg_duration_ms: null,
  pending_transactions: 0,
  top_errors: [],
})

// Metadati per i filtri
const httpCodes = ref([])
const verbs = ref([])
const applications = ref([])

// Filtri rapidi attivi
const quickFilter = ref('today')

// Filtri diretti
const filters = reactive({
  user: '',
  verb: [],
  status_codes: [],
  date_from: '',
  date_to: '',
  ip: '',
  controller: '',
  app: '',
  text: '',
  has_error: false,
  has_unfinished_transaction: false,
})

// Gruppi di ricerca avanzata (retrocompatibilità)
const groups = ref([createEmptyGroup()])

// Cancellazione richieste in volo
let abortController = null

/* ------------------------------------------------------------------ */
/*  Computed                                                           */
/* ------------------------------------------------------------------ */

const verbColors = {
  get: 'bg-emerald-500',
  post: 'bg-blue-500',
  put: 'bg-amber-500',
  patch: 'bg-violet-500',
  delete: 'bg-rose-500',
  options: 'bg-gray-400',
  head: 'bg-gray-400',
}

const statusClass = (code) => {
  if (code >= 500) return 'bg-rose-600 text-white'
  if (code >= 400) return 'bg-amber-500 text-white'
  if (code >= 300) return 'bg-indigo-500 text-white'
  return 'bg-emerald-500 text-white'
}

const statusRowClass = (code) => {
  if (code >= 500) return 'log-row--error-500'
  if (code >= 400) return 'log-row--error-400'
  if (code >= 300) return 'log-row--redirect'
  return ''
}

const hasActiveFilters = computed(() => {
  return filters.user || filters.verb.length || filters.status_codes.length
    || filters.date_from || filters.date_to || filters.ip
    || filters.controller || filters.app || filters.text
    || filters.has_error || filters.has_unfinished_transaction
})

/* ------------------------------------------------------------------ */
/*  Funzioni Gruppi                                                    */
/* ------------------------------------------------------------------ */

function createEmptyGroup() {
  return {
    operatoreGruppo: 'AND',
    operatoreCampi: 'OR',
    search: '',
    ip: '',
    controller: '',
    codicehttp: [],
    data_da: '',
    data_a: '',
  }
}

function addGroup() {
  groups.value.push(createEmptyGroup())
}

function removeGroup(index) {
  if (groups.value.length > 1) {
    groups.value.splice(index, 1)
  }
}

/* ------------------------------------------------------------------ */
/*  Quick Filters                                                      */
/* ------------------------------------------------------------------ */

function applyQuickFilter(preset) {
  quickFilter.value = preset
  const now = new Date()

  // Reset filtri
  Object.assign(filters, {
    user: '', verb: [], status_codes: [],
    date_from: '', date_to: '', ip: '',
    controller: '', app: '', text: '',
    has_error: false, has_unfinished_transaction: false,
  })

  switch (preset) {
    case 'today':
      filters.date_from = formatDateISO(startOfDay(now))
      filters.date_to = formatDateISO(endOfDay(now))
      break
    case 'last24h':
      filters.date_from = formatDateISO(new Date(now.getTime() - 86400000))
      filters.date_to = formatDateISO(now)
      break
    case 'last7d':
      filters.date_from = formatDateISO(new Date(now.getTime() - 7 * 86400000))
      filters.date_to = formatDateISO(now)
      break
    case 'errors':
      filters.has_error = true
      filters.date_from = formatDateISO(startOfDay(now))
      filters.date_to = formatDateISO(endOfDay(now))
      break
    case 'mutations':
      filters.verb = ['post', 'put', 'patch', 'delete']
      filters.date_from = formatDateISO(startOfDay(now))
      filters.date_to = formatDateISO(endOfDay(now))
      break
    case 'transactions':
      filters.has_unfinished_transaction = true
      filters.date_from = formatDateISO(startOfDay(now))
      filters.date_to = formatDateISO(endOfDay(now))
      break
  }

  loadLogs()
}

/* ------------------------------------------------------------------ */
/*  Caricamento Dati                                                   */
/* ------------------------------------------------------------------ */

async function loadLogs(page = 1) {
  if (abortController) abortController.abort()
  abortController = new AbortController()

  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', page)
    params.set('per_page', props.perPage)

    // Se il query builder avanzato è attivo, usa i gruppi
    if (showQueryBuilder.value && groups.value.length) {
      params.set('g', btoa(JSON.stringify(groups.value)))
    } else {
      // Altrimenti usa i filtri diretti
      if (filters.user) params.set('user', filters.user)
      if (filters.verb.length) filters.verb.forEach(v => params.append('verb[]', v))
      if (filters.status_codes.length) filters.status_codes.forEach(c => params.append('status_codes[]', c))
      if (filters.date_from) params.set('date_from', filters.date_from)
      if (filters.date_to) params.set('date_to', filters.date_to)
      if (filters.ip) params.set('ip', filters.ip)
      if (filters.controller) params.set('controller', filters.controller)
      if (filters.app) params.set('app', filters.app)
      if (filters.text) params.set('text', filters.text)
      if (filters.has_error) params.set('has_error', '1')
      if (filters.has_unfinished_transaction) params.set('has_unfinished_transaction', '1')
    }

    const response = await fetch(
      `${props.apiBase}?${params.toString()}`,
      { signal: abortController.signal }
    )
    logs.value = await response.json()
  } catch (e) {
    if (e.name !== 'AbortError') console.error('[LogOperations]', e)
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const params = new URLSearchParams()
    if (filters.date_from) params.set('date_from', filters.date_from)
    if (filters.date_to) params.set('date_to', filters.date_to)
    if (filters.app) params.set('app', filters.app)

    const response = await fetch(`${props.apiBase}/stats?${params.toString()}`)
    stats.value = await response.json()
  } catch (e) {
    console.error('[LogOperations] Stats error:', e)
  }
}

async function loadMetadata() {
  try {
    const [codesRes, verbsRes, appsRes] = await Promise.all([
      fetch(`${props.apiBase}/http-codes`),
      fetch(`${props.apiBase}/verbs`),
      fetch(`${props.apiBase}/applications`),
    ])
    httpCodes.value = await codesRes.json()
    verbs.value = await verbsRes.json()
    applications.value = await appsRes.json()
  } catch (e) {
    console.error('[LogOperations] Metadata error:', e)
  }
}

async function openDetail(log) {
  try {
    const res = await fetch(`${props.apiBase}/${log.id}`)
    selectedLog.value = await res.json()
    showDetail.value = true
  } catch (e) {
    console.error('[LogOperations] Detail error:', e)
  }
}

function goToPage(page) {
  if (page >= 1 && page <= logs.value.last_page) {
    loadLogs(page)
  }
}

/* ------------------------------------------------------------------ */
/*  Esportazione                                                       */
/* ------------------------------------------------------------------ */

function exportData(format) {
  const data = logs.value.data
  if (!data.length) return

  let content, filename, mime
  if (format === 'json') {
    content = JSON.stringify(data, null, 2)
    filename = 'log_operations_export.json'
    mime = 'application/json'
  } else {
    // CSV
    const headers = Object.keys(data[0])
    const rows = data.map(row =>
      headers.map(h => {
        const val = row[h]
        const str = typeof val === 'object' ? JSON.stringify(val) : String(val ?? '')
        return '"' + str.replace(/"/g, '""') + '"'
      }).join(',')
    )
    content = headers.join(',') + '\n' + rows.join('\n')
    filename = 'log_operations_export.csv'
    mime = 'text/csv'
  }

  const blob = new Blob([content], { type: mime })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

/* ------------------------------------------------------------------ */
/*  Utilità Date                                                       */
/* ------------------------------------------------------------------ */

function startOfDay(d) {
  const r = new Date(d); r.setHours(0, 0, 0, 0); return r
}
function endOfDay(d) {
  const r = new Date(d); r.setHours(23, 59, 59, 999); return r
}
function formatDateISO(d) {
  return d.toISOString().slice(0, 19)
}
function formatDateDisplay(isoStr) {
  if (!isoStr) return '—'
  const d = new Date(isoStr)
  return d.toLocaleDateString('it-IT') + ' ' + d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}
function formatDuration(ms) {
  if (ms == null) return '—'
  if (ms < 1000) return ms + ' ms'
  return (ms / 1000).toFixed(2) + ' s'
}

/* ------------------------------------------------------------------ */
/*  Montaggio                                                          */
/* ------------------------------------------------------------------ */

/* ------------------------------------------------------------------ */
/*  Toggle Verbo per i chip                                            */
/* ------------------------------------------------------------------ */

function toggleVerb(v) {
  const idx = filters.verb.indexOf(v)
  if (idx >= 0) {
    filters.verb.splice(idx, 1)
  } else {
    filters.verb.push(v)
  }
}

/* ------------------------------------------------------------------ */
/*  Paginazione intelligente                                           */
/* ------------------------------------------------------------------ */

const paginationRange = computed(() => {
  const current = logs.value.current_page
  const last = logs.value.last_page
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)

  const pages = []
  pages.push(1)
  if (current > 3) pages.push('...')
  for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) {
    pages.push(i)
  }
  if (current < last - 2) pages.push('...')
  pages.push(last)
  return pages
})

/* ------------------------------------------------------------------ */
/*  Montaggio                                                          */
/* ------------------------------------------------------------------ */

onMounted(async () => {
  await loadMetadata()
  applyQuickFilter('today')
  loadStats()
})
</script>

<template>
<div class="log-ops-viewer">
  <!-- HEADER -->
  <div class="log-ops-header">
    <div class="log-ops-header__title">
      <svg class="log-ops-header__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
        <polyline points="10 9 9 9 8 9"/>
      </svg>
      <h1>Log Operazioni</h1>
    </div>

    <div class="log-ops-header__actions">
      <button
        class="btn btn--outline btn--sm"
        @click="showQueryBuilder = !showQueryBuilder"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn__icon">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        {{ showQueryBuilder ? 'Filtri Rapidi' : 'Query Avanzata' }}
      </button>

      <div class="export-group">
        <button class="btn btn--outline btn--sm" @click="exportData('csv')">
          CSV
        </button>
        <button class="btn btn--outline btn--sm" @click="exportData('json')">
          JSON
        </button>
      </div>
    </div>
  </div>

  <!-- KPI STATS BAR -->
  <LogStatsBar :stats="stats" />

  <!-- QUICK FILTERS -->
  <div v-if="!showQueryBuilder" class="quick-filters">
    <button
      v-for="qf in [
        { key: 'today', label: 'Oggi', icon: '📅' },
        { key: 'last24h', label: 'Ultime 24h', icon: '🕐' },
        { key: 'last7d', label: 'Ultimi 7gg', icon: '📊' },
        { key: 'errors', label: 'Solo Errori', icon: '🚨' },
        { key: 'mutations', label: 'Modifiche', icon: '✏️' },
        { key: 'transactions', label: 'Transazioni', icon: '⚠️' },
      ]"
      :key="qf.key"
      :class="['quick-filter-btn', { active: quickFilter === qf.key }]"
      @click="applyQuickFilter(qf.key)"
    >
      <span class="quick-filter-btn__icon">{{ qf.icon }}</span>
      {{ qf.label }}
    </button>
  </div>

  <!-- DIRECT FILTERS BAR -->
  <div v-if="!showQueryBuilder" class="filters-bar">
    <div class="filter-group">
      <label>Utente</label>
      <input v-model="filters.user" type="text" placeholder="Nome, cognome o email" class="filter-input" @keyup.enter="loadLogs()" />
    </div>
    <div class="filter-group">
      <label>IP</label>
      <input v-model="filters.ip" type="text" placeholder="Indirizzo IP" class="filter-input filter-input--sm" @keyup.enter="loadLogs()" />
    </div>
    <div class="filter-group">
      <label>Controller</label>
      <input v-model="filters.controller" type="text" placeholder="Controller@metodo" class="filter-input" @keyup.enter="loadLogs()" />
    </div>
    <div class="filter-group">
      <label>Testo libero</label>
      <input v-model="filters.text" type="text" placeholder="Rotta, parametri, errore..." class="filter-input" @keyup.enter="loadLogs()" />
    </div>
    <div class="filter-group">
      <label>Da</label>
      <input v-model="filters.date_from" type="datetime-local" class="filter-input filter-input--date" />
    </div>
    <div class="filter-group">
      <label>A</label>
      <input v-model="filters.date_to" type="datetime-local" class="filter-input filter-input--date" />
    </div>
    <div class="filter-group">
      <label>Verbi</label>
      <div class="verb-chips">
        <button
          v-for="v in ['get', 'post', 'put', 'patch', 'delete']"
          :key="v"
          :class="['verb-chip', verbColors[v], { active: filters.verb.includes(v) }]"
          @click="toggleVerb(v)"
        >
          {{ v.toUpperCase() }}
        </button>
      </div>
    </div>
    <div class="filter-group">
      <label>Codice HTTP</label>
      <select v-model="filters.status_codes" multiple class="filter-select">
        <option v-for="c in httpCodes" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>
    <div class="filter-group">
      <label>App</label>
      <select v-model="filters.app" class="filter-select">
        <option value="">Tutte</option>
        <option v-for="a in applications" :key="a" :value="a">{{ a }}</option>
      </select>
    </div>
    <button class="btn btn--primary" @click="loadLogs(); loadStats()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn__icon">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      Cerca
    </button>
  </div>

  <!-- QUERY BUILDER AVANZATO -->
  <LogQueryBuilder
    v-if="showQueryBuilder"
    v-model:groups="groups"
    :http-codes="httpCodes"
    @search="loadLogs(); loadStats()"
    @add-group="addGroup"
    @remove-group="removeGroup"
  />

  <!-- LOADING SPINNER -->
  <div v-if="loading" class="loading-overlay">
    <div class="spinner"></div>
    <span>Ricerca in corso...</span>
  </div>

  <!-- RESULTS TABLE -->
  <div v-if="!loading && logs.data.length > 0" class="results-container">
    <div class="results-header">
      <span class="results-count">{{ logs.total }} risultati trovati</span>
    </div>

    <div class="log-table-wrap">
      <table class="log-table">
        <thead>
          <tr>
            <th class="th--verb">Verbo</th>
            <th class="th--status">HTTP</th>
            <th class="th--user">Utente</th>
            <th class="th--route">Rotta</th>
            <th class="th--controller">Controller</th>
            <th class="th--ip">IP</th>
            <th class="th--duration">Durata</th>
            <th class="th--date">Data</th>
            <th class="th--flags">Flags</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="log in logs.data"
            :key="log.id"
            :class="['log-row', statusRowClass(log.codicehttp)]"
            @click="openDetail(log)"
          >
            <td>
              <span :class="['verb-badge', verbColors[log.verbo] || 'bg-gray-400']">
                {{ (log.verbo || '').toUpperCase() }}
              </span>
            </td>
            <td>
              <span :class="['status-badge', statusClass(log.codicehttp)]">
                {{ log.codicehttp }}
              </span>
            </td>
            <td class="td--user">
              {{ log.user_label || '—' }}
            </td>
            <td class="td--route" :title="log.rotta">
              {{ log.rotta }}
            </td>
            <td class="td--controller" :title="log.controllermethod">
              {{ log.controllermethod || '—' }}
            </td>
            <td class="td--ip">
              {{ log.client_ip || '—' }}
            </td>
            <td class="td--duration">
              {{ formatDuration(log.duration_ms) }}
            </td>
            <td class="td--date">
              {{ formatDateDisplay(log.dataoperazione) }}
            </td>
            <td class="td--flags">
              <span v-if="log.error" class="flag flag--error" title="Contiene errore">🐛</span>
              <span v-if="log.transaction_status" class="flag flag--transaction" title="Transazione pendente">⚡</span>
              <span v-if="log.stack_trace && log.stack_trace.length" class="flag flag--stack" title="Stack disponibile">📚</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <div class="pagination">
      <button
        class="btn btn--outline btn--sm"
        :disabled="logs.current_page <= 1"
        @click="goToPage(logs.current_page - 1)"
      >
        ← Precedente
      </button>

      <div class="pagination__pages">
        <button
          v-for="p in paginationRange"
          :key="p"
          :class="['pagination__page', { active: p === logs.current_page }]"
          @click="goToPage(p)"
          :disabled="p === '...'"
        >
          {{ p }}
        </button>
      </div>

      <span class="pagination__info">
        Pagina {{ logs.current_page }} di {{ logs.last_page }}
      </span>

      <button
        class="btn btn--outline btn--sm"
        :disabled="logs.current_page >= logs.last_page"
        @click="goToPage(logs.current_page + 1)"
      >
        Successiva →
      </button>
    </div>
  </div>

  <!-- EMPTY STATE -->
  <div v-if="!loading && logs.data.length === 0" class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="empty-state__icon">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <h3>Nessun log trovato</h3>
    <p>Prova a modificare i criteri di ricerca</p>
  </div>

  <!-- DETAIL MODAL -->
  <LogDetailModal
    v-if="showDetail && selectedLog"
    :log="selectedLog"
    @close="showDetail = false; selectedLog = null"
  />
</div>
</template>



<style>
/* ================================================================== */
/*  LOG OPERATIONS VIEWER - Premium Design System                     */
/* ================================================================== */

@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

.log-ops-viewer {
  --lo-bg: #0f172a;
  --lo-surface: #1e293b;
  --lo-surface-2: #334155;
  --lo-surface-hover: #2d3a4f;
  --lo-border: #334155;
  --lo-border-light: #475569;
  --lo-text: #f1f5f9;
  --lo-text-muted: #94a3b8;
  --lo-text-dim: #64748b;
  --lo-primary: #6366f1;
  --lo-primary-hover: #818cf8;
  --lo-success: #10b981;
  --lo-warning: #f59e0b;
  --lo-danger: #ef4444;
  --lo-info: #3b82f6;
  --lo-radius: 10px;
  --lo-radius-sm: 6px;
  --lo-radius-lg: 14px;
  --lo-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
  --lo-transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);

  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: var(--lo-bg);
  color: var(--lo-text);
  padding: 24px;
  min-height: 100vh;
  box-sizing: border-box;
}

/* ------------------------------------------------------------------ */
/*  Header                                                             */
/* ------------------------------------------------------------------ */

.log-ops-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--lo-border);
}

.log-ops-header__title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.log-ops-header__title h1 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
  background: linear-gradient(135deg, #6366f1, #a855f7);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.log-ops-header__icon {
  width: 28px;
  height: 28px;
  color: var(--lo-primary);
}

.log-ops-header__actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.export-group {
  display: flex;
  gap: 4px;
}

/* ------------------------------------------------------------------ */
/*  Buttons                                                            */
/* ------------------------------------------------------------------ */

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: var(--lo-radius-sm);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--lo-transition);
  border: none;
  font-family: inherit;
}

.btn__icon { width: 16px; height: 16px; }

.btn--primary {
  background: var(--lo-primary);
  color: white;
}
.btn--primary:hover { background: var(--lo-primary-hover); transform: translateY(-1px); }

.btn--outline {
  background: transparent;
  color: var(--lo-text-muted);
  border: 1px solid var(--lo-border);
}
.btn--outline:hover { border-color: var(--lo-primary); color: var(--lo-primary); }

.btn--sm { padding: 6px 12px; font-size: 0.75rem; }

.btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

/* ------------------------------------------------------------------ */
/*  Quick Filters                                                      */
/* ------------------------------------------------------------------ */

.quick-filters {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.quick-filter-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: var(--lo-surface);
  border: 1px solid var(--lo-border);
  border-radius: 9999px;
  color: var(--lo-text-muted);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--lo-transition);
  font-family: inherit;
}

.quick-filter-btn:hover { border-color: var(--lo-primary); color: var(--lo-text); }
.quick-filter-btn.active {
  background: var(--lo-primary);
  border-color: var(--lo-primary);
  color: white;
}

.quick-filter-btn__icon { font-size: 0.875rem; }

/* ------------------------------------------------------------------ */
/*  Filters Bar                                                        */
/* ------------------------------------------------------------------ */

.filters-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
  padding: 16px;
  background: var(--lo-surface);
  border: 1px solid var(--lo-border);
  border-radius: var(--lo-radius);
  margin-bottom: 20px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.filter-group label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--lo-text-dim);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.filter-input {
  padding: 7px 12px;
  background: var(--lo-bg);
  border: 1px solid var(--lo-border);
  border-radius: var(--lo-radius-sm);
  color: var(--lo-text);
  font-size: 0.8125rem;
  min-width: 150px;
  font-family: inherit;
  transition: border-color var(--lo-transition);
}
.filter-input:focus { outline: none; border-color: var(--lo-primary); }
.filter-input--sm { min-width: 110px; }
.filter-input--date { min-width: 180px; }

.filter-select {
  padding: 7px 12px;
  background: var(--lo-bg);
  border: 1px solid var(--lo-border);
  border-radius: var(--lo-radius-sm);
  color: var(--lo-text);
  font-size: 0.8125rem;
  min-width: 120px;
  font-family: inherit;
}
.filter-select:focus { outline: none; border-color: var(--lo-primary); }

/* ------------------------------------------------------------------ */
/*  Verb Chips                                                         */
/* ------------------------------------------------------------------ */

.verb-chips { display: flex; gap: 4px; }

.verb-chip {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.6875rem;
  font-weight: 700;
  cursor: pointer;
  opacity: 0.35;
  transition: all var(--lo-transition);
  border: none;
  color: white;
  font-family: inherit;
}
.verb-chip:hover { opacity: 0.7; }
.verb-chip.active { opacity: 1; box-shadow: 0 0 0 2px rgba(255,255,255,0.3); }

/* ------------------------------------------------------------------ */
/*  Results Table                                                      */
/* ------------------------------------------------------------------ */

.results-container {
  animation: fadeIn 0.3s ease;
}

.results-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.results-count {
  font-size: 0.8125rem;
  color: var(--lo-text-dim);
  font-weight: 500;
}

.log-table-wrap {
  overflow-x: auto;
  border-radius: var(--lo-radius);
  border: 1px solid var(--lo-border);
}

.log-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.log-table thead {
  background: var(--lo-surface-2);
}

.log-table th {
  padding: 10px 12px;
  text-align: left;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--lo-text-dim);
  white-space: nowrap;
  border-bottom: 1px solid var(--lo-border);
}

.log-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--lo-border);
  vertical-align: middle;
}

.log-row {
  cursor: pointer;
  transition: background var(--lo-transition);
}
.log-row:hover { background: var(--lo-surface-hover); }

.log-row--error-500 { background: rgba(239, 68, 68, 0.08); }
.log-row--error-500:hover { background: rgba(239, 68, 68, 0.14); }
.log-row--error-400 { background: rgba(245, 158, 11, 0.06); }
.log-row--error-400:hover { background: rgba(245, 158, 11, 0.12); }
.log-row--redirect { background: rgba(99, 102, 241, 0.05); }

/* ------------------------------------------------------------------ */
/*  Badges                                                             */
/* ------------------------------------------------------------------ */

.verb-badge {
  display: inline-flex;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.6875rem;
  font-weight: 700;
  color: white;
  letter-spacing: 0.03em;
}

.status-badge {
  display: inline-flex;
  padding: 3px 10px;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  min-width: 38px;
  justify-content: center;
}

/* ------------------------------------------------------------------ */
/*  Table cell specifics                                               */
/* ------------------------------------------------------------------ */

.td--route { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--lo-text-muted); }
.td--controller { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--lo-text-dim); }
.td--user { white-space: nowrap; font-weight: 500; }
.td--ip { font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--lo-text-dim); }
.td--duration { white-space: nowrap; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; }
.td--date { white-space: nowrap; color: var(--lo-text-muted); font-size: 0.75rem; }
.td--flags { white-space: nowrap; }

.flag { font-size: 0.875rem; margin-right: 4px; cursor: help; }

.th--verb { width: 70px; }
.th--status { width: 60px; }
.th--duration { width: 80px; }
.th--flags { width: 80px; }

/* ------------------------------------------------------------------ */
/*  Pagination                                                         */
/* ------------------------------------------------------------------ */

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 20px;
  padding: 16px 0;
}

.pagination__pages {
  display: flex;
  gap: 4px;
}

.pagination__page {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--lo-radius-sm);
  border: 1px solid var(--lo-border);
  background: transparent;
  color: var(--lo-text-muted);
  font-size: 0.8125rem;
  cursor: pointer;
  transition: all var(--lo-transition);
  font-family: inherit;
}
.pagination__page:hover { border-color: var(--lo-primary); color: var(--lo-primary); }
.pagination__page.active {
  background: var(--lo-primary);
  border-color: var(--lo-primary);
  color: white;
}
.pagination__page:disabled { cursor: default; opacity: 0.3; }

.pagination__info {
  font-size: 0.8125rem;
  color: var(--lo-text-dim);
  font-weight: 500;
}

/* ------------------------------------------------------------------ */
/*  Loading & Empty                                                    */
/* ------------------------------------------------------------------ */

.loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 48px 0;
  color: var(--lo-text-muted);
  font-size: 0.875rem;
}

.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid var(--lo-border);
  border-top-color: var(--lo-primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

.empty-state {
  text-align: center;
  padding: 64px 24px;
  color: var(--lo-text-dim);
}

.empty-state__icon {
  width: 48px;
  height: 48px;
  margin-bottom: 16px;
  opacity: 0.4;
}

.empty-state h3 { font-size: 1.125rem; font-weight: 600; margin: 0 0 8px; color: var(--lo-text-muted); }
.empty-state p { font-size: 0.875rem; margin: 0; }

/* ------------------------------------------------------------------ */
/*  Utility Classes                                                    */
/* ------------------------------------------------------------------ */

.bg-emerald-500 { background: #10b981; }
.bg-blue-500 { background: #3b82f6; }
.bg-amber-500 { background: #f59e0b; }
.bg-violet-500 { background: #8b5cf6; }
.bg-rose-500 { background: #f43f5e; }
.bg-rose-600 { background: #e11d48; }
.bg-indigo-500 { background: #6366f1; }
.bg-gray-400 { background: #9ca3af; }

/* ------------------------------------------------------------------ */
/*  Animations                                                         */
/* ------------------------------------------------------------------ */

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ------------------------------------------------------------------ */
/*  Responsive                                                         */
/* ------------------------------------------------------------------ */

@media (max-width: 768px) {
  .log-ops-viewer { padding: 12px; }
  .log-ops-header { flex-direction: column; gap: 12px; align-items: flex-start; }
  .filters-bar { flex-direction: column; }
  .filter-input, .filter-input--sm, .filter-input--date, .filter-select { min-width: 100%; }
  .pagination { flex-wrap: wrap; }
}
</style>
