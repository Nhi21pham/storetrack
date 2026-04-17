<template>
  <div class="business-page">
    <div class="page-header">
      <div>
        <h1>My Businesses</h1>
        <p class="subtitle">Manage your businesses and their information.</p>
      </div>
      <button class="btn-create" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Business
      </button>
    </div>
    <SearchBar v-model="searchQuery" placeholder="Search businesses..." />

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading businesses...</span>
    </div>
    
    <!-- Empty -->
    <div v-else-if="filteredBusinesses.length === 0 && searchQuery.trim()" class="empty-state">
      <p>No businesses matching "{{ searchQuery }}"</p>
    </div>
    <div v-else-if="businesses.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </div>
      <h3>No businesses yet</h3>
      <p>You don't have any businesses yet. Create one or ask to be invited to a store.</p>
    </div>

    <!-- Business list -->
    <div v-else class="business-list">
      <div v-for="biz in filteredBusinesses" :key="biz.id" class="business-card">
        <div class="card-body">
          <div class="card-main">
            <div class="card-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
              </svg>
            </div>
            <div class="card-info">
              <div class="card-title-row">
                <h3>{{ biz.name }}</h3>
                <span class="owner-badge" v-if="biz.role === 'owner'">Owner</span>
                <span class="member-badge" v-else>Member</span>
              </div>
              <span class="tax-badge">{{ biz.tax_code }}</span>
            </div>
          </div>
          <div class="card-details">
            <div v-if="biz.address" class="detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              {{ biz.address }}
            </div>
            <div v-if="biz.email" class="detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              {{ biz.email }}
            </div>
            <div v-if="biz.phone" class="detail-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              {{ biz.phone }}
            </div>
          </div>
          <div class="card-meta">
            <span class="store-count">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/><path d="M8 7v4"/><path d="M16 7v4"/></svg>
              {{ biz.stores?.length || 0 }} store{{ (biz.stores?.length || 0) !== 1 ? 's' : '' }}
            </span>
          </div>
        </div>
        <div v-if="biz.role === 'owner'" class="card-actions">
          <button class="action-btn" @click="openEdit(biz)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
          <button class="action-btn danger" @click="confirmDelete(biz)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Delete
          </button>
        </div>
        <div v-else class="card-role-badge">
          <span class="member-tag">{{ biz.role }}</span>
        </div>
      </div>
    </div>

    <BusinessFormModal v-if="showForm" :business="editingBusiness" @close="showForm = false" @saved="onSaved" />

    <ConfirmDialog
      v-if="deletingBusiness"
      title="Delete Business"
      :message="`Are you sure you want to delete '${deletingBusiness.name}'? This will also delete all stores under this business. This action cannot be undone.`"
      confirm-text="Yes, delete"
      cancel-text="Cancel"
      @confirm="handleDelete"
      @cancel="deletingBusiness = null"
    />
  </div>
</template>

<script setup>
import BusinessFormModal from '@/components/business/BusinessFormModal.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import { ref, computed, onMounted, inject } from 'vue'

const emit = defineEmits(['business-updated'])

const businesses = ref([])   
const loading = ref(true)
const showForm = ref(false)
const editingBusiness = ref(null)
const deletingBusiness = ref(null)
const searchQuery = ref('')
const showToast = inject('showToast')

const filteredBusinesses = computed(() => {
  if (!searchQuery.value.trim()) return businesses.value
  const query = searchQuery.value.toLowerCase()
  return businesses.value.filter(biz => biz.name.toLowerCase().includes(query))
})

const fetchBusinesses = async () => {
  loading.value = true
  const token = localStorage.getItem('token')
  try {
    const res = await fetch('/graphql', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({
        query: `query { accessibleBusinesses { id name tax_code address email phone role stores { id name is_active my_role } } }`
      })
    })
    const data = await res.json()
    if (data.data?.accessibleBusinesses) {
      businesses.value = data.data.accessibleBusinesses
    }
  } catch (err) {
    console.error('Failed to fetch businesses:', err)
  } finally {
    loading.value = false
  }
}

const openCreate = () => { editingBusiness.value = null; showForm.value = true }
const openEdit = (biz) => { editingBusiness.value = { ...biz }; showForm.value = true }

const onSaved = (result) => {
  showForm.value = false
  fetchBusinesses()
  emit('business-updated')
  showToast(editingBusiness.value ? 'Business updated successfully!' : 'Business created successfully!')
}

const confirmDelete = (biz) => { deletingBusiness.value = biz }

const handleDelete = async () => {
  const token = localStorage.getItem('token')
  try {
    const res = await fetch('/graphql', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({
        query: `mutation DeleteBusiness($id: ID!) { deleteBusiness(id: $id) }`,
        variables: { id: deletingBusiness.value.id }
      })
    })
    const data = await res.json()
    if (!data.errors) {
      deletingBusiness.value = null
      fetchBusinesses()
      emit('business-updated')
      showToast('Business deleted successfully!')
    }
  } catch (err) {
    console.error('Failed to delete:', err)
  }
}

onMounted(fetchBusinesses)
</script>

<style scoped>
.business-page { padding: 32px; max-width: 900px; margin: 0 auto; overflow: hidden; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
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

.business-list { display: flex; flex-direction: column; gap: 12px; }

.business-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; display: flex; align-items: stretch; transition: border-color 0.15s, box-shadow 0.15s; }
.business-card:hover { border-color: #d1d5db; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }

.card-body { flex: 1; padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; }
.card-main { display: flex; align-items: center; gap: 12px; }
.card-icon { width: 40px; height: 40px; background: #f3f4f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #6b7280; flex-shrink: 0; }
.card-info { min-width: 0; }
.card-info h3 { font-size: 15px; font-weight: 600; color: #111; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tax-badge { display: inline-block; font-size: 11px; font-weight: 500; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; margin-top: 2px; font-family: monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; }

.card-details { display: flex; flex-wrap: wrap; gap: 12px; padding-left: 52px; }
.detail-item { display: flex; align-items: center; gap: 5px; font-size: 13px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.card-meta { padding-left: 52px; }
.store-count { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: #9ca3af; }

.card-actions { display: flex; flex-direction: column; border-left: 1px solid #f3f4f6; }
.action-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 0 18px; background: none; border: none; font-size: 13px; font-weight: 500; color: #6b7280; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.action-btn:first-child { border-bottom: 1px solid #f3f4f6; }
.action-btn:hover { background: #f9fafb; color: #111; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; }

.card-title-row { display: flex; align-items: center; gap: 8px; }
.owner-badge { font-size: 10px; font-weight: 600; color: #16a34a; background: #f0fdf4; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; }
.member-badge { font-size: 10px; font-weight: 600; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; }
.card-role-badge { display: flex; align-items: center; justify-content: center; padding: 0 18px; border-left: 1px solid #f3f4f6; }
.member-tag { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: capitalize; }

.card-body { flex: 1; padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; min-width: 0; overflow: hidden; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>