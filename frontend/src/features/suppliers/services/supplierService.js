import { graphql, rest } from '@/api'

const SUPPLIERS_QUERY = `
  query Suppliers($store_id: ID, $business_id: ID!) {
    suppliers(store_id: $store_id, business_id: $business_id) {
      id store_id name email phone address tax_code outstanding business_outstanding created_at
      party { id }
      stores { id name }
    }
  }
`

const CREATE_SUPPLIER_MUTATION = `
  mutation CreateSupplier($store_id: ID!, $business_id: ID!, $input: CreateSupplierInput!) {
    createSupplier(store_id: $store_id, business_id: $business_id, input: $input) {
      id name tax_code address email phone party { id }
    }
  }
`

const UPDATE_SUPPLIER_MUTATION = `
  mutation UpdateSupplier($id: ID!, $store_id: ID!, $business_id: ID!, $input: UpdateSupplierInput!) {
    updateSupplier(id: $id, store_id: $store_id, business_id: $business_id, input: $input) {
      id name tax_code address email phone party { id }
    }
  }
`

const DELETE_SUPPLIER_MUTATION = `
  mutation DeleteSupplier($id: ID!, $business_id: ID!) {
    deleteSupplier(id: $id, business_id: $business_id)
  }
`

const DELETE_SUPPLIERS_MUTATION = `
  mutation DeleteSuppliers($ids: [ID!]!, $business_id: ID!, $store_id: ID) {
    deleteSuppliers(ids: $ids, business_id: $business_id, store_id: $store_id)
  }
`

export const fetchSuppliers = async ({ storeId, businessId }) => {
  const data = await graphql(SUPPLIERS_QUERY, { store_id: storeId, business_id: businessId })
  return data.suppliers
}

export const createSupplier = async ({ storeId, businessId, input }) => {
  const data = await graphql(CREATE_SUPPLIER_MUTATION, {
    store_id: storeId,
    business_id: businessId,
    input,
  })
  return data.createSupplier
}

export const updateSupplier = async ({ id, storeId, businessId, input }) => {
  const data = await graphql(UPDATE_SUPPLIER_MUTATION, {
    id,
    store_id: storeId,
    business_id: businessId,
    input,
  })
  return data.updateSupplier
}

export const deleteSupplier = async ({ id, businessId }) => {
  await graphql(DELETE_SUPPLIER_MUTATION, { id, business_id: businessId })
}

export const deleteSuppliers = async ({ ids, businessId, storeId }) => {
  const data = await graphql(DELETE_SUPPLIERS_MUTATION, {
    ids,
    business_id: businessId,
    store_id: storeId ?? null,
  })
  return data.deleteSuppliers ?? 0
}

export const startSupplierExport = ({ businessId, params }) =>
  rest('post', `/api/exports/suppliers/${businessId}`, { params })

export const downloadSuppliersImportTemplate = ({ storeId }) =>
  rest('get', `/api/imports/suppliers/${storeId}/template`, { responseType: 'blob' })

export const previewSuppliersImport = ({ storeId, file }) => {
  const formData = new FormData()
  formData.append('file', file)
  return rest('post', `/api/imports/suppliers/${storeId}/preview`, { data: formData })
}

export const revalidateSuppliersImport = ({ storeId, rows }) =>
  rest('post', `/api/imports/suppliers/${storeId}/revalidate`, { data: { rows } })

export const startSuppliersImport = ({ storeId, rows, originalFilename }) =>
  rest('post', `/api/imports/suppliers/${storeId}`, { data: { rows, original_filename: originalFilename } })
