import { t, activeDateLocale } from '@/i18n'

// Invoice type names (GraphQL enum names, sent/received as-is)
export const INVOICE_TYPE = {
  PURCHASE: 'PURCHASE',
  SALE: 'SALE',
}

// Selectable payment methods/statuses; labels resolved through i18n so the
// dropdown options follow the active language.
export const PAYMENT_METHOD_VALUES = ['CASH', 'CARD']
export const PAYMENT_STATUS_VALUES = ['UNPAID', 'PARTIAL', 'PAID']

export const paymentMethodOptions = () =>
  PAYMENT_METHOD_VALUES.map((value) => ({ value, label: paymentMethodLabel(value) }))

export const paymentStatusOptions = () =>
  PAYMENT_STATUS_VALUES.map((value) => ({ value, label: paymentStatusLabel(value) }))

export const paymentMethodLabel = (value) =>
  value ? t(`enums.paymentMethod.${value}`) : '—'

export const paymentStatusLabel = (value) =>
  value ? t(`enums.paymentStatus.${value}`) : '—'

// Money as VND: dot-grouped, no decimals, with the ₫ symbol (e.g. "5.400.000 ₫").
export const formatMoney = (value) =>
  Number(value || 0).toLocaleString('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  })

// Quantity trimmed of trailing zeros (10.000 -> "10", 1.500 -> "1.5").
export const formatQuantity = (value) => {
  const n = Number(value || 0)
  return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(3)))
}

// invoice_date arrives as a datetime; show the date part only, in the active UI locale.
export const formatInvoiceDate = (value) => {
  if (!value) return '—'
  return new Date(value).toLocaleDateString(activeDateLocale(), {
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

// Column set for an invoice list; the party column is labelled per type
// ("Supplier" for purchases, "Customer" for sales).
export const makeInvoiceColumns = (partyLabel) => [
  { key: 'select',         label: '',            sortable: false },
  { key: 'stt',            label: 'No.',         sortable: false },
  { key: 'code',           label: 'Code',        sortable: true  },
  { key: 'invoice_date',   label: 'Date',        sortable: true  },
  { key: 'party_name',     label: partyLabel,    sortable: true  },
  { key: 'payment_method', label: 'Payment',     sortable: true  },
  { key: 'payment_status', label: 'Status',      sortable: true  },
  { key: 'subtotal',       label: 'Subtotal',    sortable: true  },
  { key: 'tax_total',      label: 'Tax',         sortable: true  },
  { key: 'grand_total',    label: 'Grand total', sortable: true  },
  { key: 'actions',        label: '',            sortable: false },
]

export const INVOICE_COLUMNS = makeInvoiceColumns('Supplier')

export const INVOICE_INITIAL_COL_WIDTHS = [40, 60, 120, 120, 190, 110, 110, 120, 110, 130, 90]
