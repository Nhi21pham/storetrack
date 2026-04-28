<template>
  <div class="audit-log-page">
    <div class="page-header">
      <div>
        <h1>Audit Log</h1>
        <p class="subtitle">
          Activity history for
          <strong>{{ viewMode === 'store' ? (currentStore?.name ?? '—') : (currentBusiness?.name ?? '—') }}</strong>
        </p>
      </div>
      <div v-if="isBusinessOwner" class="view-toggle">
        <button :class="{ active: viewMode === 'store' }" @click="switchMode('store')">Store</button>
        <button :class="{ active: viewMode === 'business' }" @click="switchMode('business')">Business</button>
      </div>
    </div>

    <div v-if="viewMode === 'store' && !currentStore" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
        </svg>
      </div>
      <h3>No store selected</h3>
      <p>Select a store from the top bar to view its activity.</p>
    </div>

    <div v-else-if="viewMode === 'business' && !currentBusiness" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
      </div>
      <h3>No business selected</h3>
      <p>Select a business to view its activity.</p>
    </div>

    <template v-else>
      <div v-if="viewMode === 'store' && currentStore && !currentStore.is_active" class="inactive-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        This store is deactivated. Showing historical activity only.
      </div>
      <SearchBar v-model="searchQuery" placeholder="Search activity..." />

      <div class="filter-bar">
        <div class="filter-row">
          <div class="filter-group">
            <label>From</label>
            <input type="date" v-model="startDate" :max="endDate || undefined" @change="applyFilter" />
          </div>
          <div class="filter-group">
            <label>To</label>
            <input type="date" v-model="endDate" :min="startDate || undefined" @change="applyFilter" />
          </div>
          <div class="filter-group">
            <label>Type</label>
            <SearchableSelect
              v-model="objectFilter"
              :options="OBJECT_OPTIONS"
              search-placeholder="Search type..."
              @change="applyFilter"
            />
          </div>
          <div class="filter-group">
            <label>Action</label>
            <SearchableSelect
              v-model="actionFilter"
              :options="ACTION_OPTIONS"
              search-placeholder="Search action..."
              @change="applyFilter"
            />
          </div>
          <button v-if="hasActiveFilter" class="btn-clear" @click="clearFilter">Clear</button>
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
        <p>Actions performed will appear here.</p>
      </div>

      <div v-else-if="filteredLogs.length === 0" class="empty-state">
        <h3>No results matching "{{ searchQuery }}"</h3>
        <p>Try a different keyword.</p>
      </div>

      <div v-else class="log-feed" :class="{ 'log-feed--fetching': fetching }">
        <div v-for="log in filteredLogs" :key="log.id" class="log-entry">
          <span class="object-badge" :class="badgeClass(log.object_type)">{{ objectLabel(log.object_type) }}</span>
          <div class="log-body">
            <div class="log-actor-block">
              <span class="log-actor" :title="log.actor_email || ''">{{ log.actor_name || 'System' }}</span>
              <span v-if="log.actor_email" class="log-actor-email">({{ log.actor_email }})</span>
            </div>
            <p class="log-action" :title="actionTitle(log)" v-html="renderAction(log)"></p>
            <div class="log-aside">
              <span v-if="viewMode === 'business' && log.store_name" class="log-store">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/></svg>
                {{ log.store_name }}
              </span>
              <span class="log-time">{{ formatDatetime(log.created_at) }}</span>
            </div>
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
import { ref, computed, watch, inject } from 'vue'
import { graphql } from '@/api'
import Pagination from '@/components/common/Pagination.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'

const currentStore    = inject('currentStore')
const currentBusiness = inject('currentBusiness')
const showToast       = inject('showToast')

const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')

const viewMode = ref('store')

const logs         = ref([])
const loading      = ref(false)
const fetching     = ref(false)
const currentPage  = ref(1)
const lastPage     = ref(1)
const total        = ref(0)
const STORAGE_KEY  = 'audit_log_per_page'
const perPage      = ref(Number(localStorage.getItem(STORAGE_KEY)) || 20)
const startDate    = ref('')
const endDate      = ref('')
const searchQuery  = ref('')
const objectFilter = ref('')
const actionFilter = ref('')

const OBJECT_OPTIONS = [
  { value: 'business',   label: 'Business' },
  { value: 'store',      label: 'Store' },
  { value: 'user',       label: 'User' },
  { value: 'invitation', label: 'Invitation' },
  { value: 'supplier',   label: 'Supplier' },
  { value: 'customer',   label: 'Customer' },
]

const ACTION_OPTIONS = [
  { value: 'created',      label: 'Created' },
  { value: 'updated',      label: 'Updated' },
  { value: 'deactivated',  label: 'Deactivated' },
  { value: 'reactivated',  label: 'Reactivated' },
  { value: 'assigned',     label: 'Assigned' },
  { value: 'role_changed', label: 'Role Changed' },
  { value: 'removed',      label: 'Removed' },
  { value: 'invited',      label: 'Invited' },
  { value: 'cancelled',    label: 'Cancelled' },
  { value: 'accepted',     label: 'Accepted' },
  { value: 'declined',     label: 'Declined' },
]

