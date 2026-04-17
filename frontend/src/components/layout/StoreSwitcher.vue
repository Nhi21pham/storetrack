<template>
  <div class="switcher" ref="switcherRef">
    <button class="switcher-btn" @click.stop="open = !open" :class="{ active: open }">
      <div class="switcher-content" v-if="currentStore">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
        <span class="switcher-text">
          <strong>{{ currentBusiness?.name }}</strong>
          <span class="divider">-</span>
          {{ currentStore.name }}
          <span class="role-tag">{{ currentStore.my_role }}</span>
        </span>
      </div>
      <div class="switcher-content placeholder" v-else>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
        <span class="switcher-text">Select a store</span>
      </div>
      <svg class="chevron" :class="{ flipped: open }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <div v-if="open" class="dropdown">
      <div class="dropdown-header">Switch Store</div>
      <div class="dropdown-list">
        <div v-for="biz in businesses" :key="biz.id" class="biz-group">
          <div class="biz-label">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            {{ biz.name }}
            <span class="biz-role-tag">{{ biz.role }}</span>
          </div>

          <button
            v-for="store in biz.stores"
            :key="store.id"
            class="store-item"
            :class="{ selected: currentStore?.id === store.id }"
            @click="selectStore(biz, store)"
          >
            <div class="store-info">
              <span class="store-name">{{ store.name }}</span>
              <span class="store-role">{{ store.my_role }}</span>
            </div>
            <svg v-if="currentStore?.id === store.id" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </button>

          <div v-if="biz.stores.length === 0" class="no-stores">No active stores</div>
        </div>
      </div>

      <div class="dropdown-footer">
        <button class="create-btn" @click="goToCreateBusiness">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Create your own business
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'

const emit = defineEmits(['switched'])
const router = useRouter()

const businesses = ref([])
const currentBusiness = ref(null)
const currentStore = ref(null)
const open = ref(false)
const switcherRef = ref(null)

const fetchBusinesses = async () => {
  const token = localStorage.getItem('token')
  try {
    const res = await fetch('/graphql', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        query: `query {
          accessibleBusinesses {
            id name tax_code role
            stores { id name is_active my_role }
          }
        }`
      })
    })
    const data = await res.json()
    if (data.data?.accessibleBusinesses) {
      businesses.value = data.data.accessibleBusinesses
      restoreSelection()
    }
  } catch (err) {
    console.error('Failed to fetch businesses:', err)
  }
}

const restoreSelection = () => {
  const savedStoreId = localStorage.getItem('currentStoreId')
  const savedBizId = localStorage.getItem('currentBusinessId')

  // Try to restore saved selection
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
      }
    }
  }

  // Fallback: pick the first store from the first business
  for (const biz of businesses.value) {
    if (biz.stores.length > 0) {
      currentBusiness.value = biz
      currentStore.value = biz.stores[0]
      saveSelection()
      emit('switched', { business: biz, store: biz.stores[0] })
      return
    }
  }
}

const selectStore = (biz, store) => {
  currentBusiness.value = biz
  currentStore.value = store
  open.value = false
  saveSelection()
  emit('switched', { business: biz, store })
}

const saveSelection = () => {
  if (currentStore.value && currentBusiness.value) {
    localStorage.setItem('currentStoreId', currentStore.value.id)
    localStorage.setItem('currentBusinessId', currentBusiness.value.id)
  }
}

const goToCreateBusiness = () => {
  open.value = false
  router.push('/business')
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

.biz-label { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #374151; padding: 6px 8px; }

.biz-role-tag { font-size: 10px; font-weight: 500; color: #9ca3af; background: #f3f4f6; padding: 1px 6px; border-radius: 4px; text-transform: capitalize; }

.store-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; padding: 9px 10px 9px 28px; border: none; background: none; border-radius: 8px; cursor: pointer; transition: background 0.15s; text-align: left; }
.store-item:hover { background: #f3f4f6; }
.store-item.selected { background: #f0fdf4; }

.store-info { display: flex; align-items: center; gap: 8px; overflow: hidden; }
.store-name { font-size: 13px; font-weight: 500; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.store-role { font-size: 10px; font-weight: 500; color: #6b7280; background: #f3f4f6; padding: 1px 6px; border-radius: 4px; text-transform: capitalize; white-space: nowrap; }

.no-stores { font-size: 12px; color: #9ca3af; padding: 6px 10px 6px 28px; }

.dropdown-footer { border-top: 1px solid #e5e7eb; padding: 8px; }

.create-btn { display: flex; align-items: center; gap: 6px; width: 100%; padding: 10px; border: none; background: none; border-radius: 8px; font-size: 13px; font-weight: 500; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.create-btn:hover { background: #f3f4f6; color: #111; }
</style>