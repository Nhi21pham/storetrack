export const SUPPLIER_COLUMNS = [
  { key: 'select',      labelKey: '',                   sortable: false },
  { key: 'stt',         labelKey: 'shared.rowNo',       sortable: false },
  { key: 'name',        labelKey: 'suppliers.name',     sortable: true  },
  { key: 'tax_code',    labelKey: 'suppliers.taxCode',  sortable: true  },
  { key: 'email',       labelKey: 'suppliers.email',    sortable: true  },
  { key: 'phone',       labelKey: 'suppliers.phone',    sortable: true  },
  { key: 'address',     labelKey: 'suppliers.address',  sortable: true  },
  { key: 'outstanding', labelKey: 'suppliers.outstanding', sortable: true  },
  { key: 'created_at',  labelKey: 'common.createdAt',   sortable: true  },
  { key: 'updated_at',  labelKey: 'common.updatedAt',   sortable: true  },
  { key: 'actions',     labelKey: '',                   sortable: false },
]

export const SUPPLIER_INITIAL_COL_WIDTHS = [40, 60, 220, 110, 160, 120, 170, 130, 170, 170, 80]
