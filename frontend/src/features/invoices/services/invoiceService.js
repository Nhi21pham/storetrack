import { graphql, rest } from '@/api'

const INVOICE_LIST_FIELDS = `
  id store_id type code party_id party_name created_by description
  invoice_date payment_method payment_status subtotal tax_total grand_total paid_amount balance
  created_at updated_at
`

const INVOICE_DETAIL_FIELDS = `
  id store_id type code party_id party_name created_by description
  invoice_date payment_method payment_status subtotal tax_total grand_total paid_amount balance
  created_at updated_at
  items {
    id product_id product_name quantity unit_price subtotal tax_total grand_total
    cost_total
    taxes { id tax_id tax_name tax_rate tax_amount }
    costs { id inventory_batch_id quantity unit_cost batch { source_invoice_date } }
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

const CREATE_SALE_INVOICE_MUTATION = `
  mutation CreateSaleInvoice($store_id: ID!, $input: CreateSaleInvoiceInput!) {
    createSaleInvoice(store_id: $store_id, input: $input) { ${INVOICE_DETAIL_FIELDS} }
  }
`

const UPDATE_SALE_INVOICE_MUTATION = `
  mutation UpdateSaleInvoice($id: ID!, $input: CreateSaleInvoiceInput!) {
    updateSaleInvoice(id: $id, input: $input) { ${INVOICE_DETAIL_FIELDS} }
  }
`

const DELETE_INVOICE_MUTATION = `
  mutation DeleteInvoice($id: ID!) {
    deleteInvoice(id: $id)
  }
`

const INVOICES_FOR_PRODUCT_QUERY = `
  query InvoicesForProduct($product_id: ID!) {
    invoicesForProduct(product_id: $product_id) {
      id type code invoice_date
    }
  }
`

const PRODUCT_STOCKS_QUERY = `
  query ProductStocks($store_id: ID!) {
    productStocks(store_id: $store_id) { product_id quantity }
  }
`

const INVENTORY_BATCHES_QUERY = `
  query InventoryBatches($store_id: ID!) {
    inventoryBatches(store_id: $store_id) { id product_id unit_cost quantity_remaining received_at source_invoice_date }
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

export const fetchInvoicesForProduct = async ({ productId }) => {
  const data = await graphql(INVOICES_FOR_PRODUCT_QUERY, { product_id: productId })
  return data.invoicesForProduct
}

export const createPurchaseInvoice = async ({ storeId, input }) => {
  const data = await graphql(CREATE_PURCHASE_INVOICE_MUTATION, { store_id: storeId, input })
  return data.createPurchaseInvoice
}

export const updatePurchaseInvoice = async ({ id, input }) => {
  const data = await graphql(UPDATE_PURCHASE_INVOICE_MUTATION, { id, input })
  return data.updatePurchaseInvoice
}

export const createSaleInvoice = async ({ storeId, input }) => {
  const data = await graphql(CREATE_SALE_INVOICE_MUTATION, { store_id: storeId, input })
  return data.createSaleInvoice
}

export const updateSaleInvoice = async ({ id, input }) => {
  const data = await graphql(UPDATE_SALE_INVOICE_MUTATION, { id, input })
  return data.updateSaleInvoice
}

export const deleteInvoice = async ({ id }) => {
  await graphql(DELETE_INVOICE_MUTATION, { id })
}

export const fetchProductStocks = async ({ storeId }) => {
  const data = await graphql(PRODUCT_STOCKS_QUERY, { store_id: storeId })
  return data.productStocks
}

export const fetchInventoryBatches = async ({ storeId }) => {
  const data = await graphql(INVENTORY_BATCHES_QUERY, { store_id: storeId })
  return data.inventoryBatches
}

export const startInvoiceExport = ({ storeId, params }) =>
  rest('post', `/api/exports/invoices/${storeId}`, { params })

// Queues a per-invoice PDF document export (a zip of one PDF per invoice).
export const startInvoiceDocumentExport = ({ storeId, params }) =>
  rest('post', `/api/exports/invoice-documents/${storeId}`, { params })
