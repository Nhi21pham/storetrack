<template>
  <span class="tag-chip" :class="{ 'tag-chip-keyonly': !value }">
    <span class="tag-chip-key">{{ tagName }}</span>
    <span v-if="value" class="tag-chip-sep">:</span>
    <span v-if="value" class="tag-chip-value">{{ value }}</span>
  </span>
</template>

<script setup>
defineProps({
  tagName: { type: String, default: '' },
  value:   { type: String, default: '' },
})
</script>

<style scoped>
.tag-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 6px;
  background: #eef2ff;
  color: #4338ca;
  line-height: 1.5;
  white-space: nowrap;
}
.tag-chip-keyonly { background: #f3f4f6; color: #4b5563; }
.tag-chip-key { font-weight: 700; }
.tag-chip-sep { opacity: 0.6; }
.tag-chip-value { font-weight: 500; }
</style>
