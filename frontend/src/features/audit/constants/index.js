export const OBJECT_OPTIONS = [
  { value: 'business',   label: 'Business' },
  { value: 'store',      label: 'Store' },
  { value: 'user',       label: 'User' },
  { value: 'invitation', label: 'Invitation' },
  { value: 'supplier',   label: 'Supplier' },
  { value: 'customer',   label: 'Customer' },
]

export const ACTION_OPTIONS = [
  { value: 'created',      label: 'Created' },
  { value: 'updated',      label: 'Updated' },
  { value: 'deactivated',  label: 'Deactivated' },
  { value: 'reactivated',  label: 'Reactivated' },
  { value: 'assigned',     label: 'Assigned' },
  { value: 'role_changed', label: 'Role Changed' },
  { value: 'removed',      label: 'Removed' },
  { value: 'deleted',      label: 'Deleted' },
  { value: 'invited',      label: 'Invited' },
  { value: 'cancelled',    label: 'Cancelled' },
  { value: 'accepted',     label: 'Accepted' },
  { value: 'declined',     label: 'Declined' },
  { value: 'exported',     label: 'Exported' },
]

export const ACTION_COLORS = {
  CREATED: '#16a34a', ACCEPTED: '#16a34a', REACTIVATED: '#16a34a',
  UPDATED: '#1d4ed8', ASSIGNED: '#1d4ed8',
  INVITED: '#7c3aed', EXPORTED: '#7c3aed',
  CANCELLED: '#b45309', DEACTIVATED: '#b45309',
  REMOVED: '#dc2626', DELETED: '#dc2626', DECLINED: '#dc2626',
}

export const ACTION_VERB_REGEX =
  /\b(CREATED|UPDATED|DEACTIVATED|REACTIVATED|ASSIGNED|REMOVED|DELETED|INVITED|CANCELLED|ACCEPTED|DECLINED|EXPORTED)\b/g

export const PER_PAGE_STORAGE_KEY = 'audit_log_per_page'
