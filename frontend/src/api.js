import axios from 'axios'
import { ErrorCode } from '@/utils/errorCodes'
import { AppError } from '@/utils/AppError'

const api = axios.create({
  baseURL: 'http://localhost'
})

const clearSession = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  localStorage.removeItem('currentStoreId')
  localStorage.removeItem('currentBusinessId')
  window.location.href = '/login'
}

export const graphql = async (query, variables = {}) => {
  const token = localStorage.getItem('token')
  const headers = {}
  if (token) headers['Authorization'] = `Bearer ${token}`

  let response
  try {
    response = await api.post('/graphql', { query, variables }, { headers })
  } catch (err) {
    if (!err.response) {
      throw new AppError(ErrorCode.NETWORK_ERROR, 'Network error. Please check your connection.')
    }

    const status = err.response.status

    if (status === 401) {
      clearSession()
      throw new AppError(ErrorCode.SESSION_EXPIRED, 'Session expired. Please log in again.', 401)
    }
    if (status === 404) {
      throw new AppError(ErrorCode.NOT_FOUND, 'The requested resource was not found.', 404)
    }
    if (status === 419) {
      clearSession()
      throw new AppError(ErrorCode.SESSION_EXPIRED, 'Session expired. Please refresh the page.', 419)
    }
    if (status === 422) {
      const messages = err.response.data?.errors
      if (messages) {
        throw new AppError(ErrorCode.VALIDATION_ERROR, Object.values(messages).flat().join('\n'), 422)
      }
    }
    if (status === 429) {
      throw new AppError(ErrorCode.RATE_LIMITED, 'Too many requests. Please wait a moment.', 429)
    }
    if (status >= 500) {
      throw new AppError(ErrorCode.SERVER_ERROR, 'Server error. Please try again later.', status)
    }

    throw new AppError(ErrorCode.SERVER_ERROR, 'Something went wrong. Please try again.')
  }

  if (response.data.errors) {
    const error = response.data.errors[0]

    // Unauthenticated from Sanctum middleware
    if (error.message === 'Unauthenticated.') {
      clearSession()
      throw new AppError(ErrorCode.SESSION_EXPIRED, 'Session expired. Please log in again.', 401)
    }

    // Laravel validation errors from @rules directive
    if (error.extensions?.validation) {
      const messages = Object.values(error.extensions.validation).flat()
      throw new AppError(ErrorCode.VALIDATION_ERROR, messages.join('\n'), 422)
    }

    // Structured error from SafeError (has code + statusCode in extensions)
    const extensions = error.extensions || {}
    const code = extensions.code || ErrorCode.SERVER_ERROR
    const statusCode = extensions.statusCode || null

    if (code === ErrorCode.SESSION_EXPIRED) {
      clearSession()
    }

    throw new AppError(code, error.message, statusCode)
  }

  return response.data.data
}

export default api