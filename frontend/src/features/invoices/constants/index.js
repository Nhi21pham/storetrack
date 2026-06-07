// Invoice type names (GraphQL enum names, sent/received as-is)
export const INVOICE_TYPE = {
  PURCHASE: 'PURCHASE',
  SALE: 'SALE',
}

export const PAYMENT_METHODS = [
  { value: 'CASH', label: 'Cash' },
  { value: 'CARD', label: 'Card' },
]

export const PAYMENT_STATUSES = [
  { value: 'UNPAID', label: 'Unpaid' },
  { value: 'PAID', label: 'Paid' },
]

export const paymentMethodLabel = (value) =>
  PAYMENT_METHODS.find((m) => m.value === value)?.label || value || '—'

export const paymentStatusLabel = (value) =>
  PAYMENT_STATUSES.find((s) => s.value === value)?.label || value || '—'

// Money as a fixed 2-decimal, thousands-separated string.
export const formatMoney = (value) =>
  Number(value || 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })

// Quantity trimmed of trailing zeros (10.000 -> "10", 1.500 -> "1.5").
export const formatQuantity = (value) => {
  const n = Number(value || 0)
  return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(3)))
}

// invoice_date arrives as a datetime; show the date part only.
export const formatInvoiceDate = (value) => {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

// Today's date as YYYY-MM-DD for a native date input default.
export const todayInputDate = () => {
  const d = new Date()
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

export const INVOICE_COLUMNS = [
  { key: 'select',         label: '',            sortable: false },
  { key: 'code',           label: 'Code',        sortable: true  },
  { key: 'invoice_date',   label: 'Date',        sortable: true  },
  { key: 'party_name',     label: 'Supplier',    sortable: true  },
  { key: 'payment_method', label: 'Payment',     sortable: true  },
  { key: 'payment_status', label: 'Status',      sortable: true  },
  { key: 'subtotal',       label: 'Subtotal',    sortable: true  },
  { key: 'tax_total',      label: 'Tax',         sortable: true  },
  { key: 'grand_total',    label: 'Grand total', sortable: true  },
  { key: 'actions',        label: '',            sortable: false },
]

export const INVOICE_INITIAL_COL_WIDTHS = [40, 120, 120, 190, 110, 110, 120, 110, 130, 90]
