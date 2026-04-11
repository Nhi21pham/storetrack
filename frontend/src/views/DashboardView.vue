<template>
  <div class="dashboard" @click="closeAll">
    <!-- Sidebar -->
    <div class="sidebar" :class="{ open: sidebarOpen }" @click.stop>
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
        <div class="menu-item active">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Dashboard
        </div>

        <div class="menu-item has-sub" @mouseenter="reportsOpen = true" @mouseleave="reportsOpen = false">
          <div class="menu-item-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>
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
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          Stores
        </div>

        <div class="menu-item">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Customers
        </div>
      </div>
    </div>

    <!-- Overlay -->
    <div class="overlay" :class="{ show: sidebarOpen }" @click="sidebarOpen = false"></div>

    <!-- Navbar -->
    <nav class="navbar">
      <div class="navbar-left">
        <button class="menu-btn" @click.stop="sidebarOpen = !sidebarOpen">
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

      <div class="navbar-right">
        <span class="hello-text">Hello, <strong>{{ username }}</strong></span>
        <div class="avatar-wrapper" @click.stop="dropdownOpen = !dropdownOpen">
          <button class="avatar-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
          </button>

          <!-- Dropdown -->
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
            <div class="dropdown-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Account Information
            </div>
            <div class="dropdown-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Change Password
            </div>

            <div class="dropdown-divider"></div>

            <div class="dropdown-item logout" @click="handleLogout">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Logout
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Content -->
    <div class="content">
      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card" v-for="stat in stats" :key="stat.label">
          <div class="stat-header">
            <span class="stat-label">{{ stat.label }}</span>
            <span class="stat-icon" v-html="stat.icon"></span>
          </div>
          <div class="stat-value">{{ stat.value }}</div>
          <div class="stat-change" :class="stat.positive ? 'positive' : 'negative'">
            <span class="arrow">{{ stat.positive ? '↗' : '↘' }}</span>
            <span class="pct">{{ stat.change }}</span>
            <span class="vs">vs last month</span>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="charts-row">
        <div class="chart-card">
          <div class="chart-header">
            <div>
              <h3>Sales Trend</h3>
              <p>Monthly revenue performance</p>
            </div>
          </div>
          <div class="chart-area">
            <svg viewBox="0 0 500 220" class="line-chart">
              <defs>
                <linearGradient id="grad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#e8450a" stop-opacity="0.15"/>
                  <stop offset="100%" stop-color="#e8450a" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <line v-for="y in [30,70,110,150]" :key="y" x1="50" :y1="y" x2="490" :y2="y" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4"/>
              <text x="40" y="34" font-size="10" fill="#9ca3af" text-anchor="end">$80k</text>
              <text x="40" y="74" font-size="10" fill="#9ca3af" text-anchor="end">$60k</text>
              <text x="40" y="114" font-size="10" fill="#9ca3af" text-anchor="end">$40k</text>
              <text x="40" y="154" font-size="10" fill="#9ca3af" text-anchor="end">$20k</text>
              <text x="40" y="180" font-size="10" fill="#9ca3af" text-anchor="end">$0k</text>
              <text v-for="(m, i) in months" :key="m" :x="50 + i * (440 / 7)" y="200" font-size="10" fill="#9ca3af" text-anchor="middle">{{ m }}</text>
              <path :d="areaPath" fill="url(#grad)"/>
              <path :d="linePath" fill="none" stroke="#e8450a" stroke-width="2.5" stroke-linejoin="round"/>
              <circle v-for="(pt, i) in points" :key="i" :cx="pt.x" :cy="pt.y" r="4" fill="#e8450a"/>
            </svg>
          </div>
        </div>

        <div class="chart-card products-card">
          <div class="chart-header">
            <div>
              <h3>Top Products</h3>
              <p>Best sellers this month</p>
            </div>
          </div>
          <div class="products-list">
            <div class="product-item" v-for="(product, i) in topProducts" :key="i">
              <div class="product-left">
                <span class="product-rank">#{{ i + 1 }}</span>
                <div>
                  <div class="product-name">{{ product.name }}</div>
                  <div class="product-revenue">{{ product.revenue }}</div>
                </div>
              </div>
              <div class="product-change" :class="product.positive ? 'positive' : 'negative'">
                <span>{{ product.positive ? '↗' : '↘' }}</span>
                {{ product.change }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Orders -->
      <div class="orders-card">
        <div class="chart-header">
          <div>
            <h3>Recent Orders</h3>
            <p>Latest transactions across all stores</p>
          </div>
        </div>
        <table class="orders-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Store</th>
              <th>Amount</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in recentOrders" :key="order.id">
              <td class="order-id">{{ order.id }}</td>
              <td class="order-store">{{ order.store }}</td>
              <td class="order-amount">{{ order.amount }}</td>
              <td class="order-time">{{ order.time }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const sidebarOpen = ref(false)
const dropdownOpen = ref(false)
const reportsOpen = ref(false)

const user = JSON.parse(localStorage.getItem('user') || '{}')
const username = ref(user.name || 'User')
const email = ref(user.email || '')

const closeAll = () => {
  dropdownOpen.value = false
  sidebarOpen.value = false
}

const handleLogout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.push('/login')
}

const stats = [
  { label: 'Total Sales', value: '$72,340', change: '+14.2%', positive: true, icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' },
  { label: 'Active Stores', value: '24', change: '+8.3%', positive: true, icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>' },
  { label: 'Products in Stock', value: '1,847', change: '-2.1%', positive: false, icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>' },
  { label: 'Total Customers', value: '12,458', change: '+22.6%', positive: true, icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' },
]

const topProducts = [
  { name: 'Premium Coffee Beans', revenue: '$12,400', change: '+12%', positive: true },
  { name: 'Organic Tea Collection', revenue: '$9,800', change: '+8%', positive: true },
  { name: 'Artisan Pastries', revenue: '$8,600', change: '-3%', positive: false },
  { name: 'Fresh Sandwiches', revenue: '$7,200', change: '+15%', positive: true },
  { name: 'Smoothie Mix', revenue: '$6,100', change: '+5%', positive: true },
]

const recentOrders = [
  { id: '#ORD-2847', store: 'Downtown', amount: '$342', time: '2 min ago' },
  { id: '#ORD-2846', store: 'Westside', amount: '$189', time: '5 min ago' },
  { id: '#ORD-2845', store: 'Harbor', amount: '$567', time: '12 min ago' },
  { id: '#ORD-2844', store: 'Downtown', amount: '$234', time: '18 min ago' },
  { id: '#ORD-2843', store: 'Northgate', amount: '$445', time: '22 min ago' },
]

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul']
const rawData = [45, 52, 48, 61, 59, 65, 68, 72]
const minVal = 0, maxVal = 80
const chartWidth = 440, chartHeight = 160, startX = 50

const points = rawData.map((v, i) => ({
  x: startX + (i * chartWidth / (rawData.length - 1)),
  y: 170 - ((v - minVal) / (maxVal - minVal)) * chartHeight
}))

const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
const areaPath = linePath + ` L ${points[points.length-1].x} 175 L ${points[0].x} 175 Z`
</script>

<style scoped>
* { box-sizing: border-box; margin: 0; padding: 0; }

.dashboard { min-height: 100vh; background: #f9fafb; font-family: 'Segoe UI', sans-serif; }

/* Sidebar */
.sidebar {
  position: fixed; top: 0; left: -280px; width: 280px; height: 100vh;
  background: #fff; border-right: 1px solid #e5e7eb;
  z-index: 1000; transition: left 0.3s ease;
  display: flex; flex-direction: column;
}
.sidebar.open { left: 0; }
.sidebar-header { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; }
.sidebar-menu { padding: 16px 12px; flex: 1; }

.menu-item {
  display: flex; flex-direction: column;
  padding: 10px 12px; border-radius: 8px; margin-bottom: 4px;
  cursor: pointer; color: #6b7280; font-size: 14px; font-weight: 500;
  transition: all 0.2s;
}
.menu-item.active { background: #f3f4f6; color: #111; }
.menu-item:hover { background: #f3f4f6; color: #111; }
.menu-item-inner { display: flex; align-items: center; gap: 10px; }
.menu-item:not(.has-sub) { flex-direction: row; align-items: center; gap: 10px; }

.chevron { margin-left: auto; }
.menu-item.has-sub {
  position: relative;
}

.submenu {
  display: none;
  position: absolute;
  left: 100%;
  top: 0;
  width: 180px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
  flex-direction: column;
  padding: 6px;
  margin-left: 8px;
  z-index: 999;
}
.submenu.open { display: flex; }
.submenu-item { padding: 10px 12px; font-size: 13px; color: #6b7280; border-radius: 6px; cursor: pointer; }
.submenu-item:hover { background: #f9fafb; color: #111; }

.overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.3); z-index: 999;
}
.overlay.show { display: block; }

/* Navbar */
.navbar {
  background: #fff; border-bottom: 1px solid #e5e7eb;
  padding: 0 24px; height: 64px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 100;
}
.navbar-left { display: flex; align-items: center; gap: 16px; }
.navbar-right { display: flex; align-items: center; gap: 12px; }

.hello-text { font-size: 14px; color: #6b7280; }
.hello-text strong { color: #111; }

.menu-btn {
  width: 40px; height: 40px; border: none; background: none;
  cursor: pointer; border-radius: 8px; display: flex; align-items: center; justify-content: center;
  color: #111; transition: background 0.2s;
}
.menu-btn:hover { background: #f3f4f6; }

.brand { display: flex; align-items: center; gap: 10px; }
.brand-logo { width: 36px; height: 36px; background: #111; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.brand-name { font-size: 18px; font-weight: 700; color: #111; }

.avatar-wrapper { position: relative; }
.avatar-btn {
  width: 40px; height: 40px; border: none; background: #111;
  cursor: pointer; border-radius: 50%; display: flex; align-items: center; justify-content: center;
  color: white;
}

/* Dropdown */
.dropdown {
  display: none; position: absolute; right: 0; top: 48px;
  width: 260px; background: #fff; border: 1px solid #e5e7eb;
  border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.12);
  z-index: 200; overflow: hidden;
}
.dropdown.open { display: block; }

.dropdown-header { display: flex; align-items: center; gap: 12px; padding: 16px; }
.dropdown-avatar { width: 40px; height: 40px; background: #111; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.dropdown-name { font-size: 14px; font-weight: 600; color: #111; }
.dropdown-email { font-size: 12px; color: #9ca3af; }
.dropdown-divider { height: 1px; background: #e5e7eb; margin: 4px 0; }
.dropdown-section-title { font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase; padding: 8px 16px 4px; letter-spacing: 0.5px; }

.dropdown-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px; font-size: 14px; color: #374151;
  cursor: pointer; transition: background 0.2s;
}
.dropdown-item:hover { background: #f9fafb; }
.dropdown-item.logout { color: #dc2626; }
.dropdown-item.logout:hover { background: #fef2f2; }

/* Content */
.content { padding: 32px; max-width: 1400px; margin: 0 auto; }

.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }

.stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.stat-label { font-size: 14px; color: #6b7280; }
.stat-value { font-size: 32px; font-weight: 700; color: #111; margin-bottom: 8px; }
.stat-change { display: flex; align-items: center; gap: 4px; font-size: 14px; }
.stat-change.positive { color: #16a34a; }
.stat-change.negative { color: #dc2626; }
.vs { color: #6b7280; }

.charts-row { display: grid; grid-template-columns: 1fr 380px; gap: 16px; margin-bottom: 24px; }

.chart-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.chart-header { margin-bottom: 20px; }
.chart-header h3 { font-size: 18px; font-weight: 700; color: #111; }
.chart-header p { font-size: 14px; color: #6b7280; margin-top: 2px; }
.line-chart { width: 100%; height: 210px; }

.products-list { display: flex; flex-direction: column; gap: 20px; }
.product-item { display: flex; justify-content: space-between; align-items: center; }
.product-left { display: flex; align-items: center; gap: 12px; }
.product-rank { font-size: 13px; color: #9ca3af; font-weight: 600; min-width: 20px; }
.product-name { font-size: 15px; font-weight: 600; color: #111; }
.product-revenue { font-size: 13px; color: #6b7280; margin-top: 2px; }
.product-change { font-size: 14px; font-weight: 600; }
.product-change.positive { color: #16a34a; }
.product-change.negative { color: #dc2626; }

/* Orders */
.orders-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }

.orders-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
.orders-table th { text-align: left; padding: 12px 16px; font-size: 13px; color: #9ca3af; font-weight: 600; border-bottom: 1px solid #e5e7eb; }
.orders-table td { padding: 18px 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
.orders-table tr:last-child td { border-bottom: none; }
.order-id { font-weight: 700; color: #111; }
.order-store { color: #6b7280; }
.order-amount { font-weight: 700; color: #111; }
.order-time { color: #9ca3af; }
</style>