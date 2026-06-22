import { ref, computed } from 'vue'
import { t } from '@/i18n'

// `nounKey` selects a pluralizable noun in the `bulk.nouns` namespace
// (e.g. 'unit'); `deleteMessage` optionally overrides the default delete copy
// with an already-translated string.
export const useBulkActions = ({ selectedIds, clearSelection, reload, setActive, remove, nounKey = 'unit', deleteMessage = null }) => {
  const bulkBusy = ref(false)
  const pendingAction = ref(null)

  const request = (action) => { pendingAction.value = action }
  const cancel = () => { pendingAction.value = null }

  const noun = (count) => t(`bulk.nouns.${nounKey}`, count)

  const run = async (taskFn, verb) => {
    if (bulkBusy.value) return
    bulkBusy.value = true
    try {
      const ids = Array.from(selectedIds.value)
      const results = await Promise.allSettled(ids.map(taskFn))
      const failed = results.filter((r) => r.status === 'rejected').length
      clearSelection()
      await reload()
      if (failed) alert(t('bulk.failedAction', { count: failed, noun: noun(failed), verb: t(`bulk.verb.${verb}`) }))
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
    if (pendingAction.value === 'delete') return {
      title: t('bulk.deleteTitle', { count: n, noun: noun(n) }),
      message: deleteMessage || t('bulk.deleteMessage', { noun: noun(n) }),
      confirmText: t('bulk.delete'),
      type: 'warning',
    }
    if (pendingAction.value === 'activate') return {
      title: t('bulk.activateTitle', { count: n, noun: noun(n) }),
      message: t('bulk.activateMessage', { noun: noun(n) }),
      confirmText: t('bulk.activate'),
      type: 'success',
    }
    if (pendingAction.value === 'deactivate') return {
      title: t('bulk.deactivateTitle', { count: n, noun: noun(n) }),
      message: t('bulk.deactivateMessage', { noun: noun(n) }),
      confirmText: t('bulk.deactivate'),
      type: 'warning',
    }
    return {}
  })

  return { bulkBusy, pendingAction, request, confirm, cancel, confirmConfig }
}
