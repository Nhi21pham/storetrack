<template>
  <span class="match-badge" :class="matched ? 'matched' : 'new'">{{ matched ? $t('invoices.matched') : $t('invoices.newCol') }}</span>
</template>

<script setup>
// Tiny pill showing whether an extracted supplier/product resolved to an existing
// record ("Matched") or still needs creating ("New").
defineProps({
  matched: { type: Boolean, default: false },
})
</script>

<style scoped>
.match-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.matched { background: #ecfdf5; color: #047857; }
.new { background: #fffbeb; color: #b45309; }
</style>
