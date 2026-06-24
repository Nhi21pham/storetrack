import { t } from '@/i18n'

export const BANK_COLUMNS = [
  { key: 'select',       labelKey: '',                    sortable: false },
  { key: 'stt',          labelKey: 'shared.rowNo',        sortable: false },
  { key: 'short_name',   labelKey: 'banking.shortName',   sortable: true  },
  { key: 'full_name_vi', labelKey: 'banking.vietnameseName', sortable: true  },
  { key: 'full_name_en', labelKey: 'banking.englishName', sortable: true  },
  { key: 'status',       labelKey: 'common.status',       sortable: true  },
  { key: 'created_at',   labelKey: 'common.createdAt',    sortable: true  },
  { key: 'updated_at',   labelKey: 'common.updatedAt',    sortable: true  },
  { key: 'actions',      labelKey: '',                    sortable: false },
]

export const BANK_INITIAL_COL_WIDTHS = [44, 60, 160, 300, 300, 110, 170, 170, 110]

export const BANK_ACCOUNT_COLUMNS = [
  { key: 'select',         labelKey: '',                     sortable: false },
  { key: 'stt',            labelKey: 'shared.rowNo',         sortable: false },
  { key: 'owner',          labelKey: 'banking.ownerType',    sortable: true  },
  { key: 'owner_name',     labelKey: 'banking.ownerName',    sortable: true  },
  { key: 'bank',           labelKey: 'banking.bank',         sortable: true  },
  { key: 'account_number', labelKey: 'banking.accountNumber', sortable: true  },
  { key: 'holder_name',    labelKey: 'banking.holderName',   sortable: true  },
  { key: 'branch',         labelKey: 'banking.branch',       sortable: true  },
  { key: 'province',       labelKey: 'banking.province',     sortable: true  },
  { key: 'created_at',     labelKey: 'common.createdAt',     sortable: true  },
  { key: 'updated_at',     labelKey: 'common.updatedAt',     sortable: true  },
  { key: 'actions',        labelKey: '',                     sortable: false },
]

export const statusOptions = () => [
  { value: 'active',   label: t('common.active') },
  { value: 'inactive', label: t('common.inactive') },
]

export const BANK_ACCOUNT_INITIAL_COL_WIDTHS = [44, 60, 120, 180, 150, 170, 200, 170, 150, 170, 170, 100]
