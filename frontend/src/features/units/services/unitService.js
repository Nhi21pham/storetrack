import { graphql } from '@/api'

const UNIT_FIELDS = `id business_id name is_active created_at updated_at`

const UNITS_QUERY = `
  query Units($business_id: ID!, $include_inactive: Boolean) {
    units(business_id: $business_id, include_inactive: $include_inactive) { ${UNIT_FIELDS} }
  }
`

const UNIT_QUERY = `
  query Unit($id: ID!) {
    unit(id: $id) { ${UNIT_FIELDS} }
  }
`

const SEARCH_UNITS_QUERY = `
  query SearchUnits($business_id: ID!, $q: String!, $include_inactive: Boolean, $limit: Int) {
    searchUnits(business_id: $business_id, q: $q, include_inactive: $include_inactive, limit: $limit) { ${UNIT_FIELDS} }
  }
`

const CREATE_UNIT_MUTATION = `
  mutation CreateUnit($business_id: ID!, $input: CreateUnitInput!) {
    createUnit(business_id: $business_id, input: $input) { ${UNIT_FIELDS} }
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

export const fetchUnits = async ({ businessId, includeInactive = false }) => {
  const data = await graphql(UNITS_QUERY, { business_id: businessId, include_inactive: includeInactive })
  return data.units
}

export const fetchUnit = async ({ id }) => {
  const data = await graphql(UNIT_QUERY, { id })
  return data.unit
}

export const searchUnits = async ({ businessId, q, includeInactive = false, limit = 10 }) => {
  const data = await graphql(SEARCH_UNITS_QUERY, { business_id: businessId, q, include_inactive: includeInactive, limit })
  return data.searchUnits
}

export const createUnit = async ({ businessId, input }) => {
  const data = await graphql(CREATE_UNIT_MUTATION, { business_id: businessId, input })
  return data.createUnit
}

export const updateUnit = async ({ id, input }) => {
  const data = await graphql(UPDATE_UNIT_MUTATION, { id, input })
  return data.updateUnit
}

export const deleteUnit = async ({ id }) => {
  await graphql(DELETE_UNIT_MUTATION, { id })
}
