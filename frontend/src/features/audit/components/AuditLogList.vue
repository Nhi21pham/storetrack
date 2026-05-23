<template>
  <div class="log-feed" :class="{ 'log-feed--fetching': fetching }">
    <div v-for="log in logs" :key="log.id" class="log-entry">
      <span class="object-badge" :class="badgeClass(log.object_type)">{{ objectLabel(log.object_type) }}</span>
      <div class="log-body">
        <div class="log-actor-block" :title="actorTitle(log)">
          <span class="log-actor">{{ log.actor_name || 'System' }}</span><span v-if="log.actor_email" class="log-actor-email">&nbsp;({{ log.actor_email }})</span>
        </div>
        <p class="log-action" :title="actionTitle(log)" v-html="renderAction(log)"></p>
        <div class="log-aside">
          <span v-if="showStoreColumn && log.store_name" class="log-store" :title="log.store_name">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/></svg>
            <span class="log-store-name">{{ log.store_name }}</span>
          </span>
          <span class="log-time">{{ formatDateTime(log.created_at) }}</span>
        </div>
      </div>
    </div>

    <Pagination
      :currentPage="currentPage"
      :totalPages="totalPages"
      :total="total"
      :perPage="perPage"
      @update:currentPage="$emit('page', $event)"
      @update:perPage="$emit('perPage', $event)"
    />
  </div>
</template>

<script setup>
import Pagination from '@/components/common/Pagination.vue'
import {
  badgeClass, objectLabel, actorTitle, actionTitle, renderAction, formatDateTime,
} from '@/features/audit/utils/format'

defineProps({
  logs:            { type: Array, required: true },
  fetching:        { type: Boolean, required: true },
  showStoreColumn: { type: Boolean, default: false },
  currentPage:     { type: Number, required: true },
  totalPages:      { type: Number, required: true },
  total:           { type: Number, required: true },
  perPage:         { type: Number, required: true },
})

defineEmits(['page', 'perPage'])
</script>

<style scoped>
.log-feed { display: flex; flex-direction: column; gap: 0; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff; transition: opacity 0.15s; }
.log-feed--fetching { opacity: 0.45; pointer-events: none; }

.log-entry { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #f3f4f6; transition: background 0.12s; }
.log-entry:last-of-type { border-bottom: none; }
.log-entry:hover { background: #fafafa; }

.object-badge { flex-shrink: 0; align-self: flex-start; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.04em; line-height: 1.4; min-width: 78px; text-align: center; }
.object-badge.badge-business   { background: #ffedd5; color: #c2410c; }
.object-badge.badge-store      { background: #dbeafe; color: #1d4ed8; }
.object-badge.badge-user       { background: #d1fae5; color: #065f46; }
.object-badge.badge-invitation { background: #ede9fe; color: #6d28d9; }
.object-badge.badge-supplier   { background: #fef9c3; color: #854d0e; }
.object-badge.badge-customer   { background: #fce7f3; color: #9d174d; }
.object-badge.badge-default    { background: #f3f4f6; color: #6b7280; }

.log-body { flex: 1; min-width: 0; display: flex; align-items: center; gap: 12px; }

.log-actor-block { flex-shrink: 0; max-width: 30%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.log-actor { color: #111; font-weight: 600; font-size: 14px; }
.log-actor-email { color: #6b7280; font-size: 12.5px; }

.log-aside { display: flex; align-items: center; gap: 8px; flex-shrink: 0; max-width: 35%; }
.log-store { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: #1d4ed8; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 5px; padding: 2px 7px; font-weight: 500; max-width: 160px; min-width: 0; }
.log-store svg { color: #1d4ed8; flex-shrink: 0; }
.log-store-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.log-time { font-size: 12px; color: #9ca3af; white-space: nowrap; flex-shrink: 0; }

.log-action { flex: 1; min-width: 0; font-size: 13.5px; line-height: 1.5; margin: 0; color: #4b5563; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.log-action :deep(strong) { font-weight: 600; color: #111; }
.log-action :deep(.verb) { font-weight: 700; letter-spacing: 0.02em; }
</style>
