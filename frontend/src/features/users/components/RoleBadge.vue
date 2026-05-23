<template>
  <span class="role-badge" :class="role">{{ roleLabel(role) }}</span>
</template>

<script setup>
import { roleLabel } from '@/features/users/utils/format'

defineProps({
  role: { type: String, required: true },
})
</script>

<style scoped>
.role-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 5px; }
.role-badge.OWNER      { color: #16a34a; background: #f0fdf4; }
.role-badge.ACCOUNTANT { color: #2563eb; background: #eff6ff; }
.role-badge.STAFF      { color: #6b7280; background: #f3f4f6; }
</style>
