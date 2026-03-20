import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost'
})

export const graphql = async (query, variables = {}) => {
  const response = await api.post('/graphql', { query, variables })
  
  if (response.data.errors) {
    const error = response.data.errors[0]
    
    // Handle validation errors
    if (error.extensions?.validation) {
      const messages = Object.values(error.extensions.validation).flat()
      throw new Error(messages.join('\n'))
    }
    
    throw new Error(error.message)
  }
  
  return response.data.data
}

export default api