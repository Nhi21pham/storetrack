import { graphql, rest } from '@/api'

const STOCK_REPORT_FIELDS = `
  id product_id product_name product_code
  tags { tag_id tag_name tag_value_id value }
  supplier_party_id supplier_name
  invoice_id invoice_code purchase_date
  quantity_received quantity_remaining unit_cost total_cost
`

const STOCK_REPORT_QUERY = `
  query StockReport($store_id: ID!) {
    stockReport(store_id: $store_id) { ${STOCK_REPORT_FIELDS} }
  }
`

export const fetchStockReport = async ({ storeId }) => {
  const data = await graphql(STOCK_REPORT_QUERY, { store_id: storeId })
  return data.stockReport
}

export const startStockReportExport = ({ storeId, params }) =>
  rest('post', `/api/exports/stock-report/${storeId}`, { params })

const SALE_REPORT_FIELDS = `
  id product_id product_name product_code
  tags { tag_id tag_name tag_value_id value }
  customer_party_id customer_name
  invoice_id invoice_code invoice_date
  quantity unit_price total_sale
`

const SALE_REPORT_QUERY = `
  query SaleReport($store_id: ID!) {
    saleReport(store_id: $store_id) { ${SALE_REPORT_FIELDS} }
  }
`

export const fetchSaleReport = async ({ storeId }) => {
  const data = await graphql(SALE_REPORT_QUERY, { store_id: storeId })
  return data.saleReport
}

export const startSaleReportExport = ({ storeId, params }) =>
  rest('post', `/api/exports/sale-report/${storeId}`, { params })
