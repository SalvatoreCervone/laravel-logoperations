/**
 * Log Operations - Vue 3 Components
 *
 * Entry point per l'importazione dei componenti Vue del pacchetto.
 *
 * Uso in applicazione Vue 3 / Inertia:
 *
 *   import { LogOperationsViewer } from './vendor/logoperations'
 *
 *   // Oppure registrazione globale:
 *   import LogOperationsPlugin from './vendor/logoperations'
 *   app.use(LogOperationsPlugin)
 */

import LogOperationsViewer from './components/LogOperationsViewer.vue'
import LogQueryBuilder from './components/LogQueryBuilder.vue'
import LogDetailModal from './components/LogDetailModal.vue'
import LogStatsBar from './components/LogStatsBar.vue'
import LogTrackingStudio from './components/LogTrackingStudio.vue'
import LogStoryboard from './components/LogStoryboard.vue'

// Export nominali per import selettivo
export {
  LogOperationsViewer,
  LogQueryBuilder,
  LogDetailModal,
  LogStatsBar,
  LogTrackingStudio,
  LogStoryboard,
}

// Export default come plugin Vue
export default {
  install(app) {
    app.component('LogOperationsViewer', LogOperationsViewer)
    app.component('LogQueryBuilder', LogQueryBuilder)
    app.component('LogDetailModal', LogDetailModal)
    app.component('LogStatsBar', LogStatsBar)
    app.component('LogTrackingStudio', LogTrackingStudio)
    app.component('LogStoryboard', LogStoryboard)
  },
}
