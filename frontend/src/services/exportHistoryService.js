import { rest } from '@/api'

// Paginated export history for a scope, optionally narrowed to a set of export
// types so each page's board shows only its own exports. `scope` is 'store' or
// 'business'; `types` is an array (e.g. ['invoices', 'invoice-documents']).
// Informational only — past export files are not re-downloadable.
export const fetchExportHistory = ({ scope = 'store', scopeId, types, page = 1, perPage = 20, startDate, endDate }) =>
  rest('get', `/api/exports/history/${scope}/${scopeId}`, {
    params: { type: types, page, per_page: perPage, start_date: startDate, end_date: endDate },
  })
