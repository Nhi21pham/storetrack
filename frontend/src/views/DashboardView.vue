<template>
  <div class="dashboard" @click="closeAll">
    <SideBar :open="sidebarOpen" @close="sidebarOpen = false" />
    <NavBar
      :username="username"
      :email="email"
      @toggle-sidebar="sidebarOpen = !sidebarOpen"
      @change-password="showChangePassword = true; dropdownOpen = false"
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
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import NavBar from '@/components/layout/NavBar.vue'
import SideBar from '@/components/layout/SideBar.vue'
import ChangePasswordModal from '@/components/layout/ChangePasswordModal.vue'
import StatCards from '@/components/dashboard/StatCards.vue'
import SalesTrend from '@/components/dashboard/SalesTrend.vue'
import TopProducts from '@/components/dashboard/TopProducts.vue'
import RecentOrders from '@/components/dashboard/RecentOrders.vue'

const router = useRouter()
const sidebarOpen = ref(false)
const showChangePassword = ref(false)

const user = JSON.parse(localStorage.getItem('user') || '{}')
const username = ref(user.name || 'User')
const email = ref(user.email || '')

const closeAll = () => {
  sidebarOpen.value = false
}

const handleLogout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.push('/login')
}
</script>

<style scoped>
* { box-sizing: border-box; margin: 0; padding: 0; }
.dashboard { min-height: 100vh; background: #f9fafb; font-family: 'Segoe UI', sans-serif; }
.content { padding: 32px; max-width: 1400px; margin: 0 auto; }
.charts-row { display: grid; grid-template-columns: 1fr 380px; gap: 16px; margin-bottom: 24px; }
</style>