// Human labels for the export `type` strings stored on each Export record.
// Used by the export-history board so a row reads "Invoice PDF" rather than
// "invoice-documents". The store/business report variants share one label.
const EXPORT_TYPE_LABELS = {
  'invoices':                  'Invoice list',
  'invoice-documents':         'Invoice PDF',
  'customers':                 'Customers',
  'suppliers':                 'Suppliers',
  'units':                     'Units',
  'tags':                      'Tags',
  'products':                  'Products',
  'product-categories':        'Categories',
  'banks':                     'Banks',
  'bank-accounts':             'Bank accounts',
  'stock-report':              'Stock report',
  'stock-report-business':     'Stock report',
  'sale-report':               'Sale report',
  'sale-report-business':      'Sale report',
  'profit-report':             'Profit report',
  'profit-report-business':    'Profit report',
  'receivables-report':        'Receivables report',
  'receivables-report-business': 'Receivables report',
  'payables-report':           'Payables report',
  'payables-report-business':  'Payables report',
  'top-products-report':       'Top products report',
  'top-products-report-business': 'Top products report',
  'audit_log_store':           'Audit log',
  'audit_log_business':        'Audit log',
}

export const exportTypeLabel = (type) =>
  EXPORT_TYPE_LABELS[type] || String(type || '').replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())

// File format taken from the filename extension, for a small chip on each row.
export const exportFormatLabel = (filename) => {
  const ext = String(filename || '').split('.').pop().toLowerCase()
  return ({ xlsx: 'Excel', pdf: 'PDF', zip: 'ZIP', csv: 'CSV' })[ext] || ''
}
