<template>
  <PageContainer :maxWidth="900">
    <PageHeader title="User Management" subtitle="Manage members and invitations across your stores.">
      <template v-if="ownedStores.length" #actions>
        <button class="btn-history" @click="showHistory = true">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          Invitation History
        </button>
      </template>
    </PageHeader>

    <SearchBar v-model="searchQuery" placeholder="Search member name or email..." />

    <LoadingState v-if="loading">Loading stores...</LoadingState>

    <EmptyState
      v-else-if="ownedStores.length === 0"
      title="No stores to manage"
      description="You need to be an owner of a store to manage its members."
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </template>
    </EmptyState>

    <div v-else class="store-list">
      <div
        v-for="store in visibleStores"
        :key="store.id"
        class="store-card"
        :class="{ expanded: expandedStores.has(store.id) }"
      >
        <div class="card-header" @click="toggleStore(store.id)">
          <div class="store-info">
            <div class="store-icon" :class="{ inactive: !store.is_active }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
              </svg>
            </div>
            <div class="store-text">
              <div class="store-name-row">
                <h3>{{ store.name }}</h3>
                <span v-if="!store.is_active" class="inactive-badge">inactive</span>
              </div>
              <span class="biz-name">{{ store.business.name }}</span>
            </div>
          </div>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </div>

        <div v-if="expandedStores.has(store.id)" class="card-body">
          <InactiveBanner v-if="!store.is_active">
            This store is deactivated. Member list is read-only.
          </InactiveBanner>
          <StoreMembersPanel
            :ref="el => { if (el) panelRefs[store.id] = el }"
            :storeId="store.id"
            :canInvite="store.is_active"
            :canRemove="store.is_active"
            :search="searchQuery"
            @invite="openInvite(store)"
            @has-matches="storeMatchMap[store.id] = $event"
            @error="showToast($event, 'error')"
            @member-removed="showToast('Member removed successfully.')"
          />
        </div>
      </div>
    </div>

    <InviteUserModal
      v-if="invitingStore"
      :storeId="invitingStore.id"
      :storeName="invitingStore.name"
      @close="invitingStore = null"
      @invited="onInvited"
    />

    <InvitationHistoryModal
      v-if="showHistory"
      :ownedStores="ownedStores"
      @close="showHistory = false"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import InactiveBanner from '@/components/common/InactiveBanner.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import StoreMembersPanel from '@/features/users/components/StoreMembersPanel.vue'
import InviteUserModal from '@/features/users/components/InviteUserModal.vue'
import InvitationHistoryModal from '@/features/users/components/InvitationHistoryModal.vue'
import { fetchAccessibleStoresBrief } from '@/features/users/services/userService'
import { ROLE } from '@/features/users/constants'

const showToast = inject('showToast')

const stores         = ref([])
const loading        = ref(true)
const searchQuery    = ref('')
const storeMatchMap  = ref({})
const invitingStore  = ref(null)
const showHistory    = ref(false)
const panelRefs      = ref({})
const expandedStores = ref(new Set())

const ownedStores = computed(() => stores.value.filter((s) => s.my_role === ROLE.OWNER))

const visibleStores = computed(() => {
  if (!searchQuery.value.trim()) return ownedStores.value
  return ownedStores.value.filter((s) => storeMatchMap.value[s.id] !== false)
})

watch(searchQuery, (q) => {
  if (q.trim()) {
    ownedStores.value.forEach((s) => expandedStores.value.add(s.id))
  } else {
    storeMatchMap.value = {}
    expandedStores.value.clear()
  }
})

const fetchStores = async () => {
  loading.value = true
  try {
    stores.value = await fetchAccessibleStoresBrief()
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value = false
  }
}

const toggleStore = (id) => {
  if (expandedStores.value.has(id)) expandedStores.value.delete(id)
  else                              expandedStores.value.add(id)
}

const openInvite = (store) => { invitingStore.value = store }

const onInvited = (email) => {
  const storeId = invitingStore.value?.id
  invitingStore.value = null
  showToast(`Invitation sent to ${email}!`)
  panelRefs.value[storeId]?.refresh()
}

onMounted(fetchStores)
</script>

<style scoped>
.store-list { display: flex; flex-direction: column; gap: 10px; }

.store-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s; }
.store-card:hover { border-color: #d1d5db; }
.store-card.expanded { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }

.card-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; cursor: pointer; transition: background 0.12s; user-select: none; }
.card-header:hover { background: #fafafa; }

.store-info { display: flex; align-items: center; gap: 12px; }
.store-icon { width: 36px; height: 36px; background: #f3f4f6; border-radius: 9px; display: flex; align-items: center; justify-content: center; color: #6b7280; flex-shrink: 0; }
.store-icon.inactive { background: #fee2e2; color: #dc2626; }
.store-name-row { display: flex; align-items: center; gap: 8px; }
.store-text h3 { font-size: 15px; font-weight: 600; color: #111; }
.biz-name { font-size: 12px; color: #9ca3af; margin-top: 1px; display: block; }

.inactive-badge { font-size: 10px; font-weight: 600; color: #dc2626; background: #fee2e2; padding: 2px 7px; border-radius: 4px; }

.chevron { color: #9ca3af; transition: transform 0.2s; flex-shrink: 0; }
.expanded .chevron { transform: rotate(180deg); }

.card-body { border-top: 1px solid #f3f4f6; padding: 14px 20px 18px; }

.btn-history { display: flex; align-items: center; gap: 6px; padding: 10px 16px; background: #fff; color: #374151; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
.btn-history:hover { border-color: #d1d5db; background: #f9fafb; }
</style>
