import {
  ROLE_LABELS,
  INVITATION_STATUS,
  INVITATION_STATUS_LABELS,
} from '@/features/users/constants'

export const roleLabel = (role) => ROLE_LABELS[role] ?? role

export const statusLabel = (status) => INVITATION_STATUS_LABELS[status] ?? status

export const formatDateTime = (dt) => {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
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
