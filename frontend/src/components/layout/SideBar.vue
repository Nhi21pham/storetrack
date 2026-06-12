<template>
  <div class="sidebar" :class="{ open: open }" @click.stop>
    <div class="sidebar-header">
      <div class="brand">
        <div class="brand-logo">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
          </svg>
        </div>
        <span class="brand-name">storetrack</span>
      </div>
    </div>

    <div class="sidebar-menu">
      <router-link to="/dashboard" class="menu-item" :class="{ active: isActive('/dashboard') }" @click="$emit('close')">
        <Icon name="dashboard" :size="18" />
        Dashboard
      </router-link>

      <router-link to="/business" class="menu-item" :class="{ active: isActive('/business') }" @click="$emit('close')">
        <Icon name="business" :size="18" />
        Business
      </router-link>

      <router-link to="/stores" class="menu-item" :class="{ active: isActive('/stores') }" @click="$emit('close')">
        <Icon name="store" :size="18" />
        Stores
      </router-link>

      <router-link to="/users" class="menu-item" :class="{ active: isActive('/users') }" @click="$emit('close')">
        <Icon name="users" :size="18" />
        Users
      </router-link>

      <router-link to="/audit-log" class="menu-item" :class="{ active: isActive('/audit-log') }" @click="$emit('close')">
        <Icon name="audit" :size="18" />
        Audit Log
      </router-link>

      <div class="menu-item has-sub" @mouseenter="reportsOpen = true" @mouseleave="reportsOpen = false">
        <div class="menu-item-inner">
          <Icon name="reports" :size="18" />
          Reports
          <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
        </div>
        <div class="submenu" :class="{ open: reportsOpen }">
          <router-link to="/reports/sales" class="submenu-item" :class="{ active: isActive('/reports/sales') }" @click="$emit('close')">Sales Report</router-link>
          <router-link to="/reports/stock" class="submenu-item" :class="{ active: isActive('/reports/stock') }" @click="$emit('close')">Stock Report</router-link>
          <router-link to="/reports/profit" class="submenu-item" :class="{ active: isActive('/reports/profit') }" @click="$emit('close')">Profit Report</router-link>
          <div class="submenu-item">Revenue Report</div>
          <div class="submenu-item">Customer Report</div>
        </div>
      </div>

      <router-link to="/products" class="menu-item" :class="{ active: isActive('/products') }" @click="$emit('close')">
        <Icon name="product" :size="18" />
        Products
      </router-link>

      <router-link to="/customers" class="menu-item" :class="{ active: isActive('/customers') }" @click="$emit('close')">
        <Icon name="users" :size="18" />
        Customers
      </router-link>

      <router-link to="/suppliers" class="menu-item" :class="{ active: isActive('/suppliers') }" @click="$emit('close')">
        <Icon name="supplier" :size="18" />
        Suppliers
      </router-link>

      <div class="menu-item has-sub" :class="{ active: isActive('/purchase-invoices') || isActive('/sale-invoices') }" @mouseenter="invoicesOpen = true" @mouseleave="invoicesOpen = false">
        <div class="menu-item-inner">
          <Icon name="invoice" :size="18" />
          Invoices
          <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
        </div>
        <div class="submenu" :class="{ open: invoicesOpen }">
          <router-link to="/purchase-invoices" class="submenu-item" :class="{ active: isActive('/purchase-invoices') }" @click="$emit('close')">
            <span class="submenu-item-label">
              <Icon name="purchase-invoice" :size="15" color="currentColor" />
              Purchase Invoices
            </span>
          </router-link>
          <router-link to="/sale-invoices" class="submenu-item" :class="{ active: isActive('/sale-invoices') }" @click="$emit('close')">
            <span class="submenu-item-label">
              <Icon name="sale-invoice" :size="15" color="currentColor" />
              Sale Invoices
            </span>
          </router-link>
        </div>
      </div>

      <div class="menu-item has-sub" @mouseenter="openOthers" @mouseleave="closeOthersSoon">
        <div class="menu-item-inner">
          <Icon name="more" :size="18" />
          Others
          <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
        </div>
        <div class="submenu" :class="{ open: othersOpen }">
          <div class="submenu-item has-nested" @mouseenter="openBanking" @mouseleave="closeBankingSoon">
            <span class="submenu-item-label">
              <Icon name="bank" :size="15" color="currentColor" />
              Banking
            </span>
            <svg class="chevron-sm" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
            <div class="submenu submenu-nested" :class="{ open: bankingOpen }">
              <router-link to="/banking/banks" class="submenu-item" :class="{ active: isActive('/banking/banks') }" @click="$emit('close')">Banks</router-link>
              <router-link to="/banking/bank-accounts" class="submenu-item" :class="{ active: isActive('/banking/bank-accounts') }" @click="$emit('close')">Bank Accounts</router-link>
            </div>
          </div>
          <router-link to="/units" class="submenu-item" :class="{ active: isActive('/units') }" @click="$emit('close')">
            <span class="submenu-item-label">
              <Icon name="unit" :size="15" color="currentColor" />
              Units
            </span>
          </router-link>
          <router-link to="/product-categories" class="submenu-item" :class="{ active: isActive('/product-categories') }" @click="$emit('close')">
            <span class="submenu-item-label">
              <Icon name="category" :size="15" color="currentColor" />
              Product Categories
            </span>
          </router-link>
          <router-link to="/tags" class="submenu-item" :class="{ active: isActive('/tags') }" @click="$emit('close')">
            <span class="submenu-item-label">
              <Icon name="tag" :size="15" color="currentColor" />
              Tags
            </span>
          </router-link>
        </div>
      </div>
    </div>
  </div>

  <div class="overlay" :class="{ show: open }" @click="$emit('close')"></div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import Icon from '@/components/common/Icon.vue'

