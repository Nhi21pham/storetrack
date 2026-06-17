import { graphql, rest } from '@/api'

const UNIT_FIELDS = `id store_id name is_active created_at updated_at`

const UNITS_QUERY = `
  query Units($store_id: ID!, $include_inactive: Boolean) {
    units(store_id: $store_id, include_inactive: $include_inactive) { ${UNIT_FIELDS} }
  }
`

const UNIT_QUERY = `
  query Unit($id: ID!) {
    unit(id: $id) { ${UNIT_FIELDS} }
  }
`

const SEARCH_UNITS_QUERY = `
  query SearchUnits($store_id: ID!, $q: String!, $include_inactive: Boolean, $limit: Int) {
    searchUnits(store_id: $store_id, q: $q, include_inactive: $include_inactive, limit: $limit) { ${UNIT_FIELDS} }
  }
`

const CREATE_UNIT_MUTATION = `
  mutation CreateUnit($store_id: ID!, $input: CreateUnitInput!) {
    createUnit(store_id: $store_id, input: $input) { ${UNIT_FIELDS} }
  }
`

const UPDATE_UNIT_MUTATION = `
  mutation UpdateUnit($id: ID!, $input: UpdateUnitInput!) {
    updateUnit(id: $id, input: $input) { ${UNIT_FIELDS} }
  }
`

const DELETE_UNIT_MUTATION = `
  mutation DeleteUnit($id: ID!) {
    deleteUnit(id: $id)
  }
`

export const fetchUnits = async ({ storeId, includeInactive = false }) => {
  const data = await graphql(UNITS_QUERY, { store_id: storeId, include_inactive: includeInactive })
  return data.units
}

export const fetchUnit = async ({ id }) => {
  const data = await graphql(UNIT_QUERY, { id })
  return data.unit
}

export const searchUnits = async ({ storeId, q, includeInactive = false, limit = 10 }) => {
  const data = await graphql(SEARCH_UNITS_QUERY, { store_id: storeId, q, include_inactive: includeInactive, limit })
  return data.searchUnits
}

export const createUnit = async ({ storeId, input }) => {
  const data = await graphql(CREATE_UNIT_MUTATION, { store_id: storeId, input })
  return data.createUnit
}

export const updateUnit = async ({ id, input }) => {
  const data = await graphql(UPDATE_UNIT_MUTATION, { id, input })
  return data.updateUnit
}

export const deleteUnit = async ({ id }) => {
  await graphql(DELETE_UNIT_MUTATION, { id })
}

export const startUnitExport = ({ storeId, params }) =>
  rest('post', `/api/exports/units/${storeId}`, { params })

export const downloadUnitsImportTemplate = ({ storeId }) =>
  rest('get', `/api/imports/units/${storeId}/template`, { responseType: 'blob' })

export const previewUnitsImport = ({ storeId, file }) => {
  const formData = new FormData()
  formData.append('file', file)
  return rest('post', `/api/imports/units/${storeId}/preview`, { data: formData })
}

export const startUnitsImport = ({ storeId, rows, originalFilename }) =>
  rest('post', `/api/imports/units/${storeId}`, { data: { rows, original_filename: originalFilename } })
