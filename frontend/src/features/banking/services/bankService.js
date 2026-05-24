import { graphql } from '@/api'

const BANK_FIELDS = `id short_name full_name_vi full_name_en is_active created_at updated_at`

const BANKS_QUERY = `
  query Banks($include_inactive: Boolean) {
    banks(include_inactive: $include_inactive) { ${BANK_FIELDS} }
  }
`

const BANK_QUERY = `
  query Bank($id: ID!) {
    bank(id: $id) { ${BANK_FIELDS} }
  }
`

const SEARCH_BANKS_QUERY = `
  query SearchBanks($q: String!, $include_inactive: Boolean, $limit: Int) {
    searchBanks(q: $q, include_inactive: $include_inactive, limit: $limit) { ${BANK_FIELDS} }
  }
`

const CREATE_BANK_MUTATION = `
  mutation CreateBank($input: CreateBankInput!) {
    createBank(input: $input) { ${BANK_FIELDS} }
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

export const fetchBanks = async ({ includeInactive = false } = {}) => {
  const data = await graphql(BANKS_QUERY, { include_inactive: includeInactive })
  return data.banks
}

export const fetchBank = async ({ id }) => {
  const data = await graphql(BANK_QUERY, { id })
  return data.bank
}

export const searchBanks = async ({ q, includeInactive = false, limit = 10 }) => {
  const data = await graphql(SEARCH_BANKS_QUERY, { q, include_inactive: includeInactive, limit })
  return data.searchBanks
}

export const createBank = async ({ input }) => {
  const data = await graphql(CREATE_BANK_MUTATION, { input })
  return data.createBank
}

export const updateBank = async ({ id, input }) => {
  const data = await graphql(UPDATE_BANK_MUTATION, { id, input })
  return data.updateBank
}

export const deleteBank = async ({ id }) => {
  await graphql(DELETE_BANK_MUTATION, { id })
}
