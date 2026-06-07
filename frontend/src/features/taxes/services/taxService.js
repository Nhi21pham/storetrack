import { graphql } from '@/api'

const TAX_FIELDS = `id store_id name description is_active is_system`

const TAXES_QUERY = `
  query Taxes($store_id: ID!, $include_inactive: Boolean) {
    taxes(store_id: $store_id, include_inactive: $include_inactive) { ${TAX_FIELDS} }
  }
`

export const fetchTaxes = async ({ storeId, includeInactive = false }) => {
  const data = await graphql(TAXES_QUERY, { store_id: storeId, include_inactive: includeInactive })
  return data.taxes
}
