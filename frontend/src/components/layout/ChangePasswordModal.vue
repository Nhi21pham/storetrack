<template>
  <div class="modal-overlay" @click.self="handleClickOutside">
    <div class="modal">
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
        <a href="#" @click.prevent="handleForgotPassword">{{ $t('account.forgotLink') }}</a>
      </p>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="success" class="success">{{ success }}</p>

      <div class="modal-actions">
        <button class="btn-cancel" @click="handleClickOutside">{{ $t('common.cancel') }}</button>
        <button class="btn-ok" :disabled="loading" @click="handleSubmit">
          {{ loading ? $t('account.updating') : $t('account.updatePassword') }}
        </button>
      </div>
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
import { ref } from 'vue'
import { graphql } from '@/api'
import { useRouter } from 'vue-router'
import { validators, validate } from '@/utils/validators'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'

const emit = defineEmits(['close'])
const showConfirm = ref(false)
const loading = ref(false)
const error = ref('')
const success = ref('')
const router = useRouter()

const form = ref({
  old_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const hasChanges = () => {
  return form.value.old_password || form.value.new_password || form.value.new_password_confirmation
}

const handleClickOutside = () => {
  if (hasChanges()) {
    showConfirm.value = true
  } else {
    emit('close')
  }
}

const handleForgotPassword = async () => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  const email = user.email

  try {
    await graphql(`
      mutation ForgotPassword($email: String!) {
        forgotPassword(email: $email) {
          message
        }
      }
    `, { email })

    // Set session flag before redirecting
    sessionStorage.setItem('canReset', 'true')

    emit('close')
    router.push({ path: '/reset-password', query: { email } })
  } catch (err) {
    error.value = translateError(err)
  }
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
.error { color: red; font-size: 13px; margin-bottom: 12px; }
.success { color: green; font-size: 13px; margin-bottom: 12px; }
.modal-actions { display: flex; gap: 12px; margin-top: 24px; }
.btn-cancel { flex: 1; padding: 14px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: #e9eaec; }
.btn-ok { flex: 1; padding: 14px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-ok:hover { background: #333; }
.btn-ok:disabled { background: #555; cursor: not-allowed; }
.forgot-link { text-align: right; margin-top: 6px; }
.forgot-link a { font-size: 13px; font-weight: 600; color: #111; text-decoration: none; }
.forgot-link a:hover { text-decoration: underline; }
</style>