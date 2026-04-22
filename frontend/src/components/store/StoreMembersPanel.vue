<template>
  <div class="members-panel">
    <!-- Members list -->
    <div class="section">
      <h4 class="section-title">Members</h4>
      <div v-if="membersLoading" class="list-loading">
        <div class="spinner"></div> Loading members...
      </div>
      <div v-else-if="members.length === 0" class="empty-list">No members yet.</div>
      <div v-else class="member-list">
        <div v-for="member in members" :key="member.id" class="member-row">
          <div class="member-info">
            <span class="member-name">{{ member.name }}</span>
            <span class="member-email">{{ member.email }}</span>
          </div>
          <div class="member-right">
            <span class="role-badge" :class="member.role">{{ roleLabel(member.role) }}</span>
            <button
              v-if="canRemove && member.role !== 'OWNER'"
              class="remove-btn"
              @click="confirmRemoveMember(member)"
              title="Remove member"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending invitations (owner only) -->
    <div v-if="canInvite" class="section">
      <h4 class="section-title">Pending Invitations</h4>
      <div v-if="invitesLoading" class="list-loading">
        <div class="spinner"></div> Loading...
      </div>
      <div v-else-if="pendingInvitations.length === 0" class="empty-list">No pending invitations.</div>
      <div v-else class="member-list">
        <div v-for="inv in pendingInvitations" :key="inv.id" class="member-row">
          <div class="member-info">
            <span class="member-name">{{ inv.invitee_email }}</span>
            <span class="member-email">Invited by {{ inv.inviter.name }}</span>
          </div>
          <div class="member-right">
            <span class="role-badge" :class="inv.role">{{ roleLabel(inv.role) }}</span>
            <button class="remove-btn" @click="handleCancelInvitation(inv)" title="Cancel invitation">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Remove member confirm -->
    <ConfirmDialog
      v-if="removingMember"
      title="Remove Member"
      :message="`Remove ${removingMember.name} from this store?`"
      confirm-text="Yes, remove"
      cancel-text="Cancel"
      type="danger"
      @confirm="handleRemoveMember"
      @cancel="removingMember = null"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { graphql } from '@/api'

const props = defineProps({
  storeId: { type: String, required: true },
  canInvite: { type: Boolean, default: false },
  canRemove: { type: Boolean, default: false },
})

const emit = defineEmits(['member-removed', 'error'])

const members = ref([])
const membersLoading = ref(true)
const pendingInvitations = ref([])
const invitesLoading = ref(true)
const removingMember = ref(null)

const roleLabel = (role) => ({ OWNER: 'Owner', ACCOUNTANT: 'Accountant', STAFF: 'Staff' }[role] ?? role)

const fetchMembers = async () => {
  membersLoading.value = true
  try {
    const data = await graphql(`
      query StoreMembers($id: ID!) {
        store(id: $id) { users { id name email role } }
      }
    `, { id: props.storeId })
    members.value = data.store.users
  } catch (err) {
    emit('error', err.message)
  } finally {
    membersLoading.value = false
  }
}

const fetchPendingInvitations = async () => {
  if (!props.canInvite) { invitesLoading.value = false; return }
  invitesLoading.value = true
  try {
    const data = await graphql(`
      query PendingInvitations($store_id: ID!) {
        storePendingInvitations(store_id: $store_id) {
          id invitee_email role status
          inviter { id name }
        }
      }
    `, { store_id: props.storeId })
    pendingInvitations.value = data.storePendingInvitations
  } catch (err) {
    emit('error', err.message)
  } finally {
    invitesLoading.value = false
  }
}

const confirmRemoveMember = (member) => { removingMember.value = member }

const handleRemoveMember = async () => {
  try {
    await graphql(`
      mutation RemoveUser($store_id: ID!, $user_id: ID!) {
        removeUserFromStore(store_id: $store_id, user_id: $user_id) { id }
      }
    `, { store_id: props.storeId, user_id: removingMember.value.id })
    removingMember.value = null
    await fetchMembers()
    emit('member-removed')
  } catch (err) {
    removingMember.value = null
    emit('error', err.message)
  }
}

const handleCancelInvitation = async (inv) => {
  try {
    await graphql(`
      mutation CancelInvitation($invitation_id: ID!) {
        cancelInvitation(invitation_id: $invitation_id)
      }
    `, { invitation_id: inv.id })
    await fetchPendingInvitations()
  } catch (err) {
    emit('error', err.message)
  }
}

const refresh = async () => {
  await Promise.all([fetchMembers(), fetchPendingInvitations()])
}

defineExpose({ refresh })

onMounted(() => {
  fetchMembers()
  fetchPendingInvitations()
})
</script>

<style scoped>
.members-panel { display: flex; flex-direction: column; gap: 20px; padding-top: 12px; }

.section { }
.section-title { font-size: 12px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }

.list-loading { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #9ca3af; padding: 8px 0; }
.spinner { width: 14px; height: 14px; border: 2px solid #e5e7eb; border-top-color: #6b7280; border-radius: 50%; animation: spin 0.6s linear infinite; }
.empty-list { font-size: 13px; color: #d1d5db; padding: 6px 0; }

.member-list { display: flex; flex-direction: column; gap: 4px; }
.member-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-radius: 8px; transition: background 0.12s; }
.member-row:hover { background: #f9fafb; }

.member-info { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.member-name { font-size: 14px; font-weight: 500; color: #111; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.member-email { font-size: 12px; color: #9ca3af; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.member-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

.role-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; }
.role-badge.OWNER { color: #16a34a; background: #f0fdf4; }
.role-badge.ACCOUNTANT { color: #2563eb; background: #eff6ff; }
.role-badge.STAFF { color: #6b7280; background: #f3f4f6; }

.remove-btn { display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border: none; background: none; color: #d1d5db; border-radius: 6px; cursor: pointer; transition: all 0.15s; }
.remove-btn:hover { background: #fef2f2; color: #dc2626; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
