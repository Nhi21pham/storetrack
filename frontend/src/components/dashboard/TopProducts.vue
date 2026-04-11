<template>
  <div class="chart-card">
    <div class="chart-header">
      <h3>Top Products</h3>
      <p>Best sellers this month</p>
    </div>
    <div class="products-list">
      <div class="product-item" v-for="(product, i) in products" :key="i">
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
</template>

<script setup>
const products = [
  { name: 'Premium Coffee Beans', revenue: '$12,400', change: '+12%', positive: true },
  { name: 'Organic Tea Collection', revenue: '$9,800', change: '+8%', positive: true },
  { name: 'Artisan Pastries', revenue: '$8,600', change: '-3%', positive: false },
  { name: 'Fresh Sandwiches', revenue: '$7,200', change: '+15%', positive: true },
  { name: 'Smoothie Mix', revenue: '$6,100', change: '+5%', positive: true },
]
</script>

<style scoped>
.chart-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.chart-header { margin-bottom: 20px; }
.chart-header h3 { font-size: 18px; font-weight: 700; color: #111; }
.chart-header p { font-size: 14px; color: #6b7280; margin-top: 2px; }
.products-list { display: flex; flex-direction: column; gap: 20px; }
.product-item { display: flex; justify-content: space-between; align-items: center; }
.product-left { display: flex; align-items: center; gap: 12px; }
.product-rank { font-size: 13px; color: #9ca3af; font-weight: 600; min-width: 20px; }
.product-name { font-size: 15px; font-weight: 600; color: #111; }
.product-revenue { font-size: 13px; color: #6b7280; margin-top: 2px; }
.product-change { font-size: 14px; font-weight: 600; }
.product-change.positive { color: #16a34a; }
.product-change.negative { color: #dc2626; }
</style>