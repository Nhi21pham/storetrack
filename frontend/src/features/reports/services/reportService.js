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
