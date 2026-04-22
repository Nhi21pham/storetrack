<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>Invite User</h2>
        <button class="close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <p class="description">Send an invitation email to add someone to <strong>{{ storeName }}</strong>.</p>

        <div class="form-group">
          <label>Email Address <span class="required">*</span></label>
          <input
            v-model="form.email"
            type="email"
            placeholder="Enter their email"
            :class="{ error: errors.email }"
            @keyup.enter="handleSubmit"
          />
          <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
        </div>

        <div class="form-group">
          <label>Role <span class="required">*</span></label>
          <div class="role-options">
            <label
              v-for="opt in roleOptions"
              :key="opt.value"
              class="role-option"
              :class="{ selected: form.role === opt.value }"
            >
              <input type="radio" :value="opt.value" v-model="form.role" />
              <div class="role-option-content">
                <span class="role-name" :class="opt.value">{{ opt.label }}</span>
                <span class="role-desc">{{ opt.description }}</span>
              </div>
            </label>
          </div>
          <span v-if="errors.role" class="error-text">{{ errors.role }}</span>
        </div>

        <div v-if="apiError" class="api-error">{{ apiError }}</div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="$emit('close')" :disabled="loading">Cancel</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading">
          <span v-if="loading" class="spinner"></span>
          Send Invitation
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { graphql } from '@/api'

const props = defineProps({
  storeId: { type: String, required: true },
  storeName: { type: String, required: true },
})

const emit = defineEmits(['close', 'invited'])

const loading = ref(false)
const apiError = ref('')
const errors = ref({ email: '', role: '' })

const form = ref({ email: '', role: 'ACCOUNTANT' })

const roleOptions = [
  { value: 'ACCOUNTANT', label: 'Accountant', description: 'Can edit store info and manage invoices' },
  { value: 'STAFF', label: 'Staff', description: 'Can create and update invoices only' },
]

const validateForm = () => {
  errors.value = { email: '', role: '' }

  if (!form.value.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    errors.value.email = 'Please enter a valid email address.'
  }
  if (!form.value.role) {
    errors.value.role = 'Please select a role.'
  }

  return !Object.values(errors.value).some(Boolean)
}

const handleSubmit = async () => {
  if (!validateForm()) return

  loading.value = true
  apiError.value = ''

  try {
    await graphql(`
      mutation InviteUser($store_id: ID!, $email: String!, $role: Role!) {
        inviteUserToStore(store_id: $store_id, email: $email, role: $role) {
          id invitee_email role status
        }
      }
    `, {
      store_id: props.storeId,
      email: form.value.email,
      role: form.value.role,
    })
    emit('invited', form.value.email)
  } catch (err) {
    apiError.value = err.message
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 460px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 16px 24px 20px; }
.description { font-size: 14px; color: #6b7280; margin-bottom: 18px; line-height: 1.5; }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #dc2626; }

.form-group input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; background: #fff; transition: border-color 0.15s; outline: none; box-sizing: border-box; }
.form-group input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.form-group input.error { border-color: #dc2626; }

.error-text { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }

.role-options { display: flex; flex-direction: column; gap: 8px; }
.role-option { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid #e5e7eb; border-radius: 10px; cursor: pointer; transition: all 0.15s; }
.role-option input[type="radio"] { display: none; }
.role-option:hover { border-color: #d1d5db; background: #f9fafb; }
.role-option.selected { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.role-option-content { display: flex; flex-direction: column; gap: 2px; }
.role-name { font-size: 14px; font-weight: 600; color: #374151; }
.role-option.selected .role-name.ACCOUNTANT { color: #2563eb; }
.role-option.selected .role-name.STAFF { color: #374151; font-weight: 700; }
.role-desc { font-size: 12px; color: #9ca3af; }

.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 4px; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }
.btn-cancel { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-cancel:hover { background: #e9eaec; }
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-submit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 6px; }
.btn-submit:hover:not(:disabled) { background: #333; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
