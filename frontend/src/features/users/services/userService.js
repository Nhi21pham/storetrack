import { graphql } from '@/api'

// --- Queries -----------------------------------------------------------------

// The user-management page only needs id/name/active/role + business name,
// so it has its own slimmer query rather than depending on the stores feature.
const ACCESSIBLE_STORES_BRIEF_QUERY = `
  query AccessibleStoresBrief {
    accessibleStores {
      id name is_active my_role
      business { id name }
    }
  }
`

const STORE_MEMBERS_QUERY = `
  query StoreMembers($id: ID!) {
    store(id: $id) { users { id name email role } }
  }
`

const STORE_PENDING_INVITATIONS_QUERY = `
  query PendingInvitations($store_id: ID!) {
    storePendingInvitations(store_id: $store_id) {
      id invitee_email role status
      inviter { id name }
    }
  }
`

const STORE_ALL_INVITATIONS_QUERY = `
  query StoreAllInvitations($store_id: ID!) {
    storeAllInvitations(store_id: $store_id) {
      id invitee_email invitee_name role status
      created_at accepted_at updated_at
      store { id name }
      inviter { id name email }
    }
  }
`

// --- Mutations ---------------------------------------------------------------

const ASSIGN_USER_MUTATION = `
  mutation ChangeRole($store_id: ID!, $user_id: ID!, $role: Role!) {
    assignUserToStore(store_id: $store_id, user_id: $user_id, role: $role) { id }
  }
`

const REMOVE_USER_MUTATION = `
  mutation RemoveUser($store_id: ID!, $user_id: ID!) {
    removeUserFromStore(store_id: $store_id, user_id: $user_id) { id }
  }
`

const INVITE_USER_MUTATION = `
  mutation InviteUser($store_id: ID!, $email: String!, $role: Role!) {
    inviteUserToStore(store_id: $store_id, email: $email, role: $role) {
      id invitee_email role status
    }
  }
`

const CANCEL_INVITATION_MUTATION = `
  mutation CancelInvitation($invitation_id: ID!) {
    cancelInvitation(invitation_id: $invitation_id)
  }
`

// --- Exported functions ------------------------------------------------------

export const fetchAccessibleStoresBrief = async () => {
  const data = await graphql(ACCESSIBLE_STORES_BRIEF_QUERY)
  return data.accessibleStores
}

export const fetchStoreMembers = async (storeId) => {
  const data = await graphql(STORE_MEMBERS_QUERY, { id: storeId })
  return data.store.users
}

export const fetchStorePendingInvitations = async (storeId) => {
  const data = await graphql(STORE_PENDING_INVITATIONS_QUERY, { store_id: storeId })
  return data.storePendingInvitations
}

export const fetchStoreAllInvitations = async (storeId) => {
  const data = await graphql(STORE_ALL_INVITATIONS_QUERY, { store_id: storeId })
  return data.storeAllInvitations
}

export const assignUserToStore = async (storeId, userId, role) => {
  await graphql(ASSIGN_USER_MUTATION, { store_id: storeId, user_id: userId, role })
}

export const removeUserFromStore = async (storeId, userId) => {
  await graphql(REMOVE_USER_MUTATION, { store_id: storeId, user_id: userId })
}

export const inviteUserToStore = async (storeId, email, role) => {
  const data = await graphql(INVITE_USER_MUTATION, { store_id: storeId, email, role })
  return data.inviteUserToStore
}

export const cancelInvitation = async (invitationId) => {
  await graphql(CANCEL_INVITATION_MUTATION, { invitation_id: invitationId })
}
