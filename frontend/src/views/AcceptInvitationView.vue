<template>
  <div class="invite-page">
    <div class="brand">
      <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        </svg>
      </div>
      <span class="brand-name">storetrack</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="card">
      <div class="loading-state">
        <div class="spinner"></div>
        <span>Loading invitation...</span>
      </div>
    </div>

    <!-- Invalid / Not Found -->
    <div v-else-if="error === 'not_found'" class="card state-card">
      <div class="state-icon error-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      </div>
      <h2>Invitation Not Found</h2>
      <p>This invitation link is invalid or has been removed.</p>
    </div>

    <!-- Expired -->
    <div v-else-if="preview?.is_expired" class="card state-card">
      <div class="state-icon warning-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <h2>Invitation Expired</h2>
      <p>This invitation has expired. Ask the store owner to send a new one.</p>
    </div>

    <!-- Already Accepted -->
    <div v-else-if="preview?.is_already_accepted" class="card state-card">
      <div class="state-icon success-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2>Already Accepted</h2>
      <p>You've already accepted this invitation.</p>
      <RouterLink class="btn-primary" to="/stores">Go to Stores</RouterLink>
    </div>

    <!-- Declined state -->
    <div v-else-if="declined" class="card state-card">
      <div class="state-icon warning-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2>Invitation Declined</h2>
      <p>You've declined the invitation to join <strong>{{ preview.store_name }}</strong>.</p>
    </div>

    <!-- Accepted state -->
    <div v-else-if="accepted" class="card state-card">
      <div class="state-icon success-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2>Welcome aboard!</h2>
      <p>You've joined <strong>{{ preview.store_name }}</strong> as <strong>{{ roleLabel }}</strong>.</p>
      <RouterLink class="btn-primary" to="/stores">Go to Stores</RouterLink>
    </div>

    <!-- Main invite card (logged in) -->
    <div v-else-if="preview && isLoggedIn" class="card">
      <div class="invite-header">
        <div class="store-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
            <path d="M8 7v4"/><path d="M16 7v4"/>
          </svg>
        </div>
        <div>
          <h2>You're invited!</h2>
          <p class="subtitle">Review the details before accepting</p>
        </div>
      </div>

      <div class="invite-details">
        <div class="detail-row">
          <span class="detail-label">Store</span>
          <span class="detail-value">{{ preview.store_name }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Invited by</span>
          <span class="detail-value">{{ preview.inviter_name }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Role</span>
          <span class="role-badge" :class="preview.role">{{ roleLabel }}</span>
        </div>
      </div>

      <p v-if="actionError" class="api-error">{{ actionError }}</p>

      <div class="invite-actions">
        <button class="btn-decline" @click="handleDecline" :disabled="actionLoading">
          Decline
        </button>
        <button class="btn-accept" @click="handleAccept" :disabled="actionLoading">
          <span v-if="actionLoading" class="spinner spinner-white"></span>
          Accept Invitation
        </button>
      </div>
    </div>

    <!-- Not logged in -->
    <div v-else-if="preview && !isLoggedIn" class="card state-card">
      <div class="state-icon info-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <h2>Sign in to continue</h2>
      <p>
        You've been invited to join <strong>{{ preview.store_name }}</strong> as
        <strong>{{ roleLabel }}</strong> by <strong>{{ preview.inviter_name }}</strong>.
      </p>
      <p class="hint">Sign in or create an account to accept this invitation.</p>
      <div class="auth-actions">
        <RouterLink class="btn-primary" :to="`/login?redirect=${encodeURIComponent(route.fullPath)}`">Sign In</RouterLink>
        <RouterLink class="btn-secondary" :to="`/register?redirect=${encodeURIComponent(route.fullPath)}`">Create Account</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { graphql } from '@/api'

const route = useRoute()
const token = route.params.token

const loading = ref(true)
const preview = ref(null)
const error = ref(null)
const actionLoading = ref(false)
const actionError = ref('')
const accepted = ref(false)
const declined = ref(false)

const isLoggedIn = computed(() => !!localStorage.getItem('token'))

const roleLabel = computed(() => {
  if (!preview.value) return ''
  const map = { OWNER: 'Owner', ACCOUNTANT: 'Accountant', STAFF: 'Staff' }
  return map[preview.value.role] ?? preview.value.role
})

const loadPreview = async () => {
  loading.value = true
  try {
    const data = await graphql(`
      query InvitationPreview($token: String!) {
        invitationPreview(token: $token) {
          inviter_name store_name role is_expired is_already_accepted status
        }
      }
    `, { token })
    preview.value = data.invitationPreview
  } catch (err) {
    error.value = 'not_found'
  } finally {
    loading.value = false
  }
}

const handleAccept = async () => {
  actionLoading.value = true
  actionError.value = ''
  try {
    await graphql(`
      mutation AcceptInvitation($token: String!) {
        acceptInvitation(token: $token) { id name }
      }
    `, { token })
    accepted.value = true
  } catch (err) {
    actionError.value = err.message
  } finally {
    actionLoading.value = false
  }
}

const handleDecline = async () => {
  actionLoading.value = true
  actionError.value = ''
  try {
    await graphql(`
      mutation DeclineInvitation($token: String!) {
        declineInvitation(token: $token)
      }
    `, { token })
    declined.value = true
  } catch (err) {
    actionError.value = err.message
  } finally {
    actionLoading.value = false
  }
}

onMounted(loadPreview)
</script>

<style scoped>
* { box-sizing: border-box; margin: 0; padding: 0; }

.invite-page {
  min-height: 100vh;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #fff;
  font-family: 'Segoe UI', sans-serif;
  padding: 24px;
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

.brand-name { font-size: 22px; font-weight: 600; color: #111; }

.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 20px;
  padding: 40px 48px;
  width: 100%;
  max-width: 500px;
}

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 20px 0; color: #6b7280; font-size: 15px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; flex-shrink: 0; }
.spinner-white { border-color: rgba(255,255,255,0.3); border-top-color: #fff; }

.state-card { text-align: center; }
.state-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.error-icon { background: #fef2f2; color: #dc2626; }
.warning-icon { background: #fffbeb; color: #d97706; }
.success-icon { background: #f0fdf4; color: #16a34a; }
.info-icon { background: #eff6ff; color: #2563eb; }

.state-card h2 { font-size: 20px; font-weight: 700; color: #111; margin-bottom: 8px; }
.state-card p { font-size: 15px; color: #6b7280; line-height: 1.6; margin-bottom: 8px; }
.hint { font-size: 13px; color: #9ca3af; margin-top: 4px; }

.invite-header { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
.store-icon { width: 52px; height: 52px; background: #f3f4f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #6b7280; flex-shrink: 0; }
.invite-header h2 { font-size: 20px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #9ca3af; margin-top: 2px; }

.invite-details { background: #f9fafb; border-radius: 12px; padding: 20px; margin-bottom: 24px; display: flex; flex-direction: column; gap: 14px; }
.detail-row { display: flex; align-items: center; justify-content: space-between; }
.detail-label { font-size: 13px; color: #9ca3af; font-weight: 500; }
.detail-value { font-size: 14px; color: #111; font-weight: 600; }

.role-badge { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 6px; text-transform: capitalize; }
.role-badge.OWNER { color: #16a34a; background: #f0fdf4; }
.role-badge.ACCOUNTANT { color: #2563eb; background: #eff6ff; }
.role-badge.STAFF { color: #6b7280; background: #f3f4f6; }

.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }

.invite-actions { display: flex; gap: 10px; }
.btn-decline { flex: 1; padding: 13px; background: #f3f4f6; color: #374151; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-decline:hover:not(:disabled) { background: #e5e7eb; }
.btn-accept { flex: 2; padding: 13px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-accept:hover:not(:disabled) { background: #333; }
.btn-decline:disabled, .btn-accept:disabled { opacity: 0.5; cursor: not-allowed; }

.auth-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
.btn-primary { display: block; text-align: center; padding: 13px; background: #111; color: #fff; border-radius: 10px; font-size: 15px; font-weight: 600; text-decoration: none; transition: background 0.2s; }
.btn-primary:hover { background: #333; }
.btn-secondary { display: block; text-align: center; padding: 13px; background: #f3f4f6; color: #374151; border-radius: 10px; font-size: 15px; font-weight: 600; text-decoration: none; transition: background 0.2s; }
.btn-secondary:hover { background: #e5e7eb; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
