import { graphql } from '@/api'

const DASHBOARD_FIELDS = `
  month
  total_sales total_sales_change
  total_profit total_profit_change
  products_in_stock products_in_stock_change
  outstanding outstanding_change
  sales_trend { label revenue }
  top_products { product_id product_name qty_sold revenue profit orders }
  store_performance { store_id store_name revenue profit }
`

const DASHBOARD_QUERY = `
  query Dashboard($store_id: ID!, $month: String) {
    dashboard(store_id: $store_id, month: $month) { ${DASHBOARD_FIELDS} }
  }
`

const DASHBOARD_BUSINESS_QUERY = `
  query DashboardByBusiness($business_id: ID!, $month: String) {
    dashboardByBusiness(business_id: $business_id, month: $month) { ${DASHBOARD_FIELDS} }
  }
`

export const fetchDashboard = async ({ storeId, month }) => {
  const data = await graphql(DASHBOARD_QUERY, { store_id: storeId, month: month || null })
  return data.dashboard
}

export const fetchDashboardByBusiness = async ({ businessId, month }) => {
  const data = await graphql(DASHBOARD_BUSINESS_QUERY, { business_id: businessId, month: month || null })
  return data.dashboardByBusiness
}
