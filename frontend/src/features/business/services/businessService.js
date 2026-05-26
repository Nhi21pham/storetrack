import { graphql } from '@/api'

const ACCESSIBLE_BUSINESSES_QUERY = `
  query {
    accessibleBusinesses {
      id name tax_code address email phone role
      party_id
      stores { id name is_active my_role }
    }
  }
`

const CREATE_BUSINESS_MUTATION = `
  mutation CreateBusiness($input: CreateBusinessInput!) {
    createBusiness(input: $input) { id name tax_code address email phone party { id } }
  }
`

const UPDATE_BUSINESS_MUTATION = `
  mutation UpdateBusiness($id: ID!, $input: UpdateBusinessInput!) {
    updateBusiness(id: $id, input: $input) { id name tax_code address email phone party { id } }
  }
`

const DELETE_BUSINESS_MUTATION = `
  mutation DeleteBusiness($id: ID!) { deleteBusiness(id: $id) }
`

export const fetchAccessibleBusinesses = async () => {
  const data = await graphql(ACCESSIBLE_BUSINESSES_QUERY)
  return data.accessibleBusinesses
}

export const createBusiness = async (input) => {
  const data = await graphql(CREATE_BUSINESS_MUTATION, { input })
  return data.createBusiness
}

export const updateBusiness = async (id, input) => {
  const data = await graphql(UPDATE_BUSINESS_MUTATION, { id, input })
  return data.updateBusiness
}

export const deleteBusiness = async (id) => {
  await graphql(DELETE_BUSINESS_MUTATION, { id })
}
