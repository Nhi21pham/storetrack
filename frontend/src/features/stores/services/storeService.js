import { graphql } from '@/api'

const ACCESSIBLE_STORES_QUERY = `
  query AccessibleStores {
    accessibleStores {
      id name address email phone is_active my_role
      business { id name }
    }
  }
`

const MY_BUSINESSES_QUERY = `query { myBusinesses { id name } }`

const CREATE_STORE_MUTATION = `
  mutation CreateStore($input: CreateStoreInput!) {
    createStore(input: $input) { id name address email phone is_active }
  }
`

const UPDATE_STORE_MUTATION = `
  mutation UpdateStore($id: ID!, $input: UpdateStoreInput!) {
    updateStore(id: $id, input: $input) { id name address email phone is_active }
  }
`

const DEACTIVATE_STORE_MUTATION = `
  mutation DeactivateStore($id: ID!) { deactivateStore(id: $id) { id is_active } }
`

const REACTIVATE_STORE_MUTATION = `
  mutation ReactivateStore($id: ID!) { reactivateStore(id: $id) { id is_active } }
`

const DELETE_STORE_MUTATION = `mutation ($id: ID!) { deleteStore(id: $id) }`

export const fetchAccessibleStores = async () => {
  const data = await graphql(ACCESSIBLE_STORES_QUERY)
  return data.accessibleStores
}

export const fetchMyBusinesses = async () => {
  const data = await graphql(MY_BUSINESSES_QUERY)
  return data.myBusinesses
}

export const createStore = async (input) => {
  const data = await graphql(CREATE_STORE_MUTATION, { input })
  return data.createStore
}

export const updateStore = async (id, input) => {
  const data = await graphql(UPDATE_STORE_MUTATION, { id, input })
  return data.updateStore
}

export const deactivateStore = async (id) => {
  const data = await graphql(DEACTIVATE_STORE_MUTATION, { id })
  return data.deactivateStore
}

export const reactivateStore = async (id) => {
  const data = await graphql(REACTIVATE_STORE_MUTATION, { id })
  return data.reactivateStore
}

export const deleteStore = async (id) => {
  await graphql(DELETE_STORE_MUTATION, { id })
}
