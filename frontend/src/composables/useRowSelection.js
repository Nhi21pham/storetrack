import { ref, computed } from 'vue'

export const useRowSelection = ({ eligibleIds, scopeToEligible = false }) => {
  const rawSelected = ref(new Set())

  const selectedIds = computed(() => {
    if (!scopeToEligible) return rawSelected.value
    const allowed = new Set(eligibleIds.value)
    const out = new Set()
    for (const id of rawSelected.value) {
      if (allowed.has(id)) out.add(id)
    }
    return out
  })

  const isSelected = (id) => rawSelected.value.has(String(id))

  const toggleRow = (id) => {
    const key = String(id)
    const next = new Set(rawSelected.value)
    if (next.has(key)) next.delete(key)
    else next.add(key)
    rawSelected.value = next
  }

  const selectedVisibleCount = computed(() =>
    eligibleIds.value.filter((id) => rawSelected.value.has(id)).length,
  )

  const allVisibleSelected = computed(() =>
    eligibleIds.value.length > 0 && selectedVisibleCount.value === eligibleIds.value.length,
  )

  const someVisibleSelected = computed(() =>
    selectedVisibleCount.value > 0 && selectedVisibleCount.value < eligibleIds.value.length,
  )

  const toggleSelectAll = () => {
    const next = new Set(rawSelected.value)
    if (allVisibleSelected.value) {
      for (const id of eligibleIds.value) next.delete(id)
    } else {
      for (const id of eligibleIds.value) next.add(id)
    }
    rawSelected.value = next
  }

  const clearSelection = () => { rawSelected.value = new Set() }

  return {
    selectedIds,
    isSelected, toggleRow, toggleSelectAll, clearSelection,
    allVisibleSelected, someVisibleSelected,
  }
}
