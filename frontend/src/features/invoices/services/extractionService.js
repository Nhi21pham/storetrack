import { rest } from '@/api'

// Upload a purchase invoice (PDF/photo) for extraction. Synchronous and
// write-nothing — returns a prefilled review payload with the supplier, line
// items and VAT already matched against the store's records.
//
// `provider` picks the reader: 'template' is the free, deterministic PDF parser
// (tried first); 'gemini' is the AI fallback. Defaults to the backend's choice.
export const extractPurchaseInvoice = ({ storeId, file, provider }) => {
  const formData = new FormData()
  formData.append('file', file)
  if (provider) formData.append('provider', provider)
  return rest('post', `/api/invoices/${storeId}/extract`, { data: formData })
}
