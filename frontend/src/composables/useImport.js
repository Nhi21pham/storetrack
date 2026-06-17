import { ref, onBeforeUnmount } from 'vue'
import { triggerBlobDownload } from '@/utils/download'

const POLL_INTERVAL_MS = 1500
const POLL_TIMEOUT_MS = 30 * 60 * 1000

// Drives the import flow: select file -> review/edit -> create (background job) ->
// poll progress -> done. The entity-specific REST calls are passed in so the
// same composable powers every entity's import.
export const useImport = ({ templateFilename, downloadTemplate, preview, start, status }) => {
  const phase = ref('select') // select | review | committing | done
  const busy = ref(false)
  const error = ref('')
  const rows = ref([])
  const requiredHeaders = ref([])
  const summary = ref(null)
  const progress = ref(null) // latest status record while committing / done
  const originalFilename = ref('')

  let pollTimer = null
  let pollStartAt = 0

  const cancelPoll = () => {
    if (pollTimer) {
      clearTimeout(pollTimer)
      pollTimer = null
    }
  }

  onBeforeUnmount(cancelPoll)

  const reset = () => {
    cancelPoll()
    phase.value = 'select'
    busy.value = false
    error.value = ''
    rows.value = []
    requiredHeaders.value = []
    summary.value = null
    progress.value = null
    originalFilename.value = ''
  }

  const getTemplate = async () => {
    error.value = ''
    try {
      const blob = await downloadTemplate()
      triggerBlobDownload(blob, templateFilename)
    } catch (err) {
      error.value = err.message
    }
  }

  const runPreview = async (file) => {
    if (!file || busy.value) return
    busy.value = true
    error.value = ''
    originalFilename.value = file.name || ''
    try {
      const data = await preview(file)
      rows.value = (data.rows || []).map((row) => ({ ...row }))
      requiredHeaders.value = data.requiredHeaders || []
      summary.value = data.summary || null
      phase.value = 'review'
    } catch (err) {
      error.value = err.message
    } finally {
      busy.value = false
    }
  }

  // Optimistically clear a row's error/warning on edit; the server re-validates
  // at commit (warnings are a preview-only hint, so a stale one is dropped here).
  const editCell = (index, header, value) => {
    const row = rows.value[index]
    if (!row) return
    row.values = { ...row.values, [header]: value }
    row.errors = {}
    row.warnings = []
    row.status = 'valid'
  }

  const removeRow = (index) => {
    rows.value.splice(index, 1)
  }

  const hasInvalid = () => rows.value.some((row) => row.status === 'invalid')

  const poll = async (id) => {
    try {
      const record = await status(id)
      progress.value = record
      if (record.status === 'completed') {
        phase.value = 'done'
        busy.value = false
        return
      }
      if (record.status === 'failed') {
        error.value = record.error_message || 'Import failed.'
        phase.value = 'done'
        busy.value = false
        return
      }
      if (Date.now() - pollStartAt > POLL_TIMEOUT_MS) {
        error.value = 'Import is taking too long. Check the import history later.'
        busy.value = false
        return
      }
      pollTimer = setTimeout(() => poll(id), POLL_INTERVAL_MS)
    } catch (err) {
      error.value = err.message
      busy.value = false
    }
  }

  const runCommit = async () => {
    if (busy.value || rows.value.length === 0 || hasInvalid()) return
    busy.value = true
    error.value = ''
    phase.value = 'committing'
    pollStartAt = Date.now()
    try {
      const payload = rows.value.map((row) => ({ rowNumber: row.rowNumber, values: row.values }))
      const { id } = await start(payload, originalFilename.value)
      poll(id)
    } catch (err) {
      error.value = err.message
      busy.value = false
      phase.value = 'review'
    }
  }

  return {
    phase, busy, error, rows, requiredHeaders, summary, progress,
    reset, getTemplate, runPreview, editCell, removeRow, hasInvalid, runCommit,
  }
}
