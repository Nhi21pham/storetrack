<template>
  <div class="switcher" ref="switcherRef">
    <button class="switcher-btn" @click.stop="open = !open" :class="{ active: open }">
      <div class="switcher-content" v-if="currentStore">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
        <span class="status-dot" :class="currentStore.is_active ? 'active' : 'inactive'"></span>
        <span class="switcher-text">
          <strong>{{ currentBusiness?.name }}</strong>
          <span class="divider">-</span>
          {{ currentStore.name }}
          <span class="role-tag">{{ currentStore.my_role }}</span>
        </span>
      </div>
      <div class="switcher-content" v-else-if="currentBusiness">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span class="switcher-text">
          <strong>{{ currentBusiness.name }}</strong>
          <span class="role-tag">{{ $t('nav.businessTag') }}</span>
        </span>
      </div>
      <div class="switcher-content placeholder" v-else>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
        <span class="switcher-text">{{ $t('nav.selectStore') }}</span>
      </div>
      <svg class="chevron" :class="{ flipped: open }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <div v-if="open" class="dropdown">
      <div class="dropdown-header">{{ $t('nav.switchStore') }}</div>
      <div class="dropdown-list">
        <div v-for="biz in businesses" :key="biz.id" class="biz-group">
          <div
            class="biz-label"
            :class="{
              clickable: biz.role === 'owner',
              selected: biz.role === 'owner' && !currentStore && currentBusiness?.id === biz.id,
            }"
            @click="biz.role === 'owner' ? selectBusiness(biz) : null"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span class="biz-name" v-tooltip="biz.name">{{ biz.name }}</span>
            <span class="biz-role-tag">{{ biz.role }}</span>
            <span v-if="biz.role === 'owner'" class="biz-level-tag">{{ $t('nav.businessLevel') }}</span>
            <svg
              v-if="biz.role === 'owner' && !currentStore && currentBusiness?.id === biz.id"
              class="biz-check"
              width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"
            >
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>

          <div
            v-for="store in biz.stores"
            :key="store.id"
            class="store-item"
            :class="{ selected: currentStore?.id === store.id, inactive: !store.is_active }"
            @click="store.is_active ? selectStore(biz, store) : null"
          >
            <div class="store-info">
              <span v-if="store.is_active" class="store-dot"></span>
              <span class="store-name" v-tooltip="store.name">{{ store.name }}</span>
              <span class="store-role">{{ store.my_role }}</span>
              <span v-if="!store.is_active" class="inactive-tag">{{ $t('nav.inactiveTag') }}</span>
            </div>
            <svg v-if="currentStore?.id === store.id && store.is_active" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>

          <div v-if="biz.stores.length === 0" class="no-stores">{{ $t('nav.noStores') }}</div>
        </div>
      </div>

      <div class="dropdown-footer">
        <button class="create-btn" @click="goToCreateBusiness">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('nav.createOwnBusiness') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { graphql } from '@/api'
import { translateError } from '@/utils/translateError'

const emit = defineEmits(['switched', 'create-business'])
const showToast = inject('showToast', null)
const router = useRouter()

const businesses = ref([])
const currentBusiness = ref(null)
const currentStore = ref(null)
const open = ref(false)
const switcherRef = ref(null)

const fetchBusinesses = async () => {
  try {
    const data = await graphql(`query {
      accessibleBusinesses {
        id party_id name tax_code role
        stores { id name is_active my_role }
      }
    }`)
    businesses.value = data.accessibleBusinesses

    // If a store is already selected, keep it — just refresh its data (e.g. is_active may have changed)
    if (currentStore.value) {
      for (const biz of businesses.value) {
        const freshStore = biz.stores.find(s => s.id === currentStore.value.id)
        if (freshStore) {
          currentBusiness.value = biz
          currentStore.value = freshStore
          emit('switched', { business: biz, store: freshStore })
          return
        }
      }
      // Store was deleted entirely — fall through to restoreSelection
    }

    restoreSelection()
  } catch (err) {
    console.error('Failed to fetch businesses:', err)
    showToast?.(translateError(err), 'error')
  }
}

const restoreSelection = () => {
  const savedStoreId = localStorage.getItem('currentStoreId')
  const savedBizId = localStorage.getItem('currentBusinessId')

  // Business-level restore: a saved business with no store means the user
  // last selected the business itself in the switcher.
  if (savedBizId && !savedStoreId) {
    const biz = businesses.value.find(b => b.id === savedBizId)
    if (biz) {
      currentBusiness.value = biz
      currentStore.value = null
      emit('switched', { business: biz, store: null })
      return
    }
  }

  // Try to restore saved store selection — active or inactive
  if (savedStoreId && savedBizId) {
    for (const biz of businesses.value) {
      if (biz.id === savedBizId) {
        const store = biz.stores.find(s => s.id === savedStoreId)
        if (store) {
          currentBusiness.value = biz
          currentStore.value = store
          emit('switched', { business: biz, store })
          return
        }
        break
      }
    }
  }

  // Fallback: pick the first active store from any business
  for (const biz of businesses.value) {
    const activeStore = biz.stores.find(s => s.is_active)
    if (activeStore) {
      currentBusiness.value = biz
      currentStore.value = activeStore
      saveSelection()
      emit('switched', { business: biz, store: activeStore })
      return
    }
  }

  // No stores anywhere — emit the business so the guard can show the right message
  const firstBiz = businesses.value[0] ?? null
  currentBusiness.value = firstBiz
  currentStore.value = null
  emit('switched', { business: firstBiz, store: null })
}

