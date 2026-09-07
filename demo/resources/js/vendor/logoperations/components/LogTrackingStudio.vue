<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  apiBase: { type: String, default: '/api/logoperations' },
})

const emit = defineEmits(['close', 'rule-updated'])

/* ------------------------------------------------------------------ */
/*  Stato Schede & Caricamento                                        */
/* ------------------------------------------------------------------ */
const activeTab = ref('routes') // 'routes' | 'classes' | 'user_sessions'
const loading = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

function showToast(msg, type = 'success') {
  toastMessage.value = msg
  toastType.value = type
  setTimeout(() => {
    toastMessage.value = ''
  }, 3500)
}

/* ------------------------------------------------------------------ */
/*  TAB 1: Mappa Rotte                                                */
/* ------------------------------------------------------------------ */
const routes = ref([])
const routeSearch = ref('')
const selectedArea = ref('all')
const selectedMethod = ref('all')
const onlyTrackedRoutes = ref(false)
const updatingRoute = ref(null)

const filteredRoutes = computed(() => {
  return routes.value.filter((r) => {
    if (selectedArea.value !== 'all' && r.area.toLowerCase() !== selectedArea.value.toLowerCase()) {
      return false
    }
    if (selectedMethod.value !== 'all' && !r.methods.includes(selectedMethod.value)) {
      return false
    }
    if (onlyTrackedRoutes.value && !r.is_tracked) {
      return false
    }
    if (routeSearch.value) {
      const q = routeSearch.value.toLowerCase()
      const matchUri = r.uri.toLowerCase().includes(q)
      const matchAction = (r.action || '').toLowerCase().includes(q)
      const matchName = (r.name || '').toLowerCase().includes(q)
      const matchCtrl = (r.controller || '').toLowerCase().includes(q)
      return matchUri || matchAction || matchName || matchCtrl
    }
    return true
  })
})

async function fetchRoutes() {
  loading.value = true
  try {
    const res = await fetch(`${props.apiBase}/studio/routes`)
    const data = await res.json()
    if (data.success) {
      routes.value = data.data
    }
  } catch (err) {
    showToast('Errore nel caricamento delle rotte.', 'error')
  } finally {
    loading.value = false
  }
}

async function toggleRoute(route) {
  updatingRoute.value = route.uri
  try {
    const newStatus = !route.is_tracked
    const res = await fetch(`${props.apiBase}/studio/rules`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: 'route',
        target: route.uri,
        name: route.name || route.controller || route.uri,
        http_methods: route.http_methods || ['*'],
        stack_level: route.stack_level || 'base',
        is_active: newStatus,
      }),
    })
    const data = await res.json()
    if (data.success) {
      route.is_tracked = newStatus
      route.rule_id = data.data.id
      showToast(newStatus ? `Tracciamento attivato per ${route.uri}` : `Tracciamento disattivato per ${route.uri}`)
      emit('rule-updated')
    }
  } catch (err) {
    showToast('Errore durante l\'aggiornamento della regola', 'error')
  } finally {
    updatingRoute.value = null
  }
}

async function updateRouteStackLevel(route, level) {
  route.stack_level = level
  if (route.is_tracked) {
    try {
      await fetch(`${props.apiBase}/studio/rules`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type: 'route',
          target: route.uri,
          stack_level: level,
          is_active: true,
        }),
      })
      showToast(`Livello stack aggiornato a ${level.toUpperCase()}`)
      emit('rule-updated')
    } catch (e) {
      showToast('Errore nel salvataggio del livello stack', 'error')
    }
  }
}

/* ------------------------------------------------------------------ */
/*  TAB 2: Funzioni & Servizi                                         */
/* ------------------------------------------------------------------ */
const classes = ref([])
const classSearch = ref('')
const expandedClasses = reactive({})
const updatingMethod = ref(null)

