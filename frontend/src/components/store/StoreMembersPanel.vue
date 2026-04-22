<template>
  <div class="members-panel">
    <!-- Members list -->
    <div class="section">
      <h4 class="section-title">Members</h4>
      <div v-if="membersLoading" class="list-loading">
        <div class="spinner"></div> Loading members...
      </div>
      <div v-else-if="members.length === 0" class="empty-list">No members yet.</div>
      <div v-else-if="filteredMembers.length === 0" class="empty-list">No members match "{{ search }}".</div>
      <div v-else class="member-list">
        <div v-for="member in filteredMembers" :key="member.id" class="member-row">
          <div class="member-info">
            <span class="member-name">{{ member.name }}</span>
            <span class="member-email">{{ member.email }}</span>
          </div>
          <div class="member-right">
            <div v-if="canRemove && member.role !== 'OWNER'" class="role-dropdown-wrap">
              <button class="role-btn" :class="member.role" @click.stop="editingRoleId = editingRoleId === member.id ? null : member.id">
                {{ roleLabel(member.role) }}
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
              <div v-if="editingRoleId === member.id" class="role-menu">
                <button
                  v-for="opt in roleOptions"
                  :key="opt.value"
                  class="role-menu-item"
                  :class="[opt.value, { active: member.role === opt.value }]"
                  :disabled="member.role === opt.value"
                  @click.stop="requestRoleChange(member, opt.value)"
                >
                  <span class="role-dot" :class="member.role === opt.value ? opt.value : 'hidden'"></span>
                  {{ opt.label }}
                  <svg v-if="member.role === opt.value" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left:auto"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
              </div>
            </div>
            <span v-else class="role-badge" :class="member.role">{{ roleLabel(member.role) }}</span>
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
      <div class="section-header">
        <h4 class="section-title">Pending Invitations</h4>
        <button class="btn-invite" @click="$emit('invite')">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Invite
        </button>
      </div>
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
            <button class="remove-btn" @click="cancellingInvitation = inv" title="Cancel invitation">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>

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

    <ConfirmDialog
      v-if="pendingRoleChange"
      title="Change Role"
      :message="`Change ${pendingRoleChange.member.name}'s role to ${roleLabel(pendingRoleChange.newRole)}?`"
      confirm-text="Yes, change"
      cancel-text="Cancel"
      type="warning"
      @confirm="confirmRoleChange"
      @cancel="pendingRoleChange = null"
    />

    <ConfirmDialog
      v-if="cancellingInvitation"
      title="Cancel Invitation"
      :message="`Cancel the invitation sent to ${cancellingInvitation.invitee_email}?`"
      confirm-text="Yes, cancel"
      cancel-text="Keep"
      type="warning"
      @confirm="handleCancelInvitation"
      @cancel="cancellingInvitation = null"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { graphql } from '@/api'

const props = defineProps({
  storeId: { type: String, required: true },
  canInvite: { type: Boolean, default: false },
  canRemove: { type: Boolean, default: false },
  search: { type: String, default: '' },
})

const emit = defineEmits(['member-removed', 'error', 'invite', 'has-matches'])

const members = ref([])
const membersLoading = ref(true)
const pendingInvitations = ref([])
const invitesLoading = ref(true)
const removingMember = ref(null)
const cancellingInvitation = ref(null)
const editingRoleId = ref(null)
const pendingRoleChange = ref(null)

const filteredMembers = computed(() => {
  const q = props.search.trim().toLowerCase()
  if (!q) return members.value
  return members.value.filter(m =>
    m.name.toLowerCase().includes(q) || m.email.toLowerCase().includes(q)
  )
})

watch([filteredMembers, membersLoading], ([filtered, loading]) => {
  if (props.search.trim() && !loading) emit('has-matches', filtered.length > 0)
})

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

const requestRoleChange = (member, newRole) => {
  editingRoleId.value = null
  if (newRole === member.role) return
  pendingRoleChange.value = { member, newRole }
}

const confirmRoleChange = async () => {
  const { member, newRole } = pendingRoleChange.value
  pendingRoleChange.value = null
  try {
    await graphql(`
      mutation ChangeRole($store_id: ID!, $user_id: ID!, $role: Role!) {
        assignUserToStore(store_id: $store_id, user_id: $user_id, role: $role) { id }
      }
    `, { store_id: props.storeId, user_id: member.id, role: newRole })
    await fetchMembers()
  } catch (err) {
    emit('error', err.message)
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

const handleCancelInvitation = async () => {
  const inv = cancellingInvitation.value
  cancellingInvitation.value = null
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

const roleOptions = [
  { value: 'ACCOUNTANT', label: 'Accountant' },
  { value: 'STAFF', label: 'Staff' },
]

const closeDropdown = () => { editingRoleId.value = null }
watch(editingRoleId, (val) => {
  if (val) document.addEventListener('click', closeDropdown)
  else document.removeEventListener('click', closeDropdown)
})
onBeforeUnmount(() => document.removeEventListener('click', closeDropdown))

defineExpose({ refresh })

onMounted(() => {
  fetchMembers()
  fetchPendingInvitations()
})
</script>

<style scoped>
.members-panel { display: flex; flex-direction: column; gap: 20px; padding-top: 12px; }

.section { }
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.section-title { font-size: 12px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; }

.btn-invite { display: flex; align-items: center; gap: 4px; padding: 4px 10px; background: #111; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-invite:hover { background: #333; }

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

.role-badge { font-size: 13px; font-weight: 600; padding: 4px 10px; border-radius: 6px; }
.role-badge.OWNER { color: #16a34a; background: #f0fdf4; }
.role-badge.ACCOUNTANT { color: #2563eb; background: #eff6ff; }
.role-badge.STAFF { color: #6b7280; background: #f3f4f6; }

.role-dropdown-wrap { position: relative; }
.role-btn { display: flex; align-items: center; gap: 5px; font-size: 13px; font-weight: 600; padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; transition: filter 0.15s; }
.role-btn.ACCOUNTANT { color: #2563eb; background: #eff6ff; }
.role-btn.STAFF { color: #6b7280; background: #f3f4f6; }
.role-btn:hover { filter: brightness(0.93); }
.role-btn svg { opacity: 0.6; }

.role-menu { position: absolute; right: 0; top: calc(100% + 6px); background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); z-index: 10; min-width: 140px; overflow: hidden; padding: 4px; }
.role-menu-item { display: flex; align-items: center; gap: 8px; width: 100%; padding: 7px 10px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-align: left; border-radius: 6px; transition: background 0.12s; background: none; }
.role-menu-item.ACCOUNTANT { color: #93c5fd; }
.role-menu-item.STAFF { color: #d1d5db; }
.role-menu-item:not(.active):hover.ACCOUNTANT { background: #eff6ff; color: #2563eb; }
.role-menu-item:not(.active):hover.STAFF { background: #f3f4f6; color: #6b7280; }
.role-menu-item.active.ACCOUNTANT { background: #eff6ff; color: #1d4ed8; cursor: default; }
.role-menu-item.active.STAFF { background: #f3f4f6; color: #374151; cursor: default; }

.role-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.role-dot.ACCOUNTANT { background: #2563eb; }
.role-dot.STAFF { background: #9ca3af; }
.role-dot.hidden { visibility: hidden; }


.remove-btn { display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border: none; background: none; color: #d1d5db; border-radius: 6px; cursor: pointer; transition: all 0.15s; }
.remove-btn:hover { background: #fef2f2; color: #dc2626; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
