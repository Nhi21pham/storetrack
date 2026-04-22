<template>
  <div class="store-page">
    <div class="page-header">
      <div>
        <h1>My Stores</h1>
        <p class="subtitle">Manage your stores across all businesses.</p>
      </div>
      <button class="btn-create" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Store
      </button>
    </div>

    <SearchBar v-model="searchQuery" placeholder="Search stores..." />

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading stores...</span>
    </div>

    <!-- No search results -->
    <div v-else-if="filteredStores.length === 0 && searchQuery.trim()" class="empty-state">
      <p>No stores matching "{{ searchQuery }}"</p>
    </div>

    <!-- Empty -->
    <div v-else-if="stores.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
      </div>
      <h3>No stores yet</h3>
      <p>Create your first store to get started.</p>
    </div>

    <!-- Store list -->
    <div v-else class="store-list">
      <div
        v-for="store in filteredStores"
        :key="store.id"
        class="store-card"
        :class="{ inactive: !store.is_active }"
      >
        <div class="card-body">
          <div class="card-main">
            <div class="card-icon" :class="{ inactive: !store.is_active }">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
                <path d="M8 7v4"/><path d="M16 7v4"/>
              </svg>
            </div>
            <div class="card-info">
              <div class="card-title-row">
                <h3>{{ store.name }}</h3>
                <label
                  v-if="store.my_role === 'OWNER'"
                  class="toggle-switch"
                  :class="{ active: store.is_active }"
                  @click.prevent="confirmToggle(store)"
                  title="Click to toggle active status"
                >
                  <span class="toggle-slider"></span>
                </label>
                <span v-else class="status-dot" :class="store.is_active ? 'active' : 'inactive'"></span>
                <span class="role-badge" :class="store.my_role">{{ store.my_role }}</span>
              </div>
              <span class="biz-name">{{ store.business.name }}</span>
            </div>
          </div>

          <div class="card-details">
            <div v-if="store.address" class="detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              {{ store.address }}
            </div>
            <div v-if="store.email" class="detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              {{ store.email }}
            </div>
            <div v-if="store.phone" class="detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              {{ store.phone }}
            </div>
          </div>
        </div>

        <!-- Owner actions -->
        <div v-if="store.my_role === 'OWNER'" class="card-actions">
          <button class="action-btn" @click="openEdit(store)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
          <button class="action-btn danger" @click="confirmDelete(store)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Delete
          </button>
        </div>

        <!-- Accountant actions -->
        <div v-else-if="store.my_role === 'ACCOUNTANT'" class="card-actions">
          <button class="action-btn" @click="openEdit(store)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
        </div>

        <!-- Staff / member - read only badge -->
        <div v-else class="card-role-badge">
          <span class="member-tag">{{ store.my_role }}</span>
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <StoreFormModal
      v-if="showForm"
      :store="editingStore"
      @close="showForm = false"
      @saved="onSaved"
    />

    <!-- Toggle Confirm -->
    <ConfirmDialog
      v-if="togglingStore"
      :title="togglingStore.is_active ? 'Deactivate Store' : 'Reactivate Store'"
      :message="togglingStore.is_active
        ? `Are you sure you want to deactivate '${togglingStore.name}'? Users will no longer be able to access this store.`
        : `Are you sure you want to reactivate '${togglingStore.name}'?`"
      :confirm-text="togglingStore.is_active ? 'Yes, deactivate' : 'Yes, reactivate'"
      cancel-text="Cancel"
      :type="togglingStore.is_active ? 'warning' : 'success'"
      @confirm="handleToggle"
      @cancel="togglingStore = null"
    />
    <ConfirmDialog
      v-if="deletingStore"
      title="Delete Store"
      :message="`Are you sure you want to delete '${deletingStore.name}'? This will remove all user assignments and cannot be undone.`"
      confirm-text="Yes, delete"
      cancel-text="Cancel"
      type="danger"
      @confirm="handleDelete"
      @cancel="deletingStore = null"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import SearchBar from '@/components/common/SearchBar.vue'
import StoreFormModal from '@/components/store/StoreFormModal.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { graphql } from '@/api'

const emit = defineEmits(['store-updated'])
const showToast = inject('showToast')

const stores = ref([])
const loading = ref(true)
const searchQuery = ref('')
const showForm = ref(false)
const editingStore = ref(null)
const togglingStore = ref(null)
const deletingStore = ref(null)

