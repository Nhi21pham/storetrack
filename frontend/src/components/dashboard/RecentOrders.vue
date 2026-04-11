<template>
  <div class="orders-card">
    <div class="chart-header">
      <h3>Recent Orders</h3>
      <p>Latest transactions across all stores</p>
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
        <tr v-for="order in orders" :key="order.id">
          <td class="order-id">{{ order.id }}</td>
          <td class="order-store">{{ order.store }}</td>
          <td class="order-amount">{{ order.amount }}</td>
          <td class="order-time">{{ order.time }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
const orders = [
  { id: '#ORD-2847', store: 'Downtown', amount: '$342', time: '2 min ago' },
  { id: '#ORD-2846', store: 'Westside', amount: '$189', time: '5 min ago' },
  { id: '#ORD-2845', store: 'Harbor', amount: '$567', time: '12 min ago' },
  { id: '#ORD-2844', store: 'Downtown', amount: '$234', time: '18 min ago' },
  { id: '#ORD-2843', store: 'Northgate', amount: '$445', time: '22 min ago' },
]
</script>

<style scoped>
.orders-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.chart-header { margin-bottom: 20px; }
.chart-header h3 { font-size: 18px; font-weight: 700; color: #111; }
.chart-header p { font-size: 14px; color: #6b7280; margin-top: 2px; }
.orders-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
.orders-table th { text-align: left; padding: 12px 16px; font-size: 13px; color: #9ca3af; font-weight: 600; border-bottom: 1px solid #e5e7eb; }
.orders-table td { padding: 18px 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
.orders-table tr:last-child td { border-bottom: none; }
.order-id { font-weight: 700; color: #111; }
.order-store { color: #6b7280; }
.order-amount { font-weight: 700; color: #111; }
.order-time { color: #9ca3af; }
</style>