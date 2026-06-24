export const TAG_COLUMNS = [
  { key: 'select',      labelKey: '',                   sortable: false },
  { key: 'stt',         labelKey: 'shared.rowNo',       sortable: false },
  { key: 'name',        labelKey: 'tags.key',           sortable: true  },
  { key: 'values',      labelKey: 'tags.values',        sortable: false },
  { key: 'description', labelKey: 'common.description', sortable: false },
  { key: 'created_at',  labelKey: 'common.createdAt',   sortable: true  },
  { key: 'updated_at',  labelKey: 'common.updatedAt',   sortable: true  },
  { key: 'actions',     labelKey: '',                   sortable: false },
]

export const TAG_INITIAL_COL_WIDTHS = [44, 60, 220, 320, 250, 170, 170, 90]
