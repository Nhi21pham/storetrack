export const PRODUCT_CATEGORY_COLUMNS = [
  { key: 'select',      label: '',            sortable: false },
  { key: 'id',          label: 'ID',          sortable: true  },
  { key: 'code',        label: 'Code',        sortable: true  },
  { key: 'name',        label: 'Name',        sortable: true  },
  { key: 'description', label: 'Description', sortable: false },
  { key: 'status',      label: 'Status',      sortable: true  },
  { key: 'created_at',  label: 'Created',     sortable: true  },
  { key: 'actions',     label: '',            sortable: false },
]

export const PRODUCT_CATEGORY_INITIAL_COL_WIDTHS = [44, 70, 100, 220, 290, 110, 160, 90]

export const STATUS_OPTIONS = [
  { value: 'active',   label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]

export const SYSTEM_CATEGORY_LABELS = {
  SV: { vi: 'Dịch vụ',  en: 'Service' },
  GD: { vi: 'Hàng hóa', en: 'Goods' },
  MF: { vi: 'Sản xuất', en: 'Manufacturing' },
  TR: { vi: 'Vận tải',  en: 'Transportation' },
  OT: { vi: 'Khác',     en: 'Other' },
}

export function displayCategoryName(category, locale = 'vi') {
  if (!category) return ''
  if (category.is_system) {
    const labels = SYSTEM_CATEGORY_LABELS[category.code]
    if (labels && labels[locale]) return labels[locale]
  }
  return category.name
}
