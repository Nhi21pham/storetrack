<template>
  <div class="login-page">
    <div class="brand">
      <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        </svg>
      </div>
      <span class="brand-name">storetrack</span>
    </div>

    <div class="card">
      <h1>Reset password</h1>
      <p class="subtitle">Enter the code sent to<br><strong>{{ email }}</strong></p>

      <form @submit.prevent="handleReset">
        <div class="code-inputs">
          <input
            v-for="(digit, index) in digits"
            :key="index"
            :ref="el => inputs[index] = el"
            v-model="digits[index]"
            type="text"
            maxlength="1"
            @input="onInput(index)"
            @keydown.delete="onDelete(index)"
            @paste="onPaste"
          />
        </div>

        <div class="field">
          <label>New Password</label>
          <input v-model="password" type="password" placeholder="Enter new password" required />
        </div>

        <div class="field">
          <label>Confirm Password</label>
          <input v-model="password_confirmation" type="password" placeholder="Confirm new password" required />
        </div>

        <p v-if="error" class="error" style="white-space: pre-line">{{ error }}</p>
        <p v-if="success" class="success">{{ success }}</p>

        <button type="submit" :disabled="loading || code.length < 6">
          {{ loading ? 'Resetting...' : 'Reset Password' }}
        </button>
        <p v-if="resendSuccess" class="success">{{ resendSuccess }}</p>
        <p class="resend">
          Didn't receive the code?
          <a href="#" @click.prevent="handleResend" :class="{ disabled: resendLoading || resendCooldown > 0 }">
            {{ resendCooldown > 0 ? `Resend in ${resendCooldown}s` : resendLoading ? 'Sending...' : 'Resend code' }}
          </a>
        </p>    

        <p class="back">
          Remember your password? <a href="/login">Sign in</a>
        </p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { graphql } from '@/api'

const router = useRouter()
const route = useRoute()

const email = ref(route.query.email || '')
const digits = ref(['', '', '', '', '', ''])
const inputs = ref([])
const password = ref('')
const password_confirmation = ref('')
const loading = ref(false)
const error = ref('')
const success = ref('')
const resendLoading = ref(false)
const resendCooldown = ref(0)
let cooldownTimer = null
const resendSuccess = ref('')

const code = computed(() => digits.value.join(''))

const onInput = (index) => {
  if (digits.value[index] && index < 5) {
    inputs.value[index + 1]?.focus()
  }
}

const onDelete = (index) => {
  if (!digits.value[index] && index > 0) {
    inputs.value[index - 1]?.focus()
  }
}

const onPaste = (e) => {
  const paste = e.clipboardData.getData('text').slice(0, 6)
  paste.split('').forEach((char, i) => {
    digits.value[i] = char
  })
  inputs.value[5]?.focus()
}

const handleReset = async () => {
  if (password.value !== password_confirmation.value) {
    error.value = 'Passwords do not match'
    return
  }
  loading.value = true
  error.value = ''
  success.value = ''
  try {
    await graphql(`
      mutation ResetPassword($email: String!, $code: String!, $password: String!, $password_confirmation: String!) {
        resetPassword(email: $email, code: $code, password: $password, password_confirmation: $password_confirmation) {
          message
        }
      }
    `, {
      email: email.value,
      code: code.value,
      password: password.value,
      password_confirmation: password_confirmation.value
    })

    success.value = 'Password reset successfully! Redirecting to login...'
    // Clear auth and force login
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setTimeout(() => router.push('/login'), 2000)
  } catch (err) {
    error.value = err.message || 'Reset failed'
  } finally {
    loading.value = false
  }
}

const handleResend = async () => {
  resendLoading.value = true
  error.value = ''
  resendSuccess.value = ''
  try {
    await graphql(`
      mutation ForgotPassword($email: String!) {
        forgotPassword(email: $email) {
          message
        }
      }
    `, { email: email.value })
    resendSuccess.value = 'Code resent! Check your email.'
    startCooldown()
  } catch (err) {
    error.value = err.message || 'Failed to resend code'
  } finally {
    resendLoading.value = false
  }
}
const startCooldown = () => {
  resendCooldown.value = 60
  cooldownTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}
onUnmounted(() => {
  if (cooldownTimer) clearInterval(cooldownTimer)
})
</script>

<style scoped>
* { box-sizing: border-box; margin: 0; padding: 0; }
.login-page { min-height: 100vh; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #fff; font-family: 'Segoe UI', sans-serif; }
.brand { display: flex; flex-direction: column; align-items: center; margin-bottom: 28px; gap: 12px; }
.logo { width: 72px; height: 72px; background: #111; border-radius: 16px; display: flex; align-items: center; justify-content: center; }
.brand-name { font-size: 22px; font-weight: 600; color: #111; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 20px; padding: 48px; width: 100%; max-width: 560px; }
h1 { font-size: 26px; font-weight: 700; color: #111; text-align: center; }
.subtitle { color: #9ca3af; text-align: center; margin-top: 8px; margin-bottom: 32px; font-size: 16px; line-height: 1.6; }
.subtitle strong { color: #111; }
.code-inputs { display: flex; gap: 12px; justify-content: center; margin-bottom: 24px; }
.code-inputs input { width: 56px; height: 64px; text-align: center; font-size: 24px; font-weight: 700; background: #f3f4f6; border: 2px solid transparent; border-radius: 12px; outline: none; transition: all 0.2s; color: #111; }
.code-inputs input:focus { background: #e9eaec; border-color: #111; }
.field { margin-bottom: 20px; }
label { display: block; font-size: 15px; font-weight: 500; color: #111; margin-bottom: 8px; }
input { width: 100%; padding: 16px 18px; background: #f3f4f6; border: none; border-radius: 12px; font-size: 15px; color: #111; outline: none; transition: background 0.2s; }
input:focus { background: #e9eaec; }
.error { color: red; font-size: 14px; margin-bottom: 16px; text-align: center; }
.success { color: green; font-size: 14px; margin-bottom: 16px; text-align: center; }
button { width: 100%; padding: 17px; background: #111; color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
button:hover { background: #333; }
button:disabled { background: #555; cursor: not-allowed; }
.back { text-align: center; margin-top: 24px; font-size: 15px; color: #6b7280; }
.back a { color: #111; font-weight: 700; text-decoration: none; }
.resend { text-align: center; margin-top: 20px; font-size: 15px; color: #6b7280; }
.resend a { color: #111; font-weight: 700; text-decoration: none; }
.resend a.disabled { color: #9ca3af; pointer-events: none; }
</style>