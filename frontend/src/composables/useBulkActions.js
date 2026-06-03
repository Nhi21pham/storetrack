import { ref, computed } from 'vue'

export const useBulkActions = ({ selectedIds, clearSelection, reload, setActive, remove, noun = 'item', deleteMessage = null }) => {
  const bulkBusy = ref(false)
  const pendingAction = ref(null)

  const request = (action) => { pendingAction.value = action }
  const cancel = () => { pendingAction.value = null }

  const run = async (taskFn, verb) => {
    if (bulkBusy.value) return
    bulkBusy.value = true
    try {
      const ids = Array.from(selectedIds.value)
      const results = await Promise.allSettled(ids.map(taskFn))
      const failed = results.filter((r) => r.status === 'rejected').length
      clearSelection()
      await reload()
      if (failed) alert(`${failed} ${noun}(s) could not be ${verb}.`)
    } finally {
      bulkBusy.value = false
    }
  }

  const confirm = async () => {
    const action = pendingAction.value
    pendingAction.value = null
    if (action === 'activate') await run((id) => setActive(id, true), 'activated')
    else if (action === 'deactivate') await run((id) => setActive(id, false), 'deactivated')
    else if (action === 'delete') await run((id) => remove(id), 'deleted')
  }

  const confirmConfig = computed(() => {
    const n = selectedIds.value.size
    const s = n === 1 ? '' : 's'
    if (pendingAction.value === 'delete') return {
      title: `Delete ${n} ${noun}${s}?`,
      message: deleteMessage || `This permanently removes the selected ${noun}${s}. Items in use can't be deleted and will be skipped.`,
      confirmText: 'Delete',
      type: 'warning',
    }
    if (pendingAction.value === 'activate') return {
      title: `Activate ${n} ${noun}${s}?`,
      message: `The selected ${noun}${s} will be marked active.`,
      confirmText: 'Activate',
      type: 'success',
    }
    if (pendingAction.value === 'deactivate') return {
      title: `Deactivate ${n} ${noun}${s}?`,
      message: `The selected ${noun}${s} will be marked inactive and won't appear in pickers for new records.`,
      confirmText: 'Deactivate',
      type: 'warning',
    }
    return {}
  })

  return { bulkBusy, pendingAction, request, confirm, cancel, confirmConfig }
}
