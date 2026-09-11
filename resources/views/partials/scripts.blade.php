<script>
    const { createApp, ref, computed, onMounted } = Vue;

    createApp({
        setup() {
            const apiPrefix = '{{ $apiPrefix }}';
            const currentTab = ref('logs');
            const logs = ref([]);
            const stats = ref({});
            const loading = ref(false);

            // Paginazione
            const currentPage = ref(1);
            const perPage = ref({{ $perPage }});
            const perPageOptions = ref(@json($perPageOptions));
            const pagination = ref({ total: 0, last_page: 1, from: 0, to: 0 });

            // Filtri
            const quickFilter = ref('all');
            const filterText = ref('');
            const filterVerb = ref('');
            const filterUser = ref('');
            const filterDateFrom = ref('');
            const filterDateTo = ref('');

            // Modal
            const activeLog = ref(null);
            const modalTab = ref('overview');
            const modalStackView = ref('core');
            const expandedModelGroups = ref({});

            // Tracking Studio State
            const routes = ref([]);
            const routeFilter = ref('');
            const routeVerbFilter = ref('all');
            const routeControllerFilter = ref('all');
            const routeTrackedFilter = ref('all');
            const selectedRouteKeys = ref([]);
            const bulkStackLevel = ref('core');
            const classes = ref([]);
            const classFilter = ref('');
            const usersList = ref([]);
            const activeSessions = ref([]);
            const targetUserId = ref('');
            const userDuration = ref(15);

            // Toast Notification State
            const toast = ref({
                show: false,
                message: '',
                type: 'success',
                timer: null,
            });

            function showToast(message, type = 'success', duration = 3500) {
                if (toast.value.timer) {
                    clearTimeout(toast.value.timer);
                }
                toast.value = {
                    show: true,
                    message,
                    type,
                    timer: setTimeout(() => {
                        toast.value.show = false;
                    }, duration)
                };
            }

            // Storyboard State
            const subjectsList = ref([]);
            const selectedSubjectKey = ref('');
            const customSubjectType = ref('');
            const customSubjectId = ref('');
            const storyboardData = ref({ events: [] });
            const storyboardLoading = ref(false);
            const storyboardCategory = ref('all');
            const storyboardSort = ref('asc');

            let debounceTimer = null;

            function debounceFetchLogs() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    currentPage.value = 1;
                    fetchLogs(1);
                }, 300);
            }

            function onFilterChange() {
                currentPage.value = 1;
                fetchLogs(1);
            }

            function setQuickFilter(key) {
                quickFilter.value = key;
                currentPage.value = 1;
                fetchLogs(1);
            }

            function resetFilters() {
                quickFilter.value = 'all';
                filterText.value = '';
                filterVerb.value = '';
                filterUser.value = '';
                filterDateFrom.value = '';
                filterDateTo.value = '';
                currentPage.value = 1;
                fetchLogs(1);
            }

            function getExportUrl(format) {
                const params = new URLSearchParams();
                params.set('format', format);
                if (filterText.value) params.set('text', filterText.value);
                if (filterVerb.value) params.set('verb', filterVerb.value);
                if (filterUser.value) params.set('user', filterUser.value);
                if (filterDateFrom.value) params.set('date_from', filterDateFrom.value);
                if (filterDateTo.value) params.set('date_to', filterDateTo.value);

                if (quickFilter.value === 'errors') params.set('has_error', '1');
                if (quickFilter.value === 'tx') params.set('has_unfinished_transaction', '1');
                if (quickFilter.value === 'slow') params.set('min_duration', '1000');
                if (quickFilter.value === '500') params.set('status_codes[]', '500');

                return `/${apiPrefix}/export?${params.toString()}`;
            }

            function exportData(format) {
                window.open(getExportUrl(format), '_blank');
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ function_exists('csrf_token') ? csrf_token() : '' }}';

            async function apiFetch(url, options = {}) {
                const headers = {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers || {})
                };
                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }
                if (options.body && typeof options.body === 'string' && !headers['Content-Type']) {
                    headers['Content-Type'] = 'application/json';
                }
                return fetch(url, { ...options, headers });
            }

            async function fetchLogs(page = 1) {
                loading.value = true;
                try {
                    const params = new URLSearchParams();
                    params.append('page', page.toString());
                    params.append('per_page', perPage.value.toString());

                    if (filterText.value) params.append('text', filterText.value);
                    if (filterVerb.value) params.append('verb', filterVerb.value);
                    if (filterUser.value) params.append('user', filterUser.value);
                    if (filterDateFrom.value) params.append('date_from', filterDateFrom.value);
                    if (filterDateTo.value) params.append('date_to', filterDateTo.value);

                    if (quickFilter.value === 'errors') params.append('has_error', '1');
                    if (quickFilter.value === 'tx') params.append('has_unfinished_transaction', '1');
                    if (quickFilter.value === 'slow') params.append('min_duration', '1000');
                    if (quickFilter.value === '500') params.append('status_codes[]', '500');

                    const res = await apiFetch(`/${apiPrefix}?${params.toString()}`);
                    const data = await res.json();

                    let list = [];
                    let total = 0, lastPage = 1, from = 0, to = 0, currPage = page;

                    if (Array.isArray(data)) {
                        list = data;
                        total = data.length;
                        from = data.length > 0 ? 1 : 0;
                        to = data.length;
                    } else if (Array.isArray(data.data)) {
                        list = data.data;
                        total = data.total ?? data.data.length;
                        lastPage = data.last_page ?? 1;
                        from = data.from ?? (data.data.length > 0 ? 1 : 0);
                        to = data.to ?? data.data.length;
                        currPage = data.current_page ?? page;
                    } else if (data.data && Array.isArray(data.data.data)) {
                        list = data.data.data;
                        total = data.data.total ?? data.data.data.length;
                        lastPage = data.data.last_page ?? 1;
                        from = data.data.from ?? (data.data.data.length > 0 ? 1 : 0);
                        to = data.data.to ?? data.data.data.length;
                        currPage = data.data.current_page ?? page;
                    } else if (data.logs && Array.isArray(data.logs)) {
                        list = data.logs;
                        total = data.total ?? data.logs.length;
                        lastPage = data.last_page ?? 1;
                    }

                    logs.value = list;
                    currentPage.value = currPage;
                    pagination.value = {
                        total: total,
                        last_page: lastPage,
                        from: from,
                        to: to,
                    };
                } catch (e) {
                    console.error('Fetch logs error:', e);
                } finally {
                    loading.value = false;
                }

                try {
                    const sRes = await apiFetch(`/${apiPrefix}/stats`);
                    const sData = await sRes.json();
                    stats.value = sData.data || sData || {};
                } catch (e) {}
            }

            function goToPage(page) {
                if (page >= 1 && page <= pagination.value.last_page && page !== currentPage.value) {
                    currentPage.value = page;
                    fetchLogs(page);
                }
            }

            function onPerPageChange() {
                currentPage.value = 1;
                fetchLogs(1);
            }

            const visiblePages = computed(() => {
                const total = pagination.value.last_page || 1;
                const current = currentPage.value;
                const pages = [];
                const delta = 2;

                for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
                    pages.push(i);
                }
                if (current - delta > 2) pages.unshift('...');
                pages.unshift(1);
                if (current + delta < total - 1) pages.push('...');
                if (total > 1) pages.push(total);
                return pages;
            });

            function getVerbClass(verbo) {
                switch ((verbo || '').toLowerCase()) {
                    case 'get': return 'badge-get';
                    case 'post': return 'badge-post';
                    case 'put': case 'patch': return 'badge-put';
                    case 'delete': return 'badge-delete';
                    default: return 'badge-get';
                }
            }

            function getStatusClass(status) {
                if (status >= 500) return 'status-500';
                if (status >= 400) return 'status-400';
                return 'status-200';
            }

            function formatTimestamp(ts) {
                if (!ts) return '—';
                const d = new Date(ts);
                return d.toLocaleDateString('it-IT') + ' ' + d.toLocaleTimeString('it-IT');
            }

            function formatSeconds(secs) {
                const m = Math.floor(secs / 60);
                const s = secs % 60;
                return `${m}m ${s < 10 ? '0' : ''}${s}s`;
            }

            async function openDetail(log) {
                try {
                    const res = await apiFetch(`/${apiPrefix}/${log.id}`);
                    const detail = await res.json();
                    const detailData = (detail.data && detail.data.data) ? detail.data.data : (detail.data || detail);
                    activeLog.value = {
                        ...detailData,
                        core_stack: detail.core_stack || (detail.data && detail.data.core_stack) || detailData.core_stack,
                        full_stack: detail.full_stack || (detail.data && detail.data.full_stack) || detailData.full_stack,
                        touched_models: detail.touched_models || (detail.data && detail.data.touched_models) || [],
                    };
                    expandedModelGroups.value = {};
                    modalTab.value = 'overview';
                    modalStackView.value = 'core';
                } catch (e) {
                    activeLog.value = log;
                }
            }

            function closeDetail() { activeLog.value = null; }

            const displayedStackFrames = computed(() => {
                if (!activeLog.value) return [];
                if (modalStackView.value === 'core') {
                    if (activeLog.value.core_stack && activeLog.value.core_stack.length) {
                        return activeLog.value.core_stack;
                    }
                    return (activeLog.value.stack_trace || []).filter(f => f.is_core);
                }
                return activeLog.value.full_stack || activeLog.value.stack_trace || [];
            });

            // Tracking Studio Logic
            async function fetchStudioData() {
                try {
                    const [rRes, cRes, ruRes, uRes] = await Promise.all([
                        apiFetch(`/${apiPrefix}/studio/routes`),
                        apiFetch(`/${apiPrefix}/studio/classes`),
                        apiFetch(`/${apiPrefix}/studio/rules`),
                        apiFetch(`/${apiPrefix}/studio/users`),
                    ]);
                    const rData = await rRes.json();
                    routes.value = rData.data || (Array.isArray(rData) ? rData : []);

                    const cData = await cRes.json();
                    classes.value = cData.data || (Array.isArray(cData) ? cData : []);

                    const ruData = await ruRes.json();
                    const rules = ruData.data || (Array.isArray(ruData) ? ruData : []);

                    const uData = await uRes.json();
                    usersList.value = uData.data || (Array.isArray(uData) ? uData : []);

                    activeSessions.value = rules
                        .filter(r => r.type === 'user_session' && r.is_active && !r.is_expired)
                        .map(s => ({
                            ...s,
                            seconds_left: s.seconds_remaining != null ? s.seconds_remaining : (s.expires_at ? Math.max(0, Math.floor((new Date(s.expires_at) - new Date()) / 1000)) : 0)
                        }));
                } catch (e) {
                    console.error('Error in fetchStudioData:', e);
                }
            }

            const uniqueControllers = computed(() => {
                if (!Array.isArray(routes.value)) return [];
                const set = new Set();
                routes.value.forEach(r => {
                    if (r.controller) {
                        set.add(r.controller);
                    }
                });
                return Array.from(set).sort();
            });

            function getRouteKey(r) {
                const uri = r.clean_uri || r.uri || '/';
                const methods = (r.methods && r.methods.length) ? r.methods.slice().sort().join(',') : '*';
                return uri + '::' + methods;
            }

            const filteredRoutes = computed(() => {
                if (!Array.isArray(routes.value)) return [];
                return routes.value.filter(r => {
                    // 1. Filtro Verbo HTTP
                    if (routeVerbFilter.value !== 'all') {
                        const verb = routeVerbFilter.value.toUpperCase();
                        if (!r.methods || !r.methods.some(m => m.toUpperCase() === verb)) {
                            return false;
                        }
                    }

                    // 2. Filtro Controller
                    if (routeControllerFilter.value !== 'all') {
                        if (!r.controller || r.controller !== routeControllerFilter.value) {
                            return false;
                        }
                    }

                    // 3. Filtro Stato Tracciamento
                    if (routeTrackedFilter.value === 'tracked' && !r.is_tracked) {
                        return false;
                    }
                    if (routeTrackedFilter.value === 'untracked' && r.is_tracked) {
                        return false;
                    }

                    // 4. Ricerca testuale
                    if (routeFilter.value) {
                        const f = routeFilter.value.toLowerCase();
                        const uriMatch = (r.uri && r.uri.toLowerCase().includes(f)) || (r.clean_uri && r.clean_uri.toLowerCase().includes(f));
                        const ctrlMatch = (r.controller && r.controller.toLowerCase().includes(f));
                        const methodMatch = (r.controller_method && r.controller_method.toLowerCase().includes(f));
                        if (!uriMatch && !ctrlMatch && !methodMatch) {
                            return false;
                        }
                    }

                    return true;
                });
            });

            const isAllSelected = computed(() => {
                const list = filteredRoutes.value;
                if (list.length === 0) return false;
                return list.every(r => selectedRouteKeys.value.includes(getRouteKey(r)));
            });

            const isSomeSelected = computed(() => {
                const list = filteredRoutes.value;
                if (list.length === 0) return false;
                const selectedCount = list.filter(r => selectedRouteKeys.value.includes(getRouteKey(r))).length;
                return selectedCount > 0 && selectedCount < list.length;
            });

            function toggleSelectAll() {
                const list = filteredRoutes.value;
                if (list.length === 0) return;
                if (isAllSelected.value) {
                    const filteredKeys = new Set(list.map(getRouteKey));
                    selectedRouteKeys.value = selectedRouteKeys.value.filter(k => !filteredKeys.has(k));
                } else {
                    const currentKeys = new Set(selectedRouteKeys.value);
                    list.forEach(r => currentKeys.add(getRouteKey(r)));
                    selectedRouteKeys.value = Array.from(currentKeys);
                }
            }

            function resetRouteFilters() {
                routeFilter.value = '';
                routeVerbFilter.value = 'all';
                routeControllerFilter.value = 'all';
                routeTrackedFilter.value = 'all';
            }

            async function bulkSetTracking(isActive) {
                if (selectedRouteKeys.value.length === 0) return;
                const selectedSet = new Set(selectedRouteKeys.value);
                const selectedRoutes = [];

                routes.value.forEach(r => {
                    if (selectedSet.has(getRouteKey(r))) {
                        r.is_tracked = isActive;
                        selectedRoutes.push({
                            target: r.clean_uri || r.uri || '/',
                            methods: r.methods || ['*']
                        });
                    }
                });

                try {
                    const res = await apiFetch(`/${apiPrefix}/studio/rules/bulk`, {
                        method: 'POST',
                        body: JSON.stringify({
                            _token: csrfToken,
                            type: 'route',
                            routes: selectedRoutes,
                            is_active: isActive
                        })
                    });
                    if (res.ok) {
                        showToast(
                            isActive
                                ? `Tracciamento attivato per ${selectedRoutes.length} rotte.`
                                : `Tracciamento disattivato per ${selectedRoutes.length} rotte.`,
                            'success'
                        );
                    } else {
                        showToast('Errore durante l\'azione massiva.', 'error');
                    }
                } catch (e) {
                    console.error('Error in bulkSetTracking:', e);
                    showToast('Errore di connessione.', 'error');
                }
            }

            async function bulkSetStackLevel(level) {
                if (selectedRouteKeys.value.length === 0) return;
                const selectedSet = new Set(selectedRouteKeys.value);
                const selectedRoutes = [];

                routes.value.forEach(r => {
                    if (selectedSet.has(getRouteKey(r))) {
                        r.stack_level = level;
                        r.is_tracked = true;
                        selectedRoutes.push({
                            target: r.clean_uri || r.uri || '/',
                            methods: r.methods || ['*']
                        });
                    }
                });

                try {
                    const res = await apiFetch(`/${apiPrefix}/studio/rules/bulk`, {
                        method: 'POST',
                        body: JSON.stringify({
                            _token: csrfToken,
                            type: 'route',
                            routes: selectedRoutes,
                            stack_level: level,
                            is_active: true
                        })
                    });
                    if (res.ok) {
                        showToast(`Livello "${level}" applicato a ${selectedRoutes.length} rotte.`, 'success');
                    } else {
                        showToast('Errore durante l\'azione massiva.', 'error');
                    }
                } catch (e) {
                    console.error('Error in bulkSetStackLevel:', e);
                    showToast('Errore di connessione.', 'error');
                }
            }

            const filteredClasses = computed(() => {
                if (!Array.isArray(classes.value)) return [];
                if (!classFilter.value) return classes.value;
                const f = classFilter.value.toLowerCase();
                return classes.value.filter(c => {
                    const matchClass = (c.class_name && c.class_name.toLowerCase().includes(f)) ||
                                       (c.short_name && c.short_name.toLowerCase().includes(f)) ||
                                       (c.category && c.category.toLowerCase().includes(f));
                    const matchMethod = (c.methods || []).some(m => m.name && m.name.toLowerCase().includes(f));
                    return matchClass || matchMethod;
                });
            });

            function getCategoryBadgeStyle(cat) {
                switch (cat) {
                    case 'Services': return { background: 'rgba(59, 130, 246, 0.2)', color: '#93c5fd', border: '1px solid rgba(59, 130, 246, 0.4)' };
                    case 'Actions': return { background: 'rgba(16, 185, 129, 0.2)', color: '#6ee7b7', border: '1px solid rgba(16, 185, 129, 0.4)' };
                    case 'Repositories': return { background: 'rgba(245, 158, 11, 0.2)', color: '#fcd34d', border: '1px solid rgba(245, 158, 11, 0.4)' };
                    case 'Jobs': return { background: 'rgba(139, 92, 246, 0.2)', color: '#c4b5fd', border: '1px solid rgba(139, 92, 246, 0.4)' };
                    case 'Controllers': return { background: 'rgba(6, 182, 212, 0.2)', color: '#67e8f9', border: '1px solid rgba(6, 182, 212, 0.4)' };
                    case 'Models': return { background: 'rgba(236, 72, 153, 0.2)', color: '#f472b6', border: '1px solid rgba(236, 72, 153, 0.4)' };
                    case 'Commands': return { background: 'rgba(234, 88, 12, 0.2)', color: '#fdba74', border: '1px solid rgba(234, 88, 12, 0.4)' };
                    default: return { background: 'rgba(100, 116, 139, 0.2)', color: '#cbd5e1', border: '1px solid rgba(100, 116, 139, 0.4)' };
                }
            }

            async function toggleRouteTracking(route) {
                const newState = !route.is_tracked;
                route.is_tracked = newState;
                const routeLabel = (route.methods ? route.methods.join('/') : '') + ' /' + (route.clean_uri || route.uri || '');
                try {
                    const res = await apiFetch(`/${apiPrefix}/studio/rules`, {
                        method: 'POST',
                        body: JSON.stringify({
                            _token: csrfToken,
                            id: route.rule_id || null,
                            type: 'route',
                            target: route.clean_uri || route.uri || '/',
                            name: route.controller || route.uri,
                            is_active: newState,
                            stack_level: route.stack_level || 'base',
                            http_methods: route.methods || ['*']
                        })
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data && data.data && data.data.id) {
                            route.rule_id = data.data.id;
                        }
                        showToast(
                            newState ? `Tracciamento attivato: ${routeLabel}` : `Tracciamento disattivato: ${routeLabel}`,
                            'success'
                        );
                    } else {
                        route.is_tracked = !newState;
                        showToast('Errore durante il salvataggio della regola', 'error');
                    }
                } catch (e) {
                    route.is_tracked = !newState;
                    console.error('Error toggling route tracking:', e);
                    showToast('Errore di connessione', 'error');
                }
            }

            async function updateRouteLevel(route) {
                const routeLabel = (route.methods ? route.methods.join('/') : '') + ' /' + (route.clean_uri || route.uri || '');
                try {
                    const res = await apiFetch(`/${apiPrefix}/studio/rules`, {
                        method: 'POST',
                        body: JSON.stringify({
                            _token: csrfToken,
                            id: route.rule_id || null,
                            type: 'route',
                            target: route.clean_uri || route.uri || '/',
                            name: route.controller || route.uri,
                            is_active: route.is_tracked,
                            stack_level: route.stack_level,
                            http_methods: route.methods || ['*']
                        })
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data && data.data && data.data.id) {
                            route.rule_id = data.data.id;
                        }
                        showToast(`Livello impostato su "${route.stack_level}" per ${routeLabel}`, 'success');
                    } else {
                        showToast('Errore durante l\'aggiornamento del livello', 'error');
                    }
                } catch (e) {
                    console.error('Error updating route level:', e);
                    showToast('Errore di connessione', 'error');
                }
            }

            async function toggleMethodTracking(className, method) {
                const targetClass = className || (method.target ? method.target.split('@')[0] : null);
                if (!targetClass) return;
                const newState = !method.is_tracked;
                method.is_tracked = newState;
                try {
                    const res = await apiFetch(`/${apiPrefix}/studio/rules`, {
                        method: 'POST',
                        body: JSON.stringify({
                            _token: csrfToken,
                            type: 'method',
                            target: targetClass + '@' + method.name,
                            is_active: newState
                        })
                    });
                    if (res.ok) {
                        showToast(
                            newState
                                ? `Tracciamento metodo attivato: ${method.name}`
                                : `Tracciamento metodo disattivato: ${method.name}`,
                            'success'
                        );
                    } else {
                        method.is_tracked = !newState;
                        showToast('Errore durante il salvataggio della regola', 'error');
                    }
                } catch (e) {
                    method.is_tracked = !newState;
                    console.error('Error toggling method tracking:', e);
                    showToast('Errore di connessione', 'error');
                }
            }

            async function startLiveSession() {
                if (!targetUserId.value) return;
                try {
                    const res = await apiFetch(`/${apiPrefix}/studio/user-session`, {
                        method: 'POST',
                        body: JSON.stringify({
                            _token: csrfToken,
                            user_id: targetUserId.value,
                            duration_minutes: userDuration.value
                        })
                    });
                    if (res.ok) {
                        showToast(`Sessione live avviata per Utente #${targetUserId.value} (${userDuration.value} min)`, 'success');
                    }
                } catch (e) {}
                await fetchStudioData();
            }

            async function stopLiveSession(ruleId) {
                try {
                    const res = await apiFetch(`/${apiPrefix}/studio/user-session/${ruleId}`, {
                        method: 'DELETE',
                        body: JSON.stringify({ _token: csrfToken })
                    });
                    if (res.ok) {
                        showToast('Sessione live terminata.', 'info');
                    }
                } catch (e) {}
                await fetchStudioData();
            }

            // Storyboard Logic
            async function fetchStoryboardSubjects() {
                try {
                    const res = await apiFetch(`/${apiPrefix}/storyboard/subjects`);
                    if (res.ok) {
                        const sData = await res.json();
                        subjectsList.value = sData.data || (Array.isArray(sData) ? sData : []);
                        if (subjectsList.value.length && !selectedSubjectKey.value) {
                            selectedSubjectKey.value = subjectsList.value[0].type + '::' + subjectsList.value[0].id;
                            customSubjectType.value = subjectsList.value[0].type;
                            customSubjectId.value = subjectsList.value[0].id;
                        }
                    }
                } catch (e) {}
            }

            function onSubjectSelectChange() {
                if (selectedSubjectKey.value) {
                    const parts = selectedSubjectKey.value.split('::');
                    customSubjectType.value = parts[0];
                    customSubjectId.value = parts[1];
                    fetchStoryboard();
                }
            }

            async function fetchStoryboard() {
                const type = customSubjectType.value;
                const id = customSubjectId.value;
                if (!type || !id) {
                    if (!subjectsList.value.length) {
                        await fetchStoryboardSubjects();
                    }
                }
                if (!customSubjectType.value || !customSubjectId.value) return;

                storyboardLoading.value = true;
                try {
                    const url = new URL(`/${apiPrefix}/storyboard`, window.location.origin);
                    url.searchParams.set('subject_type', customSubjectType.value);
                    url.searchParams.set('subject_id', customSubjectId.value);
                    url.searchParams.set('order', storyboardSort.value);

                    const res = await apiFetch(url.toString());
                    if (res.ok) {
                        const sbData = await res.json();
                        storyboardData.value = sbData.data || sbData || { events: [] };
                    }
                } catch (e) {
                } finally {
                    storyboardLoading.value = false;
                }
            }

            function toggleStoryboardSort() {
                storyboardSort.value = storyboardSort.value === 'asc' ? 'desc' : 'asc';
                fetchStoryboard();
            }

            const filteredStoryboardEvents = computed(() => {
                let list = storyboardData.value.events || [];
                if (storyboardCategory.value !== 'all') {
                    list = list.filter(e => e.classification?.category === storyboardCategory.value);
                }
                return list;
            });

            async function openStoryboardForSubject(subjectType, subjectId) {
                if (activeLog.value) activeLog.value = null;
                customSubjectType.value = subjectType;
                customSubjectId.value = subjectId;
                selectedSubjectKey.value = subjectType + '::' + subjectId;
                currentTab.value = 'storyboard';
                await fetchStoryboard();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function refreshCurrentTab() {
                if (currentTab.value === 'logs') fetchLogs(currentPage.value);
                else if (currentTab.value === 'storyboard') fetchStoryboard();
                else fetchStudioData();
            }

            onMounted(() => {
                fetchLogs(1);
                fetchStudioData();
                fetchStoryboardSubjects().then(() => fetchStoryboard());
                setInterval(() => {
                    activeSessions.value.forEach(s => { if (s.seconds_left > 0) s.seconds_left--; });
                }, 1000);
            });

            return {
                currentTab, logs, stats, loading, currentPage, perPage, perPageOptions, pagination, visiblePages,
                quickFilter, filterText, filterVerb, filterUser, filterDateFrom, filterDateTo, activeLog, modalTab,
                modalStackView, displayedStackFrames, expandedModelGroups,
                // Studio
                routes, routeFilter, routeVerbFilter, routeControllerFilter, routeTrackedFilter, uniqueControllers,
                selectedRouteKeys, bulkStackLevel, isAllSelected, isSomeSelected, toggleSelectAll, resetRouteFilters,
                bulkSetTracking, bulkSetStackLevel, getRouteKey,
                filteredRoutes, classes, classFilter, filteredClasses, getCategoryBadgeStyle, usersList, activeSessions, targetUserId, userDuration,
                toggleRouteTracking, updateRouteLevel, toggleMethodTracking, startLiveSession, stopLiveSession,
                // Storyboard
                subjectsList, selectedSubjectKey, customSubjectType, customSubjectId, storyboardData, storyboardLoading,
                storyboardCategory, storyboardSort, filteredStoryboardEvents, onSubjectSelectChange, fetchStoryboard,
                toggleStoryboardSort, openStoryboardForSubject, refreshCurrentTab,
                // Actions
                fetchLogs, goToPage, onPerPageChange, debounceFetchLogs, onFilterChange, setQuickFilter, resetFilters,
                getVerbClass, getStatusClass, formatTimestamp, formatSeconds, openDetail, closeDetail, exportData,
                toast, showToast,
            };
        }
    }).mount('#app');
</script>
