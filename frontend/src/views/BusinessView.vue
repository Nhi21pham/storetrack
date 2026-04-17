<template>
  <BusinessPage @business-updated="onBusinessUpdated" />
</template>

<script setup>
import { inject } from 'vue'
import BusinessPage from '@/components/business/BusinessPage.vue'

const refreshStoreSwitcher = inject('refreshStoreSwitcher')

const onBusinessUpdated = () => {
  refreshStoreSwitcher?.()
}
</script>