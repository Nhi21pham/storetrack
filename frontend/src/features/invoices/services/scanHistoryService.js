import { rest } from '@/api'

// Paginated scan history for a store, filtered by invoice kind (type: 'purchase'
// | 'sale') and optionally by engine (scan_type: 'ai' | 'template'), status and a
// created-at date range.
export const fetchScanHistory = ({ storeId, type, scanType, status, page = 1, perPage = 20, startDate, endDate }) =>
  rest('get', `/api/invoices/${storeId}/scans`, {
    params: { type, scan_type: scanType, status, page, per_page: perPage, start_date: startDate, end_date: endDate },
  })

// One scan incl. the full read summary (supplier, items, warnings, totals) and
// any entities created from the review step.
export const fetchScanDetail = ({ storeId, scanId }) =>
  rest('get', `/api/invoices/${storeId}/scans/${scanId}`)

// Attribute an entity created during review (supplier / product / unit) back to
// its scan, so the history shows what the scan led to.
export const recordScanEntity = ({ storeId, scanId, type, id, name }) =>
  rest('post', `/api/invoices/${storeId}/scans/${scanId}/entities`, {
    data: { type, id, name },
  })