const filteredStores = computed(() => {
  if (!searchQuery.value.trim()) return stores.value
  const query = searchQuery.value.toLowerCase()
  return stores.value.filter(s =>
    s.name.toLowerCase().includes(query) ||
    s.business.name.toLowerCase().includes(query)
  )
})

const fetchStores = async () => {
  loading.value = true
  try {
    const data = await graphql(`query {
      accessibleStores {
        id name address email phone is_active my_role
        business { id name }
      }
    }`)
    stores.value = data.accessibleStores
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value = false
  }
}

const openCreate = () => { editingStore.value = null; showForm.value = true }

const openEdit = (store) => {
  editingStore.value = { ...store }
  showForm.value = true
}

const onSaved = () => {
  showForm.value = false
  fetchStores()
  emit('store-updated')
  showToast(editingStore.value ? 'Store updated successfully!' : 'Store created successfully!')
}

const confirmToggle = (store) => { togglingStore.value = store }

const handleToggle = async () => {
  const store = togglingStore.value
  const mutation = store.is_active ? 'deactivateStore' : 'reactivateStore'
  try {
    await graphql(`mutation ($id: ID!) { ${mutation}(id: $id) { id is_active } }`, { id: store.id })
    togglingStore.value = null
    fetchStores()
    emit('store-updated')
    showToast(store.is_active ? 'Store deactivated!' : 'Store reactivated!')
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const confirmDelete = (store) => { deletingStore.value = store }

const handleDelete = async () => {
  try {
    await graphql(`mutation ($id: ID!) { deleteStore(id: $id) }`, { id: deletingStore.value.id })
    deletingStore.value = null
    fetchStores()
    emit('store-updated')
    showToast('Store deleted successfully!')
  } catch (err) {
    showToast(err.message, 'error')
  }
}

onMounted(fetchStores)
</script>

<style scoped>
.store-page { padding: 32px; max-width: 900px; margin: 0 auto; overflow: hidden; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

.btn-create { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; }
.btn-create:hover { background: #333; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; margin-bottom: 20px; }

.store-list { display: flex; flex-direction: column; gap: 12px; }

.store-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; display: flex; align-items: stretch; transition: border-color 0.15s, box-shadow 0.15s; }
.store-card:hover { border-color: #d1d5db; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.store-card.inactive { opacity: 0.7; }

.card-body { flex: 1; padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; min-width: 0; overflow: hidden; }
.card-main { display: flex; align-items: center; gap: 12px; }
.card-icon { width: 40px; height: 40px; background: #f3f4f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #6b7280; flex-shrink: 0; }
.card-icon.inactive { background: #fee2e2; color: #dc2626; }

.card-info { min-width: 0; }
.card-title-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.card-info h3 { font-size: 15px; font-weight: 600; color: #111; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.toggle-switch { position: relative; width: 36px; height: 20px; background: #d1d5db; border-radius: 10px; cursor: pointer; transition: background 0.2s; flex-shrink: 0; }
.toggle-switch.active { background: #16a34a; }
.toggle-slider { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
.toggle-switch.active .toggle-slider { transform: translateX(16px); }

.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.status-dot.active { background: #16a34a; }
.status-dot.inactive { background: #dc2626; }

.role-badge { font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 4px; text-transform: capitalize; }
.role-badge.OWNER { color: #16a34a; background: #f0fdf4; }
.role-badge.ACCOUNTANT { color: #2563eb; background: #eff6ff; }
.role-badge.STAFF { color: #6b7280; background: #f3f4f6; }

.biz-name { font-size: 12px; color: #9ca3af; margin-top: 2px; }

.card-details { display: flex; flex-wrap: wrap; gap: 12px; padding-left: 52px; }
.detail-item { display: flex; align-items: center; gap: 5px; font-size: 13px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.card-actions { display: flex; flex-direction: column; border-left: 1px solid #f3f4f6; }
.action-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 0 18px; background: none; border: none; font-size: 13px; font-weight: 500; color: #6b7280; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.action-btn:first-child { border-bottom: 1px solid #f3f4f6; }
.action-btn:hover { background: #f9fafb; color: #111; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; }

.card-role-badge { display: flex; align-items: center; justify-content: center; padding: 0 18px; border-left: 1px solid #f3f4f6; }
.member-tag { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: capitalize; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
