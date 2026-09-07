<script setup>
/**
 * LogStoryboard.vue
 *
 * Componente autonomo per la visualizzazione della Storyboard e dell'Audit Trail di un record.
 * Può essere incorporato in qualsiasi pagina di dettaglio entità (es. Ordine, Fattura, Ticket, Cliente).
 *
 * Utilizzo:
 *   <LogStoryboard
 *       :subject-id="order.id"
 *       subject-type="App\\Models\\Order"
 *   />
 */
import { ref, computed, onMounted, watch } from 'vue'

const props = defineProps({
  subjectType: { type: String, required: true },
  subjectId: { type: [String, Number], required: true },
  apiUrl: { type: String, default: '/api/logoperations/storyboard' },
  title: { type: String, default: 'Storyboard & Audit Trail' },
  collapsible: { type: Boolean, default: true },
})

const loading = ref(false)
const error = ref(null)
const data = ref({
  subject: null,
  kpis: {
    total_events: 0,
    total_errors: 0,
    total_rollbacks: 0,
    total_checkpoints: 0,
    first_activity: null,
    last_activity: null,
  },
  events: [],
})

// Filtri locali e controlli UI
const selectedCategory = ref('all')
const searchQuery = ref('')
const sortOrder = ref('asc') // 'asc' = cronologico, 'desc' = inverso
const expandedEvents = ref(new Set())
const copyFeedback = ref('')

/* ------------------------------------------------------------------ */
/*  Data Fetching                                                      */
/* ------------------------------------------------------------------ */

async function fetchStoryboard() {
  if (!props.subjectType || props.subjectId == null) return

  loading.value = true
  error.value = null

  try {
    const url = new URL(props.apiUrl, window.location.origin)
    url.searchParams.set('subject_type', props.subjectType)
    url.searchParams.set('subject_id', props.subjectId)
    url.searchParams.set('order', sortOrder.value)

    const response = await fetch(url.toString(), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    if (!response.ok) {
      // Fallback a prefisso alternativo /api/log-operations/storyboard se necessario
      if (response.status === 404 && !props.apiUrl.includes('log-operations')) {
        const altUrl = new URL('/api/log-operations/storyboard', window.location.origin)
        altUrl.searchParams.set('subject_type', props.subjectType)
        altUrl.searchParams.set('subject_id', props.subjectId)
        altUrl.searchParams.set('order', sortOrder.value)
        const altRes = await fetch(altUrl.toString(), {
          headers: { 'Accept': 'application/json' }
        })
        if (altRes.ok) {
          data.value = await altRes.json()
          autoExpandRecent()
          return
        }
      }
      throw new Error(`Errore HTTP ${response.status}: ${response.statusText}`)
    }

    data.value = await response.json()
    autoExpandRecent()
  } catch (err) {
    error.value = err.message || 'Impossibile caricare la timeline di vita del record.'
  } finally {
    loading.value = false
  }
}

function autoExpandRecent() {
  // Espandi di default l'ultimo evento o gli errori
  if (data.value.events?.length) {
    const target = sortOrder.value === 'asc'
      ? data.value.events[data.value.events.length - 1]
      : data.value.events[0]
    if (target) {
      expandedEvents.value.add(target.id)
    }
    // Espandi anche eventuali errori
    data.value.events.forEach(e => {
      if (e.codicehttp >= 400 || e.error) {
        expandedEvents.value.add(e.id)
      }
    })
  }
}

watch(() => [props.subjectType, props.subjectId, sortOrder.value], () => {
  fetchStoryboard()
})

onMounted(() => {
  fetchStoryboard()
})

/* ------------------------------------------------------------------ */
/*  Computed & Filtering                                               */
/* ------------------------------------------------------------------ */

const filteredEvents = computed(() => {
  let list = data.value.events || []

  // Filtro per categoria
  if (selectedCategory.value !== 'all') {
    list = list.filter(e => {
      const cat = e.classification?.category
      if (selectedCategory.value === 'error') return cat === 'error'
      if (selectedCategory.value === 'checkpoint') return cat === 'checkpoint'
      if (selectedCategory.value === 'create') return cat === 'create'
      if (selectedCategory.value === 'update') return cat === 'update'
      if (selectedCategory.value === 'delete') return cat === 'delete'
      return true
    })
  }

  // Filtro testuale
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(e => {
      return (
        (e.rotta && e.rotta.toLowerCase().includes(q)) ||
        (e.controllermethod && e.controllermethod.toLowerCase().includes(q)) ||
        (e.user_label && e.user_label.toLowerCase().includes(q)) ||
        (e.classification?.title && e.classification.title.toLowerCase().includes(q)) ||
        (e.error && e.error.toLowerCase().includes(q)) ||
        (JSON.stringify(e.parametri || {}).toLowerCase().includes(q))
      )
    })
  }

  return list
})

