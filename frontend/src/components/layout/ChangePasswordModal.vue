<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <h2>Change Password</h2>
      <p class="modal-subtitle">Update your account password</p>

      <div class="field">
        <label>Old Password</label>
        <input v-model="form.old_password" type="password" placeholder="Enter old password" />
      </div>
      <div class="field">
        <label>New Password</label>
        <input v-model="form.new_password" type="password" placeholder="Enter new password" />
      </div>
      <div class="field">
        <label>Confirm New Password</label>
        <input v-model="form.new_password_confirmation" type="password" placeholder="Confirm new password" />
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="success" class="success">{{ success }}</p>

      <div class="modal-actions">
        <button class="btn-cancel" @click="$emit('close')">Cancel</button>
        <button class="btn-ok" :disabled="loading" @click="handleSubmit">
          {{ loading ? 'Updating...' : 'Update Password' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { graphql } from '@/api'

defineEmits(['close'])

const loading = ref(false)
const error = ref('')
const success = ref('')
const form = ref({
  old_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const handleSubmit = async () => {
  loading.value = true
  error.value = ''
  success.value = ''
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

    success.value = 'Password updated successfully!'
    form.value = { old_password: '', new_password: '', new_password_confirmation: '' }
    setTimeout(() => { success.value = ''; }, 2000)
  } catch (err) {
    error.value = err.message || 'Failed to update password'
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
</style>