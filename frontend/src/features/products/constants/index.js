export const PRODUCT_COLUMNS = [
  { key: 'id',         label: 'ID',           sortable: true  },
  { key: 'code',       label: 'Code',         sortable: true  },
  { key: 'name',       label: 'Product Name', sortable: true  },
  { key: 'category',   label: 'Category',     sortable: true  },
  { key: 'unit',       label: 'Unit',         sortable: true  },
  { key: 'tags',       label: 'Tags',         sortable: false },
  { key: 'status',     label: 'Status',       sortable: true  },
  { key: 'created_at', label: 'Created',      sortable: true  },
  { key: 'actions',    label: '',             sortable: false },
]

export const PRODUCT_INITIAL_COL_WIDTHS = [50, 110, 220, 140, 100, 200, 110, 170, 90]

export const STATUS_OPTIONS = [
  { value: 'active',   label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]