/* ------------------------------------------------------------------ */
/*  UI Actions                                                         */
/* ------------------------------------------------------------------ */

function toggleEvent(id) {
  if (expandedEvents.value.has(id)) {
    expandedEvents.value.delete(id)
  } else {
    expandedEvents.value.add(id)
  }
}

function expandAll() {
  filteredEvents.value.forEach(e => expandedEvents.value.add(e.id))
}

function collapseAll() {
  expandedEvents.value.clear()
}

function toggleSort() {
  sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
}

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(typeof text === 'object' ? JSON.stringify(text, null, 2) : text)
    copyFeedback.value = 'Copiato!'
    setTimeout(() => { copyFeedback.value = '' }, 2000)
  } catch {
    copyFeedback.value = 'Errore copia'
    setTimeout(() => { copyFeedback.value = '' }, 2000)
  }
}

function formatDate(isoStr) {
  if (!isoStr) return '—'
  const d = new Date(isoStr)
  return d.toLocaleString('it-IT', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

function formatRelative(isoStr) {
  if (!isoStr) return ''
  const diffSec = Math.floor((new Date() - new Date(isoStr)) / 1000)
  if (diffSec < 60) return 'poco fa'
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)} min fa`
  if (diffSec < 86400) return `${Math.floor(diffSec / 3600)} ore fa`
  return `${Math.floor(diffSec / 86400)} gg fa`
}
</script>

<template>
  <div class="log-storyboard-container">
    <!-- Header -->
    <div class="storyboard-header">
      <div class="header-main">
        <div class="title-wrap">
          <div class="title-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
          <div>
            <h3 class="storyboard-title">{{ title }}</h3>
            <span class="storyboard-subject">
              {{ data.subject?.label || `${subjectType} #${subjectId}` }}
            </span>
          </div>
        </div>

        <div class="header-actions">
          <button class="btn-action" :title="sortOrder === 'asc' ? 'Dal più vecchio al più recente' : 'Dal più recente al più vecchio'" @click="toggleSort">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/>
            </svg>
            <span>{{ sortOrder === 'asc' ? 'Cronologico' : 'Inverso' }}</span>
          </button>

          <button class="btn-action" title="Espandi tutti gli eventi" @click="expandAll">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="7 11 12 6 17 11"></polyline>
              <polyline points="7 18 12 13 17 18"></polyline>
            </svg>
          </button>

          <button class="btn-action" title="Comprimi tutti gli eventi" @click="collapseAll">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="7 13 12 18 17 13"></polyline>
              <polyline points="7 6 12 11 17 6"></polyline>
            </svg>
          </button>

          <button class="btn-action btn-refresh" :disabled="loading" title="Ricarica timeline" @click="fetchStoryboard">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="{ 'spin': loading }">
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- KPI Bar -->
      <div v-if="data.kpis" class="kpis-bar">
        <div class="kpi-card">
          <span class="kpi-label">Eventi Totali</span>
          <span class="kpi-value">{{ data.kpis.total_events }}</span>
        </div>
        <div class="kpi-card" :class="{ 'kpi-alert': data.kpis.total_errors > 0 }">
          <span class="kpi-label">Errori</span>
          <span class="kpi-value">{{ data.kpis.total_errors }}</span>
        </div>
        <div class="kpi-card" :class="{ 'kpi-warn': data.kpis.total_rollbacks > 0 }">
          <span class="kpi-label">Rollback</span>
          <span class="kpi-value">{{ data.kpis.total_rollbacks }}</span>
        </div>
        <div class="kpi-card">
          <span class="kpi-label">Checkpoint</span>
          <span class="kpi-value">{{ data.kpis.total_checkpoints }}</span>
        </div>
        <div v-if="data.kpis.last_activity" class="kpi-card kpi-meta">
          <span class="kpi-label">Ultima Attività</span>
          <span class="kpi-value-sm">{{ formatRelative(data.kpis.last_activity) }}</span>
        </div>
      </div>

      <!-- Controls & Filter Pills -->
      <div class="storyboard-controls">
        <div class="filter-pills">
          <button
            class="pill"
            :class="{ active: selectedCategory === 'all' }"
            @click="selectedCategory = 'all'"
          >
            Tutti ({{ data.events?.length || 0 }})
          </button>
          <button
            v-if="data.kpis.total_errors > 0 || data.kpis.total_rollbacks > 0"
            class="pill pill-error"
            :class="{ active: selectedCategory === 'error' }"
            @click="selectedCategory = 'error'"
          >
            Errori & Rollback ({{ data.kpis.total_errors + data.kpis.total_rollbacks }})
          </button>
          <button
            class="pill"
            :class="{ active: selectedCategory === 'create' }"
            @click="selectedCategory = 'create'"
          >
            Creazione
          </button>
          <button
            class="pill"
            :class="{ active: selectedCategory === 'update' }"
            @click="selectedCategory = 'update'"
          >
            Modifiche
          </button>
          <button
            v-if="data.kpis.total_checkpoints > 0"
            class="pill pill-checkpoint"
            :class="{ active: selectedCategory === 'checkpoint' }"
            @click="selectedCategory = 'checkpoint'"
          >
            Checkpoint ({{ data.kpis.total_checkpoints }})
          </button>
        </div>

        <div class="search-wrap">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cerca rotta, autore, payload, errore..."
            class="search-input"
          />
          <button v-if="searchQuery" class="clear-search" @click="searchQuery = ''">✕</button>
        </div>
      </div>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="storyboard-alert-error">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
      <span>{{ error }}</span>
    </div>

    <!-- Feedback toast -->
    <div v-if="copyFeedback" class="copy-toast">
      {{ copyFeedback }}
    </div>

    <!-- Timeline Body -->
    <div class="timeline-wrapper">
      <!-- Loading skeleton -->
      <div v-if="loading && (!data.events || data.events.length === 0)" class="timeline-loading">
        <div v-for="i in 3" :key="i" class="skeleton-node">
          <div class="skeleton-dot"></div>
          <div class="skeleton-card"></div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="filteredEvents.length === 0" class="timeline-empty">
        <div class="empty-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
        </div>
        <p class="empty-title">Nessun evento registrato</p>
        <p class="empty-subtitle">Non sono presenti log o checkpoint che corrispondono ai filtri selezionati per questa entità.</p>
      </div>

      <!-- Timeline list -->
      <div v-else class="timeline-list">
        <div
          v-for="(event, idx) in filteredEvents"
          :key="event.id"
          class="timeline-node"
          :class="[
            `node-${event.classification?.category || 'read'}`,
            { 'is-expanded': expandedEvents.has(event.id) }
          ]"
        >
          <!-- Timeline stem and bullet indicator -->
          <div class="timeline-stem">
            <div
              class="timeline-bullet"
              :style="{ backgroundColor: event.classification?.badge_color || '#6b7280' }"
            >
              <!-- Icon inside bullet -->
              <span v-if="event.classification?.category === 'error'" class="bullet-icon">✕</span>
              <span v-else-if="event.classification?.category === 'create'" class="bullet-icon">+</span>
              <span v-else-if="event.classification?.category === 'checkpoint'" class="bullet-icon">★</span>
              <span v-else-if="event.classification?.category === 'delete'" class="bullet-icon">−</span>
              <span v-else class="bullet-icon">●</span>
            </div>
            <div v-if="idx < filteredEvents.length - 1" class="timeline-line"></div>
          </div>

          <!-- Event Card -->
          <div class="timeline-card">
            <!-- Card Header (Always clickable to toggle) -->
            <div class="card-header" @click="toggleEvent(event.id)">
              <div class="card-headline">
                <span
                  class="badge-category"
                  :style="{ backgroundColor: (event.classification?.badge_color || '#6b7280') + '20', color: event.classification?.badge_color || '#6b7280' }"
                >
                  {{ event.classification?.badge_label || event.verbo }}
                </span>

                <h4 class="card-title">{{ event.classification?.title }}</h4>

                <span v-if="event.codicehttp" class="badge-status" :class="`http-${Math.floor(event.codicehttp / 100)}xx`">
                  {{ event.codicehttp }}
                </span>

                <span v-if="event.duration_ms != null && event.duration_ms > 0" class="badge-duration">
                  {{ event.duration_ms }} ms
                </span>
              </div>

              <div class="card-meta">
                <!-- Autore -->
                <div class="meta-author" :title="event.user_type">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  <span>{{ event.user_label }}</span>
                </div>

                <!-- Timestamp -->
                <div class="meta-time" :title="formatDate(event.dataoperazione)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                  <span>{{ formatRelative(event.dataoperazione) }}</span>
                </div>

                <!-- Chevron -->
                <div class="meta-chevron">
                  <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    :class="{ 'rotate-180': expandedEvents.has(event.id) }"
                  >
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </div>
              </div>
            </div>

            <!-- Card Collapsible Details -->
            <div v-if="expandedEvents.has(event.id)" class="card-body">
              <!-- Route & Controller Method -->
              <div class="detail-row">
                <span class="detail-label">Endpoint</span>
                <span class="detail-value code-pill">
                  <strong>{{ (event.verbo || '').toUpperCase() }}</strong> {{ event.rotta }}
                </span>
              </div>

              <div v-if="event.controllermethod" class="detail-row">
                <span class="detail-label">Handler</span>
                <span class="detail-value code-pill">{{ event.controllermethod }}</span>
              </div>

              <div v-if="event.client_ip" class="detail-row">
                <span class="detail-label">Indirizzo IP</span>
                <span class="detail-value font-mono">{{ event.client_ip }}</span>
              </div>

              <!-- Transaction Status Alert -->
              <div v-if="event.transaction_status" class="transaction-alert">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span>Transazione intercettata: <strong>{{ event.transaction_status }}</strong> (Livello {{ event.transaction_level }})</span>
              </div>

              <!-- Custom Steps (Checkpoint / logStep) -->
              <div v-if="event.custom_traces?.steps?.length" class="custom-steps-section">
                <div class="section-title">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                  </svg>
                  <span>Passaggi Chiave & Note Esecutive</span>
                </div>
                <div
                  v-for="(step, sIdx) in event.custom_traces.steps"
                  :key="sIdx"
                  class="step-item"
                >
                  <div class="step-badge">{{ sIdx + 1 }}</div>
                  <div class="step-content">
                    <span class="step-label">{{ step.label }}</span>
                    <pre v-if="step.context && Object.keys(step.context).length" class="step-json">{{ JSON.stringify(step.context, null, 2) }}</pre>
                  </div>
                </div>
              </div>

              <!-- Parametri / Payload -->
              <div v-if="event.parametri" class="payload-section">
                <div class="section-title-wrap">
                  <div class="section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="4" y1="9" x2="20" y2="9"></line>
                      <line x1="4" y1="15" x2="20" y2="15"></line>
                      <line x1="10" y1="3" x2="8" y2="21"></line>
                      <line x1="16" y1="3" x2="14" y2="21"></line>
                    </svg>
                    <span>Parametri Richiesta / Payload</span>
                  </div>
                  <button class="btn-copy-sm" @click="copyText(event.parametri)">Copia</button>
                </div>
                <pre class="json-viewer">{{ JSON.stringify(event.parametri, null, 2) }}</pre>
              </div>

              <!-- Errore & Stack Trace -->
              <div v-if="event.error" class="error-section">
                <div class="section-title-wrap">
                  <div class="section-title title-error">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>Dettaglio Errore</span>
                  </div>
                  <button class="btn-copy-sm" @click="copyText(event.error)">Copia Trace</button>
                </div>
                <pre class="error-box">{{ event.error }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.log-storyboard-container {
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #1e293b;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* Header */
.storyboard-header {
  padding: 16px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.header-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.title-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #eff6ff;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
}

.storyboard-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
}

.storyboard-subject {
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 500;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-action:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #94a3b8;
}

.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* KPIs Bar */
.kpis-bar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  padding-top: 4px;
}

