import { graphql, rest } from '@/api'

const STORE_AUDIT_LOGS_QUERY = `
  query AuditLogs($store_id: ID!, $page: Int, $per_page: Int, $start_date: String, $end_date: String, $object_type: String, $action: String, $search: String) {
    auditLogs(store_id: $store_id, page: $page, per_page: $per_page, start_date: $start_date, end_date: $end_date, object_type: $object_type, action: $action, search: $search) {
      data { id actor_name actor_email object_type action message created_at }
      total current_page last_page per_page
    }
  }
`

const BUSINESS_AUDIT_LOGS_QUERY = `
  query BusinessAuditLogs($business_id: ID!, $page: Int, $per_page: Int, $start_date: String, $end_date: String, $object_type: String, $action: String, $search: String) {
    businessAuditLogs(business_id: $business_id, page: $page, per_page: $per_page, start_date: $start_date, end_date: $end_date, object_type: $object_type, action: $action, search: $search) {
      data { id actor_name actor_email object_type action message store_name created_at }
      total current_page last_page per_page
    }
  }
`

export const fetchStoreAuditLogs = async (storeId, filters) => {
  const data = await graphql(STORE_AUDIT_LOGS_QUERY, { store_id: storeId, ...filters })
  return data.auditLogs
}

export const fetchBusinessAuditLogs = async (businessId, filters) => {
  const data = await graphql(BUSINESS_AUDIT_LOGS_QUERY, { business_id: businessId, ...filters })
  return data.businessAuditLogs
}

export const startStoreAuditExport = (storeId, params) =>
  rest('post', `/api/exports/audit-logs/store/${storeId}`, { params })

export const startBusinessAuditExport = (businessId, params) =>
  rest('post', `/api/exports/audit-logs/business/${businessId}`, { params })
