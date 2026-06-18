import { graphql, rest } from '@/api'

const BANK_ACCOUNT_FIELDS = `
  id
  party_id
  bank_id
  province_id
  account_number
  account_holder_name
  branch
  bank { id short_name full_name_vi full_name_en is_active }
  province { id code name_vi name_en }
  party { id type display_name }
  created_at
  updated_at
`

const BANK_ACCOUNTS_FOR_PARTY_QUERY = `
  query BankAccountsForParty($party_id: ID!) {
    bankAccountsForParty(party_id: $party_id) { ${BANK_ACCOUNT_FIELDS} }
  }
`

const BANK_ACCOUNTS_QUERY = `
  query BankAccounts($business_id: ID!, $search: String) {
    bankAccounts(business_id: $business_id, search: $search) { ${BANK_ACCOUNT_FIELDS} }
  }
`

const BANK_ACCOUNT_QUERY = `
  query BankAccount($id: ID!) {
    bankAccount(id: $id) { ${BANK_ACCOUNT_FIELDS} }
  }
`

const CREATE_BANK_ACCOUNT_MUTATION = `
  mutation CreateBankAccount($party_id: ID!, $input: CreateBankAccountInput!) {
    createBankAccount(party_id: $party_id, input: $input) { ${BANK_ACCOUNT_FIELDS} }
  }
`

const UPDATE_BANK_ACCOUNT_MUTATION = `
  mutation UpdateBankAccount($id: ID!, $input: UpdateBankAccountInput!) {
    updateBankAccount(id: $id, input: $input) { ${BANK_ACCOUNT_FIELDS} }
  }
`

const DELETE_BANK_ACCOUNT_MUTATION = `
  mutation DeleteBankAccount($id: ID!) {
    deleteBankAccount(id: $id)
  }
`

export const fetchBankAccountsForParty = async ({ partyId }) => {
  const data = await graphql(BANK_ACCOUNTS_FOR_PARTY_QUERY, { party_id: partyId })
  return data.bankAccountsForParty
}

export const fetchBankAccounts = async ({ businessId, search = null }) => {
  const data = await graphql(BANK_ACCOUNTS_QUERY, { business_id: businessId, search })
  return data.bankAccounts
}

export const fetchBankAccount = async ({ id }) => {
  const data = await graphql(BANK_ACCOUNT_QUERY, { id })
  return data.bankAccount
}

export const createBankAccount = async ({ partyId, input }) => {
  const data = await graphql(CREATE_BANK_ACCOUNT_MUTATION, { party_id: partyId, input })
  return data.createBankAccount
}

export const updateBankAccount = async ({ id, input }) => {
  const data = await graphql(UPDATE_BANK_ACCOUNT_MUTATION, { id, input })
  return data.updateBankAccount
}

export const deleteBankAccount = async ({ id }) => {
  await graphql(DELETE_BANK_ACCOUNT_MUTATION, { id })
}

export const startBankAccountExport = ({ businessId, params }) =>
  rest('post', `/api/exports/bank-accounts/${businessId}`, { params })

export const downloadBankAccountsImportTemplate = ({ businessId }) =>
  rest('get', `/api/imports/bank-accounts/${businessId}/template`, { responseType: 'blob' })

export const previewBankAccountsImport = ({ businessId, file }) => {
  const formData = new FormData()
  formData.append('file', file)
  return rest('post', `/api/imports/bank-accounts/${businessId}/preview`, { data: formData })
}

export const revalidateBankAccountsImport = ({ businessId, rows }) =>
  rest('post', `/api/imports/bank-accounts/${businessId}/revalidate`, { data: { rows } })

export const startBankAccountsImport = ({ businessId, rows, originalFilename }) =>
  rest('post', `/api/imports/bank-accounts/${businessId}`, { data: { rows, original_filename: originalFilename } })
