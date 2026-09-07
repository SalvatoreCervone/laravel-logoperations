<script setup>
/**
 * LogQueryBuilder.vue
 *
 * Query builder avanzato a gruppi logici con operatori AND / OR / NOT.
 * Retrocompatibile con il formato di ricerca a gruppi del vecchio frontend.
 */
import { computed } from 'vue'

const props = defineProps({
  groups: { type: Array, required: true },
  httpCodes: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:groups', 'search', 'add-group', 'remove-group'])

const operatoreGruppoOptions = ['AND', 'OR', 'NOT']
const operatoreCampiOptions = ['AND', 'OR']

const availableHttpCodes = computed(() => {
  const defaults = [200, 201, 400, 401, 403, 404, 422, 500, 502, 503]
  const combined = Array.from(new Set([...(props.httpCodes || []), ...defaults]))
  return combined.sort((a, b) => a - b)
})

function updateGroup(index, field, value) {
  const updated = [...props.groups]
  updated[index] = { ...updated[index], [field]: value }
  emit('update:groups', updated)
}

function toggleHttpCode(groupIndex, code) {
  const group = props.groups[groupIndex]
  const codes = [...(group.codicehttp || [])]
  const idx = codes.indexOf(code)
  if (idx >= 0) {
    codes.splice(idx, 1)
  } else {
    codes.push(code)
  }
  updateGroup(groupIndex, 'codicehttp', codes)
}

function handleSearch() {
  // Validazione: almeno un gruppo con un criterio
  const hasData = props.groups.some(g =>
    (g.search || '').trim() || (g.ip || '').trim() || (g.controller || '').trim()
    || (g.codicehttp && g.codicehttp.length) || g.data_da || g.data_a
  )

  if (!hasData) {
    alert('Inserisci almeno un criterio di ricerca in uno dei gruppi.')
    return
  }

  emit('search')
}
</script>

<template>
<div class="qb-container">
  <div class="qb-header">
    <div class="qb-header__left">
      <h3 class="qb-title">Query Builder Avanzato</h3>
      <span class="qb-subtitle">Combina criteri con operatori logici tra gruppi</span>
    </div>
    <div class="qb-header__right">
      <button class="btn btn--outline btn--sm" @click="$emit('add-group')">
        + Aggiungi Gruppo
      </button>
      <button class="btn btn--primary" @click="handleSearch">
        🔍 Cerca
      </button>
    </div>
  </div>

  <div class="qb-groups">
    <div
      v-for="(group, index) in groups"
      :key="index"
      class="qb-group"
    >
      <!-- GROUP CONNECTOR -->
      <div v-if="index > 0" class="qb-connector">
        <div class="qb-connector__line"></div>
        <select
          :value="group.operatoreGruppo"
          @change="updateGroup(index, 'operatoreGruppo', $event.target.value)"
          class="qb-connector__select"
        >
          <option v-for="op in operatoreGruppoOptions" :key="op" :value="op">{{ op }}</option>
        </select>
        <div class="qb-connector__line"></div>
      </div>

      <!-- GROUP CARD -->
      <div class="qb-group-card">
        <div class="qb-group-card__header">
          <div class="qb-group-card__title">
            <span class="qb-group-card__number">{{ index + 1 }}</span>
            <span>Gruppo {{ index + 1 }}</span>
          </div>

          <div class="qb-group-card__controls">
            <label class="qb-group-card__operator-label">Operatore campi:</label>
            <select
              :value="group.operatoreCampi"
              @change="updateGroup(index, 'operatoreCampi', $event.target.value)"
              class="qb-operator-select"
            >
              <option v-for="op in operatoreCampiOptions" :key="op" :value="op">{{ op }}</option>
            </select>

            <button
              v-if="groups.length > 1"
              class="qb-group-card__delete"
              @click="$emit('remove-group', index)"
              title="Elimina gruppo"
            >
              ✕
            </button>
          </div>
        </div>

        <div class="qb-group-card__fields">
          <!-- UTENTE -->
          <div class="qb-field">
            <label>Utente / Email</label>
            <input
              type="text"
              :value="group.search"
              @input="updateGroup(index, 'search', $event.target.value)"
              placeholder="Nome, cognome o email"
              class="qb-input"
            />
          </div>

          <!-- IP -->
          <div class="qb-field">
            <label>Indirizzo IP</label>
            <input
              type="text"
              :value="group.ip"
              @input="updateGroup(index, 'ip', $event.target.value)"
              placeholder="Indirizzo IP"
              class="qb-input"
            />
          </div>

          <!-- CONTROLLER -->
          <div class="qb-field">
            <label>Controller / Metodo</label>
            <input
              type="text"
              :value="group.controller"
              @input="updateGroup(index, 'controller', $event.target.value)"
              placeholder="Controller@metodo"
              class="qb-input"
            />
          </div>

          <!-- CODICE HTTP -->
          <div class="qb-field">
            <label>Codici HTTP</label>
            <div class="qb-http-codes">
              <button
                v-for="code in availableHttpCodes"
                :key="code"
                :class="['qb-http-chip', {
                  active: (group.codicehttp || []).includes(code),
                  'qb-http-chip--2xx': code >= 200 && code < 300,
                  'qb-http-chip--3xx': code >= 300 && code < 400,
                  'qb-http-chip--4xx': code >= 400 && code < 500,
                  'qb-http-chip--5xx': code >= 500,
                }]"
                @click="toggleHttpCode(index, code)"
              >
                {{ code }}
              </button>
            </div>
          </div>

          <!-- DATE -->
          <div class="qb-field">
            <label>Data Da</label>
            <input
              type="datetime-local"
              :value="group.data_da"
              @input="updateGroup(index, 'data_da', $event.target.value)"
              class="qb-input"
            />
          </div>

          <div class="qb-field">
            <label>Data A</label>
            <input
              type="datetime-local"
              :value="group.data_a"
              @input="updateGroup(index, 'data_a', $event.target.value)"
              class="qb-input"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<style scoped>
