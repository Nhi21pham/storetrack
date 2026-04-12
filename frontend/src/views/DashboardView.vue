<template>
  <div class="dashboard" @click="closeAll">
    <SideBar :open="sidebarOpen" @close="sidebarOpen = false" />
    <NavBar
      :username="username"
      :email="email"
      @toggle-sidebar="sidebarOpen = !sidebarOpen"
      @change-password="showChangePassword = true; dropdownOpen = false"
      @account-info="showAccountInfo = true"
      @logout="handleLogout"
    />
    <div class="content">
      <StatCards />
      <div class="charts-row">
        <SalesTrend />
        <TopProducts />
      </div>
      <RecentOrders />
    </div>
    <ChangePasswordModal v-if="showChangePassword" @close="showChangePassword = false" />
    <AccountModal v-if="showAccountInfo" @close="showAccountInfo = false" @updated="onProfileUpdated"
/>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import NavBar from '@/components/layout/NavBar.vue'
import SideBar from '@/components/layout/SideBar.vue'
import ChangePasswordModal from '@/components/layout/ChangePasswordModal.vue'
import StatCards from '@/components/dashboard/StatCards.vue'
import SalesTrend from '@/components/dashboard/SalesTrend.vue'
import TopProducts from '@/components/dashboard/TopProducts.vue'
import RecentOrders from '@/components/dashboard/RecentOrders.vue'
import AccountModal from '@/components/layout/AccountModal.vue'
import { graphql } from '@/api'

const router = useRouter()
const sidebarOpen = ref(false)
const showChangePassword = ref(false)
const showAccountInfo = ref(false)
const user = JSON.parse(localStorage.getItem('user') || '{}')
const username = ref('')
const email = ref('')

const closeAll = () => {
  if (showChangePassword.value || showAccountInfo.value) return
  sidebarOpen.value = false
}

const onProfileUpdated = (updatedUser) => {
  username.value = updatedUser.name
}

const handleLogout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.push('/login')
}

onMounted(async () => {
  try {
    const data = await graphql(`
      query {
        user {
          id
          name
          email
        }
      }
    `)
    username.value = data.user.name
    email.value = data.user.email
    localStorage.setItem('user', JSON.stringify(data.user))
  } catch (err) {
    // fallback to localStorage
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    username.value = user.name || 'User'
    email.value = user.email || ''
  }
})
</script>

<style scoped>
* { box-sizing: border-box; margin: 0; padding: 0; }
.dashboard { min-height: 100vh; background: #f9fafb; font-family: 'Segoe UI', sans-serif; }
.content { padding: 32px; max-width: 1400px; margin: 0 auto; }
.charts-row { display: grid; grid-template-columns: 1fr 380px; gap: 16px; margin-bottom: 24px; }
</style>