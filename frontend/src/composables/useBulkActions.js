import { ref, computed } from 'vue'
import { t } from '@/i18n'
import { translateError } from '@/utils/translateError'

// `nounKey` selects a pluralizable noun in the `bulk.nouns` namespace
// (e.g. 'unit'); `deleteMessage` optionally overrides the default delete copy
// with an already-translated string. `attachTags(ids, pairs)` / `detachTags(ids, pairs)`
// enable the bulk add/remove-tags flows; `onTagsAttached` / `onTagsDetached` fire on success.
export const useBulkActions = ({
  selectedIds, clearSelection, reload, setActive, remove,
  nounKey = 'unit', deleteMessage = null,
  attachTags = null, onTagsAttached = null, detachTags = null, onTagsDetached = null,
}) => {
  const bulkBusy = ref(false)
  const pendingAction = ref(null)
  const tagsModalOpen = ref(false)
  const removeTagsModalOpen = ref(false)
  const removeTagsPending = ref(null)

  const request = (action) => { pendingAction.value = action }
  const cancel = () => { pendingAction.value = null }

  const noun = (count) => t(`bulk.nouns.${nounKey}`, count)

  const openTags = () => { tagsModalOpen.value = true }
  const closeTags = () => { if (!bulkBusy.value) tagsModalOpen.value = false }

  const openRemoveTags = () => { removeTagsModalOpen.value = true }
  const closeRemoveTags = () => { if (!bulkBusy.value) removeTagsModalOpen.value = false }

  const runTagChange = async (op, pairs, modalRef, onDone) => {
    if (bulkBusy.value || !op) return
    bulkBusy.value = true
    try {
      const ids = Array.from(selectedIds.value)
      await op(ids, pairs)
      modalRef.value = false
      clearSelection()
      await reload()
      onDone?.(ids)
    } catch (err) {
      alert(translateError(err))
    } finally {
      bulkBusy.value = false
    }
  }

  const applyTags = (pairs) => runTagChange(attachTags, pairs, tagsModalOpen, onTagsAttached)

  // Remove is destructive, so it takes a confirm step: the modal's "apply" stages
  // the pairs, then the ConfirmDialog runs the detach (Cancel keeps the picker open).
  const applyRemoveTags = (pairs) => { removeTagsPending.value = pairs }
  const cancelRemoveTags = () => { if (!bulkBusy.value) removeTagsPending.value = null }
  const confirmRemoveTags = async () => {
    const pairs = removeTagsPending.value
    if (!pairs) return
    await runTagChange(detachTags, pairs, removeTagsModalOpen, onTagsDetached)
    removeTagsPending.value = null
  }

  const removeTagsConfirmConfig = computed(() => {
    const tags = removeTagsPending.value?.length || 0
    const n = selectedIds.value.size
    return {
      title: t('bulk.removeTagsConfirmTitle'),
      message: t('bulk.removeTagsConfirmMessage', {
        tags, tagNoun: t('bulk.nouns.tag', tags), count: n, noun: noun(n),
      }),
      confirmText: t('bulk.removeTagsConfirm'),
      type: 'warning',
    }
  })

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

  return {
    bulkBusy, pendingAction, request, confirm, cancel, confirmConfig, noun,
    tagsModalOpen, openTags, closeTags, applyTags,
    removeTagsModalOpen, openRemoveTags, closeRemoveTags, applyRemoveTags,
    removeTagsPending, cancelRemoveTags, confirmRemoveTags, removeTagsConfirmConfig,
  }
}
