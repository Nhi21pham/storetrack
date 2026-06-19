import { rest } from '@/api'

// Paginated import history for a scope, filtered to one entity type so each
// page's modal shows only its own imports. `scope` is 'store' for store-scoped
// entities (units, tags) or 'business' for business-scoped ones (banks).
export const fetchImportHistory = ({ scope = 'store', scopeId, type, page = 1, perPage = 20, startDate, endDate }) =>
  rest('get', `/api/imports/history/${scope}/${scopeId}`, {
    params: { type, page, per_page: perPage, start_date: startDate, end_date: endDate },
  })

// One import incl. per-row results (which rows were skipped/failed and why).
export const fetchImportDetail = ({ scope = 'store', scopeId, importId }) =>
  rest('get', `/api/imports/history/${scope}/${scopeId}/${importId}`)
