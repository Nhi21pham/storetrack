import { INVITATION_STATUS } from '@/features/users/constants'
import { formatDateTime } from '@/utils/datetime'
import { t, te } from '@/i18n'

export { formatDateTime }

export const roleLabel = (role) => {
  const key = `enums.role.${role}`
  return te(key) ? t(key) : role
}

export const statusLabel = (status) => {
  const key = `users.invitationStatus.${status}`
  return te(key) ? t(key) : status
}

// An invitation's "resolved" timestamp is whenever it left the PENDING state.
// Accepted invitations record their own `accepted_at`; everything else uses
// the row's last update time.
export const resolvedTimestamp = (invitation) => {
  if (invitation.status === INVITATION_STATUS.PENDING) return ''
  if (invitation.status === INVITATION_STATUS.ACCEPTED && invitation.accepted_at) {
    return invitation.accepted_at
  }
  return invitation.updated_at
}

export const resolvedDateTime = (invitation) => {
  const ts = resolvedTimestamp(invitation)
  return ts ? formatDateTime(ts) : '—'
}
