<template>
  <div class="brand-logo" :style="boxStyle">
    <svg
      :width="icon"
      :height="icon"
      viewBox="0 0 24 24"
      fill="none"
      stroke="#ffffff"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
    </svg>
  </div>
</template>

<script setup>
import { computed } from 'vue'

// The single source of truth for the StoreTrack brand mark: a dark rounded
// square holding the box icon. Used by the navbar, sidebar, and login screen.
// (The browser-tab favicon is a separate file — public/favicon.svg — kept
// visually matched to this.)
const props = defineProps({
  size: { type: Number, default: 36 },
  icon: { type: Number, default: 20 },
  radius: { type: Number, default: null },
})

const boxStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  borderRadius: `${props.radius ?? Math.round(props.size * 0.22)}px`,
}))
</script>

<style scoped>
.brand-logo {
  background: #111;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
