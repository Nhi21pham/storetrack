import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost'
})

export const graphql = async (query, variables = {}) => {
  const token = localStorage.getItem('token')
  const headers = {}
  if (token) headers['Authorization'] = `Bearer ${token}`

  let response
  try {
    response = await api.post('/graphql', { query, variables }, { headers })
  } catch (err) {
    // Network error or server down
    if (!err.response) {
      throw new Error('Network error. Please check your connection.')
    }
    throw new Error('Server error. Please try again.')
  }

  if (response.data.errors) {
    const error = response.data.errors[0]

    // Session expired → auto-logout
    if (error.message === 'Unauthenticated.') {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('currentStoreId')
      localStorage.removeItem('currentBusinessId')
      window.location.href = '/login'
      throw new Error('Session expired. Please log in again.')
    }

    // Validation errors (Laravel format)
    if (error.extensions?.validation) {
      const messages = Object.values(error.extensions.validation).flat()
      throw new Error(messages.join('\n'))
    }

    throw new Error(error.message)
  }

  return response.data.data
}

export default api