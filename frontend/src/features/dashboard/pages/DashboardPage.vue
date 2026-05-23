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
import StatCards from '@/features/dashboard/components/StatCards.vue'
import SalesTrend from '@/features/dashboard/components/SalesTrend.vue'
import TopProducts from '@/features/dashboard/components/TopProducts.vue'
import RecentOrders from '@/features/dashboard/components/RecentOrders.vue'
</script>

<style scoped>
.dashboard-content { padding: 32px; max-width: 1400px; margin: 0 auto; }
.charts-row { display: grid; grid-template-columns: 1fr 380px; gap: 16px; margin-bottom: 24px; }
</style>
