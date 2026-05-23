<template>
  <PageContainer :maxWidth="900">
    <PageHeader title="My Businesses" subtitle="Manage your businesses and their information.">
      <template #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Business
        </button>
      </template>
    </PageHeader>

    <SearchBar v-model="searchQuery" placeholder="Search businesses..." />

    <LoadingState v-if="loading">Loading businesses...</LoadingState>

    <EmptyState
      v-else-if="filteredBusinesses.length === 0 && searchQuery.trim()"
      :description="`No businesses matching &quot;${searchQuery}&quot;`"
    />

    <EmptyState
      v-else-if="businesses.length === 0"
      title="No businesses yet"
      description="You don't have any businesses yet. Create one or ask to be invited to a store."
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </template>
    </EmptyState>

    <div v-else class="business-list">
      <BusinessCard
        v-for="biz in filteredBusinesses"
        :key="biz.id"
        :business="biz"
        @edit="openEdit"
        @delete="confirmDelete"
      />
    </div>

    <BusinessFormModal
      v-if="showForm"
      :business="editingBusiness"
      @close="showForm = false"
      @saved="onSaved"
    />

    <ConfirmDialog
      v-if="deletingBusiness"
      title="Delete Business"
      :message="`Are you sure you want to delete '${deletingBusiness.name}'? This will also delete all stores under this business. This action cannot be undone.`"
      confirm-text="Yes, delete"
      cancel-text="Cancel"
      type="danger"
      @confirm="handleDelete"
      @cancel="deletingBusiness = null"
    />
  </PageContainer>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import BusinessCard from '@/features/business/components/BusinessCard.vue'
import BusinessFormModal from '@/features/business/components/BusinessFormModal.vue'
import { useBusinesses } from '@/features/business/composables/useBusinesses'

const showToast = inject('showToast')
const refreshStoreSwitcher = inject('refreshStoreSwitcher')

const {
  businesses, loading, searchQuery, filteredBusinesses,
  fetchBusinesses, removeBusiness,
} = useBusinesses({ onError: (msg) => showToast(msg, 'error') })

const showForm = ref(false)
const editingBusiness = ref(null)
const deletingBusiness = ref(null)

const openCreate = () => {
  editingBusiness.value = null
  showForm.value = true
}

const openEdit = (biz) => {
  editingBusiness.value = { ...biz }
  showForm.value = true
}

const onSaved = () => {
  const wasEdit = !!editingBusiness.value
  showForm.value = false
  fetchBusinesses()
  refreshStoreSwitcher?.()
  showToast(wasEdit ? 'Business updated successfully!' : 'Business created successfully!')
}

const confirmDelete = (biz) => { deletingBusiness.value = biz }

const handleDelete = async () => {
  try {
    await removeBusiness(deletingBusiness.value.id)
    deletingBusiness.value = null
    refreshStoreSwitcher?.()
    showToast('Business deleted successfully!')
  } catch (err) {
    showToast(err.message, 'error')
  }
}

onMounted(fetchBusinesses)
</script>

<style scoped>
.business-list { display: flex; flex-direction: column; gap: 12px; }

.btn-create { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; }
.btn-create:hover { background: #333; }
</style>
