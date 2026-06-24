import axios from 'axios'
import { ErrorCode } from '@/utils/errorCodes'
import { AppError } from '@/utils/AppError'

const api = axios.create({
  baseURL: 'http://localhost'
})

// Stable per-browser id used by the backend to dedupe export requests
// without merging two devices (or two browsers) that share a login.
const getClientId = () => {
  let id = localStorage.getItem('client_id')
  if (!id) {
    id = (typeof crypto !== 'undefined' && crypto.randomUUID)
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2)}`
    localStorage.setItem('client_id', id)
  }
  return id
}

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

export const rest = async (method, url, { params, data, responseType, headers: extraHeaders } = {}) => {
  const token = localStorage.getItem('token')
  const headers = { ...(extraHeaders || {}) }
  if (token) headers['Authorization'] = `Bearer ${token}`
  headers['X-Client-Id'] = getClientId()
  // The active UI language, so request-triggered artifacts (Excel/PDF exports)
  // can be rendered in the user's language. Read straight from storage to keep
  // this layer dependency-light.
  headers['X-Locale'] = localStorage.getItem('locale') || 'vi'

  let response
  try {
    response = await api.request({ method, url, params, data, headers, responseType })
  } catch (err) {
    if (!err.response) {
      throw new AppError(ErrorCode.NETWORK_ERROR, 'Network error. Please check your connection.')
    }

    const status = err.response.status
    let body = err.response.data
    if (body instanceof Blob) {
      try { body = JSON.parse(await body.text()) } catch (e) { body = {} }
    }

    if (status === 401) {
      clearSession()
      throw new AppError(ErrorCode.SESSION_EXPIRED, 'Session expired. Please log in again.', 401)
    }
    if (status === 419) {
      clearSession()
      throw new AppError(ErrorCode.SESSION_EXPIRED, 'Session expired. Please refresh the page.', 419)
    }
    if (status === 429) {
      throw new AppError(ErrorCode.RATE_LIMITED, 'Too many requests. Please wait a moment.', 429)
    }
    if (status >= 500) {
      throw new AppError(ErrorCode.SERVER_ERROR, body?.message || 'Server error. Please try again later.', status)
    }

    throw new AppError(
      body?.code || ErrorCode.SERVER_ERROR,
      body?.message || 'Something went wrong. Please try again.',
      status,
    )
  }

  return response.data
}

export default api