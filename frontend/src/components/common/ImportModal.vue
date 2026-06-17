<template>
  <div class="modal-overlay" @click.self="requestClose">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ title }}</h2>
        <button class="close-btn" @click="requestClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <!-- SELECT -->
      <div v-if="im.phase.value === 'select'" class="modal-body">
        <div class="instructions">
          <p class="lead">Upload an Excel (.xlsx, .xls) or .csv file. Required columns are marked with <strong>*</strong>:</p>
          <ul class="col-list">
            <li v-for="h in requiredHeaders" :key="h"><span class="req">{{ h }} *</span></li>
            <li v-for="h in optionalHeaders" :key="h"><span class="opt">{{ h }}</span></li>
          </ul>
          <p class="hint">Header names must match exactly. Extra columns are ignored. Not sure of the format? Download the template below.</p>

          <ul v-if="instructions.length" class="format-notes">
            <li v-for="(note, i) in instructions" :key="i">{{ note }}</li>
          </ul>
        </div>

        <div class="select-actions">
          <button class="btn-secondary" @click="im.getTemplate()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download template
          </button>

          <label class="btn-primary file-label" :class="{ disabled: im.busy.value }">
            <span v-if="im.busy.value" class="spinner"></span>
            {{ im.busy.value ? 'Reading file...' : 'Choose file' }}
            <input type="file" accept=".xlsx,.xls,.csv" :disabled="im.busy.value" @change="onFileChange" />
          </label>
        </div>

        <div v-if="im.error.value" class="api-error">{{ im.error.value }}</div>
      </div>

      <!-- REVIEW -->
      <div v-else-if="im.phase.value === 'review'" class="modal-body review">
        <div class="summary-bar">
          <span class="chip ok">{{ liveCounts.valid }} to create</span>
          <span class="chip warn">{{ liveCounts.duplicate }} will skip</span>
          <span class="chip bad">{{ liveCounts.invalid }} need fixing</span>
        </div>

        <div class="table-scroll">
          <table class="review-table">
            <thead>
              <tr>
                <th class="row-num">#</th>
                <th v-for="col in columns" :key="col">{{ col }}</th>
                <th class="status-col">Status</th>
                <th class="actions-col"></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(row, i) in pagedRows" :key="row.rowNumber">
                <tr :class="rowClass(row)">
                  <td class="row-num">{{ row.rowNumber }}</td>
                  <td v-for="col in columns" :key="col">
                    <input
                      class="cell-input"
                      :class="{ error: row.errors && row.errors[col] }"
                      :value="row.values[col]"
                      @input="im.editCell(absoluteIndex(i), col, $event.target.value)"
                    />
                    <span v-if="row.errors && row.errors[col]" class="cell-error">{{ row.errors[col] }}</span>
                  </td>
                  <td class="status-col">
                    <span class="badge" :class="statusInfo(row.status).cls">{{ statusInfo(row.status).label }}</span>
                  </td>
                  <td class="actions-col">
                    <button class="icon-btn" title="Remove row" @click="im.removeRow(absoluteIndex(i))">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </td>
                </tr>
                <tr v-if="row.warnings && row.warnings.length" class="warn-row">
                  <td></td>
                  <td :colspan="columns.length + 2">
                    <p v-for="(w, wi) in row.warnings" :key="wi" class="warn-line">⚠ {{ w }}</p>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div v-if="totalPages > 1" class="pager">
          <button class="btn-secondary sm" :disabled="page === 1" @click="page--">Prev</button>
          <span>Page {{ page }} / {{ totalPages }}</span>
          <button class="btn-secondary sm" :disabled="page === totalPages" @click="page++">Next</button>
        </div>

        <div v-if="im.error.value" class="api-error">{{ im.error.value }}</div>
      </div>

      <!-- COMMITTING -->
      <div v-else-if="im.phase.value === 'committing'" class="modal-body committing">
        <p class="lead">Creating records…</p>
        <div class="progress-track"><div class="progress-fill" :style="{ width: percent + '%' }"></div></div>
        <p class="progress-text">{{ processed }} of {{ total }} processed</p>
        <div class="summary-bar">
          <span class="chip ok">{{ created }} created</span>
          <span class="chip warn">{{ skipped }} skipped</span>
          <span class="chip bad">{{ failed }} failed</span>
        </div>
      </div>

      <!-- DONE -->
      <div v-else class="modal-body done">
        <div v-if="im.error.value" class="api-error">{{ im.error.value }}</div>
        <p v-else class="lead">Import finished.</p>
        <div class="summary-bar">
          <span class="chip ok">{{ created }} created</span>
          <span class="chip warn">{{ skipped }} skipped</span>
          <span class="chip bad">{{ failed }} failed</span>
        </div>
        <div v-if="problems.length" class="problems">
          <p class="hint">Rows that were skipped or failed:</p>
          <ul>
            <li v-for="p in problems" :key="p.rowNumber">
              <span class="badge" :class="statusInfo(p.status).cls">Row {{ p.rowNumber }}</span>
              <span v-if="formatValues(p.values)" class="row-data">{{ formatValues(p.values) }}</span>
              <span class="row-msg">— {{ p.message }}</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="modal-footer">
        <template v-if="im.phase.value === 'review'">
          <button class="btn-cancel" @click="im.reset()">Back</button>
          <button class="btn-submit" :disabled="!canCommit" @click="im.runCommit()">
            Create {{ liveCounts.valid }} record(s)
          </button>
        </template>
        <template v-else-if="im.phase.value === 'done'">
          <button class="btn-submit" @click="$emit('close')">Done</button>
        </template>
        <template v-else-if="im.phase.value === 'select'">
          <button class="btn-cancel" @click="requestClose">Cancel</button>
        </template>
      </div>
    </div>
  </div>

  <ConfirmDialog
    v-if="showUnsavedWarning"
    title="Discard import?"
    message="You've loaded a file but haven't imported it yet. Closing now will discard the reviewed rows."
    confirm-text="Yes, discard"
    cancel-text="Keep reviewing"
    type="warning"
    @confirm="discardAndClose"
    @cancel="showUnsavedWarning = false"
  />
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import { useImport } from '@/composables/useImport'

