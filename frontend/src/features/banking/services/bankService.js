import { graphql, rest } from '@/api'

const BANK_FIELDS = `id business_id short_name full_name_vi full_name_en is_active created_at updated_at`

const BANKS_QUERY = `
  query Banks($business_id: ID!, $include_inactive: Boolean) {
    banks(business_id: $business_id, include_inactive: $include_inactive) { ${BANK_FIELDS} }
  }
`

const BANK_QUERY = `
  query Bank($id: ID!) {
    bank(id: $id) { ${BANK_FIELDS} }
  }
`

const SEARCH_BANKS_QUERY = `
  query SearchBanks($business_id: ID!, $q: String!, $include_inactive: Boolean, $limit: Int) {
    searchBanks(business_id: $business_id, q: $q, include_inactive: $include_inactive, limit: $limit) { ${BANK_FIELDS} }
  }
`

const CREATE_BANK_MUTATION = `
  mutation CreateBank($business_id: ID!, $input: CreateBankInput!) {
    createBank(business_id: $business_id, input: $input) { ${BANK_FIELDS} }
  }
`

const UPDATE_BANK_MUTATION = `
  mutation UpdateBank($id: ID!, $input: UpdateBankInput!) {
    updateBank(id: $id, input: $input) { ${BANK_FIELDS} }
  }
`

const DELETE_BANK_MUTATION = `
  mutation DeleteBank($id: ID!) {
    deleteBank(id: $id)
  }
`

export const fetchBanks = async ({ businessId, includeInactive = false }) => {
  const data = await graphql(BANKS_QUERY, { business_id: businessId, include_inactive: includeInactive })
  return data.banks
}

export const fetchBank = async ({ id }) => {
  const data = await graphql(BANK_QUERY, { id })
  return data.bank
}

export const searchBanks = async ({ businessId, q, includeInactive = false, limit = 10 }) => {
  const data = await graphql(SEARCH_BANKS_QUERY, { business_id: businessId, q, include_inactive: includeInactive, limit })
  return data.searchBanks
}

export const createBank = async ({ businessId, input }) => {
  const data = await graphql(CREATE_BANK_MUTATION, { business_id: businessId, input })
  return data.createBank
}

export const updateBank = async ({ id, input }) => {
  const data = await graphql(UPDATE_BANK_MUTATION, { id, input })
  return data.updateBank
}

export const deleteBank = async ({ id }) => {
  await graphql(DELETE_BANK_MUTATION, { id })
}

export const startBankExport = ({ businessId, params }) =>
  rest('post', `/api/exports/banks/${businessId}`, { params })

export const downloadBanksImportTemplate = ({ businessId }) =>
  rest('get', `/api/imports/banks/${businessId}/template`, { responseType: 'blob' })

export const previewBanksImport = ({ businessId, file }) => {
  const formData = new FormData()
  formData.append('file', file)
  return rest('post', `/api/imports/banks/${businessId}/preview`, { data: formData })
}

export const startBanksImport = ({ businessId, rows, originalFilename }) =>
  rest('post', `/api/imports/banks/${businessId}`, { data: { rows, original_filename: originalFilename } })
