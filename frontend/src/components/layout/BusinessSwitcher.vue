<template>
  <div class="switcher" ref="switcherRef">
    <button class="switcher-btn" @click.stop="open = !open" :class="{ active: open }">
      <div class="switcher-content" v-if="currentBusiness">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span class="biz-name">{{ currentBusiness.name }}</span>
        <span class="biz-divider">–</span>
        <span class="biz-tax">{{ currentBusiness.tax_code }}</span>
      </div>
      <div class="switcher-content placeholder" v-else>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span class="biz-name">Select a business</span>
      </div>
      <svg class="chevron" :class="{ flipped: open }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <div v-if="open" class="dropdown">
      <div class="dropdown-header">Switch Business</div>
      <div class="dropdown-list">
        <button
          v-for="biz in businesses"
          :key="biz.id"
          class="dropdown-item"
          :class="{ selected: currentBusiness?.id === biz.id }"
          @click="selectBusiness(biz)"
        >
          <div class="item-info">
            <span class="item-name">{{ biz.name }}</span>
            <span class="item-tax">{{ biz.tax_code }}</span>
          </div>
          <svg v-if="currentBusiness?.id === biz.id" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </button>
      </div>
      <div v-if="businesses.length === 0" class="dropdown-empty">No businesses found.</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const emit = defineEmits(['switched'])

const businesses = ref([])
const currentBusiness = ref(null)
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
        query: `query { myBusinesses { id name tax_code } }`
      })
    })
    const data = await res.json()
    if (data.data?.myBusinesses) {
      businesses.value = data.data.myBusinesses
      const savedId = localStorage.getItem('currentBusinessId')
      const saved = businesses.value.find(b => b.id === savedId)
      currentBusiness.value = saved || businesses.value[0] || null
      if (currentBusiness.value) {
        localStorage.setItem('currentBusinessId', currentBusiness.value.id)
        emit('switched', currentBusiness.value)
      }
    }
  } catch (err) {
    console.error('Failed to fetch businesses:', err)
  }
}

const selectBusiness = (biz) => {
  currentBusiness.value = biz
  localStorage.setItem('currentBusinessId', biz.id)
  open.value = false
  emit('switched', biz)
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

.switcher-btn { display: flex; align-items: center; gap: 8px; padding: 6px 14px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.15s; min-width: 180px; }
.switcher-btn:hover, .switcher-btn.active { background: #e5e7eb; border-color: #d1d5db; }

.switcher-content { display: flex; align-items: center; gap: 6px; flex: 1; overflow: hidden; }
.switcher-content.placeholder { color: #9ca3af; }

.biz-name { font-size: 13px; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.placeholder .biz-name { color: #9ca3af; font-weight: 400; }
.biz-divider { color: #d1d5db; font-size: 13px; }
.biz-tax { font-size: 11px; color: #6b7280; font-family: monospace; white-space: nowrap; }

.chevron { color: #9ca3af; transition: transform 0.2s; flex-shrink: 0; }
.chevron.flipped { transform: rotate(180deg); }

.dropdown { position: absolute; top: calc(100% + 6px); left: 0; right: 0; min-width: 260px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); z-index: 500; overflow: hidden; }
.dropdown-header { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 14px 6px; }
.dropdown-list { max-height: 240px; overflow-y: auto; padding: 4px 6px 6px; }

.dropdown-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; padding: 10px; border: none; background: none; border-radius: 8px; cursor: pointer; transition: background 0.15s; text-align: left; }
.dropdown-item:hover { background: #f3f4f6; }
.dropdown-item.selected { background: #f0fdf4; }

.item-info { display: flex; flex-direction: column; gap: 1px; overflow: hidden; }
.item-name { font-size: 13px; font-weight: 500; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item-tax { font-size: 11px; color: #9ca3af; font-family: monospace; }

.dropdown-empty { padding: 20px; text-align: center; font-size: 13px; color: #9ca3af; }
</style>