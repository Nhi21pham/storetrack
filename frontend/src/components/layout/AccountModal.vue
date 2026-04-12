<template>
  <div class="modal-overlay" @click.self="handleClickOutside">
    <div class="modal">
      <div class="modal-header">
        <h2>Account Information</h2>
        <button class="edit-btn" @click="handleCancel">
          {{ editing ? 'Cancel' : 'Edit' }}
        </button>
      </div>

      <!-- Avatar placeholder -->
      <div class="avatar-section">
        <div class="avatar-placeholder">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
        </div>
      </div>

      <div class="field">
        <label>Username</label>
        <input v-if="editing" v-model="form.name" type="text" placeholder="Enter username" />
        <p v-else class="field-value">{{ form.name }}</p>
      </div>

      <div class="field">
        <label>Email</label>
        <p class="field-value muted">{{ form.email }}</p>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="success" class="success">{{ success }}</p>

      <div class="modal-actions">
        <button class="btn-cancel" @click="handleClickOutside">Close</button>
        <button v-if="editing" class="btn-ok" :disabled="loading" @click="handleSave">
          {{ loading ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </div>
  </div>
  <ConfirmDialog
    v-if="showConfirm"
    title="Unsaved Changes"
    message="You have unsaved changes. Are you sure you want to discard them?"
    confirm-text="Yes, discard"
    cancel-text="Keep editing"
    @confirm="onConfirmDiscard"
    @cancel="showConfirm = false"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { graphql } from '@/api'
import { validators, validate } from '@/utils/validators'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'

const emit = defineEmits(['close', 'updated'])
const showConfirm = ref(false)
const editing = ref(false)
const loading = ref(false)
const error = ref('')
const success = ref('')
const originalUser = ref(JSON.parse(localStorage.getItem('user') || '{}'))

const form = ref({
  name: originalUser.value.name || '',
  email: originalUser.value.email || '',
})

onMounted(async () => {
  try {
    const data = await graphql(`
      query {
        user {
          id
          name
          email
        }
      }
    `)
    form.value.name = data.user.name
    form.value.email = data.user.email
    originalUser.value = data.user
  } catch (err) {
    error.value = 'Failed to load user info'
  }
})

const handleCancel = () => {
  if (editing.value) {
    if (hasChanges()) {
      showConfirm.value = true
    } else {
      discardChanges()
    }
  } else {
    editing.value = true
  }
}

const handleSave = async () => {
  const errors = validate([
    () => validators.name(form.value.name),
  ])

  if (errors.length > 0) {
    error.value = errors.join('\n')
    return
  }

  loading.value = true
  error.value = ''
  success.value = ''
  try {
    const data = await graphql(`
      mutation UpdateUser($name: String!) {
        updateUser(name: $name) {
          id
          name
          email
        }
      }
    `, { name: form.value.name })

    const updatedUser = data.updateUser
    localStorage.setItem('user', JSON.stringify(updatedUser))
    originalUser.value = updatedUser

    success.value = 'Profile updated successfully!'
    editing.value = false
    emit('updated', updatedUser)
    setTimeout(() => { success.value = '' }, 2000)
  } catch (err) {
    error.value = err.message || 'Failed to update profile'
  } finally {
    loading.value = false
  }
}
const hasChanges = () => {
  return form.value.name !== originalUser.value.name
}

const onConfirmDiscard = () => {
  showConfirm.value = false
  discardChanges()
}

const discardChanges = () => {
  form.value.name = originalUser.value.name || ''
  form.value.email = originalUser.value.email || ''
  error.value = ''
  success.value = ''
  editing.value = false
}

const handleClickOutside = () => {
  if (editing.value && hasChanges()) {
    showConfirm.value = true
  } else {
    emit('close')
  }
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal { background: #fff; border-radius: 20px; padding: 40px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
.modal-header h2 { font-size: 22px; font-weight: 700; color: #111; }
.edit-btn { background: none; border: 1px solid #e5e7eb; padding: 6px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; color: #111; transition: all 0.2s; }
.edit-btn:hover { background: #f3f4f6; }
.avatar-section { display: flex; justify-content: center; margin-bottom: 28px; }
.avatar-placeholder { width: 80px; height: 80px; background: #111; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 14px; font-weight: 500; color: #111; margin-bottom: 6px; }
.field input { width: 100%; padding: 14px 16px; background: #f3f4f6; border: none; border-radius: 10px; font-size: 14px; color: #111; outline: none; }
.field input:focus { background: #e9eaec; }
.field-value { font-size: 15px; color: #111; padding: 4px 0; }
.field-value.muted { color: #6b7280; }
.error { color: red; font-size: 13px; margin-bottom: 12px; }
.success { color: green; font-size: 13px; margin-bottom: 12px; }
.modal-actions { display: flex; gap: 12px; margin-top: 24px; }
.btn-cancel { flex: 1; padding: 14px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: #e9eaec; }
.btn-ok { flex: 1; padding: 14px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn-ok:hover { background: #333; }
.btn-ok:disabled { background: #555; cursor: not-allowed; }
</style>