const hasActiveFilter = computed(() =>
  !!(startDate.value || endDate.value || objectFilter.value || actionFilter.value)
)

const filteredLogs = computed(() => {
  if (!searchQuery.value.trim()) return logs.value
  const q = searchQuery.value.toLowerCase()
  return logs.value.filter(l =>
    l.message.toLowerCase().includes(q) ||
    l.actor_name?.toLowerCase().includes(q) ||
    l.actor_email?.toLowerCase().includes(q) ||
    l.store_name?.toLowerCase().includes(q)
  )
})

const STORE_QUERY = `
  query AuditLogs($store_id: ID!, $page: Int, $per_page: Int, $start_date: String, $end_date: String, $object_type: String, $action: String) {
    auditLogs(store_id: $store_id, page: $page, per_page: $per_page, start_date: $start_date, end_date: $end_date, object_type: $object_type, action: $action) {
      data { id actor_name actor_email object_type action message created_at }
      total current_page last_page per_page
    }
  }
`

const BUSINESS_QUERY = `
  query BusinessAuditLogs($business_id: ID!, $page: Int, $per_page: Int, $start_date: String, $end_date: String, $object_type: String, $action: String) {
    businessAuditLogs(business_id: $business_id, page: $page, per_page: $per_page, start_date: $start_date, end_date: $end_date, object_type: $object_type, action: $action) {
      data { id actor_name actor_email object_type action message store_name created_at }
      total current_page last_page per_page
    }
  }
`

const fetchLogs = async (page = 1) => {
  if (viewMode.value === 'store' && !currentStore.value?.id) return
  if (viewMode.value === 'business' && !currentBusiness.value?.id) return

  if (logs.value.length === 0) loading.value = true
  else fetching.value = true

  try {
    let data
    if (viewMode.value === 'store') {
      data = await graphql(STORE_QUERY, {
        store_id: currentStore.value.id,
        page,
        per_page: perPage.value,
        start_date: startDate.value || null,
        end_date: endDate.value || null,
        object_type: objectFilter.value || null,
        action: actionFilter.value || null,
      })
      logs.value        = data.auditLogs.data
      currentPage.value = data.auditLogs.current_page
      lastPage.value    = data.auditLogs.last_page
      total.value       = data.auditLogs.total
    } else {
      data = await graphql(BUSINESS_QUERY, {
        business_id: currentBusiness.value.id,
        page,
        per_page: perPage.value,
        start_date: startDate.value || null,
        end_date: endDate.value || null,
        object_type: objectFilter.value || null,
        action: actionFilter.value || null,
      })
      logs.value        = data.businessAuditLogs.data
      currentPage.value = data.businessAuditLogs.current_page
      lastPage.value    = data.businessAuditLogs.last_page
      total.value       = data.businessAuditLogs.total
    }
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value  = false
    fetching.value = false
  }
}

const switchMode = (mode) => {
  if (viewMode.value === mode) return
  viewMode.value = mode
  resetAndFetch()
}

const resetAndFetch = () => {
  logs.value        = []
  currentPage.value = 1
  startDate.value   = ''
  endDate.value     = ''
  searchQuery.value = ''
  objectFilter.value = ''
  actionFilter.value = ''
  fetchLogs(1)
}

const loadPage      = (page) => fetchLogs(page)
const changePerPage = (val) => { perPage.value = val; localStorage.setItem(STORAGE_KEY, val); fetchLogs(1) }
const applyFilter   = () => { currentPage.value = 1; fetchLogs(1) }
const clearFilter   = () => {
  startDate.value = ''
  endDate.value = ''
  objectFilter.value = ''
  actionFilter.value = ''
  currentPage.value = 1
  fetchLogs(1)
}

const ACTION_COLORS = {
  CREATED: '#16a34a', ACCEPTED: '#16a34a', REACTIVATED: '#16a34a',
  UPDATED: '#1d4ed8', ASSIGNED: '#1d4ed8',
  INVITED: '#7c3aed',
  CANCELLED: '#b45309', DEACTIVATED: '#b45309',
  REMOVED: '#dc2626', DECLINED: '#dc2626',
}

const escapeHtml = (s) =>
  String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

const stripActorPrefix = (log) => {
  const actor = log.actor_name && log.actor_email ? `${log.actor_name}(${log.actor_email})` : null
  let detail = log.message
  if (actor) {
    const prefix = `${actor} has `
    if (detail.startsWith(prefix)) detail = detail.slice(prefix.length)
  }
  return detail.replace(/\.$/, '').trim()
}

const actionTitle = (log) => stripActorPrefix(log)