const filteredClasses = computed(() => {
  if (!classSearch.value) return classes.value
  const q = classSearch.value.toLowerCase()
  return classes.value
    .map((c) => {
      const matchClass = c.class_name.toLowerCase().includes(q) || c.short_name.toLowerCase().includes(q)
      const matchingMethods = c.methods.filter((m) => m.name.toLowerCase().includes(q))
      if (matchClass || matchingMethods.length > 0) {
        return {
          ...c,
          methods: matchClass ? c.methods : matchingMethods,
        }
      }
      return null
    })
    .filter(Boolean)
})

async function fetchClasses() {
  loading.value = true
  try {
    const res = await fetch(`${props.apiBase}/studio/classes`)
    const data = await res.json()
    if (data.success) {
      classes.value = data.data
      // Espandi di default le prime 5 classi
      classes.value.slice(0, 5).forEach((c) => {
        expandedClasses[c.class_name] = true
      })
    }
  } catch (err) {
    showToast('Errore nella scansione delle classi.', 'error')
  } finally {
    loading.value = false
  }
}

function toggleClassExpand(className) {
  expandedClasses[className] = !expandedClasses[className]
}

async function toggleMethod(method, cls) {
  updatingMethod.value = method.target
  try {
    const newStatus = !method.is_tracked
    const res = await fetch(`${props.apiBase}/studio/rules`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: 'method',
        target: method.target,
        name: `${cls.short_name}::${method.name}`,
        stack_level: method.stack_level || 'core',
        is_active: newStatus,
      }),
    })
    const data = await res.json()
    if (data.success) {
      method.is_tracked = newStatus
      method.rule_id = data.data.id
      showToast(newStatus ? `Metodo ${method.name} sotto monitoraggio!` : `Metodo ${method.name} rimosso dal monitoraggio`)
      emit('rule-updated')
    }
  } catch (err) {
    showToast('Errore aggiornamento metodo', 'error')
  } finally {
    updatingMethod.value = null
  }
}

/* ------------------------------------------------------------------ */
/*  TAB 3: Monitoraggio Utente Live                                   */
/* ------------------------------------------------------------------ */
const userSearch = ref('')
const userResults = ref([])
const selectedUser = ref(null)
const sessionDuration = ref(15) // default 15 min
const activeSessions = ref([])
const userSearching = ref(false)
let countdownInterval = null

async function searchUsers() {
  if (!userSearch.value || userSearch.value.length < 2) {
    userResults.value = []
    return
  }
  userSearching.value = true
  try {
    const res = await fetch(`${props.apiBase}/studio/users?q=${encodeURIComponent(userSearch.value)}`)
    const data = await res.json()
    if (data.success) {
      userResults.value = data.data
    }
  } catch (e) {
    //
  } finally {
    userSearching.value = false
  }
}

function selectUser(user) {
  selectedUser.value = user
  userSearch.value = user.name || user.email
  userResults.value = []
}

async function fetchRulesAndSessions() {
  try {
    const res = await fetch(`${props.apiBase}/studio/rules`)
    const data = await res.json()
    if (data.success) {
      activeSessions.value = data.data
        .filter((r) => r.type === 'user_session' && !r.is_expired)
        .map((s) => {
          const remaining = Math.max(0, Math.floor((new Date(s.expires_at) - new Date()) / 1000))
          return {
            ...s,
            seconds_left: remaining,
          }
        })
    }
  } catch (e) {
    //
  }
}

async function startSession() {
  if (!selectedUser.value) {
    showToast('Seleziona prima un utente da monitorare', 'error')
    return
  }

  loading.value = true
  try {
    const res = await fetch(`${props.apiBase}/studio/user-session`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: selectedUser.value.id,
        duration_minutes: sessionDuration.value,
        user_name: selectedUser.value.name,
        user_email: selectedUser.value.email,
      }),
    })
    const data = await res.json()
    if (data.success) {
      showToast(`Monitoraggio attivo per ${selectedUser.value.name} (${sessionDuration.value}m)`)
      selectedUser.value = null
      userSearch.value = ''
      await fetchRulesAndSessions()
      emit('rule-updated')
    }
  } catch (e) {
    showToast('Errore avvio sessione', 'error')
  } finally {
    loading.value = false
  }
}

