<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/api'

const message = ref('')

onMounted(async () => {
  const response = await api.get('/test')
  message.value = response.data.message
})
</script>

<template>
  <div>{{ message }}</div>
</template>
