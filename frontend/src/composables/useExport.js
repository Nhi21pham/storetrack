import { ref, onBeforeUnmount } from 'vue'
import { rest } from '@/api'

const POLL_INTERVAL_MS = 1500
const POLL_TIMEOUT_MS  = 5 * 60 * 1000

const triggerBlobDownload = (blob, filename) => {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

// Drives the create -> poll -> download flow used by every Export button.
// `start` is the feature-specific call that kicks off an export and resolves
// with `{ id }`. `defaultFilename(exportId)` is used when the server doesn't
// supply one in the status payload.
export const useExport = ({ start, defaultFilename, onSuccess, onError }) => {
  const exporting = ref(false)
  let pollTimer = null
  let pollStartAt = 0

  const cancelPoll = () => {
    if (pollTimer) {
      clearTimeout(pollTimer)
      pollTimer = null
    }
  }

  onBeforeUnmount(cancelPoll)

  const downloadCompleted = async (exportId, filename) => {
    const blob = await rest('get', `/api/exports/${exportId}/download`, { responseType: 'blob' })
    triggerBlobDownload(blob, filename || defaultFilename(exportId))
  }

  const poll = async (exportId) => {
    try {
      const status = await rest('get', `/api/exports/${exportId}`)
      if (status.status === 'completed') {
        await downloadCompleted(exportId, status.filename)
        exporting.value = false
        onSuccess?.()
        return
      }
      if (status.status === 'failed') {
        exporting.value = false
        onError?.(status.error_message || 'Export failed. Please try again.')
        return
      }
      if (Date.now() - pollStartAt > POLL_TIMEOUT_MS) {
        exporting.value = false
        onError?.('Export is taking too long. Please try again later.')
        return
      }
      pollTimer = setTimeout(() => poll(exportId), POLL_INTERVAL_MS)
    } catch (err) {
      exporting.value = false
      onError?.(err.message)
    }
  }

  const run = async () => {
    if (exporting.value) return
    exporting.value = true
    pollStartAt = Date.now()
    try {
      const { id } = await start()
      poll(id)
    } catch (err) {
      exporting.value = false
      onError?.(err.message)
    }
  }

  return { exporting, run }
}
