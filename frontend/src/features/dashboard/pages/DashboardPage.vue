<template>
  <div class="dashboard-content">
    <div class="dash-header">
      <div class="scope-label">
        <span class="scope-name">{{ scope === 'business' ? currentBusiness?.name : currentStore?.name }}</span>
        <span class="scope-tag" :class="scope">{{ scope === 'business' ? 'Business' : 'Store' }}</span>
      </div>
      <label class="month-picker">
        <span>Month</span>
        <input type="month" v-model="month" :max="maxMonth" />
      </label>
    </div>

    <EmptyState
      v-if="!scopeId"
      title="No store selected"
      description="Pick a store (or Business level) from the switcher to see its dashboard."
    />

    <template v-else>
      <LoadingState v-if="loading">Loading dashboard…</LoadingState>

      <template v-else-if="data">
        <StatCards :summary="data" />
        <div class="charts-row">
          <SalesTrend :points="data.sales_trend" />
          <TopProducts :products="data.top_products" />
        </div>
        <StorePerformance v-if="scope === 'business'" :stores="data.store_performance" />
      </template>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import StatCards from '@/features/dashboard/components/StatCards.vue'
import SalesTrend from '@/features/dashboard/components/SalesTrend.vue'
import TopProducts from '@/features/dashboard/components/TopProducts.vue'
import StorePerformance from '@/features/dashboard/components/StorePerformance.vue'
import { fetchDashboard, fetchDashboardByBusiness } from '@/features/dashboard/services/dashboardService'

const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')
const scope = computed(() => (!currentStore.value && isBusinessOwner.value) ? 'business' : 'store')
const scopeId = computed(() => scope.value === 'business' ? currentBusiness.value?.id : currentStore.value?.id)

const maxMonth = new Date().toISOString().slice(0, 7)
const month = ref(maxMonth)

const data = ref(null)
const loading = ref(false)

const load = async () => {
  if (!scopeId.value) return
  loading.value = true
  try {
    data.value = scope.value === 'business'
      ? await fetchDashboardByBusiness({ businessId: scopeId.value, month: month.value })
      : await fetchDashboard({ storeId: scopeId.value, month: month.value })
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value = false
  }
}

watch([scope, scopeId, month], load, { immediate: true })
</script>

<style scoped>
.dashboard-content { padding: 32px; max-width: 1400px; margin: 0 auto; }
.dash-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.scope-label { display: flex; align-items: center; gap: 10px; }
.scope-name { font-size: 20px; font-weight: 700; color: #111; }
.scope-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 3px 9px; border-radius: 6px; }
.scope-tag.store { background: #eef2ff; color: #4338ca; }
.scope-tag.business { background: #fef3c7; color: #b45309; }
.month-picker { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #6b7280; }
.month-picker input { padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 8px; font: inherit; color: #111; cursor: pointer; }
.month-picker input:focus { outline: none; border-color: #111; }
.charts-row { display: grid; grid-template-columns: 1fr 380px; gap: 16px; margin-bottom: 24px; }
</style>
