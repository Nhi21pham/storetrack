import { graphql } from '@/api'

const PROVINCES_QUERY = `
  query Provinces {
    provinces { id code name_vi name_en }
  }
`

export const fetchProvinces = async () => {
  const data = await graphql(PROVINCES_QUERY)
  return data.provinces
}
