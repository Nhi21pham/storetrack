<template>
  <StorePage @store-updated="onStoreUpdated" />
</template>

<script setup>
import { inject } from 'vue'
import StorePage from '@/components/store/StorePage.vue'

const refreshStoreSwitcher = inject('refreshStoreSwitcher')

const onStoreUpdated = () => {
  refreshStoreSwitcher?.()
}
</script>