const props = defineProps({
  title:            { type: String, required: true },
  templateFilename: { type: String, required: true },
  requiredHeaders:  { type: Array,  default: () => [] },
  optionalHeaders:  { type: Array,  default: () => [] },
  instructions:     { type: Array,  default: () => [] },
  downloadTemplate: { type: Function, required: true },
  preview:          { type: Function, required: true },
  start:            { type: Function, required: true },
  status:           { type: Function, required: true },
})

const emit = defineEmits(['close', 'imported'])

const PER_PAGE = 50

const im = useImport({
  templateFilename: props.templateFilename,
  downloadTemplate: () => props.downloadTemplate(),
  preview: (file) => props.preview(file),
  start: (rows, originalFilename) => props.start(rows, originalFilename),
  status: (id) => props.status(id),
})

const showUnsavedWarning = ref(false)

// Confirm before closing only when a file is loaded but not yet imported
// (review phase) — otherwise close straight away.
const requestClose = () => {
  if (im.phase.value === 'review' && im.rows.value.length > 0) {
    showUnsavedWarning.value = true
  } else {
    emit('close')
  }
}

const discardAndClose = () => {
  showUnsavedWarning.value = false
  emit('close')
}

const page = ref(1)

const onFileChange = (event) => {
  const file = event.target.files && event.target.files[0]
  if (file) im.runPreview(file)
  event.target.value = ''
}

const columns = computed(() => {
  if (im.rows.value.length) return Object.keys(im.rows.value[0].values)
  return props.requiredHeaders
})

const totalPages = computed(() => Math.max(1, Math.ceil(im.rows.value.length / PER_PAGE)))
const pagedRows = computed(() => {
  const start = (page.value - 1) * PER_PAGE
  return im.rows.value.slice(start, start + PER_PAGE)
})
const absoluteIndex = (pageIndex) => (page.value - 1) * PER_PAGE + pageIndex

watch(totalPages, (tp) => { if (page.value > tp) page.value = tp })

const liveCounts = computed(() => {
  const counts = { valid: 0, invalid: 0, duplicate: 0 }
  for (const row of im.rows.value) {
    if (row.status === 'invalid') counts.invalid++
    else if (row.status === 'duplicate_db' || row.status === 'duplicate_file') counts.duplicate++
    else counts.valid++
  }
  return counts
})

const canCommit = computed(() => !im.busy.value && im.rows.value.length > 0 && liveCounts.value.invalid === 0)

const progress = computed(() => im.progress.value || {})
const total = computed(() => progress.value.total_rows || 0)
const processed = computed(() => progress.value.processed_count || 0)
const created = computed(() => progress.value.created_count || 0)
const skipped = computed(() => progress.value.skipped_count || 0)
const failed = computed(() => progress.value.failed_count || 0)
const percent = computed(() => (total.value ? Math.round((processed.value / total.value) * 100) : 0))
const problems = computed(() => progress.value.results || [])

const formatValues = (values) =>
  Object.values(values || {}).filter((v) => v !== '' && v != null).join(' · ')

