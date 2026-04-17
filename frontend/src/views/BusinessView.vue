<template>
  <BusinessPage @business-updated="onBusinessUpdated" />
</template>

<script setup>
import { inject } from 'vue'
import BusinessPage from '@/components/business/BusinessPage.vue'

const refreshBusinessSwitcher = inject('refreshBusinessSwitcher')

const onBusinessUpdated = () => {
  refreshBusinessSwitcher?.()
}
</script>