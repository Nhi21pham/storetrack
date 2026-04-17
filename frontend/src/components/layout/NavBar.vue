<template>
  <nav class="navbar">
    <div class="navbar-left">
      <button class="menu-btn" @click.stop="$emit('toggle-sidebar')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <div class="brand">
        <div class="brand-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          </svg>
        </div>
        <span class="brand-name">storetrack</span>
      </div>
    </div>

    <!-- NEW: Business Switcher in center -->
    <div class="navbar-center">
      <StoreSwitcher ref="switcherRef" @switched="$emit('store-switched', $event)" />
    </div>

    <div class="navbar-right">
      <span class="hello-text">Hello, <strong>{{ username }}</strong></span>
      <div class="avatar-wrapper" @click.stop="dropdownOpen = !dropdownOpen">
        <button class="avatar-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
        </button>

        <div class="dropdown" :class="{ open: dropdownOpen }">
          <div class="dropdown-header">
            <div class="dropdown-avatar">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
              </svg>
            </div>
            <div>
              <div class="dropdown-name">{{ username }}</div>
              <div class="dropdown-email">{{ email }}</div>
            </div>
          </div>

          <div class="dropdown-divider"></div>
          <div class="dropdown-section-title">Store</div>
          <div class="dropdown-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            Store Information
          </div>
          <div class="dropdown-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41"/><path d="M4.93 4.93l1.41 1.41"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M20 12h2"/><path d="M2 12h2"/><path d="M19.07 19.07l-1.41-1.41"/><path d="M4.93 19.07l1.41-1.41"/></svg>
            Store Settings
          </div>

          <div class="dropdown-divider"></div>
          <div class="dropdown-section-title">Account</div>
          <div class="dropdown-item" @click.stop="handleAction('account-info')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Account Information
          </div>
          <div class="dropdown-item" @click.stop="handleAction('change-password')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Change Password
          </div>

          <div class="dropdown-divider"></div>
          <div class="dropdown-item logout" @click.stop="handleAction('logout')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
          </div>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import StoreSwitcher from '@/components/layout/StoreSwitcher.vue'

const props = defineProps({
  username: String,
  email: String
})

const dropdownOpen = ref(false)
const switcherRef = ref(null)

const emit = defineEmits(['toggle-sidebar', 'account-info', 'change-password', 'logout', 'store-switched'])

const handleAction = (action) => {
  dropdownOpen.value = false
  emit(action)
}

const handleClickOutside = (e) => {
  const wrapper = document.querySelector('.avatar-wrapper')
  if (wrapper && !wrapper.contains(e.target)) {
    dropdownOpen.value = false
  }
}

// Expose so AppLayout can trigger refresh
const refreshBusinesses = () => {
  switcherRef.value?.fetchBusinesses()
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

defineExpose({ refreshBusinesses })
</script>

<style scoped>
.navbar { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 0 24px; height: 64px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
.navbar-left { display: flex; align-items: center; gap: 16px; }
.navbar-center { flex: 1; display: flex; justify-content: center; padding: 0 20px; max-width: 400px; margin: 0 auto; }
.navbar-right { display: flex; align-items: center; gap: 12px; }
.hello-text { font-size: 14px; color: #6b7280; }
.hello-text strong { color: #111; }
.menu-btn { width: 40px; height: 40px; border: none; background: none; cursor: pointer; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #111; transition: background 0.2s; }
.menu-btn:hover { background: #f3f4f6; }
.brand { display: flex; align-items: center; gap: 10px; }
.brand-logo { width: 36px; height: 36px; background: #111; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.brand-name { font-size: 18px; font-weight: 700; color: #111; }
.avatar-wrapper { position: relative; }
.avatar-btn { width: 40px; height: 40px; border: none; background: #111; cursor: pointer; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; }
.dropdown { display: none; position: absolute; right: 0; top: 48px; width: 260px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); z-index: 200; overflow: hidden; }
.dropdown.open { display: block; }
.dropdown-header { display: flex; align-items: center; gap: 12px; padding: 16px; }
.dropdown-avatar { width: 40px; height: 40px; background: #111; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.dropdown-name { font-size: 14px; font-weight: 600; color: #111; }
.dropdown-email { font-size: 12px; color: #9ca3af; }
.dropdown-divider { height: 1px; background: #e5e7eb; margin: 4px 0; }
.dropdown-section-title { font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase; padding: 8px 16px 4px; letter-spacing: 0.5px; }
.dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; font-size: 14px; color: #374151; cursor: pointer; transition: background 0.2s; }
.dropdown-item:hover { background: #f9fafb; }
.dropdown-item.logout { color: #dc2626; }
.dropdown-item.logout:hover { background: #fef2f2; }
</style>