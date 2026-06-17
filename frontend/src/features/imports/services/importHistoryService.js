import { rest } from '@/api'

// Paginated import history for a store, filtered to one entity type so each
// page's modal shows only its own imports.
export const fetchImportHistory = ({ storeId, type, page = 1, perPage = 20, startDate, endDate }) =>
  rest('get', `/api/imports/history/store/${storeId}`, {
    params: { type, page, per_page: perPage, start_date: startDate, end_date: endDate },
  })

// One import incl. per-row results (which rows were skipped/failed and why).
export const fetchImportDetail = ({ storeId, importId }) =>
  rest('get', `/api/imports/history/store/${storeId}/${importId}`)
