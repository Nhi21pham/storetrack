import { t } from '@/i18n'

// Maps the export `type` strings stored on each Export record to an i18n key.
// Used by the export-history board so a row reads "Invoice PDF" rather than
// "invoice-documents". The store/business report variants share one label.
const EXPORT_TYPE_KEYS = {
  'invoices':                     'invoiceList',
  'invoice-documents':            'invoicePdf',
  'customers':                    'customers',
  'suppliers':                    'suppliers',
  'units':                        'units',
  'tags':                         'tags',
  'products':                     'products',
  'product-categories':           'categories',
  'banks':                        'banks',
  'bank-accounts':                'bankAccounts',
  'stock-report':                 'stockReport',
  'stock-report-business':        'stockReport',
  'sale-report':                  'saleReport',
  'sale-report-business':         'saleReport',
  'profit-report':                'profitReport',
  'profit-report-business':       'profitReport',
  'receivables-report':           'receivablesReport',
  'receivables-report-business':  'receivablesReport',
  'payables-report':              'payablesReport',
  'payables-report-business':     'payablesReport',
  'top-products-report':          'topProductsReport',
  'top-products-report-business': 'topProductsReport',
  'audit_log_store':              'auditLog',
  'audit_log_business':           'auditLog',
}

// Called inside templates, so calling t() here re-translates live on locale switch.
export const exportTypeLabel = (type) => {
  const key = EXPORT_TYPE_KEYS[type]
  if (key) return t('exportTypes.' + key)
  return String(type || '').replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

// File format taken from the filename extension, for a small chip on each row.
export const exportFormatLabel = (filename) => {
  const ext = String(filename || '').split('.').pop().toLowerCase()
  return ({ xlsx: 'Excel', pdf: 'PDF', zip: 'ZIP', csv: 'CSV' })[ext] || ''
}
