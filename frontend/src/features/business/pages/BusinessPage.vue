<template>
  <PageContainer :maxWidth="900">
    <PageHeader :title="$t('business.title')" :subtitle="$t('business.subtitle')">
      <template #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('business.newBusiness') }}
        </button>
      </template>
    </PageHeader>

    <SearchBar v-model="searchQuery" :placeholder="$t('business.searchPlaceholder')" />

    <LoadingState v-if="loading">{{ $t('business.loadingBusinesses') }}</LoadingState>

    <EmptyState
      v-else-if="filteredBusinesses.length === 0 && searchQuery.trim()"
      :description="$t('business.noBusinessesMatching', { query: searchQuery })"
    />

    <EmptyState
      v-else-if="businesses.length === 0"
      :title="$t('business.noBusinessesTitle')"
      :description="$t('business.noBusinessesDesc')"
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
      :title="$t('business.deleteTitle')"
      :message="$t('business.deleteMessage', { name: deletingBusiness.name })"
      :confirm-text="$t('business.confirmDelete')"
      :cancel-text="$t('common.cancel')"
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
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

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
  showToast(wasEdit ? t('business.updateSuccess') : t('business.createSuccess'))
}

const confirmDelete = (biz) => { deletingBusiness.value = biz }

const handleDelete = async () => {
  try {
    await removeBusiness(deletingBusiness.value.id)
    deletingBusiness.value = null
    refreshStoreSwitcher?.()
    showToast(t('business.deleteSuccess'))
  } catch (err) {
    showToast(translateError(err), 'error')
  }
}

onMounted(fetchBusinesses)
</script>

<style scoped>
.business-list { display: flex; flex-direction: column; gap: 12px; }

.btn-create { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; }
.btn-create:hover { background: #333; }
</style>
