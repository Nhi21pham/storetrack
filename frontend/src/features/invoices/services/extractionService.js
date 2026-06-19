import { rest } from '@/api'

// Upload a purchase invoice (PDF/photo) for AI extraction. Synchronous and
// write-nothing — returns a prefilled review payload with the supplier, line
// items and VAT already matched against the store's records.
export const extractPurchaseInvoice = ({ storeId, file }) => {
  const formData = new FormData()
  formData.append('file', file)
  return rest('post', `/api/invoices/${storeId}/extract`, { data: formData })
}
