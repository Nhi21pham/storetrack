import { graphql, rest } from '@/api'

const CUSTOMERS_QUERY = `
  query Customers($store_id: ID, $business_id: ID!) {
    customers(store_id: $store_id, business_id: $business_id) {
      id store_id name email phone address tax_code outstanding business_outstanding created_at updated_at
      party { id }
      stores { id name }
    }
  }
`

const CUSTOMER_QUERY = `
  query Customer($id: ID!) {
    customer(id: $id) {
      id store_id name email phone address tax_code created_at updated_at
      party { id }
      stores { id name }
    }
  }
`

const CREATE_CUSTOMER_MUTATION = `
  mutation CreateCustomer($store_id: ID!, $business_id: ID!, $input: CreateCustomerInput!) {
    createCustomer(store_id: $store_id, business_id: $business_id, input: $input) {
      id name tax_code address email phone party { id }
    }
  }
`

const UPDATE_CUSTOMER_MUTATION = `
  mutation UpdateCustomer($id: ID!, $store_id: ID!, $business_id: ID!, $input: UpdateCustomerInput!) {
    updateCustomer(id: $id, store_id: $store_id, business_id: $business_id, input: $input) {
      id name tax_code address email phone party { id }
    }
  }
`

const DELETE_CUSTOMER_MUTATION = `
  mutation DeleteCustomer($id: ID!, $business_id: ID!) {
    deleteCustomer(id: $id, business_id: $business_id)
  }
`

const DELETE_CUSTOMERS_MUTATION = `
  mutation DeleteCustomers($ids: [ID!]!, $business_id: ID!, $store_id: ID) {
    deleteCustomers(ids: $ids, business_id: $business_id, store_id: $store_id)
  }
`

export const fetchCustomers = async ({ storeId, businessId }) => {
  const data = await graphql(CUSTOMERS_QUERY, { store_id: storeId, business_id: businessId })
  return data.customers
}

export const fetchCustomer = async ({ id }) => {
  const data = await graphql(CUSTOMER_QUERY, { id })
  return data.customer
}

export const createCustomer = async ({ storeId, businessId, input }) => {
  const data = await graphql(CREATE_CUSTOMER_MUTATION, {
    store_id: storeId,
    business_id: businessId,
    input,
  })
  return data.createCustomer
}

export const updateCustomer = async ({ id, storeId, businessId, input }) => {
  const data = await graphql(UPDATE_CUSTOMER_MUTATION, {
    id,
    store_id: storeId,
    business_id: businessId,
    input,
  })
  return data.updateCustomer
}

export const deleteCustomer = async ({ id, businessId }) => {
  await graphql(DELETE_CUSTOMER_MUTATION, { id, business_id: businessId })
}

export const deleteCustomers = async ({ ids, businessId, storeId }) => {
  const data = await graphql(DELETE_CUSTOMERS_MUTATION, {
    ids,
    business_id: businessId,
    store_id: storeId ?? null,
  })
  return data.deleteCustomers ?? 0
}

export const startCustomerExport = ({ businessId, params }) =>
  rest('post', `/api/exports/customers/${businessId}`, { params })

export const downloadCustomersImportTemplate = ({ storeId }) =>
  rest('get', `/api/imports/customers/${storeId}/template`, { responseType: 'blob' })

export const previewCustomersImport = ({ storeId, file }) => {
  const formData = new FormData()
  formData.append('file', file)
  return rest('post', `/api/imports/customers/${storeId}/preview`, { data: formData })
}

export const revalidateCustomersImport = ({ storeId, rows }) =>
  rest('post', `/api/imports/customers/${storeId}/revalidate`, { data: { rows } })

export const startCustomersImport = ({ storeId, rows, originalFilename }) =>
  rest('post', `/api/imports/customers/${storeId}`, { data: { rows, original_filename: originalFilename } })
