export { formatMoney, formatQuantity, formatInvoiceDate as formatDate } from '@/features/invoices/constants'

// Shown only in the consolidated business view, where the same product spans
// multiple stores; inserted right after the leading select column.
export const REPORT_STORE_COLUMN = { key: 'store_name', label: 'Store', sortable: true }
export const REPORT_STORE_COLUMN_WIDTH = 170

export const STOCK_REPORT_COLUMNS = [
  { key: 'select',             label: '',                 sortable: false },
  { key: 'product_name',       label: 'Product',          sortable: true  },
  { key: 'product_code',       label: 'Code',             sortable: true  },
  { key: 'tags',               label: 'Tags',             sortable: false },
  { key: 'supplier_name',      label: 'Supplier',         sortable: true  },
  { key: 'invoice_code',       label: 'Purchase invoice', sortable: true  },
  { key: 'purchase_date',      label: 'Purchase date',    sortable: true  },
  { key: 'quantity_received',  label: 'Purchased',        sortable: true  },
  { key: 'quantity_remaining', label: 'In stock',         sortable: true  },
  { key: 'unit_cost',          label: 'Cost / unit',      sortable: true  },
  { key: 'total_cost',         label: 'Total cost',       sortable: true  },
]

export const STOCK_REPORT_INITIAL_COL_WIDTHS = [40, 200, 130, 200, 180, 150, 130, 110, 140, 120, 130]

export const SALE_REPORT_COLUMNS = [
  { key: 'select',        label: '',                  sortable: false },
  { key: 'order_number',  label: '#',                 sortable: false },
  { key: 'product_name',  label: 'Product',           sortable: true  },
  { key: 'product_code',  label: 'Code',              sortable: true  },
  { key: 'tags',          label: 'Tags',              sortable: false },
  { key: 'customer_name', label: 'Customer',          sortable: true  },
  { key: 'invoice_code',  label: 'Sale invoice',      sortable: true  },
  { key: 'invoice_date',  label: 'Sale date',         sortable: true  },
  { key: 'quantity',      label: 'Qty sold',          sortable: true  },
  { key: 'unit_price',    label: 'Sale price / unit', sortable: true  },
  { key: 'total_sale',    label: 'Total sale',        sortable: true  },
]

export const SALE_REPORT_INITIAL_COL_WIDTHS = [40, 50, 200, 130, 200, 180, 150, 130, 110, 150, 130]

export const PROFIT_REPORT_COLUMNS = [
  { key: 'select',                label: '',                  sortable: false },
  { key: 'order_number',          label: '#',                 sortable: false },
  { key: 'product_name',          label: 'Product',           sortable: true  },
  { key: 'product_code',          label: 'Code',              sortable: true  },
  { key: 'tags',                  label: 'Tags',              sortable: false },
  { key: 'purchase_invoice_code', label: 'Purchase invoice',  sortable: true  },
  { key: 'purchase_date',         label: 'Purchase date',     sortable: true  },
  { key: 'invoice_code',          label: 'Sale invoice',      sortable: true  },
  { key: 'invoice_date',          label: 'Sale date',         sortable: true  },
  { key: 'quantity',              label: 'Qty sold',          sortable: true  },
  { key: 'unit_cost',             label: 'Cost / unit',       sortable: true  },
  { key: 'unit_price',            label: 'Sale price / unit', sortable: true  },
  { key: 'cost',                  label: 'Cost',              sortable: true  },
  { key: 'revenue',               label: 'Revenue',           sortable: true  },
  { key: 'profit',                label: 'Profit',            sortable: true  },
]

export const PROFIT_REPORT_INITIAL_COL_WIDTHS = [40, 50, 200, 130, 200, 160, 130, 150, 130, 110, 140, 150, 130, 130, 130]
