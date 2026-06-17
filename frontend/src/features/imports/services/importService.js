import { rest } from '@/api'

// Poll a queued import's progress/result. Entity-agnostic — every importer's
// background job reports through the same endpoint.
export const fetchImportStatus = ({ importId }) =>
  rest('get', `/api/imports/${importId}`)
