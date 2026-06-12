import { graphql, rest } from '@/api'

const STOCK_REPORT_FIELDS = `
  id store_id store_name product_id product_name product_code
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

const STOCK_REPORT_BUSINESS_QUERY = `
  query StockReportByBusiness($business_id: ID!) {
    stockReportByBusiness(business_id: $business_id) { ${STOCK_REPORT_FIELDS} }
  }
`

export const fetchStockReport = async ({ storeId }) => {
  const data = await graphql(STOCK_REPORT_QUERY, { store_id: storeId })
  return data.stockReport
}

export const fetchStockReportByBusiness = async ({ businessId }) => {
  const data = await graphql(STOCK_REPORT_BUSINESS_QUERY, { business_id: businessId })
  return data.stockReportByBusiness
}

export const startStockReportExport = ({ storeId, params }) =>
  rest('post', `/api/exports/stock-report/${storeId}`, { params })

export const startStockReportBusinessExport = ({ businessId, params }) =>
  rest('post', `/api/exports/stock-report/business/${businessId}`, { params })

const SALE_REPORT_FIELDS = `
  id store_id store_name product_id product_name product_code
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

const SALE_REPORT_BUSINESS_QUERY = `
  query SaleReportByBusiness($business_id: ID!) {
    saleReportByBusiness(business_id: $business_id) { ${SALE_REPORT_FIELDS} }
  }
`

export const fetchSaleReport = async ({ storeId }) => {
  const data = await graphql(SALE_REPORT_QUERY, { store_id: storeId })
  return data.saleReport
}

export const fetchSaleReportByBusiness = async ({ businessId }) => {
  const data = await graphql(SALE_REPORT_BUSINESS_QUERY, { business_id: businessId })
  return data.saleReportByBusiness
}

export const startSaleReportExport = ({ storeId, params }) =>
  rest('post', `/api/exports/sale-report/${storeId}`, { params })

export const startSaleReportBusinessExport = ({ businessId, params }) =>
  rest('post', `/api/exports/sale-report/business/${businessId}`, { params })
