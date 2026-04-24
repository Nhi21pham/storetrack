<template>
  <div class="audit-log-page">
    <div class="page-header">
      <div>
        <h1>Audit Log</h1>
        <p class="subtitle">
          Activity history for
          <strong>{{ currentStore?.name ?? '—' }}</strong>
        </p>
      </div>
    </div>

    <div v-if="!currentStore" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
        </svg>
      </div>
      <h3>No store selected</h3>
      <p>Select a store from the top bar to view its activity.</p>
    </div>

    <template v-else>
      <div class="filter-bar">
        <div class="date-range">
          <label>From</label>
          <input type="date" v-model="startDate" :max="endDate || undefined" @change="applyFilter" />
          <label>To</label>
          <input type="date" v-model="endDate" :min="startDate || undefined" @change="applyFilter" />
          <button v-if="startDate || endDate" class="btn-clear" @click="clearFilter">Clear</button>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Loading activity...</span>
      </div>

      <div v-else-if="logs.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
        </div>
        <h3>No activity yet</h3>
        <p>Actions performed in this store will appear here.</p>
      </div>

      <div v-else class="log-feed" :class="{ 'log-feed--fetching': fetching }">
        <div v-for="log in logs" :key="log.id" class="log-entry">
          <div class="log-left">
            <span class="badge" :class="badgeClass(log.object_type)">{{ log.object_type }}</span>
          </div>
          <div class="log-body">
            <p class="log-message" v-html="highlightMessage(log.message)"></p>
            <span class="log-time">{{ formatDatetime(log.created_at) }}</span>
          </div>
        </div>

        <Pagination
          :currentPage="currentPage"
          :totalPages="lastPage"
          :total="total"
          :perPage="perPage"
          @update:currentPage="loadPage"
          @update:perPage="changePerPage"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, watch, inject } from 'vue'
import { graphql } from '@/api'
import Pagination from '@/components/common/Pagination.vue'

const currentStore = inject('currentStore')
const showToast = inject('showToast')

const logs = ref([])
const loading = ref(false)
const fetching = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const STORAGE_KEY = 'audit_log_per_page'
const perPage = ref(Number(localStorage.getItem(STORAGE_KEY)) || 20)
const startDate = ref('')
const endDate = ref('')

const QUERY = `
  query AuditLogs($store_id: ID!, $page: Int, $per_page: Int, $start_date: String, $end_date: String) {
    auditLogs(store_id: $store_id, page: $page, per_page: $per_page, start_date: $start_date, end_date: $end_date) {
      data {
        id
        actor_name
        actor_email
        object_type
        action
        message
        created_at
      }
      total
      current_page
      last_page
      per_page
    }
  }
`

const fetchLogs = async (page = 1) => {
  if (!currentStore.value?.id) return
  if (logs.value.length === 0) loading.value = true
  else fetching.value = true
  try {
    const data = await graphql(QUERY, {
      store_id: currentStore.value.id,
      page,
      per_page: perPage.value,
      start_date: startDate.value || null,
      end_date: endDate.value || null,
    })
    logs.value = data.auditLogs.data
    currentPage.value = data.auditLogs.current_page
    lastPage.value = data.auditLogs.last_page
    total.value = data.auditLogs.total
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value = false
    fetching.value = false
  }
}

const loadPage = (page) => fetchLogs(page)

const changePerPage = (val) => {
  perPage.value = val
  localStorage.setItem(STORAGE_KEY, val)
  fetchLogs(1)
}

const applyFilter = () => { currentPage.value = 1; fetchLogs(1) }

const clearFilter = () => {
  startDate.value = ''
  endDate.value = ''
  currentPage.value = 1
  fetchLogs(1)
}

const ACTION_COLORS = {
  CREATED: '#16a34a',
  ACCEPTED: '#16a34a',
  REACTIVATED: '#16a34a',
  UPDATED: '#1d4ed8',
  ASSIGNED: '#1d4ed8',
  INVITED: '#7c3aed',
  CANCELLED: '#b45309',
  DEACTIVATED: '#b45309',
  REMOVED: '#dc2626',
  DECLINED: '#dc2626',
}

const highlightMessage = (msg) => {
  const escaped = msg
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/^\[[\w]+\]\s*/, '') // strip legacy [ObjectType] prefix

  // Bold name(email) patterns
  const withNames = escaped.replace(
    /(\S+\([^)]+\))/g,
    '<strong>$1</strong>'
  )

  // Color known action verbs
  return withNames.replace(
    /\b(CREATED|UPDATED|DEACTIVATED|REACTIVATED|ASSIGNED|REMOVED|INVITED|CANCELLED|ACCEPTED|DECLINED)\b/g,
    (match) => `<span style="font-weight:700;color:${ACTION_COLORS[match] ?? '#374151'}">${match}</span>`
  )
}

watch(
  () => currentStore.value?.id,
  (id) => {
    if (id) {
      currentPage.value = 1
      startDate.value = ''
      endDate.value = ''
      fetchLogs(1)
    }
  },
  { immediate: true }
)

const badgeClass = (objectType) => {
  const map = { Business: 'badge-business', Store: 'badge-store', User: 'badge-user', Invitation: 'badge-invitation' }
  return map[objectType] ?? 'badge-default'
}

const formatDatetime = (isoString) =>
  new Date(isoString).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
</script>

<style scoped>
.audit-log-page { padding: 32px; max-width: 860px; margin: 0 auto; }

.page-header { margin-bottom: 28px; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

.filter-bar { margin-bottom: 16px; }
.date-range { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.date-range label { font-size: 12.5px; font-weight: 400; color: #9ca3af; letter-spacing: 0.01em; }
.date-range input[type="date"] { padding: 7px 11px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13.5px; font-family: inherit; color: #374151; background: #fafafa; cursor: pointer; outline: none; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; }
.date-range input[type="date"]:hover { background: #fff; border-color: #d1d5db; }
.date-range input[type="date"]:focus { background: #fff; border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }
.btn-clear { padding: 7px 13px; border: 1px solid #f3f4f6; border-radius: 10px; font-size: 12.5px; font-family: inherit; color: #c0c4cc; background: #fafafa; cursor: pointer; transition: background 0.2s, color 0.2s, border-color 0.2s; }
.btn-clear:hover { background: #f3f4f6; border-color: #e5e7eb; color: #6b7280; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; }

.log-feed { display: flex; flex-direction: column; gap: 0; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff; transition: opacity 0.15s; }
.log-feed--fetching { opacity: 0.45; pointer-events: none; }

.log-entry { display: flex; align-items: flex-start; gap: 14px; padding: 14px 20px; border-bottom: 1px solid #f3f4f6; transition: background 0.12s; }
.log-entry:last-of-type { border-bottom: none; }
.log-entry:hover { background: #fafafa; }

.log-left { padding-top: 1px; flex-shrink: 0; width: 84px; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.03em; text-transform: uppercase; }
.badge-business   { background: #ffedd5; color: #c2410c; }
.badge-store      { background: #dbeafe; color: #1d4ed8; }
.badge-user       { background: #d1fae5; color: #065f46; }
.badge-invitation { background: #ede9fe; color: #6d28d9; }
.badge-default    { background: #f3f4f6; color: #6b7280; }

.log-body { flex: 1; min-width: 0; }
.log-message { font-size: 13.5px; color: #111; line-height: 1.5; word-break: break-word; margin: 0 0 4px; }
.log-message :deep(strong) { font-weight: 600; color: #111; }
.log-message :deep(.action-verb) { font-weight: 700; color: #374151; }
.log-time { font-size: 12px; color: #9ca3af; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
