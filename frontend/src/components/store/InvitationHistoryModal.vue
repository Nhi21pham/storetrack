<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>Invitation History</h2>
        <div class="header-actions">
          <button class="refresh-btn" @click="fetchInvitations" :disabled="loading || refreshing" title="Refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="{ spinning: refreshing }">
              <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
          </button>
          <button class="close-btn" @click="$emit('close')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="modal-filters">
        <div class="filter-tabs">
          <button
            v-for="f in statusFilters"
            :key="f.value"
            class="filter-tab"
            :class="{ active: activeFilter === f.value }"
            @click="setFilter(f.value)"
          >
            {{ f.label }}
            <span class="tab-count">{{ countByStatus(f.value) }}</span>
          </button>
        </div>
        <button v-if="sortCriteria.length" class="btn-clear-sort" @click="clearSort">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Clear sort
        </button>
      </div>

      <div class="modal-body">
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <span>Loading invitations...</span>
        </div>
        <template v-else>
          <div v-if="!sortedInvitations.length" class="empty-state">No invitations found.</div>
          <div v-else class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th v-for="col in columns" :key="col.key" class="sortable-th">
                    <span class="th-content" :class="{ 'th-active': !!getSortInfo(col.key) }">
                      {{ col.label }}
                      <span class="sort-icons">
                        <svg @click="toggleSort(col.key, 'asc')" class="sort-arrow" :class="{ active: getSortInfo(col.key)?.dir === 'asc' }" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="getSortInfo(col.key)?.dir === 'asc' ? 3 : 1.5"><polyline points="18 15 12 9 6 15"/></svg>
                        <svg @click="toggleSort(col.key, 'desc')" class="sort-arrow" :class="{ active: getSortInfo(col.key)?.dir === 'desc' }" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="getSortInfo(col.key)?.dir === 'desc' ? 3 : 1.5"><polyline points="6 9 12 15 18 9"/></svg>
                      </span>
                      <span v-if="sortCriteria.length > 1 && getSortInfo(col.key)" class="sort-rank">{{ sortRank(col.key) }}</span>
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="inv in paginatedInvitations" :key="inv.id">
                  <td>
                    <div class="invitee-name">{{ inv.invitee_name || '—' }}</div>
                    <div class="invitee-email">{{ inv.invitee_email }}</div>
                  </td>
                  <td class="store-cell">{{ inv.store.name }}</td>
                  <td><span class="role-badge" :class="inv.role">{{ roleLabel(inv.role) }}</span></td>
                  <td><span class="status-badge" :class="inv.status?.toLowerCase()">{{ statusLabel(inv.status) }}</span></td>
                  <td class="date-cell">{{ formatDateTime(inv.created_at) }}</td>
                  <td class="date-cell">{{ resolvedDateTime(inv) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <Pagination
            v-if="sortedInvitations.length"
            v-model:currentPage="currentPage"
            v-model:perPage="pageSize"
            :totalPages="totalPages"
            :total="sortedInvitations.length"
          />
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { graphql } from '@/api'
import { useSortCriteria } from '@/composables/useSortCriteria'
import Pagination from '@/components/common/Pagination.vue'

const props = defineProps({
  ownedStores: { type: Array, required: true },
})

defineEmits(['close'])

const loading = ref(true)
const refreshing = ref(false)
const allInvitations = ref([])
const activeFilter = ref('ALL')
const { sortCriteria, getSortInfo, sortRank, toggleSort, clearSort, sortItems } = useSortCriteria()
const currentPage = ref(1)
const pageSize = ref(20)

const columns = [
  { key: 'invitee_email', label: 'Invitee' },
  { key: 'store_name',    label: 'Store' },
  { key: 'role',          label: 'Role' },
  { key: 'status',        label: 'Status' },
  { key: 'created_at',    label: 'Sent' },
  { key: 'resolved_at',   label: 'Resolved' },
]

const statusFilters = [
  { value: 'ALL',       label: 'All' },
  { value: 'PENDING',   label: 'Pending' },
  { value: 'ACCEPTED',  label: 'Accepted' },
  { value: 'DECLINED',  label: 'Declined' },
  { value: 'CANCELLED', label: 'Cancelled' },
  { value: 'EXPIRED',   label: 'Expired' },
]

const roleLabel   = (r) => ({ OWNER: 'Owner', ACCOUNTANT: 'Accountant', STAFF: 'Staff' }[r] ?? r)
const statusLabel = (s) => ({ PENDING: 'Pending', ACCEPTED: 'Accepted', DECLINED: 'Declined', CANCELLED: 'Cancelled', EXPIRED: 'Expired' }[s] ?? s)

const resolvedTimestamp = (inv) => {
  if (inv.status === 'PENDING') return ''
  if (inv.status === 'ACCEPTED' && inv.accepted_at) return inv.accepted_at
  return inv.updated_at
}

const formatDateTime = (dt) => {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

const resolvedDateTime = (inv) => {
  const ts = resolvedTimestamp(inv)
  return ts ? formatDateTime(ts) : '—'
}

const getSortValue = (inv, key) => {
  if (key === 'store_name')  return inv.store.name.toLowerCase()
  if (key === 'invitee_email') return (inv.invitee_name || inv.invitee_email).toLowerCase()
  if (key === 'resolved_at') return resolvedTimestamp(inv) || ''
  if (key === 'role')   return inv.role
  if (key === 'status') return inv.status
  return inv[key] ?? ''
}

const filteredInvitations = computed(() =>
  activeFilter.value === 'ALL'
    ? allInvitations.value
    : allInvitations.value.filter(i => i.status === activeFilter.value)
)

const sortedInvitations = computed(() =>
  sortItems(filteredInvitations.value, getSortValue)
)

const totalPages = computed(() => Math.max(1, Math.ceil(sortedInvitations.value.length / pageSize.value)))

const paginatedInvitations = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return sortedInvitations.value.slice(start, start + pageSize.value)
})

const countByStatus = (status) =>
  status === 'ALL' ? allInvitations.value.length : allInvitations.value.filter(i => i.status === status).length

const setFilter = (val) => { activeFilter.value = val }

watch([activeFilter, pageSize], () => { currentPage.value = 1 })
watch(sortCriteria, () => { currentPage.value = 1 }, { deep: true })

const fetchInvitations = async () => {
  if (!loading.value) refreshing.value = true
  try {
    const results = await Promise.all(
      props.ownedStores.map(store =>
        graphql(`
          query StoreAllInvitations($store_id: ID!) {
            storeAllInvitations(store_id: $store_id) {
              id invitee_email invitee_name role status
              created_at accepted_at updated_at
              store { id name }
              inviter { id name email }
            }
          }
        `, { store_id: store.id })
      )
    )
    allInvitations.value = results
      .flatMap(r => r.storeAllInvitations)
      .map(inv => ({ ...inv, status: inv.status?.toUpperCase(), role: inv.role?.toUpperCase() }))
      .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

onMounted(fetchInvitations)
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; animation: overlay-in 0.2s ease; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 860px; min-height: 520px; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; animation: modal-in 0.28s cubic-bezier(0.34, 1.4, 0.64, 1); }
@keyframes overlay-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes modal-in { from { opacity: 0; transform: translateY(24px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.header-actions { display: flex; align-items: center; gap: 4px; }
.refresh-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.15s; display: flex; align-items: center; justify-content: center; }
.refresh-btn:hover:not(:disabled) { color: #374151; background: #f3f4f6; }
.refresh-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.refresh-btn svg.spinning { animation: spin 0.7s linear infinite; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-filters { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 24px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
.filter-tabs { display: flex; gap: 4px; flex-wrap: wrap; overflow-x: auto; }
.filter-tab { display: flex; align-items: center; gap: 6px; padding: 6px 12px; border: none; background: none; font-size: 13px; font-weight: 500; color: #6b7280; border-radius: 7px; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.filter-tab:hover { background: #f3f4f6; color: #374151; }
.filter-tab.active { background: #111; color: #fff; }
.tab-count { font-size: 11px; font-weight: 600; background: rgba(0,0,0,0.08); padding: 1px 6px; border-radius: 10px; }
.filter-tab.active .tab-count { background: rgba(255,255,255,0.2); }
.btn-clear-sort { display: flex; align-items: center; gap: 5px; padding: 5px 10px; border: 1px solid #e5e7eb; background: #fff; color: #6b7280; border-radius: 7px; font-size: 12px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: all 0.15s; flex-shrink: 0; }
.btn-clear-sort:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

.modal-body { flex: 1; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 48px; color: #6b7280; font-size: 14px; }
.spinner { width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }
.empty-state { text-align: center; padding: 48px; font-size: 14px; color: #9ca3af; }

.table-wrap { overflow-y: scroll; flex: 1; min-height: 280px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.sortable-th:nth-child(1) { width: 26%; }
.sortable-th:nth-child(2) { width: 18%; }
.sortable-th:nth-child(3) { width: 11%; }
.sortable-th:nth-child(4) { width: 12%; }
.sortable-th:nth-child(5) { width: 16%; }
.sortable-th:nth-child(6) { width: 17%; }
thead { position: sticky; top: 0; background: #fff; z-index: 1; box-shadow: 0 1px 0 #f3f4f6; }

.sortable-th { padding: 10px 16px; text-align: left; user-select: none; white-space: nowrap; }
.sort-arrow { cursor: pointer; }
.sortable-th:hover .th-content { color: #111; }
.th-content { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; transition: color 0.15s; }
.th-content.th-active { color: #111; font-weight: 700; }
.sort-icons { display: flex; flex-direction: column; gap: 1px; }
.sort-arrow { color: #e5e7eb; transition: color 0.12s; }
.sort-arrow.active { color: #111; }
.sort-rank { font-size: 10px; font-weight: 700; color: #fff; background: #111; border-radius: 50%; width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center; margin-left: 2px; }

td { padding: 12px 16px; font-size: 13px; color: #374151; border-bottom: 1px solid #f9fafb; vertical-align: middle; overflow: hidden; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafafa; }

.invitee-name { font-weight: 500; color: #111; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.invitee-email { font-size: 12px; color: #9ca3af; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.store-cell { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.date-cell { white-space: nowrap; color: #6b7280; font-size: 12px; }

.role-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 5px; }
.role-badge.OWNER { color: #16a34a; background: #f0fdf4; }
.role-badge.ACCOUNTANT { color: #2563eb; background: #eff6ff; }
.role-badge.STAFF { color: #6b7280; background: #f3f4f6; }

.status-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 5px; white-space: nowrap; }
.status-badge.pending   { color: #d97706; background: #fffbeb; }
.status-badge.accepted  { color: #16a34a; background: #f0fdf4; }
.status-badge.declined  { color: #dc2626; background: #fef2f2; }
.status-badge.cancelled { color: #6b7280; background: #f3f4f6; }
.status-badge.expired   { color: #ea580c; background: #fff7ed; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
