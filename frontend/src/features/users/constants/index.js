export const ROLE = {
  OWNER:      'OWNER',
  ACCOUNTANT: 'ACCOUNTANT',
  STAFF:      'STAFF',
}

export const ROLE_LABELS = {
  [ROLE.OWNER]:      'Owner',
  [ROLE.ACCOUNTANT]: 'Accountant',
  [ROLE.STAFF]:      'Staff',
}

// Roles users can be invited or reassigned to (excludes OWNER).
export const ASSIGNABLE_ROLE_OPTIONS = [
  { value: ROLE.ACCOUNTANT, label: 'Accountant', description: 'Can edit store info and manage invoices' },
  { value: ROLE.STAFF,      label: 'Staff',      description: 'Can create and update invoices only' },
]

export const INVITATION_STATUS = {
  PENDING:   'PENDING',
  ACCEPTED:  'ACCEPTED',
  DECLINED:  'DECLINED',
  CANCELLED: 'CANCELLED',
  EXPIRED:   'EXPIRED',
}

export const INVITATION_STATUS_LABELS = {
  [INVITATION_STATUS.PENDING]:   'Pending',
  [INVITATION_STATUS.ACCEPTED]:  'Accepted',
  [INVITATION_STATUS.DECLINED]:  'Declined',
  [INVITATION_STATUS.CANCELLED]: 'Cancelled',
  [INVITATION_STATUS.EXPIRED]:   'Expired',
}

export const INVITATION_STATUS_FILTERS = [
  { value: 'ALL',                           label: 'All' },
  { value: INVITATION_STATUS.PENDING,       label: 'Pending' },
  { value: INVITATION_STATUS.ACCEPTED,      label: 'Accepted' },
  { value: INVITATION_STATUS.DECLINED,      label: 'Declined' },
  { value: INVITATION_STATUS.CANCELLED,     label: 'Cancelled' },
  { value: INVITATION_STATUS.EXPIRED,       label: 'Expired' },
]

export const INVITATION_HISTORY_COLUMNS = [
  { key: 'invitee_email', label: 'Invitee' },
  { key: 'store_name',    label: 'Store' },
  { key: 'role',          label: 'Role' },
  { key: 'status',        label: 'Status' },
  { key: 'created_at',    label: 'Sent' },
  { key: 'resolved_at',   label: 'Resolved' },
]
