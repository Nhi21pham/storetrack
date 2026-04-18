<template>
  <div class="search-bar">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" type="text" :placeholder="placeholder" />
    <button v-if="modelValue" class="clear-btn" @click="$emit('update:modelValue', '')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search...' }
})

defineEmits(['update:modelValue'])
</script>

<style scoped>
.search-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 20px; transition: border-color 0.15s; }
.search-bar:focus-within { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.search-bar svg { color: #9ca3af; flex-shrink: 0; }
.search-bar input { flex: 1; border: none; outline: none; font-size: 14px; color: #111; background: none; }
.search-bar input::placeholder { color: #9ca3af; }
.clear-btn { background: none; border: none; cursor: pointer; padding: 2px; border-radius: 4px; color: #9ca3af; display: flex; align-items: center; }
.clear-btn:hover { color: #374151; background: #f3f4f6; }
</style>