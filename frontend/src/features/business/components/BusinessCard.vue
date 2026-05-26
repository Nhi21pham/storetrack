<template>
  <div class="business-card">
    <div class="card-body">
      <div class="card-main">
        <div class="card-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <div class="card-info">
          <div class="card-title-row">
            <h3>{{ business.name }}</h3>
            <span class="owner-badge" v-if="business.role === 'owner'">Owner</span>
            <span class="member-badge" v-else>Member</span>
          </div>
          <span class="tax-badge">{{ business.tax_code }}</span>
        </div>
      </div>
      <div class="card-details">
        <div v-if="business.address" class="detail-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          {{ business.address }}
        </div>
        <div v-if="business.email" class="detail-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          {{ business.email }}
        </div>
        <div v-if="business.phone" class="detail-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
          </svg>
          {{ business.phone }}
        </div>
      </div>
      <div class="card-meta">
        <span class="store-count">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/><path d="M8 7v4"/><path d="M16 7v4"/></svg>
          {{ storeCount }} store{{ storeCount !== 1 ? 's' : '' }}
        </span>
      </div>
    </div>
    <div v-if="business.role === 'owner'" class="card-actions">
      <button class="action-btn" @click="$emit('edit', business)" title="Edit">
        <Icon name="edit" :size="15" />
      </button>
      <button class="action-btn danger" @click="$emit('delete', business)" title="Delete">
        <Icon name="delete" :size="15" />
      </button>
    </div>
    <div v-else class="card-role-badge">
      <span class="member-tag">{{ business.role }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Icon from '@/components/common/Icon.vue'

const props = defineProps({
  business: { type: Object, required: true },
})

defineEmits(['edit', 'delete'])

const storeCount = computed(() => props.business.stores?.length || 0)
</script>

<style scoped>
.business-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; display: flex; align-items: stretch; transition: border-color 0.15s, box-shadow 0.15s; }
.business-card:hover { border-color: #d1d5db; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }

.card-body { flex: 1; padding: 18px 20px; display: flex; flex-direction: column; gap: 10px; min-width: 0; overflow: hidden; }
.card-main { display: flex; align-items: center; gap: 12px; }
.card-icon { width: 40px; height: 40px; background: #f3f4f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #6b7280; flex-shrink: 0; }
.card-info { min-width: 0; }
.card-info h3 { font-size: 15px; font-weight: 600; color: #111; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tax-badge { display: inline-block; font-size: 11px; font-weight: 500; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; margin-top: 2px; font-family: monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; }

.card-details { display: flex; flex-wrap: wrap; gap: 12px; padding-left: 52px; }
.detail-item { display: flex; align-items: center; gap: 5px; font-size: 13px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.card-meta { padding-left: 52px; }
.store-count { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: #9ca3af; }

.card-actions { display: flex; flex-direction: column; border-left: 1px solid #f3f4f6; }
.action-btn { flex: 1; display: flex; align-items: center; justify-content: center; padding: 0 14px; background: none; border: none; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:first-child { border-bottom: 1px solid #f3f4f6; }
.action-btn:hover { background: #f9fafb; color: #111; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; }

.card-title-row { display: flex; align-items: center; gap: 8px; }
.owner-badge { font-size: 10px; font-weight: 600; color: #16a34a; background: #f0fdf4; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; }
.member-badge { font-size: 10px; font-weight: 600; color: #6b7280; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; }
.card-role-badge { display: flex; align-items: center; justify-content: center; padding: 0 18px; border-left: 1px solid #f3f4f6; }
.member-tag { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: capitalize; }
</style>
