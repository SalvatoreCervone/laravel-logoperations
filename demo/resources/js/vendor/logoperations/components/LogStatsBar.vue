<script setup>
/**
 * LogStatsBar.vue
 *
 * Barra KPI con statistiche rapide per la dashboard dei log.
 * Mostra: Totale richieste, Tasso errori, Tempo medio, Transazioni pendenti.
 */
defineProps({
  stats: { type: Object, default: () => ({}) },
})
</script>

<template>
<div class="stats-bar">
  <!-- TOTALE RICHIESTE -->
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--total">📊</div>
    <div class="stat-card__content">
      <div class="stat-card__value">{{ (stats.total_requests || 0).toLocaleString('it-IT') }}</div>
      <div class="stat-card__label">Richieste Totali</div>
    </div>
  </div>

  <!-- TASSO ERRORI -->
  <div class="stat-card" :class="{ 'stat-card--alert': stats.error_rate > 5 }">
    <div class="stat-card__icon stat-card__icon--errors">🚨</div>
    <div class="stat-card__content">
      <div class="stat-card__value">
        {{ stats.total_errors || 0 }}
        <span class="stat-card__sub">({{ stats.error_rate || 0 }}%)</span>
      </div>
      <div class="stat-card__label">Errori</div>
    </div>
  </div>

  <!-- TEMPO MEDIO -->
  <div class="stat-card">
    <div class="stat-card__icon stat-card__icon--duration">⚡</div>
    <div class="stat-card__content">
      <div class="stat-card__value">
        {{ stats.avg_duration_ms != null ? stats.avg_duration_ms + ' ms' : '—' }}
      </div>
      <div class="stat-card__label">Durata Media</div>
    </div>
  </div>

  <!-- TRANSAZIONI PENDENTI -->
  <div class="stat-card" :class="{ 'stat-card--warn': stats.pending_transactions > 0 }">
    <div class="stat-card__icon stat-card__icon--transactions">⚠️</div>
    <div class="stat-card__content">
      <div class="stat-card__value">{{ stats.pending_transactions || 0 }}</div>
      <div class="stat-card__label">Transazioni Pendenti</div>
    </div>
  </div>

  <!-- TOP ERRORI (mini) -->
  <div v-if="stats.top_errors && stats.top_errors.length" class="stat-card stat-card--wide">
    <div class="stat-card__icon stat-card__icon--top">🏆</div>
    <div class="stat-card__content">
      <div class="stat-card__label" style="margin-bottom: 4px;">Top Errori</div>
      <div class="top-errors">
        <span
          v-for="err in stats.top_errors"
          :key="err.codicehttp"
          :class="['top-error-chip', {
            'top-error-chip--4xx': err.codicehttp >= 400 && err.codicehttp < 500,
            'top-error-chip--5xx': err.codicehttp >= 500,
          }]"
        >
          {{ err.codicehttp }} ({{ err.count }})
        </span>
      </div>
    </div>
  </div>
</div>
</template>

<style scoped>
.stats-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 160px;
  padding: 14px 16px;
  background: var(--lo-surface, #1e293b);
  border: 1px solid var(--lo-border, #334155);
  border-radius: var(--lo-radius, 10px);
  transition: all 0.2s;
}
.stat-card:hover { border-color: var(--lo-border-light, #475569); transform: translateY(-1px); }

.stat-card--wide { min-width: 220px; }

.stat-card--alert { border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.05); }
.stat-card--warn { border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.05); }

.stat-card__icon {
  font-size: 1.5rem;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  flex-shrink: 0;
}

.stat-card__icon--total { background: rgba(99, 102, 241, 0.15); }
.stat-card__icon--errors { background: rgba(239, 68, 68, 0.15); }
.stat-card__icon--duration { background: rgba(16, 185, 129, 0.15); }
.stat-card__icon--transactions { background: rgba(245, 158, 11, 0.15); }
.stat-card__icon--top { background: rgba(168, 85, 247, 0.15); }

.stat-card__content { flex: 1; }

.stat-card__value {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--lo-text, #f1f5f9);
  line-height: 1.2;
}

.stat-card__sub {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--lo-text-dim, #64748b);
}

.stat-card__label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--lo-text-dim, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.top-errors {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.top-error-chip {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.6875rem;
  font-weight: 700;
  color: white;
}

.top-error-chip--4xx { background: #f59e0b; }
.top-error-chip--5xx { background: #ef4444; }

@media (max-width: 768px) {
  .stats-bar { flex-direction: column; }
}
</style>