async function stopSession(session) {
  try {
    const res = await fetch(`${props.apiBase}/studio/user-session/${session.id}`, {
      method: 'DELETE',
    })
    const data = await res.json()
    if (data.success) {
      showToast('Monitoraggio utente interrotto')
      await fetchRulesAndSessions()
      emit('rule-updated')
    }
  } catch (e) {
    showToast('Errore interruzione sessione', 'error')
  }
}

function formatCountdown(totalSeconds) {
  if (totalSeconds <= 0) return 'Scaduto'
  const m = Math.floor(totalSeconds / 60)
  const s = totalSeconds % 60
  return `${m}m ${s < 10 ? '0' : ''}${s}s`
}

/* ------------------------------------------------------------------ */
/*  Lifecycle                                                         */
/* ------------------------------------------------------------------ */
onMounted(() => {
  fetchRoutes()
  fetchClasses()
  fetchRulesAndSessions()

  countdownInterval = setInterval(() => {
    activeSessions.value.forEach((s) => {
      if (s.seconds_left > 0) {
        s.seconds_left--
      }
    })
  }, 1000)
})

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval)
})
</script>

<template>
  <div class="tracking-studio">
    <!-- Header Studio -->
    <div class="studio-header">
      <div class="studio-title-block">
        <div class="studio-badge">⚡ ZERO-CODE TRACKING STUDIO</div>
        <h2>Configurazione Tracciamento Dinamico</h2>
        <p>Attiva o disattiva il monitoraggio permanente su rotte e funzioni, o traccia un utente specifico senza scrivere codice.</p>
      </div>

      <!-- Tab Switcher -->
      <div class="studio-nav">
        <button
          :class="['nav-btn', { active: activeTab === 'routes' }]"
          @click="activeTab = 'routes'"
        >
          <span>🗺️ Studio Rotte (Pagine & API)</span>
          <span class="count-pill">{{ routes.length }}</span>
        </button>
        <button
          :class="['nav-btn', { active: activeTab === 'classes' }]"
          @click="activeTab = 'classes'"
        >
          <span>⚙️ Studio Funzioni (Metodi PHP)</span>
          <span class="count-pill">{{ classes.length }}</span>
        </button>
        <button
          :class="['nav-btn', { active: activeTab === 'user_sessions' }]"
          @click="activeTab = 'user_sessions'"
        >
          <span>👤 Monitor Utente Live (A Tempo)</span>
          <span v-if="activeSessions.length > 0" class="pulse-pill">{{ activeSessions.length }} ATTIVO</span>
        </button>
      </div>
    </div>

    <!-- Toast Alert -->
    <div v-if="toastMessage" :class="['toast-notification', toastType]">
      <span>{{ toastType === 'success' ? '✅' : '⚠️' }}</span>
      <span>{{ toastMessage }}</span>
    </div>

    <!-- -------------------------------------------------------------- -->
    <!-- TAB 1: ROTTE                                                    -->
    <!-- -------------------------------------------------------------- -->
    <div v-if="activeTab === 'routes'" class="tab-content">
      <!-- Guida Rapida Rotte -->
      <div class="guide-banner">
        <span class="guide-icon">💡</span>
        <div>
          <strong>Cosa fa lo Studio Rotte:</strong>
          <p>
            Tutte le rotte dell'applicazione sono scoperte automaticamente. Clicca sull'interruttore <strong>(ON)</strong> per iniziare a salvarne le chiamate nel registro storico in modo permanente, senza toccare codice. Scegli <em>Base</em> per salvare tempi e parametri, <em>🎯 Core</em> per evidenziare i tuoi file PHP, oppure <em>🔍 Completo</em> per l'intero stack trace.
          </p>
        </div>
      </div>

      <!-- Toolbar Filtri Rotte -->
      <div class="studio-toolbar">
        <div class="search-box">
          <input
            v-model="routeSearch"
            type="text"
            placeholder="Cerca rotta (es. api/ordini, CheckoutController, nome rotta...)"
          />
          <button v-if="routeSearch" class="clear-btn" @click="routeSearch = ''">✕</button>
        </div>

        <div class="filter-group">
          <select v-model="selectedArea">
            <option value="all">Tutte le Aree</option>
            <option value="API">Solo API</option>
            <option value="Web">Solo Web</option>
            <option value="Admin">Solo Admin</option>
          </select>

          <select v-model="selectedMethod">
            <option value="all">Tutti i Verbi HTTP</option>
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="PUT">PUT</option>
            <option value="DELETE">DELETE</option>
            <option value="PATCH">PATCH</option>
          </select>

          <label class="checkbox-label">
            <input v-model="onlyTrackedRoutes" type="checkbox" />
            <span>Mostra solo attive</span>
          </label>
        </div>
      </div>

      <!-- Elenco Rotte -->
      <div class="routes-table-container">
        <table class="studio-table">
          <thead>
            <tr>
              <th style="width: 130px;">
                Stato Log
                <span class="info-pill" title="Accende o spegne il salvataggio dei log su questa rotta in tempo reale">ℹ️</span>
              </th>
              <th style="width: 130px;">
                Metodo
                <span class="info-pill" title="Verbo HTTP della richiesta (GET, POST, PUT, DELETE, PATCH)">ℹ️</span>
              </th>
              <th>Percorso Rotta (URI)</th>
              <th>Controller & Azione PHP</th>
              <th style="width: 200px;">
                Livello Dettaglio
                <span class="info-pill" title="Base: Salva richiesta e tempi | Core: Isola i file in app/ (Consigliato) | Completo: include l'intero framework">ℹ️</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in filteredRoutes"
              :key="r.uri"
              :class="{ 'row-active': r.is_tracked }"
            >
              <!-- Switch ON/OFF -->
              <td>
                <button
                  :class="['switch-toggle', { on: r.is_tracked }]"
                  :disabled="updatingRoute === r.uri"
                  @click="toggleRoute(r)"
                  :title="r.is_tracked ? 'Tracciamento attivo (clicca per disattivare)' : 'Tracciamento disattivo (clicca per attivare)'"
                >
                  <span class="toggle-slider"></span>
                  <span class="toggle-label">{{ r.is_tracked ? 'ON' : 'OFF' }}</span>
                </button>
              </td>

              <!-- Metodi HTTP -->
              <td>
                <div class="methods-badges">
                  <span
                    v-for="m in r.methods"
                    :key="m"
                    :class="['verb-badge', m.toLowerCase()]"
                  >
                    {{ m }}
                  </span>
                </div>
              </td>

              <!-- URI e Area -->
              <td>
                <div class="uri-block">
                  <span class="area-badge">{{ r.area }}</span>
                  <span class="uri-text">/{{ r.clean_uri }}</span>
                </div>
                <div v-if="r.name" class="route-name-sub">
                  Nome: {{ r.name }}
                </div>
              </td>

              <!-- Controller & Action -->
              <td>
                <div class="ctrl-block">
                  <span class="ctrl-name">{{ r.controller }}</span>
                  <span v-if="r.controller_method" class="ctrl-action">@{{ r.controller_method }}</span>
                </div>
              </td>

              <!-- Livello Stack -->
              <td>
                <select
                  class="stack-select"
                  :value="r.stack_level"
                  :disabled="!r.is_tracked"
                  @change="updateRouteStackLevel(r, $event.target.value)"
                >
                  <option value="base">Base (Richiesta + Durata)</option>
                  <option value="core">🎯 Core (Consigliato: file app/)</option>
                  <option value="full">🔍 Completo (Tecnico: include Vendor)</option>
                </select>
              </td>
            </tr>

            <tr v-if="filteredRoutes.length === 0">
              <td colspan="5" class="empty-state">
                Nessuna rotta corrispondente ai filtri impostati.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- -------------------------------------------------------------- -->
    <!-- TAB 2: FUNZIONI & SERVIZI                                       -->
    <!-- -------------------------------------------------------------- -->
    <div v-if="activeTab === 'classes'" class="tab-content">
      <!-- Guida Rapida Funzioni -->
      <div class="guide-banner">
        <span class="guide-icon">💡</span>
        <div>
          <strong>Cosa fa lo Studio Funzioni:</strong>
          <p>
            Rileva in tempo reale le classi di logica applicativa in <code>app/</code>. Cliccando su <strong>"LOG"</strong> viene attivato un proxy dinamico: ogni volta che il metodo viene invocato, registra gli argomenti, il tempo di esecuzione e l'esito, senza modificare il codice PHP originale.
          </p>
        </div>
      </div>

      <div class="studio-toolbar">
        <div class="search-box">
          <input
            v-model="classSearch"
            type="text"
            placeholder="Cerca classe o metodo (es. OrderService, calcolaTotale, Payment...)"
          />
          <button v-if="classSearch" class="clear-btn" @click="classSearch = ''">✕</button>
        </div>
      </div>

      <div class="classes-grid">
        <div
          v-for="cls in filteredClasses"
          :key="cls.class_name"
          class="class-card"
        >
          <!-- Class Header -->
          <div class="class-card-header" @click="toggleClassExpand(cls.class_name)">
            <div class="class-info">
              <span class="category-pill">{{ cls.category }}</span>
              <span class="class-title">{{ cls.short_name }}</span>
              <span class="class-fqn">{{ cls.class_name }}</span>
            </div>
            <div class="class-meta">
              <span class="method-count">{{ cls.methods.length }} metodi</span>
              <span class="chevron">{{ expandedClasses[cls.class_name] ? '▲' : '▼' }}</span>
            </div>
          </div>

          <!-- Methods List -->
          <div v-if="expandedClasses[cls.class_name]" class="methods-list">
            <div
              v-for="m in cls.methods"
              :key="m.target"
              :class="['method-row', { 'method-active': m.is_tracked }]"
            >
              <div class="method-meta">
                <button
                  :class="['switch-toggle small', { on: m.is_tracked }]"
                  :disabled="updatingMethod === m.target"
                  @click="toggleMethod(m, cls)"
                  :title="m.is_tracked ? 'Intercettazione attiva' : 'Clicca per intercettare questo metodo'"
                >
                  <span class="toggle-slider"></span>
                  <span class="toggle-label">{{ m.is_tracked ? 'LOG' : 'OFF' }}</span>
                </button>

                <div class="method-sig">
                  <span class="method-name">{{ m.name }}</span>
                  <span class="method-params">(
                    <span v-for="(p, idx) in m.parameters" :key="p.name">
                      <span class="p-type">{{ p.type }}</span> ${{ p.name }}<span v-if="idx < m.parameters.length - 1">, </span>
                    </span>
                  )</span>
                  <span class="method-ret">: {{ m.return_type }}</span>
                </div>
              </div>

              <div class="method-badge-status">
                <span v-if="m.is_tracked" class="tracked-indicator">⚡ Proxy Interceptor Attivo</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="filteredClasses.length === 0" class="empty-state card">
          Nessuna classe applicativa trovata nella cartella app/
        </div>
      </div>
    </div>

    <!-- -------------------------------------------------------------- -->
    <!-- TAB 3: MONITOR UTENTE LIVE                                      -->
    <!-- -------------------------------------------------------------- -->
    <div v-if="activeTab === 'user_sessions'" class="tab-content">
      <!-- Guida Rapida Utente Live -->
      <div class="guide-banner">
        <span class="guide-icon">💡</span>
        <div>
          <strong>Cosa fa il Monitor Utente Live:</strong>
          <p>
            Permette di tracciare in tempo reale tutte le azioni compiute da un singolo cliente per una finestra temporale (es. 15 minuti). È pensato per dare supporto mirato quando un cliente segnala un malfunzionamento, registrando tutti i dettagli e disattivandosi da solo allo scadere del timer.
          </p>
        </div>
      </div>

      <div class="user-tracker-layout">
        <!-- Pannello di Avvio -->
        <div class="session-creator-card">
          <h3>⏱️ Avvia Monitoraggio Utente Live</h3>
          <p class="section-desc">
            Traccia tutte le operazioni eseguite da uno specifico utente per i prossimi X minuti con Stack Trace completo.
          </p>

          <!-- Ricerca Utente -->
          <div class="form-group">
            <label>Seleziona Utente da Monitorare:</label>
            <div class="user-search-wrapper">
              <input
                v-model="userSearch"
                type="text"
                placeholder="Digita nome, cognome o email utente..."
                @input="searchUsers"
              />
              <div v-if="userSearching" class="spinner-inline">...</div>
            </div>

            <!-- Tendina Risultati -->
            <div v-if="userResults.length > 0" class="user-autocomplete-list">
              <div
                v-for="u in userResults"
                :key="u.id"
                class="user-result-item"
                @click="selectUser(u)"
              >
                <div class="u-name">{{ u.name }}</div>
                <div class="u-email">{{ u.email || 'ID: ' + u.id }}</div>
              </div>
            </div>

            <div v-if="selectedUser" class="selected-user-pill">
              <span>👤 Utente selezionato: <strong>{{ selectedUser.name }}</strong> ({{ selectedUser.email || selectedUser.id }})</span>
              <button class="remove-btn" @click="selectedUser = null">✕</button>
            </div>
          </div>

          <!-- Durata Sessione -->
          <div class="form-group">
            <label>Finestra Temporale di Monitoraggio:</label>
            <div class="duration-buttons">
              <button
                v-for="m in [5, 15, 30, 60]"
                :key="m"
                :class="['duration-btn', { active: sessionDuration === m }]"
                @click="sessionDuration = m"
                :title="`Registra l'utente per ${m} minuti prima di disattivarsi automaticamente`"
              >
                ⏱️ {{ m }} Minuti
              </button>
            </div>
          </div>

          <!-- Bottone di Avvio -->
          <button
            class="start-session-btn"
            :disabled="!selectedUser || loading"
            @click="startSession"
          >
            ⚡ Avvia Monitoraggio Live ({{ sessionDuration }} min)
          </button>
        </div>

        <!-- Sessioni Attive in Corso -->
        <div class="active-sessions-card">
          <h3>🔴 Sessioni di Monitoraggio Attive</h3>
          <p class="section-desc">Sessioni in corso con registrazione live dello stack trace:</p>

          <div v-if="activeSessions.length > 0" class="sessions-list">
            <div
              v-for="s in activeSessions"
              :key="s.id"
              class="active-session-item"
            >
              <div class="session-user-info">
                <div class="pulse-indicator"></div>
                <div>
                  <div class="user-target-title">{{ s.metadata?.user_name || 'Utente #' + s.target }}</div>
                  <div class="user-target-sub">{{ s.metadata?.user_email || 'ID: ' + s.target }}</div>
                </div>
              </div>

              <div class="session-timer-block">
                <div class="countdown-display">
                  ⏱️ {{ formatCountdown(s.seconds_left) }}
                </div>
                <button class="stop-btn" @click="stopSession(s)">
                  ⏹️ Interrompi
                </button>
              </div>
            </div>
          </div>

          <div v-else class="empty-sessions">
            <div class="empty-icon">🟢</div>
            <p>Nessun monitoraggio utente attivo al momento.</p>
            <span>Seleziona un utente dal form a fianco per attivarlo istantaneamente.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tracking-studio {
  font-family: inherit;
  color: #1e293b;
}

