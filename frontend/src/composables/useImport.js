import { ref, onBeforeUnmount } from 'vue'
import { triggerBlobDownload } from '@/utils/download'
import { normalizeText } from '@/utils/textNormalizer'

const POLL_INTERVAL_MS = 1500
const POLL_TIMEOUT_MS = 30 * 60 * 1000

// Drives the import flow: select file -> review/edit -> create (background job) ->
// poll progress -> done. The entity-specific REST calls are passed in so the
// same composable powers every entity's import.
export const useImport = ({ templateFilename, downloadTemplate, preview, revalidate, start, status }) => {
  const phase = ref('select') // select | review | committing | done
  const busy = ref(false)
  const validating = ref(false) // a server re-check of edited rows is pending/in-flight
  const error = ref('')
  const rows = ref([])
  const requiredHeaders = ref([])
  const summary = ref(null)
  const progress = ref(null) // latest status record while committing / done
  const originalFilename = ref('')

  let pollTimer = null
  let pollStartAt = 0
  let revalTimer = null
  let revalToken = 0

  const cancelPoll = () => {
    if (pollTimer) {
      clearTimeout(pollTimer)
      pollTimer = null
    }
  }

  const cancelRevalidate = () => {
    if (revalTimer) {
      clearTimeout(revalTimer)
      revalTimer = null
    }
    revalToken++ // abandon any in-flight response
  }

  onBeforeUnmount(() => {
    cancelPoll()
    cancelRevalidate()
  })

  const reset = () => {
    cancelPoll()
    cancelRevalidate()
    phase.value = 'select'
    busy.value = false
    validating.value = false
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

  // Editing a cell can't be trusted to fix the row, so the status isn't guessed
  // client-side: the row goes "checking" (not creatable) and the server re-checks
  // it (debounced). The edited column's error is cleared for immediate feedback
  // while typing. Without a revalidate endpoint we fall back to an optimistic
  // clear (single-field importers, re-checked at commit either way).
  const editCell = (index, header, value) => {
    const row = rows.value[index]
    if (!row) return
    row.values = { ...row.values, [header]: value }
    if (row.errors && header in row.errors) {
      const { [header]: _cleared, ...rest } = row.errors
      row.errors = rest
    }
    row.warnings = []
    if (revalidate) {
      row.status = 'checking'
      scheduleRevalidate()
    } else if (!row.errors || Object.keys(row.errors).length === 0) {
      row.status = 'valid'
    }
  }

  const removeRow = (index) => {
    rows.value.splice(index, 1)
    // Removing a row can change another row's in-file duplicate status.
    scheduleRevalidate()
  }

  // Clear one column's error on every row whose value for that column now
  // resolves (e.g. a referenced bank was just created inline), then let the
  // server confirm. Optimistic clear keeps the UI responsive; revalidate is the
  // source of truth.
  const resolveReference = (column, resolvedValues) => {
    const wanted = new Set(resolvedValues.map((v) => normalizeText(String(v ?? ''))))
    for (const row of rows.value) {
      if (!row.errors || !row.errors[column]) continue
      if (!wanted.has(normalizeText(String(row.values[column] ?? '')))) continue
      const { [column]: _cleared, ...rest } = row.errors
      row.errors = rest
      if (Object.keys(rest).length === 0) row.status = revalidate ? 'checking' : 'valid'
    }
    scheduleRevalidate()
  }

  // Re-validate the current rows against the server (the only authority for
  // status), debounced ~400ms after the last edit so typing doesn't fire a
  // request per keystroke and there's no fixed-interval polling. A token guards
  // against an older response landing after a newer edit; `validating` keeps the
  // Create button disabled from the first edit until the re-check returns.
  const scheduleRevalidate = () => {
    if (!revalidate) return
    validating.value = true
    if (revalTimer) clearTimeout(revalTimer)
    revalTimer = setTimeout(runRevalidate, 400)
  }

  const runRevalidate = async () => {
    const token = ++revalToken
    try {
      const payload = rows.value.map((row) => ({ rowNumber: row.rowNumber, values: row.values }))
      const data = await revalidate(payload)
      if (token !== revalToken) return // a newer edit superseded this response
      const fresh = new Map((data.rows || []).map((r) => [r.rowNumber, r]))
      for (const row of rows.value) {
        const r = fresh.get(row.rowNumber)
        if (!r) continue
        row.errors = r.errors || {}
        row.warnings = r.warnings || []
        row.status = r.status
      }
      summary.value = data.summary || summary.value
    } catch (err) {
      error.value = err.message
    } finally {
      if (token === revalToken) validating.value = false
    }
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
    if (busy.value || validating.value || rows.value.length === 0 || hasInvalid()) return
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
    phase, busy, validating, error, rows, requiredHeaders, summary, progress,
    reset, getTemplate, runPreview, editCell, removeRow, resolveReference, hasInvalid, runCommit,
  }
}
