import { graphql, rest } from '@/api'

const INVOICE_LIST_FIELDS = `
  id store_id type code party_id party_name created_by description
  invoice_date payment_method payment_status subtotal tax_total grand_total
  created_at updated_at
`

const INVOICE_DETAIL_FIELDS = `
  id store_id type code party_id party_name created_by description
  invoice_date payment_method payment_status subtotal tax_total grand_total
  created_at updated_at
  items {
    id product_id product_name quantity unit_price subtotal tax_total grand_total
    taxes { id tax_id tax_name tax_rate tax_amount }
  }
`

const INVOICES_QUERY = `
  query Invoices($store_id: ID!, $type: InvoiceType) {
    invoices(store_id: $store_id, type: $type) { ${INVOICE_LIST_FIELDS} }
  }
`

const INVOICE_QUERY = `
  query Invoice($id: ID!) {
    invoice(id: $id) { ${INVOICE_DETAIL_FIELDS} }
  }
`

const CREATE_PURCHASE_INVOICE_MUTATION = `
  mutation CreatePurchaseInvoice($store_id: ID!, $input: CreatePurchaseInvoiceInput!) {
    createPurchaseInvoice(store_id: $store_id, input: $input) { ${INVOICE_DETAIL_FIELDS} }
  }
`

const UPDATE_PURCHASE_INVOICE_MUTATION = `
  mutation UpdatePurchaseInvoice($id: ID!, $input: CreatePurchaseInvoiceInput!) {
    updatePurchaseInvoice(id: $id, input: $input) { ${INVOICE_DETAIL_FIELDS} }
  }
`

const DELETE_INVOICE_MUTATION = `
  mutation DeleteInvoice($id: ID!) {
    deleteInvoice(id: $id)
  }
`

export const fetchInvoices = async ({ storeId, type = null }) => {
  const data = await graphql(INVOICES_QUERY, { store_id: storeId, type })
  return data.invoices
}

export const fetchInvoice = async ({ id }) => {
  const data = await graphql(INVOICE_QUERY, { id })
  return data.invoice
}

export const createPurchaseInvoice = async ({ storeId, input }) => {
  const data = await graphql(CREATE_PURCHASE_INVOICE_MUTATION, { store_id: storeId, input })
  return data.createPurchaseInvoice
}

export const updatePurchaseInvoice = async ({ id, input }) => {
  const data = await graphql(UPDATE_PURCHASE_INVOICE_MUTATION, { id, input })
  return data.updatePurchaseInvoice
}

export const deleteInvoice = async ({ id }) => {
  await graphql(DELETE_INVOICE_MUTATION, { id })
}

export const startInvoiceExport = ({ storeId, params }) =>
  rest('post', `/api/exports/invoices/${storeId}`, { params })
