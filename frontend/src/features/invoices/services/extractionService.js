import { rest } from '@/api'

// Upload an invoice (PDF/photo) for extraction. Synchronous and write-nothing —
// returns a prefilled review payload with the counterparty, line items and VAT
// already matched against the store's records.
//
// `type` is the invoice kind ('purchase' | 'sale') and decides which printed
// party becomes the counterparty: a purchase matches the seller as the supplier,
// a sale matches the buyer as the customer.
//
// `provider` picks the reader: 'template' is the free, deterministic PDF parser
// (tried first); 'gemini' is the AI fallback. Defaults to the backend's choice.
export const extractInvoice = ({ storeId, file, provider, type }) => {
  const formData = new FormData()
  formData.append('file', file)
  if (provider) formData.append('provider', provider)
  if (type) formData.append('type', type)
  return rest('post', `/api/invoices/${storeId}/extract`, { data: formData })
}

export const extractPurchaseInvoice = ({ storeId, file, provider }) =>
  extractInvoice({ storeId, file, provider, type: 'purchase' })

export const extractSaleInvoice = ({ storeId, file, provider }) =>
  extractInvoice({ storeId, file, provider, type: 'sale' })