/* Header */
.studio-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}

.studio-badge {
  display: inline-block;
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 9999px;
  letter-spacing: 0.05em;
  margin-bottom: 6px;
}

.studio-title-block h2 {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.studio-title-block p {
  color: #64748b;
  font-size: 13px;
  margin: 0;
}

/* Nav tabs */
.studio-nav {
  display: flex;
  background: #f1f5f9;
  padding: 4px;
  border-radius: 10px;
  gap: 4px;
}

.nav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  border: none;
  background: transparent;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
}

.nav-btn.active {
  background: #fff;
  color: #4f46e5;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.count-pill {
  background: #e2e8f0;
  padding: 2px 7px;
  border-radius: 9999px;
  font-size: 11px;
}

.pulse-pill {
  background: #ef4444;
  color: #fff;
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 9999px;
  font-weight: 700;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

/* Toast */
.toast-notification {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 16px;
  animation: fadeIn 0.3s;
}

.toast-notification.success {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.toast-notification.error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Tooltips & Guida Contestuale */
.has-tooltip {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  cursor: help;
}
.info-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 15px;
  height: 15px;
  background: #e2e8f0;
  color: #64748b;
  border-radius: 50%;
  font-size: 10px;
  font-weight: 700;
  cursor: help;
  transition: all 0.2s;
  user-select: none;
}
.has-tooltip:hover .info-pill {
  background: #4f46e5;
  color: #fff;
}
.tooltip-card {
  visibility: hidden;
  opacity: 0;
  position: absolute;
  bottom: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%);
  background: #0f172a;
  color: #f8fafc;
  text-align: left;
  padding: 9px 12px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.45;
  min-width: 210px;
  max-width: 310px;
  box-shadow: 0 12px 30px rgba(0,0,0,0.4), 0 0 0 1px #334155;
  z-index: 1000;
  transition: opacity 0.2s, visibility 0.2s;
  pointer-events: none;
  text-transform: none;
}
.tooltip-card::after {
  content: "";
  position: absolute;
  top: 100%;
  left: 50%;
  margin-left: -6px;
  border-width: 6px;
  border-style: solid;
  border-color: #0f172a transparent transparent transparent;
}
.has-tooltip:hover .tooltip-card {
  visibility: visible;
  opacity: 1;
}

