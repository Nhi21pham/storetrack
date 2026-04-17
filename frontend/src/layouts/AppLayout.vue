<template>
  <div class="app-layout">
    <SideBar :open="sidebarOpen" @close="sidebarOpen = false" />
    <NavBar
      ref="navbarRef"
      :username="username"
      :email="email"
      @toggle-sidebar="sidebarOpen = !sidebarOpen"
      @change-password="showChangePassword = true"
      @account-info="showAccountInfo = true"
      @logout="handleLogout"
      @store-switched="onStoreSwitched"
      @create-business="showCreateBusiness = true"
    />

    <main>
      <router-view />
    </main>

    <ChangePasswordModal v-if="showChangePassword" @close="showChangePassword = false" />
    <AccountModal v-if="showAccountInfo" @close="showAccountInfo = false" @updated="onProfileUpdated" />
    <BusinessFormModal v-if="showCreateBusiness" @close="showCreateBusiness = false" @saved="onBusinessCreated" />
    <Toast :message="toastMessage" :type="toastType" @done="toastMessage = ''" />
  </div>
</template>

<script setup>
import { ref, provide } from 'vue'
import { useRouter } from 'vue-router'
import SideBar from '@/components/layout/SideBar.vue'
import NavBar from '@/components/layout/NavBar.vue'
import ChangePasswordModal from '@/components/layout/ChangePasswordModal.vue'
import AccountModal from '@/components/layout/AccountModal.vue'
import BusinessFormModal from '@/components/business/BusinessFormModal.vue'
import Toast from '@/components/common/Toast.vue'

const router = useRouter()
const sidebarOpen = ref(false)
const showChangePassword = ref(false)
const showAccountInfo = ref(false)
const navbarRef = ref(null)
const currentBusiness = ref(null)
const currentStore = ref(null)
const showCreateBusiness = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const user = JSON.parse(localStorage.getItem('user') || '{}')
const username = ref(user.name || 'User')
const email = ref(user.email || '')

const showToast = (message, type = 'success') => {
  toastMessage.value = ''
  setTimeout(() => {
    toastMessage.value = message
    toastType.value = type
  }, 10)
}
const onProfileUpdated = (updatedUser) => {
  username.value = updatedUser.name
}

const onStoreSwitched = (payload) => {
  currentBusiness.value = payload.business
  currentStore.value = payload.store
}
const onBusinessCreated = () => {
  showCreateBusiness.value = false
  refreshBusinessSwitcher()
  showToast('Business created successfully!')
}
const refreshBusinessSwitcher = () => {
  navbarRef.value?.refreshBusinesses()
}

provide('currentBusiness', currentBusiness)
provide('currentStore', currentStore)
provide('refreshStoreSwitcher', refreshBusinessSwitcher)
provide('showToast', showToast)

const handleLogout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  localStorage.removeItem('currentBusinessId')
  router.push('/login')
}
</script>

<style scoped>
.app-layout {
  min-height: 100vh;
  background: #f9fafb;
  font-family: 'Segoe UI', sans-serif;
}
</style>