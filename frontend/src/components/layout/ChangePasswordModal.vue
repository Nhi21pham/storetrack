<template>
  <div class="modal-overlay" @click.self="handleClickOutside">
    <div class="modal">
      <template v-if="mode === 'change'">
        <h2>{{ $t('account.changePassword') }}</h2>
        <p class="modal-subtitle">{{ $t('account.changePasswordSubtitle') }}</p>

        <div class="field">
          <label>{{ $t('account.oldPassword') }}</label>
          <input v-model="form.old_password" type="password" :placeholder="$t('account.enterOldPassword')" />
        </div>
        <div class="field">
          <label>{{ $t('account.newPassword') }}</label>
          <input v-model="form.new_password" type="password" :placeholder="$t('account.enterNewPassword')" />
        </div>
        <div class="field">
          <label>{{ $t('account.confirmNewPassword') }}</label>
          <input v-model="form.new_password_confirmation" type="password" :placeholder="$t('account.confirmNewPasswordPlaceholder')" />
        </div>
        <p class="forgot-link">
          <a href="#" @click.prevent="startReset">{{ $t('account.forgotLink') }}</a>
        </p>

        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="success" class="success">{{ success }}</p>

        <div class="modal-actions">
          <BaseButton variant="secondary" block @click="handleClickOutside">{{ $t('common.cancel') }}</BaseButton>
          <BaseButton variant="primary" block :disabled="loading" @click="handleSubmit">
            {{ loading ? $t('account.updating') : $t('account.updatePassword') }}
          </BaseButton>
        </div>
      </template>

      <template v-else>
        <h2>{{ $t('account.resetPassword') }}</h2>
        <p class="modal-subtitle">{{ $t('account.resetSubtitle') }}</p>

        <div class="field">
          <label>{{ $t('account.newPassword') }}</label>
          <input v-model="form.new_password" type="password" :placeholder="$t('account.enterNewPassword')" />
        </div>
        <div class="field">
          <label>{{ $t('account.confirmNewPassword') }}</label>
          <input v-model="form.new_password_confirmation" type="password" :placeholder="$t('account.confirmNewPasswordPlaceholder')" />
        </div>

        <template v-if="resetStep === 'code'">
          <p class="code-sent-note">{{ $t('account.codeSentNote') }}</p>
          <div class="field">
            <label>{{ $t('account.verificationCode') }}</label>
            <input v-model="resetCode" type="text" inputmode="numeric" maxlength="6" :placeholder="$t('account.enterCode')" />
          </div>
          <p class="reset-note">{{ $t('account.resetPendingNote') }}</p>
          <p class="resend">
            {{ $t('account.didntReceive') }}
            <a href="#" @click.prevent="handleResend" :class="{ disabled: loading || resendCooldown > 0 }">
              {{ resendCooldown > 0 ? $t('account.resendIn', { seconds: resendCooldown }) : $t('account.resendCode') }}
            </a>
          </p>
        </template>

        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="success" class="success">{{ success }}</p>

        <div class="modal-actions">
          <BaseButton variant="secondary" block @click="backToChange">{{ $t('account.back') }}</BaseButton>
          <BaseButton v-if="resetStep === 'password'" variant="primary" block :disabled="loading" @click="handleSendCode">
            {{ loading ? $t('account.sendingCode') : $t('account.sendCode') }}
          </BaseButton>
          <BaseButton v-else variant="primary" block :disabled="loading" @click="handleResetPassword">
            {{ loading ? $t('account.resetting') : $t('account.resetPasswordBtn') }}
          </BaseButton>
        </div>
      </template>
    </div>
  </div>
  <ConfirmDialog
  v-if="showConfirm"
  :title="$t('account.discardTitle')"
  :message="$t('account.discardMessage')"
  :confirm-text="$t('account.discard')"
  :cancel-text="$t('account.keepEditing')"
  @confirm="emit('close')"
  @cancel="showConfirm = false"
  />
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { graphql } from '@/api'
import { useRouter } from 'vue-router'
import { validators, validate } from '@/utils/validators'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import BaseButton from '@/components/common/BaseButton.vue'

const emit = defineEmits(['close'])
const showConfirm = ref(false)
const loading = ref(false)
const error = ref('')
const success = ref('')
const router = useRouter()

const mode = ref('change')         // 'change' | 'reset'
const resetStep = ref('password')  // 'password' | 'code'
const resetCode = ref('')
const resendCooldown = ref(0)
let cooldownTimer = null