const statusInfo = (status) => {
  switch (status) {
    case 'invalid': return { label: 'Fix', cls: 'bad' }
    case 'duplicate_db': return { label: 'Exists', cls: 'warn' }
    case 'duplicate_file': return { label: 'Dup', cls: 'warn' }
    case 'failed': return { label: 'Failed', cls: 'bad' }
    case 'skipped': return { label: 'Skipped', cls: 'warn' }
    default: return { label: 'New', cls: 'ok' }
  }
}

const rowClass = (row) => ({
  'row-invalid': row.status === 'invalid',
  'row-dup': row.status === 'duplicate_db' || row.status === 'duplicate_file',
})

// Reload the underlying list once the job actually created something.
watch(() => im.phase.value, (phase) => {
  if (phase === 'done' && (im.progress.value?.created_count || 0) > 0) {
    emit('imported')
  }
})
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 820px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); display: flex; flex-direction: column; max-height: 88vh; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; overflow-y: auto; }

.instructions .lead { font-size: 14px; color: #111; margin-bottom: 10px; }
.col-list { list-style: none; display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 10px; padding: 0; }
.col-list .req { background: #eef2ff; color: #3730a3; padding: 4px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; }
.col-list .opt { background: #f3f4f6; color: #4b5563; padding: 4px 10px; border-radius: 999px; font-size: 12.5px; }
.hint { font-size: 12.5px; color: #6b7280; margin: 0; }
.format-notes { margin: 10px 0 0; padding: 10px 12px; list-style: none; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; flex-direction: column; gap: 5px; }
.format-notes li { font-size: 12.5px; color: #4b5563; line-height: 1.45; padding-left: 14px; position: relative; }
.format-notes li::before { content: '•'; position: absolute; left: 2px; color: #9ca3af; }

.select-actions { display: flex; gap: 12px; margin-top: 18px; }
.file-label { position: relative; overflow: hidden; }
.file-label input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.file-label.disabled input[type="file"] { cursor: not-allowed; }

.summary-bar { display: flex; gap: 8px; margin-bottom: 14px; }
.chip { padding: 4px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; }
.chip.ok { background: #dcfce7; color: #166534; }
.chip.warn { background: #fef9c3; color: #854d0e; }
.chip.bad { background: #fee2e2; color: #991b1b; }

.table-scroll { border: 1px solid #e5e7eb; border-radius: 10px; overflow: auto; max-height: 44vh; }
.review-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.review-table th { position: sticky; top: 0; background: #f9fafb; text-align: left; padding: 8px 10px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; }
.review-table td { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
.row-num { width: 44px; color: #9ca3af; font-variant-numeric: tabular-nums; }
.status-col { width: 86px; }
.actions-col { width: 40px; text-align: right; }
.row-invalid { background: #fef2f2; }
.row-dup { background: #fffbeb; }
.warn-row td { padding-top: 0; border-bottom: 1px solid #f3f4f6; }
.warn-line { margin: 0 0 2px; font-size: 11.5px; color: #854d0e; line-height: 1.4; }

.cell-input { width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font: inherit; font-size: 13px; box-sizing: border-box; outline: none; }
.cell-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.cell-input.error { border-color: #dc2626; }
.cell-error { display: block; font-size: 11.5px; color: #dc2626; margin-top: 3px; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; }
.badge.ok { background: #dcfce7; color: #166534; }
.badge.warn { background: #fef9c3; color: #854d0e; }
.badge.bad { background: #fee2e2; color: #991b1b; }

.icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; }
.icon-btn:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

.pager { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 12px; font-size: 13px; color: #4b5563; }

.committing .lead, .done .lead { font-size: 14px; color: #111; margin-bottom: 12px; }
.progress-track { height: 10px; background: #f3f4f6; border-radius: 999px; overflow: hidden; margin-bottom: 8px; }
.progress-fill { height: 100%; background: #111; transition: width 0.3s; }
.progress-text { font-size: 12.5px; color: #6b7280; margin: 0 0 14px; }

.problems { margin-top: 8px; }
.problems ul { margin: 6px 0 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 6px; max-height: 30vh; overflow-y: auto; }
.problems li { font-size: 12.5px; color: #6b7280; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.problems .row-data { font-weight: 600; color: #111; }
.problems .row-msg { color: #6b7280; }

.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 12px; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }
.btn-cancel { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: #e9eaec; }
.btn-submit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-submit:hover:not(:disabled) { background: #333; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; background: #fff; color: #111; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-secondary:hover:not(:disabled) { background: #f9fafb; }
.btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary.sm { padding: 6px 12px; font-size: 12.5px; }

.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; background: #111; color: #fff; border: 1px solid #111; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: #333; }

.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
