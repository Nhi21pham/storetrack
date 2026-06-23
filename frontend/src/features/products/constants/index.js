import { t } from '@/i18n'

export const PRODUCT_COLUMNS = [
  { key: 'select',     labelKey: '',                  sortable: false },
  { key: 'stt',        labelKey: 'shared.rowNo',      sortable: false },
  { key: 'code',       labelKey: 'products.code',     sortable: true  },
  { key: 'name',       labelKey: 'products.productName', sortable: true  },
  { key: 'category',   labelKey: 'products.category', sortable: true  },
  { key: 'unit',       labelKey: 'products.unit',     sortable: true  },
  { key: 'tags',       labelKey: 'products.tags',     sortable: false },
  { key: 'status',     labelKey: 'common.status',     sortable: true  },
  { key: 'created_at', labelKey: 'common.createdAt',  sortable: true  },
  { key: 'updated_at', labelKey: 'common.updatedAt',  sortable: true  },
  { key: 'actions',    labelKey: '',                  sortable: false },
]

export const PRODUCT_INITIAL_COL_WIDTHS = [44, 60, 110, 240, 140, 100, 200, 110, 170, 170, 90]

export const statusOptions = () => [
  { value: 'active',   label: t('common.active') },
  { value: 'inactive', label: t('common.inactive') },
]