const renderAction = (log) => {
  const detail = stripActorPrefix(log)
  if (!detail) return ''
  const escaped = escapeHtml(detail)
    .replace(/(\S+\([^)]+\))/g, '<strong>$1</strong>')
    .replace(/\b([A-Za-z][\w.+-]*@[\w.-]+\.[A-Za-z]{2,})\b/g, '<strong>$1</strong>')
  return escaped.replace(
    /\b(CREATED|UPDATED|DEACTIVATED|REACTIVATED|ASSIGNED|REMOVED|INVITED|CANCELLED|ACCEPTED|DECLINED)\b/g,
    (m) => `<span class="verb" style="color:${ACTION_COLORS[m] ?? '#374151'}">${m}</span>`
  )
}

watch(() => currentStore.value?.id, (id) => {
  if (viewMode.value === 'store' && id) resetAndFetch()
}, { immediate: true })

watch(() => currentBusiness.value?.id, (id) => {
  if (!isBusinessOwner.value && viewMode.value === 'business') {
    viewMode.value = 'store'
  }
  if (viewMode.value === 'business' && id) resetAndFetch()
})

const badgeClass = (objectType) => {
  const map = {
    business:   'badge-business',
    store:      'badge-store',
    user:       'badge-user',
    invitation: 'badge-invitation',
    supplier:   'badge-supplier',
    customer:   'badge-customer',
  }
  return map[objectType?.toLowerCase()] ?? 'badge-default'
}

const OBJECT_LABELS = {
  business:   'Business',
  store:      'Store',
  user:       'User',
  invitation: 'Invitation',
  supplier:   'Supplier',
  customer:   'Customer',
}

const objectLabel = (objectType) => OBJECT_LABELS[objectType?.toLowerCase()] ?? (objectType || 'Other')

const formatDatetime = (isoString) =>
  new Date(isoString).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
</script>

<style scoped>
.audit-log-page { padding: 32px; max-width: 860px; margin: 0 auto; }

.inactive-banner { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; font-size: 13px; color: #92400e; margin-bottom: 16px; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

.view-toggle { display: flex; background: #f3f4f6; border-radius: 10px; padding: 3px; gap: 2px; }
.view-toggle button { padding: 7px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; background: none; color: #6b7280; transition: all 0.15s; }
.view-toggle button.active { background: #fff; color: #111; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

.filter-bar { margin-bottom: 16px; }
.filter-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 11.5px; font-weight: 500; color: #9ca3af; letter-spacing: 0.02em; text-transform: uppercase; }
.filter-group input,
.filter-group select { padding: 7px 11px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13.5px; font-family: inherit; color: #374151; background: #fafafa; cursor: pointer; outline: none; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; min-height: 36px; }
.filter-group input:hover,
.filter-group select:hover { background: #fff; border-color: #d1d5db; }
.filter-group input:focus,
.filter-group select:focus { background: #fff; border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }
.btn-clear { padding: 7px 13px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 12.5px; font-weight: 500; font-family: inherit; color: #374151; background: #fff; cursor: pointer; transition: background 0.2s, color 0.2s, border-color 0.2s; height: 36px; align-self: flex-end; }
.btn-clear:hover { background: #f3f4f6; border-color: #9ca3af; color: #111; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; }

.log-feed { display: flex; flex-direction: column; gap: 0; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff; transition: opacity 0.15s; }
.log-feed--fetching { opacity: 0.45; pointer-events: none; }

.log-entry { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #f3f4f6; transition: background 0.12s; }
.log-entry:last-of-type { border-bottom: none; }
.log-entry:hover { background: #fafafa; }

.object-badge { flex-shrink: 0; align-self: flex-start; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.04em; line-height: 1.4; min-width: 78px; text-align: center; }
.object-badge.badge-business   { background: #ffedd5; color: #c2410c; }
.object-badge.badge-store      { background: #dbeafe; color: #1d4ed8; }
.object-badge.badge-user       { background: #d1fae5; color: #065f46; }
.object-badge.badge-invitation { background: #ede9fe; color: #6d28d9; }
.object-badge.badge-supplier   { background: #fef9c3; color: #854d0e; }
.object-badge.badge-customer   { background: #fce7f3; color: #9d174d; }
.object-badge.badge-default    { background: #f3f4f6; color: #6b7280; }

.log-body { flex: 1; min-width: 0; display: flex; align-items: center; gap: 12px; }

.log-actor-block { display: flex; align-items: baseline; gap: 6px; flex-shrink: 0; max-width: 40%; }
.log-actor { color: #111; font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.log-actor-email { color: #6b7280; font-size: 12.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.log-aside { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.log-store { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: #1d4ed8; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 5px; padding: 2px 7px; font-weight: 500; white-space: nowrap; }
.log-store svg { color: #1d4ed8; }
.log-time { font-size: 12px; color: #9ca3af; white-space: nowrap; }

.log-action { flex: 1; min-width: 0; font-size: 13.5px; line-height: 1.5; margin: 0; color: #4b5563; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.log-action :deep(strong) { font-weight: 600; color: #111; }
.log-action :deep(.verb) { font-weight: 700; letter-spacing: 0.02em; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
