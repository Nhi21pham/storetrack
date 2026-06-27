<template>
  <div class="login-page">
    <div class="brand">
      <BrandLogo :size="72" :icon="28" />
      <span class="brand-name">storetrack</span>
    </div>

    <div class="card">
      <h1>{{ $t('auth.welcomeBack') }}</h1>
      <p class="subtitle">{{ $t('auth.signInSubtitle') }}</p>

      <form @submit.prevent="handleLogin">
        <div class="field">
          <label>{{ $t('auth.email') }}</label>
          <input
            v-model="form.email"
            type="email"
            :placeholder="$t('auth.enterEmail')"
            required
          />
        </div>

        <div class="field">
          <label>{{ $t('auth.password') }}</label>
          <input
            v-model="form.password"
            type="password"
            :placeholder="$t('auth.enterPassword')"
            required
          />
        </div>

        <div class="forgot">
          <a href="/forgot-password">{{ $t('auth.forgotPassword') }}</a>
        </div>

        <p v-if="error" class="error" style="white-space: pre-line">{{ error }}</p>

        <button type="submit" :disabled="loading">
          {{ loading ? $t('auth.signingIn') : $t('auth.signIn') }}
        </button>

        <p class="register">
          {{ $t('auth.noAccount') }} <RouterLink to="/register">{{ $t('auth.register') }}</RouterLink>
        </p>
      </form>
    </div>
  </div>
</template>

<script setup>
import BrandLogo from '@/components/common/BrandLogo.vue'
  import { ref } from 'vue'
  import { useRouter, useRoute } from 'vue-router'
  import { graphql } from '@/api'
  import { validators, validate } from '@/utils/validators'
  import { translateError } from '@/utils/translateError'

  const router = useRouter()
  const route = useRoute()
  const form = ref({ email: '', password: '' })
  const loading = ref(false)
  const error = ref('')

  const handleLogin = async () => {
    const errors = validate([
      () => validators.email(form.value.email),
      () => validators.password(form.value.password),
    ])

    if (errors.length > 0) {
      error.value = errors.join('\n')
      return
    }
    loading.value = true
    error.value = ''
    try {
      const data = await graphql(`
        mutation Login($email: String!, $password: String!) {
          login(email: $email, password: $password) {
            message
            token
            user {
              id
              name
              email
            }
          }
        }
      `, {
        email: form.value.email,
        password: form.value.password
      })

      // Save token to localStorage
      localStorage.setItem('token', data.login.token)
      localStorage.setItem('user', JSON.stringify(data.login.user))

      const redirect = route.query.redirect || sessionStorage.getItem('postLoginRedirect')
      sessionStorage.removeItem('postLoginRedirect')
      router.push(typeof redirect === 'string' ? redirect : '/dashboard')
    } catch (err) {
      error.value = translateError(err)
    } finally {
      loading.value = false
    }
  }
  </script>

  <style scoped>
  * { box-sizing: border-box; margin: 0; padding: 0; }

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

  .forgot {
    text-align: right;
    margin-bottom: 24px;
  }

  .forgot a {
    font-size: 14px;
    font-weight: 600;
    color: #111;
    text-decoration: none;
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

  button:hover { background: #333; }
  button:disabled { background: #555; cursor: not-allowed; }

  .register {
    text-align: center;
    margin-top: 24px;
    font-size: 15px;
    color: #6b7280;
  }

  .register a {
    color: #111;
    font-weight: 700;
    text-decoration: none;
  }

  .error {
  color: red;
  text-align: center;
  font-size: 14px;
  margin-bottom: 16px;
}
</style>
