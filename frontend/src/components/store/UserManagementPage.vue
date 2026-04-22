<template>
  <div class="user-management-page">
    <div class="page-header">
      <div>
        <h1>User Management</h1>
        <p class="subtitle">Manage members and invitations across your stores.</p>
      </div>
      <button v-if="ownedStores.length" class="btn-history" @click="showHistory = true">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        Invitation History
      </button>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading stores...</span>
    </div>

    <div v-else-if="ownedStores.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <h3>No stores to manage</h3>
      <p>You need to be an owner of a store to manage its members.</p>
    </div>

    <div v-else class="store-list">
      <div v-for="store in ownedStores" :key="store.id" class="store-card" :class="{ expanded: expandedStores.has(store.id) }">

        <div class="card-header" @click="toggleStore(store.id)">
          <div class="store-info">
            <div class="store-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
              </svg>
            </div>
            <div class="store-text">
              <h3>{{ store.name }}</h3>
              <span class="biz-name">{{ store.business.name }}</span>
            </div>
          </div>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </div>

        <div v-if="expandedStores.has(store.id)" class="card-body">
          <StoreMembersPanel
            :ref="el => { if (el) panelRefs[store.id] = el }"
            :storeId="store.id"
            :canInvite="true"
            :canRemove="true"
            @invite="openInvite(store)"
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import { graphql } from '@/api'
import StoreMembersPanel from '@/components/store/StoreMembersPanel.vue'
import InviteUserModal from '@/components/store/InviteUserModal.vue'
import InvitationHistoryModal from '@/components/store/InvitationHistoryModal.vue'

const showToast = inject('showToast')

const stores = ref([])
const loading = ref(true)
const invitingStore = ref(null)
const showHistory = ref(false)
const panelRefs = ref({})
const expandedStores = ref(new Set())

const ownedStores = computed(() => stores.value.filter(s => s.my_role === 'OWNER'))

const fetchStores = async () => {
  loading.value = true
  try {
    const data = await graphql(`query {
      accessibleStores {
        id name my_role
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

const toggleStore = (id) => {
  if (expandedStores.value.has(id)) {
    expandedStores.value.delete(id)
  } else {
    expandedStores.value.add(id)
  }
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
.user-management-page { padding: 32px; max-width: 900px; margin: 0 auto; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

.btn-history { display: flex; align-items: center; gap: 6px; padding: 10px 16px; background: #fff; color: #374151; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
.btn-history:hover { border-color: #d1d5db; background: #f9fafb; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; }

.store-list { display: flex; flex-direction: column; gap: 10px; }

.store-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s; }
.store-card:hover { border-color: #d1d5db; }
.store-card.expanded { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }

.card-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; cursor: pointer; transition: background 0.12s; user-select: none; }
.card-header:hover { background: #fafafa; }

.store-info { display: flex; align-items: center; gap: 12px; }
.store-icon { width: 36px; height: 36px; background: #f3f4f6; border-radius: 9px; display: flex; align-items: center; justify-content: center; color: #6b7280; flex-shrink: 0; }
.store-text h3 { font-size: 15px; font-weight: 600; color: #111; }
.biz-name { font-size: 12px; color: #9ca3af; margin-top: 1px; display: block; }

.chevron { color: #9ca3af; transition: transform 0.2s; flex-shrink: 0; }
.expanded .chevron { transform: rotate(180deg); }

.card-body { border-top: 1px solid #f3f4f6; padding: 14px 20px 18px; }


@keyframes spin { to { transform: rotate(360deg); } }
</style>
