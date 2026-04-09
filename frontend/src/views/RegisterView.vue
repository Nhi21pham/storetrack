<template>
  <div class="login-page">
    <div class="brand">
      <div class="logo">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="28"
          height="28"
          viewBox="0 0 24 24"
          fill="none"
          stroke="white"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path
            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"
          />
        </svg>
      </div>
      <span class="brand-name">storetrack</span>
    </div>

    <div class="card">
      <h1>Create account</h1>
      <p class="subtitle">Sign up to get started</p>

      <form @submit.prevent="handleRegister">
        <div class="field">
          <label>Name</label>
          <input v-model="form.name" type="text" placeholder="Enter your name" required />
        </div>

        <div class="field">
          <label>Email</label>
          <input v-model="form.email" type="email" placeholder="Enter your email" required />
        </div>

        <div class="field">
          <label>Password</label>
          <input
            v-model="form.password"
            type="password"
            placeholder="Enter your password"
            required
          />
        </div>

        <div class="field">
          <label>Confirm Password</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            placeholder="Confirm your password"
            required
          />
        </div>

        <p v-if="error" class="error" style="white-space: pre-line">{{ error }}</p>

        <button type="submit" :disabled="loading">
          {{ loading ? 'Creating account...' : 'Register' }}
        </button>

        <p class="login">Already have an account? <a href="/login">Sign in</a></p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { graphql } from '@/api'

const router = useRouter()
const error = ref('')
const loading = ref(false)
const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const handleRegister = async () => {
  loading.value = true
  error.value = ''
  try {
    await graphql(`
      mutation Register($name: String!, $email: String!, $password: String!, $password_confirmation: String!) {
        register(name: $name, email: $email, password: $password, password_confirmation: $password_confirmation) {
          message
          email
        }
      }
    `, {
      name: form.value.name,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation
    })

    router.push({ path: '/verify-code', query: { email: form.value.email } })
  } catch (err) {
    error.value = err.message || 'Registration failed'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}
.login-page {
  min-height: 100vh;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #fff;
  font-family: 'Segoe UI', sans-serif;
}
.brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 28px;
  gap: 12px;
}
.logo {
  width: 72px;
  height: 72px;
  background: #111;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.brand-name {
  font-size: 22px;
  font-weight: 600;
  color: #111;
}
.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 20px;
  padding: 48px;
  width: 100%;
  max-width: 560px;
}
h1 {
  font-size: 26px;
  font-weight: 700;
  color: #111;
  text-align: center;
}
.subtitle {
  color: #9ca3af;
  text-align: center;
  margin-top: 8px;
  margin-bottom: 32px;
  font-size: 16px;
}
.field {
  margin-bottom: 20px;
}
label {
  display: block;
  font-size: 15px;
  font-weight: 500;
  color: #111;
  margin-bottom: 8px;
}
input {
  width: 100%;
  padding: 16px 18px;
  background: #f3f4f6;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  color: #111;
  outline: none;
  transition: background 0.2s;
}
input:focus {
  background: #e9eaec;
}
.error {
  color: red;
  font-size: 14px;
  margin-bottom: 16px;
  text-align: center;
}
button {
  width: 100%;
  padding: 17px;
  background: #111;
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
button:hover {
  background: #333;
}
button:disabled {
  background: #555;
  cursor: not-allowed;
}
.login {
  text-align: center;
  margin-top: 24px;
  font-size: 15px;
  color: #6b7280;
}
.login a {
  color: #111;
  font-weight: 700;
  text-decoration: none;
}
</style>
