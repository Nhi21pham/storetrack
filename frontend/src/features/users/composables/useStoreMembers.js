import { ref } from 'vue'
import {
  fetchStoreMembers,
  fetchStorePendingInvitations,
  assignUserToStore,
  removeUserFromStore,
  cancelInvitation,
} from '@/features/users/services/userService'

export const useStoreMembers = ({ storeId, canInvite, onError }) => {
  const members             = ref([])
  const membersLoading      = ref(true)
  const pendingInvitations  = ref([])
  const invitesLoading      = ref(true)

  const fetchMembers = async () => {
    membersLoading.value = true
    try {
      members.value = await fetchStoreMembers(storeId)
    } catch (err) {
      onError?.(err.message)
    } finally {
      membersLoading.value = false
    }
  }

  const fetchPendingInvitations = async () => {
    if (!canInvite) {
      invitesLoading.value = false
      return
    }
    invitesLoading.value = true
    try {
      pendingInvitations.value = await fetchStorePendingInvitations(storeId)
    } catch (err) {
      onError?.(err.message)
    } finally {
      invitesLoading.value = false
    }
  }

  const changeRole = async (userId, role) => {
    try {
      await assignUserToStore(storeId, userId, role)
      await fetchMembers()
    } catch (err) {
      onError?.(err.message)
    }
  }

  const removeMember = async (userId) => {
    try {
      await removeUserFromStore(storeId, userId)
      await fetchMembers()
      return true
    } catch (err) {
      onError?.(err.message)
      return false
    }
  }

  const cancelPendingInvitation = async (invitationId) => {
    try {
      await cancelInvitation(invitationId)
      await fetchPendingInvitations()
    } catch (err) {
      onError?.(err.message)
    }
  }

  const refresh = async () => {
    await Promise.all([fetchMembers(), fetchPendingInvitations()])
  }

  return {
    members, membersLoading,
    pendingInvitations, invitesLoading,
    fetchMembers, fetchPendingInvitations,
    changeRole, removeMember, cancelPendingInvitation,
    refresh,
  }
}
