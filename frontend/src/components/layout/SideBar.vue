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
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
      </router-link>

      <router-link to="/business" class="menu-item" :class="{ active: isActive('/business') }" @click="$emit('close')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        Business
      </router-link>

      <router-link to="/stores" class="menu-item" :class="{ active: isActive('/stores') }" @click="$emit('close')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
        Stores
      </router-link>

      <router-link to="/users" class="menu-item" :class="{ active: isActive('/users') }" @click="$emit('close')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Users
      </router-link>

      <router-link to="/audit-log" class="menu-item" :class="{ active: isActive('/audit-log') }" @click="$emit('close')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
          <line x1="10" y1="9" x2="8" y2="9"/>
        </svg>
        Audit Log
      </router-link>

      <div class="menu-item has-sub" @mouseenter="reportsOpen = true" @mouseleave="reportsOpen = false">
        <div class="menu-item-inner">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          Reports
          <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
        </div>
        <div class="submenu" :class="{ open: reportsOpen }">
          <div class="submenu-item">Sales Report</div>
          <div class="submenu-item">Stock Report</div>
          <div class="submenu-item">Revenue Report</div>
          <div class="submenu-item">Customer Report</div>
        </div>
      </div>

      <div class="menu-item">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        Products
      </div>

      <div class="menu-item">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Customers
      </div>
    </div>
  </div>

  <div class="overlay" :class="{ show: open }" @click="$emit('close')"></div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'

defineProps({ open: Boolean })
defineEmits(['close'])

const route = useRoute()
const reportsOpen = ref(false)

const isActive = (path) => route.path.startsWith(path)
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
.submenu { display: none; position: absolute; left: 100%; top: 0; width: 180px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); flex-direction: column; padding: 6px; margin-left: 8px; z-index: 999; }
.submenu.open { display: flex; }
.submenu-item { padding: 10px 12px; font-size: 13px; color: #6b7280; border-radius: 6px; cursor: pointer; }
.submenu-item:hover { background: #f9fafb; color: #111; }
.overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 999; }
.overlay.show { display: block; }
</style>