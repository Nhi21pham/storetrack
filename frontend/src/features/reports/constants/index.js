export { formatMoney, formatQuantity, formatInvoiceDate as formatDate } from '@/features/invoices/constants'

export const STOCK_REPORT_COLUMNS = [
  { key: 'select',             label: '',                 sortable: false },
  { key: 'product_name',       label: 'Product',          sortable: true  },
  { key: 'product_code',       label: 'Code',             sortable: true  },
  { key: 'supplier_name',      label: 'Supplier',         sortable: true  },
  { key: 'invoice_code',       label: 'Purchase invoice', sortable: true  },
  { key: 'purchase_date',      label: 'Purchase date',    sortable: true  },
  { key: 'quantity_received',  label: 'Purchased',        sortable: true  },
  { key: 'quantity_remaining', label: 'In stock',         sortable: true  },
  { key: 'unit_cost',          label: 'Cost / unit',      sortable: true  },
  { key: 'total_cost',         label: 'Total cost',       sortable: true  },
]

export const STOCK_REPORT_INITIAL_COL_WIDTHS = [40, 200, 130, 180, 150, 130, 110, 140, 120, 130]
