import { graphql, rest } from '@/api'

const CATEGORY_FIELDS = `
  id store_id code name description is_active is_system last_sequence created_at updated_at
`

const CATEGORIES_QUERY = `
  query ProductCategories($store_id: ID!, $include_inactive: Boolean) {
    productCategories(store_id: $store_id, include_inactive: $include_inactive) { ${CATEGORY_FIELDS} }
  }
`

const CATEGORY_QUERY = `
  query ProductCategory($id: ID!) {
    productCategory(id: $id) { ${CATEGORY_FIELDS} }
  }
`

const SEARCH_CATEGORIES_QUERY = `
  query SearchProductCategories($store_id: ID!, $q: String!, $include_inactive: Boolean, $limit: Int) {
    searchProductCategories(store_id: $store_id, q: $q, include_inactive: $include_inactive, limit: $limit) { ${CATEGORY_FIELDS} }
  }
`

const CREATE_CATEGORY_MUTATION = `
  mutation CreateProductCategory($store_id: ID!, $input: CreateProductCategoryInput!) {
    createProductCategory(store_id: $store_id, input: $input) { ${CATEGORY_FIELDS} }
  }
`

const UPDATE_CATEGORY_MUTATION = `
  mutation UpdateProductCategory($id: ID!, $input: UpdateProductCategoryInput!) {
    updateProductCategory(id: $id, input: $input) { ${CATEGORY_FIELDS} }
  }
`

const DELETE_CATEGORY_MUTATION = `
  mutation DeleteProductCategory($id: ID!) {
    deleteProductCategory(id: $id)
  }
`

export const fetchProductCategories = async ({ storeId, includeInactive = false }) => {
  const data = await graphql(CATEGORIES_QUERY, { store_id: storeId, include_inactive: includeInactive })
  return data.productCategories
}

export const fetchProductCategory = async ({ id }) => {
  const data = await graphql(CATEGORY_QUERY, { id })
  return data.productCategory
}

export const searchProductCategories = async ({ storeId, q, includeInactive = false, limit = 10 }) => {
  const data = await graphql(SEARCH_CATEGORIES_QUERY, { store_id: storeId, q, include_inactive: includeInactive, limit })
  return data.searchProductCategories
}

export const createProductCategory = async ({ storeId, input }) => {
  const data = await graphql(CREATE_CATEGORY_MUTATION, { store_id: storeId, input })
  return data.createProductCategory
}

export const updateProductCategory = async ({ id, input }) => {
  const data = await graphql(UPDATE_CATEGORY_MUTATION, { id, input })
  return data.updateProductCategory
}

export const deleteProductCategory = async ({ id }) => {
  await graphql(DELETE_CATEGORY_MUTATION, { id })
}

export const startProductCategoryExport = ({ storeId, params }) =>
  rest('post', `/api/exports/product-categories/${storeId}`, { params })