defineProps({ open: Boolean })
defineEmits(['close'])

const route = useRoute()
const reportsOpen = ref(false)
const invoicesOpen = ref(false)
const othersOpen = ref(false)
const bankingOpen = ref(false)

const isActive = (path) => route.path.startsWith(path)

const CLOSE_DELAY_MS = 150
let othersCloseTimer = null
let bankingCloseTimer = null

const openOthers = () => {
  if (othersCloseTimer) { clearTimeout(othersCloseTimer); othersCloseTimer = null }
  othersOpen.value = true
}

const closeOthersSoon = () => {
  if (othersCloseTimer) clearTimeout(othersCloseTimer)
  othersCloseTimer = setTimeout(() => {
    othersOpen.value = false
    bankingOpen.value = false
  }, CLOSE_DELAY_MS)
}

const openBanking = () => {
  if (bankingCloseTimer) { clearTimeout(bankingCloseTimer); bankingCloseTimer = null }
  if (othersCloseTimer) { clearTimeout(othersCloseTimer); othersCloseTimer = null }
  bankingOpen.value = true
}

const closeBankingSoon = () => {
  if (bankingCloseTimer) clearTimeout(bankingCloseTimer)
  bankingCloseTimer = setTimeout(() => {
    bankingOpen.value = false
  }, CLOSE_DELAY_MS)
}
</script>

<style scoped>
.sidebar { position: fixed; top: 0; left: -280px; width: 280px; height: 100vh; background: #fff; border-right: 1px solid #e5e7eb; z-index: 1000; transition: left 0.3s ease; display: flex; flex-direction: column; }
.sidebar.open { left: 0; }
.sidebar-header { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; }
.sidebar-menu { padding: 16px 12px; flex: 1; }
.brand { display: flex; align-items: center; gap: 10px; }
.brand-logo { width: 36px; height: 36px; background: #111; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.brand-name { font-size: 18px; font-weight: 700; color: #111; }
.menu-item { display: flex; flex-direction: column; padding: 10px 12px; border-radius: 8px; margin-bottom: 4px; cursor: pointer; color: #6b7280; font-size: 14px; font-weight: 500; transition: all 0.2s; text-decoration: none; }
.menu-item.active { background: #f3f4f6; color: #111; }
.menu-item:hover { background: #f3f4f6; color: #111; }
.menu-item:not(.has-sub) { flex-direction: row; align-items: center; gap: 10px; }
.menu-item-inner { display: flex; align-items: center; gap: 10px; }
.chevron { margin-left: auto; }
.menu-item.has-sub { position: relative; }
.submenu { display: none; position: absolute; left: 100%; top: -4px; min-width: 200px; background: #f7f7f8; border: 1px solid #ececef; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 10px 25px -5px rgba(0,0,0,0.12); flex-direction: column; padding: 8px; margin-left: 10px; z-index: 999; opacity: 0; transform: translateX(-4px); transition: opacity 0.15s ease, transform 0.15s ease; pointer-events: none; }
.submenu.open { display: flex; opacity: 1; transform: translateX(0); pointer-events: auto; }
.submenu::before { content: ''; position: absolute; top: 0; left: -10px; width: 10px; height: 100%; }
.submenu-item { padding: 9px 12px; font-size: 13.5px; font-weight: 500; color: #4b5563; border-radius: 8px; cursor: pointer; text-decoration: none; display: block; transition: background 0.12s, color 0.12s; }
.submenu-item-label { display: inline-flex; align-items: center; gap: 10px; }
.submenu-item:hover { background: #ebebef; color: #111; }
.submenu-item.active { background: #eef2ff; color: #1d4ed8; font-weight: 600; }
.submenu-item.has-nested { position: relative; display: flex; align-items: center; justify-content: space-between; }
.submenu-item.has-nested:hover .chevron-sm { color: #111; }
.submenu-nested { left: calc(100% - 4px); top: -8px; margin-left: 6px; min-width: 220px; }
.chevron-sm { flex-shrink: 0; color: #9ca3af; transition: color 0.12s; }
.overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 999; }
.overlay.show { display: block; }
</style>