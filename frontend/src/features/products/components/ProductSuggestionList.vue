<template>
  <div class="suggestions" @mousedown.prevent>
    <div class="suggestions-header">Existing matches</div>
    <button
      v-for="item in items"
      :key="item.id"
      class="suggestion-row"
      type="button"
      @click="$emit('pick', item)"
    >
      <div class="row-main">
        <span class="name">{{ item.name }}</span>
        <span v-if="!item.is_active" class="inactive-badge">Inactive</span>
      </div>
      <div v-if="item.unit?.name" class="row-sub">Unit: {{ item.unit.name }}</div>
    </button>
  </div>
</template>

<script setup>
defineProps({
  items: { type: Array, required: true }
})
defineEmits(['pick'])
</script>

<style scoped>
.suggestions {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
  z-index: 10;
  max-height: 280px;
  overflow-y: auto;
  padding: 6px;
}
.suggestions-header { padding: 6px 10px 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; font-weight: 600; }
.suggestion-row { display: block; width: 100%; text-align: left; background: none; border: none; padding: 8px 10px; border-radius: 6px; cursor: pointer; }
.suggestion-row:hover { background: #f3f4f6; }
.row-main { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: #111; }
.row-sub { font-size: 12px; color: #6b7280; margin-top: 2px; }
.inactive-badge { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px; border-radius: 4px; background: #fef3c7; color: #92400e; font-weight: 600; }
</style>