const selectStore = (biz, store) => {
  currentBusiness.value = biz
  currentStore.value = store
  open.value = false
  saveSelection()
  emit('switched', { business: biz, store })
}

const selectBusiness = (biz) => {
  currentBusiness.value = biz
  currentStore.value = null
  open.value = false
  saveBusinessLevelSelection()
  emit('switched', { business: biz, store: null })
}

const saveSelection = () => {
  if (currentStore.value && currentBusiness.value) {
    localStorage.setItem('currentStoreId', currentStore.value.id)
    localStorage.setItem('currentBusinessId', currentBusiness.value.id)
  }
}

const saveBusinessLevelSelection = () => {
  if (currentBusiness.value) {
    localStorage.setItem('currentBusinessId', currentBusiness.value.id)
    localStorage.removeItem('currentStoreId')
  }
}

const goToCreateBusiness = () => {
  open.value = false
  emit('create-business')
}

const handleClickOutside = (e) => {
  if (switcherRef.value && !switcherRef.value.contains(e.target)) {
    open.value = false
  }
}

onMounted(() => {
  fetchBusinesses()
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})

defineExpose({ fetchBusinesses })
</script>

<style scoped>
.switcher { position: relative; }

.switcher-btn { display: flex; align-items: center; gap: 8px; padding: 6px 14px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.15s; min-width: 200px; max-width: 420px; }
.switcher-btn:hover, .switcher-btn.active { background: #e5e7eb; border-color: #d1d5db; }

.switcher-content { display: flex; align-items: center; gap: 6px; flex: 1; overflow: hidden; }
.switcher-content.placeholder { color: #9ca3af; }

.switcher-text { font-size: 13px; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.switcher-text strong { font-weight: 600; }
.placeholder .switcher-text { color: #9ca3af; }

.divider { color: #d1d5db; margin: 0 2px; }

.role-tag { font-size: 10px; font-weight: 600; color: #6b7280; background: #e5e7eb; padding: 1px 6px; border-radius: 4px; margin-left: 4px; text-transform: capitalize; }

.chevron { color: #9ca3af; transition: transform 0.2s; flex-shrink: 0; }
.chevron.flipped { transform: rotate(180deg); }

/* Dropdown */
.dropdown { position: absolute; top: calc(100% + 6px); left: 0; min-width: 300px; max-width: 420px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); z-index: 500; overflow: hidden; }

.dropdown-header { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 14px 6px; }

.dropdown-list { max-height: 300px; overflow-y: auto; padding: 4px 6px; }

.biz-group { margin-bottom: 8px; }
.biz-group:last-child { margin-bottom: 0; }

.biz-label { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #374151; padding: 6px 8px; border-radius: 8px; transition: background 0.15s; }
.biz-label.clickable { cursor: pointer; }
.biz-label.clickable:hover { background: #f3f4f6; }
.biz-label.clickable:hover .biz-role-tag { background: #fff; }
.biz-label.selected { background: #f0fdf4; }
.biz-label .biz-name { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.biz-label .biz-check { flex-shrink: 0; }

.biz-role-tag { font-size: 10px; font-weight: 500; color: #9ca3af; background: #f3f4f6; padding: 1px 6px; border-radius: 4px; text-transform: capitalize; }
.biz-level-tag { font-size: 10px; font-weight: 500; color: #1d4ed8; background: #eff6ff; border: 1px solid #dbeafe; padding: 1px 6px; border-radius: 4px; }

.store-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; padding: 9px 10px 9px 28px; border: none; background: none; border-radius: 8px; cursor: pointer; transition: background 0.15s; text-align: left; }
.store-item:hover { background: #f3f4f6; }
.store-item.selected { background: #f0fdf4; }
.store-item.inactive { cursor: default; opacity: 0.5; }
.store-item.inactive:hover { background: none; }

.inactive-tag { font-size: 10px; font-weight: 500; color: #dc2626; background: #fee2e2; padding: 1px 6px; border-radius: 4px; white-space: nowrap; }

.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.status-dot.active { background: #16a34a; }
.status-dot.inactive { background: #dc2626; }

.store-info { display: flex; align-items: center; gap: 8px; overflow: hidden; }
.store-dot { width: 7px; height: 7px; border-radius: 50%; background: #16a34a; flex-shrink: 0; }
.store-name { font-size: 13px; font-weight: 500; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.store-role { font-size: 10px; font-weight: 500; color: #6b7280; background: #f3f4f6; padding: 1px 6px; border-radius: 4px; text-transform: capitalize; white-space: nowrap; }

.no-stores { font-size: 12px; color: #9ca3af; padding: 6px 10px 6px 28px; }

.dropdown-footer { border-top: 1px solid #e5e7eb; padding: 8px; }

.create-btn { display: flex; align-items: center; gap: 6px; width: 100%; padding: 10px; border: none; background: none; border-radius: 8px; font-size: 13px; font-weight: 500; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.create-btn:hover { background: #f3f4f6; color: #111; }
</style>