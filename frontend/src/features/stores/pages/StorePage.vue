<template>
  <PageContainer :maxWidth="900">
    <PageHeader :title="$t('stores.title')" :subtitle="$t('stores.subtitle')">
      <template #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('stores.newStore') }}
        </button>
      </template>
    </PageHeader>

    <SearchBar v-model="searchQuery" :placeholder="$t('stores.searchPlaceholder')" />

    <LoadingState v-if="loading">{{ $t('stores.loadingStores') }}</LoadingState>

    <EmptyState
      v-else-if="filteredStores.length === 0 && searchQuery.trim()"
      :description="$t('stores.noStoresMatching', { query: searchQuery })"
    />

    <EmptyState
      v-else-if="stores.length === 0"
      :title="$t('stores.noStoresTitle')"
      :description="$t('stores.noStoresDesc')"
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
      </template>
    </EmptyState>

    <div v-else class="store-list">
      <StoreCard
        v-for="store in filteredStores"
        :key="store.id"
        :store="store"
        @edit="openEdit"
        @delete="confirmDelete"
        @toggle="confirmToggle"
      />
    </div>

    <StoreFormModal
      v-if="showForm"
      :store="editingStore"
      @close="showForm = false"
      @saved="onSaved"
    />

    <ConfirmDialog
      v-if="togglingStore"
      :title="togglingStore.is_active ? $t('stores.toggleDeactivateTitle') : $t('stores.toggleReactivateTitle')"
      :message="togglingStore.is_active
        ? $t('stores.toggleDeactivateMessage', { name: togglingStore.name })
        : $t('stores.toggleReactivateMessage', { name: togglingStore.name })"
      :confirm-text="togglingStore.is_active ? $t('stores.toggleDeactivateConfirm') : $t('stores.toggleReactivateConfirm')"
      :cancel-text="$t('common.cancel')"
      :type="togglingStore.is_active ? 'warning' : 'success'"
      @confirm="handleToggle"
      @cancel="togglingStore = null"
    />

    <ConfirmDialog
      v-if="deletingStore"
      :title="$t('stores.deleteTitle')"
      :message="$t('stores.deleteMessage', { name: deletingStore.name })"
      :confirm-text="$t('stores.confirmDelete')"
      :cancel-text="$t('common.cancel')"
      type="danger"
      @confirm="handleDelete"
      @cancel="deletingStore = null"
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
import StoreCard from '@/features/stores/components/StoreCard.vue'
import StoreFormModal from '@/features/stores/components/StoreFormModal.vue'
import { useStores } from '@/features/stores/composables/useStores'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const showToast = inject('showToast')
const refreshStoreSwitcher = inject('refreshStoreSwitcher')

const {
  stores, loading, searchQuery, filteredStores,
  fetchStores, toggleActive, removeStore,
} = useStores({ onError: (msg) => showToast(msg, 'error') })

const showForm      = ref(false)
const editingStore  = ref(null)
const togglingStore = ref(null)
const deletingStore = ref(null)

const openCreate = () => { editingStore.value = null; showForm.value = true }

const openEdit = (store) => {
  editingStore.value = { ...store }
  showForm.value = true
}

const onSaved = () => {
  const wasEdit = !!editingStore.value
  showForm.value = false
  fetchStores()
  refreshStoreSwitcher?.()
  showToast(wasEdit ? t('stores.updateSuccess') : t('stores.createSuccess'))
}

const confirmToggle = (store) => { togglingStore.value = store }

const handleToggle = async () => {
  const store = togglingStore.value
  try {
    await toggleActive(store)
    togglingStore.value = null
    refreshStoreSwitcher?.()
    showToast(store.is_active ? t('stores.deactivated') : t('stores.reactivated'))
  } catch (err) {
    showToast(translateError(err), 'error')
  }
}

const confirmDelete = (store) => { deletingStore.value = store }

const handleDelete = async () => {
  try {
    await removeStore(deletingStore.value.id)
    deletingStore.value = null
    refreshStoreSwitcher?.()
    showToast(t('stores.deleteSuccess'))
  } catch (err) {
    showToast(translateError(err), 'error')
  }
}

onMounted(fetchStores)
</script>

<style scoped>
.store-list { display: flex; flex-direction: column; gap: 12px; }

.btn-create { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; }
.btn-create:hover { background: #333; }
</style>
