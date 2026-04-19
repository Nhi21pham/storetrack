<template>
  <div class="error-page">
    <div class="error-content">
      <div class="error-code">{{ code }}</div>
      <h1>{{ title }}</h1>
      <p>{{ message }}</p>
      <div class="error-actions">
        <button class="btn-secondary" @click="goBack">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
          </svg>
          Go Back
        </button>
        <button class="btn-primary" @click="goHome">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          Go to Dashboard
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

const code = computed(() => route.params.code || '404')

const title = computed(() => {
  const titles = {
    '404': 'Page not found',
    '500': 'Server error',
    '403': 'Access denied',
  }
  return titles[code.value] || 'Something went wrong'
})

const message = computed(() => {
  const messages = {
    '404': "The page you're looking for doesn't exist or has been moved.",
    '500': 'Something went wrong on our end. Please try again later.',
    '403': "You don't have permission to access this page.",
  }
  return messages[code.value] || 'An unexpected error occurred.'
})

const goHome = () => {
  const token = localStorage.getItem('token')
  router.push(token ? '/dashboard' : '/login')
}

const goBack = () => {
  if (window.history.length > 1) {
    router.back()
  } else {
    goHome()
  }
}
</script>

<style scoped>
.error-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f9fafb;
  font-family: 'Segoe UI', sans-serif;
  padding: 24px;
}

.error-content {
  text-align: center;
  max-width: 480px;
}

.error-code {
  font-size: 120px;
  font-weight: 800;
  color: #e5e7eb;
  line-height: 1;
  margin-bottom: 8px;
}

h1 {
  font-size: 24px;
  font-weight: 700;
  color: #111;
  margin-bottom: 12px;
}

p {
  font-size: 15px;
  color: #6b7280;
  line-height: 1.6;
  margin-bottom: 32px;
}

.error-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.btn-primary {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #111;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-primary:hover { background: #333; }

.btn-secondary {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #f3f4f6;
  color: #374151;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-secondary:hover { background: #e5e7eb; }
</style>