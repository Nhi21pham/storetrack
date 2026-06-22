<template>
  <div v-if="groups.length" class="missing-banner">
    <div class="banner-head">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>{{ message }}</span>
    </div>

    <div v-for="group in groups" :key="group.type" class="ref-group">
      <span class="group-label">{{ group.label }}</span>
      <div class="chips">
        <button v-for="chip in group.chips" :key="chip.id" class="ref-chip" @click="$emit('create', group.type, chip)">
          <span class="chip-name">{{ chip.label }}</span>
          <span class="chip-add">+ Create</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { t } from '@/i18n'

// Presentational shell for the "create the records these rows reference" banner.
// Shared by the import review grid and the AI invoice-extraction review page —
// each owns its own grouping + create/resolve logic and just feeds `groups` and
// handles `create`.
defineProps({
  // [{ type, label, chips: [{ id, label }] }]
  groups: { type: Array, default: () => [] },
  message: {
    type: String,
    default: () => t('shared.referenceBannerDefault'),
  },
})

defineEmits(['create'])
</script>

<style scoped>
.missing-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; }
.banner-head { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #92400e; font-weight: 500; }
.banner-head svg { flex-shrink: 0; }
.ref-group { margin-top: 10px; }
.group-label { display: block; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #b45309; margin-bottom: 6px; }
.chips { display: flex; flex-wrap: wrap; gap: 8px; }
.ref-chip { display: inline-flex; align-items: center; gap: 8px; padding: 5px 10px; background: #fff; border: 1px solid #fcd34d; border-radius: 999px; font-size: 12.5px; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.ref-chip:hover { background: #fef3c7; border-color: #f59e0b; }
.chip-name { font-weight: 600; color: #111; }
.chip-add { color: #b45309; font-weight: 600; }
</style>