.qb-container {
  margin-bottom: 20px;
  animation: fadeIn 0.3s ease;
}

.qb-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}

.qb-header__left { display: flex; flex-direction: column; gap: 2px; }
.qb-header__right { display: flex; gap: 8px; }

.qb-title {
  font-size: 1rem;
  font-weight: 700;
  margin: 0;
  color: var(--lo-text, #f1f5f9);
}

.qb-subtitle {
  font-size: 0.75rem;
  color: var(--lo-text-dim, #64748b);
}

/* ------------------------------------------------------------------ */
/*  Groups                                                             */
/* ------------------------------------------------------------------ */

.qb-groups {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.qb-connector {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
}

.qb-connector__line {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--lo-border, #334155), transparent);
}

.qb-connector__select {
  padding: 4px 12px;
  background: var(--lo-primary, #6366f1);
  border: none;
  border-radius: 9999px;
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}

/* ------------------------------------------------------------------ */
/*  Group Card                                                         */
/* ------------------------------------------------------------------ */

.qb-group-card {
  background: var(--lo-surface, #1e293b);
  border: 1px solid var(--lo-border, #334155);
  border-radius: var(--lo-radius, 10px);
  padding: 16px;
  transition: border-color 0.2s;
}
.qb-group-card:hover { border-color: var(--lo-border-light, #475569); }

.qb-group-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  flex-wrap: wrap;
  gap: 8px;
}

.qb-group-card__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--lo-text, #f1f5f9);
}

.qb-group-card__number {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: var(--lo-primary, #6366f1);
  color: white;
  border-radius: 50%;
  font-size: 0.75rem;
  font-weight: 700;
}

.qb-group-card__controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.qb-group-card__operator-label {
  font-size: 0.6875rem;
  color: var(--lo-text-dim, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.qb-operator-select {
  padding: 4px 10px;
  background: var(--lo-bg, #0f172a);
  border: 1px solid var(--lo-border, #334155);
  border-radius: var(--lo-radius-sm, 6px);
  color: var(--lo-text, #f1f5f9);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}

.qb-group-card__delete {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: 1px solid var(--lo-danger, #ef4444);
  border-radius: var(--lo-radius-sm, 6px);
  color: var(--lo-danger, #ef4444);
  cursor: pointer;
  font-size: 0.75rem;
  transition: all 0.2s;
}
.qb-group-card__delete:hover { background: var(--lo-danger, #ef4444); color: white; }

/* ------------------------------------------------------------------ */
/*  Fields                                                             */
/* ------------------------------------------------------------------ */

.qb-group-card__fields {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.qb-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.qb-field label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--lo-text-dim, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.qb-input {
  padding: 7px 12px;
  background: var(--lo-bg, #0f172a);
  border: 1px solid var(--lo-border, #334155);
  border-radius: var(--lo-radius-sm, 6px);
  color: var(--lo-text, #f1f5f9);
  font-size: 0.8125rem;
  font-family: inherit;
  transition: border-color 0.2s;
}
.qb-input:focus { outline: none; border-color: var(--lo-primary, #6366f1); }

/* ------------------------------------------------------------------ */
/*  HTTP Code Chips                                                    */
/* ------------------------------------------------------------------ */

.qb-http-codes {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.qb-http-chip {
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.6875rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid var(--lo-border, #334155);
  background: transparent;
  color: var(--lo-text-muted, #94a3b8);
  font-family: inherit;
}

.qb-http-chip:hover { border-color: var(--lo-text-muted, #94a3b8); }

.qb-http-chip.active.qb-http-chip--2xx { background: #10b981; color: white; border-color: #10b981; }
.qb-http-chip.active.qb-http-chip--3xx { background: #6366f1; color: white; border-color: #6366f1; }
.qb-http-chip.active.qb-http-chip--4xx { background: #f59e0b; color: white; border-color: #f59e0b; }
.qb-http-chip.active.qb-http-chip--5xx { background: #ef4444; color: white; border-color: #ef4444; }

/* ------------------------------------------------------------------ */
/*  Shared                                                             */
/* ------------------------------------------------------------------ */

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  font-family: inherit;
}
.btn--primary { background: #6366f1; color: white; }
.btn--primary:hover { background: #818cf8; }
.btn--outline { background: transparent; color: #94a3b8; border: 1px solid #334155; }
.btn--outline:hover { border-color: #6366f1; color: #6366f1; }
.btn--sm { padding: 6px 12px; font-size: 0.75rem; }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 640px) {
  .qb-group-card__fields { grid-template-columns: 1fr; }
  .qb-header { flex-direction: column; align-items: flex-start; }
}
</style>