.guide-banner {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-left: 4px solid #4f46e5;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 16px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 13px;
  color: #334155;
  line-height: 1.5;
}
.guide-banner .guide-icon {
  font-size: 18px;
  flex-shrink: 0;
}
.guide-banner strong {
  color: #0f172a;
}
.guide-banner p {
  margin: 0;
}

/* Toolbar */
.studio-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 260px;
}

.search-box input {
  width: 100%;
  padding: 9px 14px;
  padding-right: 32px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13px;
  box-sizing: border-box;
}

.clear-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.filter-group select {
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13px;
  background: #fff;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #475569;
  cursor: pointer;
}

/* Table */
.routes-table-container {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.studio-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
}

.studio-table th {
  background: #f8fafc;
  padding: 12px 16px;
  font-weight: 700;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.studio-table td {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.studio-table tr.row-active {
  background: #faf5ff;
}

/* Switch Toggle */
.switch-toggle {
  position: relative;
  display: inline-flex;
  align-items: center;
  width: 60px;
  height: 28px;
  background: #cbd5e1;
  border: none;
  border-radius: 9999px;
  cursor: pointer;
  padding: 2px;
  transition: background 0.25s;
}

.switch-toggle.on {
  background: #4f46e5;
}

.switch-toggle.small {
  width: 52px;
  height: 24px;
}

.toggle-slider {
  position: absolute;
  left: 3px;
  width: 22px;
  height: 22px;
  background: #fff;
  border-radius: 50%;
  transition: transform 0.25s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.switch-toggle.small .toggle-slider {
  width: 18px;
  height: 18px;
}

.switch-toggle.on .toggle-slider {
  transform: translateX(32px);
}

.switch-toggle.small.on .toggle-slider {
  transform: translateX(28px);
}

.toggle-label {
  width: 100%;
  text-align: right;
  padding-right: 8px;
  font-size: 10px;
  font-weight: 800;
  color: #fff;
}

.switch-toggle.on .toggle-label {
  text-align: left;
  padding-left: 8px;
}

/* Badges */
.methods-badges {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.verb-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  color: #fff;
}

.verb-badge.get { background: #10b981; }
.verb-badge.post { background: #3b82f6; }
.verb-badge.put { background: #f59e0b; }
.verb-badge.delete { background: #ef4444; }
.verb-badge.patch { background: #8b5cf6; }

.uri-block {
  display: flex;
  align-items: center;
  gap: 8px;
}

.area-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  background: #e2e8f0;
  color: #475569;
  border-radius: 4px;
}

.uri-text {
  font-family: monospace;
  font-weight: 600;
  color: #0f172a;
}

.route-name-sub {
  font-size: 11px;
  color: #94a3b8;
  margin-top: 2px;
}

.ctrl-name {
  font-weight: 600;
  color: #334155;
}

.ctrl-action {
  color: #6366f1;
  font-weight: 600;
}

.stack-select {
  padding: 6px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 12px;
  background: #fff;
  width: 100%;
}

/* Classes Tab */
.classes-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.class-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.class-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  cursor: pointer;
  background: #f8fafc;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.15s;
}

.class-card-header:hover {
  background: #f1f5f9;
}

.class-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.category-pill {
  background: #e0e7ff;
  color: #4338ca;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.class-title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
}

.class-fqn {
  font-size: 12px;
  color: #94a3b8;
  font-family: monospace;
}

.class-meta {
  display: flex;
  align-items: center;
  gap: 12px;
}

.method-count {
  font-size: 12px;
  color: #64748b;
  font-weight: 600;
}

.methods-list {
  padding: 8px 18px;
}

.method-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #f8fafc;
}

.method-row:last-child {
  border-bottom: none;
}

.method-row.method-active {
  background: #fdf4ff;
  padding-left: 8px;
  padding-right: 8px;
  border-radius: 6px;
}

.method-meta {
  display: flex;
  align-items: center;
  gap: 12px;
}

.method-sig {
  font-family: monospace;
  font-size: 13px;
}

.method-name {
  font-weight: 700;
  color: #4338ca;
}

.p-type {
  color: #059669;
}

.method-ret {
  color: #9333ea;
}

.tracked-indicator {
  font-size: 11px;
  font-weight: 700;
  color: #7c3aed;
}

/* User Live Tab */
.user-tracker-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

@media (max-width: 860px) {
  .user-tracker-layout {
    grid-template-columns: 1fr;
  }
}

.session-creator-card,
.active-sessions-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.session-creator-card h3,
.active-sessions-card h3 {
  margin: 0 0 6px 0;
  font-size: 17px;
  font-weight: 800;
  color: #0f172a;
}

.section-desc {
  font-size: 13px;
  color: #64748b;
  margin: 0 0 20px 0;
  line-height: 1.5;
}

.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 8px;
}

.user-search-wrapper {
  position: relative;
}

.user-search-wrapper input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  box-sizing: border-box;
}

.user-autocomplete-list {
  background: #fff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  margin-top: 4px;
  max-height: 200px;
  overflow-y: auto;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.user-result-item {
  padding: 10px 14px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
}

.user-result-item:hover {
  background: #f8fafc;
}

.user-result-item .u-name {
  font-weight: 700;
  color: #0f172a;
}

.user-result-item .u-email {
  font-size: 12px;
  color: #64748b;
}

.selected-user-pill {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #e0e7ff;
  color: #3730a3;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 13px;
  margin-top: 8px;
}

.remove-btn {
  background: none;
  border: none;
  color: #4f46e5;
  font-weight: 700;
  cursor: pointer;
}

.duration-buttons {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.duration-btn {
  padding: 10px;
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s;
}

.duration-btn.active {
  background: #4f46e5;
  color: #fff;
  border-color: #4f46e5;
}

.start-session-btn {
  width: 100%;
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: #fff;
  border: none;
  padding: 14px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
  transition: opacity 0.2s;
}

.start-session-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Active sessions */
.active-session-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 10px;
}

.session-user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pulse-indicator {
  width: 12px;
  height: 12px;
  background: #ef4444;
  border-radius: 50%;
  animation: pulse 1s infinite;
}

.user-target-title {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}

.user-target-sub {
  font-size: 12px;
  color: #64748b;
}

.session-timer-block {
  display: flex;
  align-items: center;
  gap: 12px;
}

.countdown-display {
  font-family: monospace;
  font-size: 14px;
  font-weight: 800;
  background: #fee2e2;
  color: #991b1b;
  padding: 4px 10px;
  border-radius: 6px;
}

.stop-btn {
  background: #ef4444;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.empty-sessions {
  text-align: center;
  padding: 40px 20px;
  color: #94a3b8;
}

.empty-icon {
  font-size: 28px;
  margin-bottom: 8px;
}
</style>
