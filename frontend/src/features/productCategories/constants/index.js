import { t, activeLocale } from '@/i18n'

export const PRODUCT_CATEGORY_COLUMNS = [
  { key: 'select',      labelKey: '',                   sortable: false },
  { key: 'stt',         labelKey: 'shared.rowNo',       sortable: false },
  { key: 'code',        labelKey: 'productCategories.code', sortable: true  },
  { key: 'name',        labelKey: 'common.name',        sortable: true  },
  { key: 'description', labelKey: 'common.description', sortable: false },
  { key: 'status',      labelKey: 'common.status',      sortable: true  },
  { key: 'created_at',  labelKey: 'common.createdAt',   sortable: true  },
  { key: 'updated_at',  labelKey: 'common.updatedAt',   sortable: true  },
  { key: 'actions',     labelKey: '',                   sortable: false },
]

export const PRODUCT_CATEGORY_INITIAL_COL_WIDTHS = [44, 60, 100, 240, 290, 110, 170, 170, 90]

export const statusOptions = () => [
  { value: 'active',   label: t('common.active') },
  { value: 'inactive', label: t('common.inactive') },
]

export const SYSTEM_CATEGORY_LABELS = {
  SV: { vi: 'Dịch vụ',  en: 'Service' },
  GD: { vi: 'Hàng hóa', en: 'Goods' },
  MF: { vi: 'Sản xuất', en: 'Manufacturing' },
  TR: { vi: 'Vận tải',  en: 'Transportation' },
  OT: { vi: 'Khác',     en: 'Other' },
}

// System category names are stored bilingually; resolve to the active UI locale.
export function displayCategoryName(category, locale = activeLocale()) {
  if (!category) return ''
  if (category.is_system) {
    const labels = SYSTEM_CATEGORY_LABELS[category.code]
    if (labels && labels[locale]) return labels[locale]
  }
  return category.name
}
