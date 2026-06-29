import { t } from '@/i18n'

export { formatMoney, formatQuantity, formatInvoiceDate as formatDate } from '@/features/invoices/constants'

// Shown only in the consolidated business view, where the same product spans
// multiple stores; inserted right after the leading select column.
export const REPORT_STORE_COLUMN = { key: 'store_name', labelKey: 'reports.col.store', sortable: true }
export const REPORT_STORE_COLUMN_WIDTH = 170

export const STOCK_REPORT_COLUMNS = [
  { key: 'select',             labelKey: '',                       sortable: false },
  { key: 'product_name',       labelKey: 'reports.col.product',    sortable: true  },
  { key: 'product_code',       labelKey: 'reports.col.code',       sortable: true  },
  { key: 'tags',               labelKey: 'reports.col.tags',       sortable: false },
  { key: 'supplier_name',      labelKey: 'reports.col.supplier',   sortable: true  },
  { key: 'invoice_code',       labelKey: 'reports.col.purchaseInvoice', sortable: true  },
  { key: 'purchase_date',      labelKey: 'reports.col.purchaseDate', sortable: true  },
  { key: 'quantity_received',  labelKey: 'reports.col.purchased',  sortable: true  },
  { key: 'quantity_remaining', labelKey: 'reports.col.inStock',    sortable: true  },
  { key: 'unit_cost',          labelKey: 'reports.col.costPerUnit', sortable: true  },
  { key: 'total_cost',         labelKey: 'reports.col.totalCost',  sortable: true  },
]

export const STOCK_REPORT_INITIAL_COL_WIDTHS = [40, 200, 130, 200, 180, 150, 130, 110, 140, 120, 130]

export const SALE_REPORT_COLUMNS = [
  { key: 'select',                labelKey: '',                          sortable: false },
  { key: 'order_number',          labelKey: 'reports.col.num',           sortable: false },
  { key: 'product_name',          labelKey: 'reports.col.product',       sortable: true  },
  { key: 'product_code',          labelKey: 'reports.col.code',          sortable: true  },
  { key: 'tags',                  labelKey: 'reports.col.tags',          sortable: false },
  { key: 'customer_name',         labelKey: 'reports.col.customer',      sortable: true  },
  { key: 'purchase_invoice_code', labelKey: 'reports.col.purchaseInvoice', sortable: true  },
  { key: 'purchase_date',         labelKey: 'reports.col.purchaseDate',  sortable: true  },
  { key: 'invoice_code',          labelKey: 'reports.col.saleInvoice',   sortable: true  },
  { key: 'invoice_date',          labelKey: 'reports.col.saleDate',      sortable: true  },
  { key: 'quantity',              labelKey: 'reports.col.qtySold',       sortable: true  },
  { key: 'unit_price',            labelKey: 'reports.col.salePricePerUnit', sortable: true  },
  { key: 'total_sale',            labelKey: 'reports.col.totalSale',     sortable: true  },
]

export const SALE_REPORT_INITIAL_COL_WIDTHS = [40, 50, 200, 130, 200, 180, 150, 130, 150, 130, 110, 150, 130]

export const PROFIT_REPORT_COLUMNS = [
  { key: 'select',                labelKey: '',                          sortable: false },
  { key: 'order_number',          labelKey: 'reports.col.num',           sortable: false },
  { key: 'product_name',          labelKey: 'reports.col.product',       sortable: true  },
  { key: 'product_code',          labelKey: 'reports.col.code',          sortable: true  },
  { key: 'tags',                  labelKey: 'reports.col.tags',          sortable: false },
  { key: 'purchase_invoice_code', labelKey: 'reports.col.purchaseInvoice', sortable: true  },
  { key: 'purchase_date',         labelKey: 'reports.col.purchaseDate',  sortable: true  },
  { key: 'invoice_code',          labelKey: 'reports.col.saleInvoice',   sortable: true  },
  { key: 'invoice_date',          labelKey: 'reports.col.saleDate',      sortable: true  },
  { key: 'quantity',              labelKey: 'reports.col.qtySold',       sortable: true  },
  { key: 'unit_cost',             labelKey: 'reports.col.costPerUnit',   sortable: true  },
  { key: 'unit_price',            labelKey: 'reports.col.salePricePerUnit', sortable: true  },
  { key: 'cost',                  labelKey: 'reports.col.cost',          sortable: true  },
  { key: 'revenue',               labelKey: 'reports.col.revenue',       sortable: true  },
  { key: 'profit',                labelKey: 'reports.col.profit',        sortable: true  },
]

export const PROFIT_REPORT_INITIAL_COL_WIDTHS = [40, 50, 200, 130, 200, 160, 130, 150, 130, 110, 140, 150, 130, 130, 130]

// The debt reports (customer / supplier) share one column set; only the party
// name and "spent" labels differ per ledger, so those carry an explicit label.
export const makeDebtReportColumns = ({ partyLabel, spentLabel }) => [
  { key: 'select',        labelKey: '',                       sortable: false },
  { key: 'order_number',  labelKey: 'reports.col.num',        sortable: false },
  { key: 'name',          label: partyLabel,                  sortable: true  },
  { key: 'phone',         labelKey: 'reports.col.phone',      sortable: true  },
  { key: 'email',         labelKey: 'reports.col.email',      sortable: true  },
  { key: 'invoice_count', labelKey: 'reports.col.numInvoices', sortable: true  },
  { key: 'spent',         label: spentLabel,                  sortable: true  },
  { key: 'paid',          labelKey: 'reports.col.totalPaid',  sortable: true  },
  { key: 'owe',           labelKey: 'reports.col.totalOwe',   sortable: true  },
]

export const DEBT_REPORT_INITIAL_COL_WIDTHS = [40, 50, 220, 150, 220, 100, 160, 160, 160]

// '# Invoices' and 'Email' start hidden; the rest (incl. Phone) show by default.
export const DEBT_REPORT_DEFAULT_HIDDEN = ['invoice_count', 'email']

// Top Products: one ranked row per product. The order_number column is the rank
// (rows are sorted by the active "Rank by" metric).
export const TOP_PRODUCTS_COLUMNS = [
  { key: 'select',       labelKey: '',                     sortable: false },
  { key: 'order_number', labelKey: 'reports.col.num',      sortable: false },
  { key: 'product_name', labelKey: 'reports.col.product',  sortable: true  },
  { key: 'product_code', labelKey: 'reports.col.code',     sortable: true  },
  { key: 'tags',         labelKey: 'reports.col.tags',     sortable: false },
  { key: 'qty_sold',     labelKey: 'reports.col.qtySold',  sortable: true  },
  { key: 'revenue',      labelKey: 'reports.col.revenue',  sortable: true  },
  { key: 'profit',       labelKey: 'reports.col.profit',   sortable: true  },
  { key: 'orders',       labelKey: 'reports.col.numOrders', sortable: true  },
]

export const TOP_PRODUCTS_INITIAL_COL_WIDTHS = [40, 50, 220, 130, 200, 130, 160, 160, 110]

// The metrics the "Rank by" toggle switches between (each sorts that column desc).
export const topProductsRankMetrics = () => [
  { value: 'qty_sold', label: t('reports.col.qtySold') },
  { value: 'revenue',  label: t('reports.col.revenue') },
  { value: 'profit',   label: t('reports.col.profit') },
  { value: 'orders',   label: t('reports.col.numOrders') },
]
