export const BANK_COLUMNS = [
  { key: 'short_name',   label: 'Short Name',      sortable: true  },
  { key: 'full_name_vi', label: 'Vietnamese Name', sortable: true  },
  { key: 'full_name_en', label: 'English Name',    sortable: true  },
  { key: 'status',       label: 'Status',          sortable: true  },
  { key: 'actions',      label: '',                sortable: false },
]

export const BANK_INITIAL_COL_WIDTHS = [160, 330, 330, 110, 110]

export const BANK_ACCOUNT_COLUMNS = [
  { key: 'owner',          label: 'Owner Type',     sortable: true  },
  { key: 'owner_name',     label: 'Owner Name',     sortable: true  },
  { key: 'bank',           label: 'Bank',           sortable: true  },
  { key: 'account_number', label: 'Account Number', sortable: true  },
  { key: 'holder_name',    label: 'Holder Name',    sortable: true  },
  { key: 'branch',         label: 'Branch',         sortable: true  },
  { key: 'province',       label: 'Province',       sortable: true  },
  { key: 'actions',        label: '',               sortable: false },
]

export const BANK_ACCOUNT_INITIAL_COL_WIDTHS = [120, 180, 150, 170, 200, 170, 150, 100]
