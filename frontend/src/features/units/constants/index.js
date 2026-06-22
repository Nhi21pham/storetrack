import { t } from '@/i18n'

// Columns carry an i18n `labelKey` (resolved at render time) so headers follow
// the active language without rebuilding the table.
export const UNIT_COLUMNS = [
  { key: 'select', labelKey: '', sortable: false },
  { key: 'stt', labelKey: 'shared.rowNo', sortable: false },
  { key: 'name', labelKey: 'units.unitName', sortable: true },
  { key: 'status', labelKey: 'common.status', sortable: true },
  { key: 'created_at', labelKey: 'common.createdAt', sortable: true },
  { key: 'updated_at', labelKey: 'common.updatedAt', sortable: true },
  { key: 'actions', labelKey: '', sortable: false },
]

export const UNIT_INITIAL_COL_WIDTHS = [44, 60, 220, 140, 280, 280, 90]

export const statusOptions = () => [
  { value: 'active',   label: t('common.active') },
  { value: 'inactive', label: t('common.inactive') },
]
