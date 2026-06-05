import { graphql, rest } from '@/api'

const TAG_VALUE_FIELDS = `
  id tag_id value created_at updated_at
`

const TAG_FIELDS = `
  id store_id name description created_at updated_at
  values { ${TAG_VALUE_FIELDS} }
`

const TAGS_QUERY = `
  query Tags($store_id: ID!) {
    tags(store_id: $store_id) { ${TAG_FIELDS} }
  }
`

const TAG_QUERY = `
  query Tag($id: ID!) {
    tag(id: $id) { ${TAG_FIELDS} }
  }
`

const SEARCH_TAGS_QUERY = `
  query SearchTags($store_id: ID!, $q: String!, $limit: Int) {
    searchTags(store_id: $store_id, q: $q, limit: $limit) { ${TAG_FIELDS} }
  }
`

const CREATE_TAG_MUTATION = `
  mutation CreateTag($store_id: ID!, $input: CreateTagInput!) {
    createTag(store_id: $store_id, input: $input) { ${TAG_FIELDS} }
  }
`

const UPDATE_TAG_MUTATION = `
  mutation UpdateTag($id: ID!, $input: UpdateTagInput!) {
    updateTag(id: $id, input: $input) { ${TAG_FIELDS} }
  }
`

const DELETE_TAG_MUTATION = `
  mutation DeleteTag($id: ID!) {
    deleteTag(id: $id)
  }
`

const CREATE_TAG_VALUE_MUTATION = `
  mutation CreateTagValue($tag_id: ID!, $input: CreateTagValueInput!) {
    createTagValue(tag_id: $tag_id, input: $input) { ${TAG_VALUE_FIELDS} }
  }
`

const ADD_TAG_VALUES_MUTATION = `
  mutation AddTagValues($tag_id: ID!, $input: AddTagValuesInput!) {
    addTagValues(tag_id: $tag_id, input: $input) { ${TAG_FIELDS} }
  }
`

const UPDATE_TAG_VALUE_MUTATION = `
  mutation UpdateTagValue($id: ID!, $input: UpdateTagValueInput!) {
    updateTagValue(id: $id, input: $input) { ${TAG_VALUE_FIELDS} }
  }
`

const DELETE_TAG_VALUE_MUTATION = `
  mutation DeleteTagValue($id: ID!) {
    deleteTagValue(id: $id)
  }
`

export const fetchTags = async ({ storeId }) => {
  const data = await graphql(TAGS_QUERY, { store_id: storeId })
  return data.tags
}

export const fetchTag = async ({ id }) => {
  const data = await graphql(TAG_QUERY, { id })
  return data.tag
}

export const searchTags = async ({ storeId, q, limit = 10 }) => {
  const data = await graphql(SEARCH_TAGS_QUERY, { store_id: storeId, q, limit })
  return data.searchTags
}

export const createTag = async ({ storeId, input }) => {
  const data = await graphql(CREATE_TAG_MUTATION, { store_id: storeId, input })
  return data.createTag
}

export const updateTag = async ({ id, input }) => {
  const data = await graphql(UPDATE_TAG_MUTATION, { id, input })
  return data.updateTag
}

export const deleteTag = async ({ id }) => {
  await graphql(DELETE_TAG_MUTATION, { id })
}

export const createTagValue = async ({ tagId, input }) => {
  const data = await graphql(CREATE_TAG_VALUE_MUTATION, { tag_id: tagId, input })
  return data.createTagValue
}

export const addTagValues = async ({ tagId, values }) => {
  const data = await graphql(ADD_TAG_VALUES_MUTATION, { tag_id: tagId, input: { values } })
  return data.addTagValues
}

export const updateTagValue = async ({ id, input }) => {
  const data = await graphql(UPDATE_TAG_VALUE_MUTATION, { id, input })
  return data.updateTagValue
}

export const deleteTagValue = async ({ id }) => {
  await graphql(DELETE_TAG_VALUE_MUTATION, { id })
}

export const startTagExport = ({ storeId, params }) =>
  rest('post', `/api/exports/tags/${storeId}`, { params })
