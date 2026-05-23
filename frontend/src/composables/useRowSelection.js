import { ref, computed } from 'vue'

// Generic Set-based row-selection. `eligibleIds` is a reactive list of the row
// IDs the user is currently allowed to select (e.g. filtered + permission-checked).
// The composable keeps the underlying Set immutable-by-replacement so Vue's
// reactivity picks up changes.
export const useRowSelection = ({ eligibleIds }) => {
  const selectedIds = ref(new Set())

  const isSelected = (id) => selectedIds.value.has(String(id))

  const toggleRow = (id) => {
    const key = String(id)
    const next = new Set(selectedIds.value)
    if (next.has(key)) next.delete(key)
    else next.add(key)
    selectedIds.value = next
  }

  const selectedVisibleCount = computed(() =>
    eligibleIds.value.filter((id) => selectedIds.value.has(id)).length,
  )

  const allVisibleSelected = computed(() =>
    eligibleIds.value.length > 0 && selectedVisibleCount.value === eligibleIds.value.length,
  )

  const someVisibleSelected = computed(() =>
    selectedVisibleCount.value > 0 && selectedVisibleCount.value < eligibleIds.value.length,
  )

  const toggleSelectAll = () => {
    const next = new Set(selectedIds.value)
    if (allVisibleSelected.value) {
      for (const id of eligibleIds.value) next.delete(id)
    } else {
      for (const id of eligibleIds.value) next.add(id)
    }
    selectedIds.value = next
  }

  const clearSelection = () => { selectedIds.value = new Set() }

  return {
    selectedIds,
    isSelected, toggleRow, toggleSelectAll, clearSelection,
    allVisibleSelected, someVisibleSelected,
  }
}
