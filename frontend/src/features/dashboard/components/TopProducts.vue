<template>
  <div class="chart-card">
    <div class="chart-header">
      <h3>Top Products</h3>
      <p>Ranked by {{ activeLabel.toLowerCase() }} this month</p>
    </div>

    <SegmentedToggle v-model="rankBy" :options="RANK_METRICS" class="rank-toggle" />

    <div v-if="ranked.length" class="products-list">
      <div class="product-item" v-for="(product, i) in ranked" :key="product.product_id">
        <div class="product-left">
          <span class="product-rank">#{{ i + 1 }}</span>
          <div class="product-name">{{ product.product_name }}</div>
        </div>
        <div class="product-metric">{{ metricValue(product) }}</div>
      </div>
    </div>
    <p v-else class="empty">No products sold this month.</p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import SegmentedToggle from '@/components/common/SegmentedToggle.vue'
import { formatMoney, formatQuantity } from '@/features/invoices/constants'

const props = defineProps({
  products: { type: Array, required: true }, // all month's products with all 4 metrics
})

const RANK_METRICS = [
  { value: 'revenue',  label: 'Revenue'  },
  { value: 'qty_sold', label: 'Qty' },
  { value: 'profit',   label: 'Profit'   },
  { value: 'orders',   label: 'Orders' },
]

const rankBy = ref('revenue')
const activeLabel = computed(() => RANK_METRICS.find((m) => m.value === rankBy.value)?.label ?? 'Revenue')

// Re-rank client-side by the active metric and take the top 5.
const ranked = computed(() =>
  [...props.products]
    .sort((a, b) => (Number(b[rankBy.value]) || 0) - (Number(a[rankBy.value]) || 0))
    .slice(0, 5),
)

const metricValue = (product) => {
  if (rankBy.value === 'revenue' || rankBy.value === 'profit') return formatMoney(product[rankBy.value])
  if (rankBy.value === 'qty_sold') return formatQuantity(product.qty_sold)
  return String(product.orders)
}
</script>

<style scoped>
.chart-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.chart-header { margin-bottom: 14px; }
.chart-header h3 { font-size: 18px; font-weight: 700; color: #111; }
.chart-header p { font-size: 14px; color: #6b7280; margin-top: 2px; }
.rank-toggle { margin-bottom: 18px; }
.products-list { display: flex; flex-direction: column; gap: 18px; }
.product-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.product-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.product-rank { font-size: 13px; color: #9ca3af; font-weight: 700; min-width: 22px; }
.product-name { font-size: 14.5px; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.product-metric { font-size: 14.5px; font-weight: 700; color: #111; font-variant-numeric: tabular-nums; white-space: nowrap; }
.empty { padding: 24px 8px; text-align: center; color: #9ca3af; font-size: 13.5px; }
</style>
