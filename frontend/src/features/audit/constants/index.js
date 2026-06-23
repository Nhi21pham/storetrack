import { t } from '@/i18n'

const OBJECT_VALUES = [
  'business', 'store', 'user', 'invitation', 'supplier', 'customer',
  'bank', 'bank_account', 'unit', 'product', 'product_category',
]

export const objectOptions = () =>
  OBJECT_VALUES.map((value) => ({ value, label: t(`enums.objectType.${value}`) }))

const ACTION_VALUES = [
  'created', 'updated', 'deactivated', 'reactivated', 'assigned', 'role_changed',
  'removed', 'deleted', 'invited', 'cancelled', 'accepted', 'declined', 'exported',
]

export const actionOptions = () =>
  ACTION_VALUES.map((value) => ({ value, label: t(`enums.auditAction.${value}`) }))

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