.kpi-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  min-width: 80px;
}

.kpi-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  font-weight: 600;
}

.kpi-value {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
}

.kpi-value-sm {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin-top: 2px;
}

.kpi-alert {
  background: #fef2f2;
  border-color: #fecaca;
}
.kpi-alert .kpi-label { color: #dc2626; }
.kpi-alert .kpi-value { color: #b91c1c; }

.kpi-warn {
  background: #fffbeb;
  border-color: #fde68a;
}
.kpi-warn .kpi-label { color: #d97706; }
.kpi-warn .kpi-value { color: #b45309; }

/* Filter Controls */
.storyboard-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-pills {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.pill {
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 500;
  border-radius: 9999px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pill:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.pill.active {
  background: #0f172a;
  color: #ffffff;
  border-color: #0f172a;
}

.pill-error.active {
  background: #ef4444;
  border-color: #ef4444;
  color: #ffffff;
}

.pill-checkpoint.active {
  background: #2563eb;
  border-color: #2563eb;
  color: #ffffff;
}

.search-wrap {
  position: relative;
  min-width: 260px;
  flex: 1;
  max-width: 400px;
}

.search-input {
  width: 100%;
  padding: 6px 28px 6px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 12px;
  outline: none;
  background: #ffffff;
  box-sizing: border-box;
}

.search-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}

.clear-search {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  font-size: 12px;
  padding: 0;
}

/* Timeline Body */
.timeline-wrapper {
  padding: 24px 20px;
  min-height: 200px;
}

.timeline-list {
  display: flex;
  flex-direction: column;
}

.timeline-node {
  display: flex;
  gap: 16px;
  position: relative;
}

.timeline-stem {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 28px;
  flex-shrink: 0;
}

.timeline-bullet {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 12px;
  font-weight: bold;
  z-index: 2;
  box-shadow: 0 0 0 4px #ffffff, 0 1px 3px rgba(0, 0, 0, 0.1);
}

.bullet-icon {
  line-height: 1;
}

.timeline-line {
  width: 2px;
  flex-grow: 1;
  background: #e2e8f0;
  margin: 4px 0;
}

/* Card */
.timeline-card {
  flex: 1;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  margin-bottom: 16px;
  overflow: hidden;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.timeline-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.card-header {
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  gap: 12px;
  user-select: none;
}

.card-headline {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.badge-category {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: uppercase;
}

.card-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.badge-status {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
}
.http-2xx { background: #dcfce7; color: #166534; }
.http-3xx { background: #e0e7ff; color: #3730a3; }
.http-4xx { background: #fef3c7; color: #92400e; }
.http-5xx { background: #fee2e2; color: #991b1b; }

.badge-duration {
  font-size: 11px;
  color: #64748b;
  font-family: monospace;
}

.card-meta {
  display: flex;
  align-items: center;
  gap: 14px;
  color: #64748b;
  font-size: 12px;
  flex-shrink: 0;
}

.meta-author, .meta-time {
  display: flex;
  align-items: center;
  gap: 4px;
}

.meta-chevron svg {
  transition: transform 0.2s;
}
.rotate-180 {
  transform: rotate(180deg);
}

/* Card Body */
.card-body {
  padding: 14px 16px;
  border-top: 1px solid #f1f5f9;
  background: #fafafa;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-row {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

.detail-label {
  width: 90px;
  font-weight: 600;
  color: #64748b;
  font-size: 12px;
  flex-shrink: 0;
}

.detail-value {
  color: #1e293b;
}

.code-pill {
  background: #e2e8f0;
  padding: 2px 8px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 12px;
}

.transaction-alert {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
}

/* Custom steps */
.custom-steps-section {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.step-item {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.step-badge {
  width: 20px;
  height: 20px;
  background: #2563eb;
  color: #ffffff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: bold;
  flex-shrink: 0;
}

.step-content {
  flex: 1;
}

.step-label {
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
}

.step-json {
  margin: 4px 0 0 0;
  background: #f8fafc;
  padding: 6px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-family: monospace;
  color: #334155;
  overflow-x: auto;
}

/* Section Title */
.section-title-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
}

.title-error {
  color: #dc2626;
}

.btn-copy-sm {
  background: none;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 10px;
  color: #475569;
  cursor: pointer;
}
.btn-copy-sm:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.json-viewer, .error-box {
  margin: 4px 0 0 0;
  background: #0f172a;
  color: #f8fafc;
  padding: 10px 12px;
  border-radius: 6px;
  font-family: monospace;
  font-size: 11px;
  line-height: 1.45;
  max-height: 250px;
  overflow-y: auto;
}

.error-box {
  background: #450a0a;
  color: #fecaca;
  border: 1px solid #7f1d1d;
}

/* Empty State */
.timeline-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
  text-align: center;
  color: #64748b;
}

.empty-icon {
  margin-bottom: 12px;
  color: #94a3b8;
}

.empty-title {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #334155;
}

.empty-subtitle {
  margin: 4px 0 0 0;
  font-size: 13px;
  max-width: 360px;
}

/* Skeleton Loading */
.timeline-loading {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skeleton-node {
  display: flex;
  gap: 16px;
}

.skeleton-dot {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #e2e8f0;
  animation: pulse 1.5s infinite;
}

.skeleton-card {
  flex: 1;
  height: 56px;
  border-radius: 8px;
  background: #f1f5f9;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.copy-toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: #0f172a;
  color: #ffffff;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 9999;
}
</style>
