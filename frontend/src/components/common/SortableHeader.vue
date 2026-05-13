<template>
  <span class="th-content" :class="{ 'th-active': !!sortInfo }">
    {{ label }}
    <span class="sort-icons">
      <svg @click="$emit('sort', 'asc')" class="sort-arrow" :class="{ active: sortInfo?.dir === 'asc' }" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="sortInfo?.dir === 'asc' ? 3 : 1.5"><polyline points="18 15 12 9 6 15"/></svg>
      <svg @click="$emit('sort', 'desc')" class="sort-arrow" :class="{ active: sortInfo?.dir === 'desc' }" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="sortInfo?.dir === 'desc' ? 3 : 1.5"><polyline points="6 9 12 15 18 9"/></svg>
    </span>
    <span v-if="rank" class="sort-rank">{{ rank }}</span>
  </span>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  sortInfo: { type: Object, default: null },
  rank: { type: Number, default: null },
})

defineEmits(['sort'])
</script>

<style scoped>
.th-content { display: inline-flex; align-items: center; gap: 4px; font: inherit; color: inherit; transition: color 0.15s; }
.th-content.th-active { color: #111; font-weight: 700; }
.th-content:hover { color: #111; }
.sort-icons { display: flex; flex-direction: column; gap: 1px; }
.sort-arrow { cursor: pointer; color: #d1d5db; transition: color 0.12s; }
.sort-arrow.active { color: #111; }
.sort-rank { font-size: 10px; font-weight: 700; color: #fff; background: #111; border-radius: 50%; width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center; margin-left: 2px; }
</style>
