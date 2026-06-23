export const CUSTOMER_COLUMNS = [
  { key: 'select',      labelKey: '',                   sortable: false },
  { key: 'stt',         labelKey: 'shared.rowNo',       sortable: false },
  { key: 'name',        labelKey: 'customers.name',     sortable: true  },
  { key: 'tax_code',    labelKey: 'customers.taxCode',  sortable: true  },
  { key: 'email',       labelKey: 'customers.email',    sortable: true  },
  { key: 'phone',       labelKey: 'customers.phone',    sortable: true  },
  { key: 'address',     labelKey: 'customers.address',  sortable: true  },
  { key: 'outstanding', labelKey: 'customers.outstanding', sortable: true  },
  { key: 'created_at',  labelKey: 'common.createdAt',   sortable: true  },
  { key: 'updated_at',  labelKey: 'common.updatedAt',   sortable: true  },
  { key: 'actions',     labelKey: '',                   sortable: false },
]

export const CUSTOMER_INITIAL_COL_WIDTHS = [40, 60, 220, 110, 160, 120, 170, 130, 170, 170, 80]
