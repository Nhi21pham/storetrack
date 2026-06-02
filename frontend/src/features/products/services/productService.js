import { graphql } from '@/api'

const PRODUCT_FIELDS = `
  id store_id product_category_id unit_id code name is_active created_at updated_at
  unit { id name is_active }
  category { id code name is_active is_system }
  tags { tag_id tag_name tag_value_id value }
`

const PRODUCTS_BY_TAG_QUERY = `
  query ProductsByTag($store_id: ID!, $tag_id: ID!, $tag_value_id: ID, $include_inactive: Boolean) {
    productsByTag(store_id: $store_id, tag_id: $tag_id, tag_value_id: $tag_value_id, include_inactive: $include_inactive) {
      id code name is_active
    }
  }
`

const PRODUCTS_QUERY = `
  query Products($store_id: ID!, $include_inactive: Boolean) {
    products(store_id: $store_id, include_inactive: $include_inactive) { ${PRODUCT_FIELDS} }
  }
`

const PRODUCT_QUERY = `
  query Product($id: ID!) {
    product(id: $id) { ${PRODUCT_FIELDS} }
  }
`

const SEARCH_PRODUCTS_QUERY = `
  query SearchProducts($store_id: ID!, $q: String!, $include_inactive: Boolean, $limit: Int) {
    searchProducts(store_id: $store_id, q: $q, include_inactive: $include_inactive, limit: $limit) { ${PRODUCT_FIELDS} }
  }
`

const CREATE_PRODUCT_MUTATION = `
  mutation CreateProduct($store_id: ID!, $input: CreateProductInput!) {
    createProduct(store_id: $store_id, input: $input) { ${PRODUCT_FIELDS} }
  }
`

const UPDATE_PRODUCT_MUTATION = `
  mutation UpdateProduct($id: ID!, $input: UpdateProductInput!) {
    updateProduct(id: $id, input: $input) { ${PRODUCT_FIELDS} }
  }
`

const DELETE_PRODUCT_MUTATION = `
  mutation DeleteProduct($id: ID!) {
    deleteProduct(id: $id)
  }
`

export const fetchProducts = async ({ storeId, includeInactive = false }) => {
  const data = await graphql(PRODUCTS_QUERY, { store_id: storeId, include_inactive: includeInactive })
  return data.products
}

export const fetchProduct = async ({ id }) => {
  const data = await graphql(PRODUCT_QUERY, { id })
  return data.product
}

export const searchProducts = async ({ storeId, q, includeInactive = false, limit = 10 }) => {
  const data = await graphql(SEARCH_PRODUCTS_QUERY, { store_id: storeId, q, include_inactive: includeInactive, limit })
  return data.searchProducts
}

export const createProduct = async ({ storeId, input }) => {
  const data = await graphql(CREATE_PRODUCT_MUTATION, { store_id: storeId, input })
  return data.createProduct
}

export const updateProduct = async ({ id, input }) => {
  const data = await graphql(UPDATE_PRODUCT_MUTATION, { id, input })
  return data.updateProduct
}

export const deleteProduct = async ({ id }) => {
  await graphql(DELETE_PRODUCT_MUTATION, { id })
}

export const fetchProductsByTag = async ({ storeId, tagId, tagValueId = null, includeInactive = true }) => {
  const data = await graphql(PRODUCTS_BY_TAG_QUERY, {
    store_id: storeId,
    tag_id: tagId,
    tag_value_id: tagValueId,
    include_inactive: includeInactive,
  })
  return data.productsByTag
}
