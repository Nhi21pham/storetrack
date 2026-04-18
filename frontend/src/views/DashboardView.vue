<template>
  <div class="dashboard-content">
    <StatCards />
    <div class="charts-row">
      <SalesTrend />
      <TopProducts />
    </div>
    <RecentOrders />
  </div>
</template>

<script setup>
import StatCards from '@/components/dashboard/StatCards.vue'
import SalesTrend from '@/components/dashboard/SalesTrend.vue'
import TopProducts from '@/components/dashboard/TopProducts.vue'
import RecentOrders from '@/components/dashboard/RecentOrders.vue'
</script>

<style scoped>
.dashboard-content { padding: 32px; max-width: 1400px; margin: 0 auto; }
.charts-row { display: grid; grid-template-columns: 1fr 380px; gap: 16px; margin-bottom: 24px; }
</style>