const form = ref({
  old_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const userEmail = () => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  return user.email
}

const hasChanges = () => {
  return form.value.old_password || form.value.new_password || form.value.new_password_confirmation || resetCode.value
}

const handleClickOutside = () => {
  if (hasChanges()) {
    showConfirm.value = true
  } else {
    emit('close')
  }
}

const startReset = () => {
  error.value = ''
  success.value = ''
  mode.value = 'reset'
  resetStep.value = 'password'
}

const backToChange = () => {
  error.value = ''
  success.value = ''
  mode.value = 'change'
  resetStep.value = 'password'
  resetCode.value = ''
}

const startCooldown = () => {
  resendCooldown.value = 60
  cooldownTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

const validateNewPassword = () => {
  return validate([
    () => validators.password(form.value.new_password),
    () => validators.confirmPassword(form.value.new_password_confirmation, form.value.new_password),
  ])
}

const sendCode = async () => {
  await graphql(`
    mutation ForgotPassword($email: String!) {
      forgotPassword(email: $email) {
        message
      }
    }
  `, { email: userEmail() })
}

const handleSubmit = async () => {
  error.value = ''
  success.value = ''
  const errors = validate([
    () => validators.password(form.value.old_password),
    () => validators.password(form.value.new_password),
    () => validators.confirmPassword(form.value.new_password_confirmation, form.value.new_password),
  ])

  if (errors.length > 0) {
    error.value = errors.join('\n')
    return
  }

  loading.value = true
  try {
    await graphql(`
      mutation UpdatePassword($old_password: String!, $new_password: String!, $new_password_confirmation: String!) {
        updatePassword(old_password: $old_password, new_password: $new_password, new_password_confirmation: $new_password_confirmation) {
          message
        }
      }
    `, {
      old_password: form.value.old_password,
      new_password: form.value.new_password,
      new_password_confirmation: form.value.new_password_confirmation
    })

    success.value = t('account.passwordUpdated')
    form.value = { old_password: '', new_password: '', new_password_confirmation: '' }
    setTimeout(() => { success.value = '' }, 2000)
  } catch (err) {
    error.value = translateError(err)
  } finally {
    loading.value = false
  }
}

const handleSendCode = async () => {
  error.value = ''
  success.value = ''
  const errors = validateNewPassword()

  if (errors.length > 0) {
    error.value = errors.join('\n')
    return
  }

  loading.value = true
  try {
    await sendCode()
    resetStep.value = 'code'
    success.value = t('account.codeSent')
    startCooldown()
  } catch (err) {
    error.value = translateError(err)
  } finally {
    loading.value = false
  }
}

const handleResend = async () => {
  if (resendCooldown.value > 0 || loading.value) return
  error.value = ''
  success.value = ''
  loading.value = true
  try {
    await sendCode()
    success.value = t('account.codeResent')
    startCooldown()
  } catch (err) {
    error.value = translateError(err)
  } finally {
    loading.value = false
  }
}

const handleResetPassword = async () => {
  error.value = ''
  success.value = ''
  const errors = validateNewPassword()

  if (errors.length > 0) {
    error.value = errors.join('\n')
    return
  }

  if (!/^\d{6}$/.test(resetCode.value)) {
    error.value = t('account.enterCode')
    return
  }

  loading.value = true
  try {
    await graphql(`
      mutation ResetPassword($email: String!, $code: String!, $password: String!, $password_confirmation: String!) {
        resetPassword(email: $email, code: $code, password: $password, password_confirmation: $password_confirmation) {
          message
        }
      }
    `, {
      email: userEmail(),
      code: resetCode.value,
      password: form.value.new_password,
      password_confirmation: form.value.new_password_confirmation
    })

    success.value = t('account.passwordChanged')
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setTimeout(() => { emit('close'); router.push('/login') }, 1500)
  } catch (err) {
    error.value = translateError(err)
  } finally {
    loading.value = false
  }
}

onUnmounted(() => {
  if (cooldownTimer) clearInterval(cooldownTimer)
})
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal { background: #fff; border-radius: 20px; padding: 40px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal h2 { font-size: 22px; font-weight: 700; color: #111; margin-bottom: 6px; }
.modal-subtitle { color: #9ca3af; font-size: 14px; margin-bottom: 28px; }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 14px; font-weight: 500; color: #111; margin-bottom: 6px; }
.field input { width: 100%; padding: 14px 16px; background: #f3f4f6; border: none; border-radius: 10px; font-size: 14px; color: #111; outline: none; }
.field input:focus { background: #e9eaec; }
.error { color: red; font-size: 13px; margin-bottom: 12px; white-space: pre-line; }
.success { color: green; font-size: 13px; margin-bottom: 12px; }
.code-sent-note { color: #6b7280; font-size: 13px; margin-bottom: 12px; }
.reset-note { color: #9ca3af; font-size: 12px; margin-bottom: 12px; }
.resend { color: #6b7280; font-size: 13px; margin-bottom: 12px; }
.resend a { color: #111; font-weight: 600; text-decoration: none; }
.resend a:hover { text-decoration: underline; }
.resend a.disabled { color: #9ca3af; pointer-events: none; }
.modal-actions { display: flex; gap: 12px; margin-top: 24px; }
.forgot-link { text-align: right; margin-top: 6px; }
.forgot-link a { font-size: 13px; font-weight: 600; color: #111; text-decoration: none; }
.forgot-link a:hover { text-decoration: underline; }
</style>
