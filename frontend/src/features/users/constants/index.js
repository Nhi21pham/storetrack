import { t } from '@/i18n'

export const ROLE = {
  OWNER:      'OWNER',
  ACCOUNTANT: 'ACCOUNTANT',
  STAFF:      'STAFF',
}

// Roles users can be invited or reassigned to (excludes OWNER). Labels and
// descriptions resolve through i18n at call time so they follow the language.
export const assignableRoleOptions = () => [
  { value: ROLE.ACCOUNTANT, label: t('enums.role.ACCOUNTANT'), description: t('users.roleDesc.ACCOUNTANT') },
  { value: ROLE.STAFF,      label: t('enums.role.STAFF'),      description: t('users.roleDesc.STAFF') },
]

export const INVITATION_STATUS = {
  PENDING:   'PENDING',
  ACCEPTED:  'ACCEPTED',
  DECLINED:  'DECLINED',
  CANCELLED: 'CANCELLED',
  EXPIRED:   'EXPIRED',
}

export const invitationStatusFilters = () => [
  { value: 'ALL',                       label: t('users.invitationStatus.ALL') },
  { value: INVITATION_STATUS.PENDING,   label: t('users.invitationStatus.PENDING') },
  { value: INVITATION_STATUS.ACCEPTED,  label: t('users.invitationStatus.ACCEPTED') },
  { value: INVITATION_STATUS.DECLINED,  label: t('users.invitationStatus.DECLINED') },
  { value: INVITATION_STATUS.CANCELLED, label: t('users.invitationStatus.CANCELLED') },
  { value: INVITATION_STATUS.EXPIRED,   label: t('users.invitationStatus.EXPIRED') },
]

export const INVITATION_HISTORY_COLUMNS = [
  { key: 'invitee_email', labelKey: 'users.invitee' },
  { key: 'store_name',    labelKey: 'users.store' },
  { key: 'role',          labelKey: 'users.role' },
  { key: 'status',        labelKey: 'users.status' },
  { key: 'created_at',    labelKey: 'users.sent' },
  { key: 'resolved_at',   labelKey: 'users.resolved' },
]
