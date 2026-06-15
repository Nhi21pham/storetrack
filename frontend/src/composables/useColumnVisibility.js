import { ref, computed, watch } from 'vue'

const STORAGE_PREFIX = 'columns:'

// Persist the HIDDEN columns (not the visible ones) as { hidden: [...] }. Anything
// not listed is visible — so a column added to a table later shows by default
// instead of being hidden for everyone who used the page before it existed.
// The old format (a plain array of visible keys) is ignored — treated as
// "nothing hidden" — so it migrates cleanly to all-visible.
// Returns the stored hidden keys, or null when the user has no stored preference
// yet (so the caller can seed its own defaults). Distinguishing "stored empty"
// from "never stored" is what lets defaultHiddenKeys apply only on first use.
const readHidden = (storageKey, togglableKeys) => {
  try {
    const parsed = JSON.parse(localStorage.getItem(STORAGE_PREFIX + storageKey) || 'null')
    if (parsed && Array.isArray(parsed.hidden)) {
      return parsed.hidden.filter((k) => togglableKeys.includes(k))
    }
    return null
  } catch {
    return null
  }
}

const writeHidden = (storageKey, hiddenKeys) => {
  try {
    localStorage.setItem(STORAGE_PREFIX + storageKey, JSON.stringify({ hidden: hiddenKeys }))
  } catch {
    /* ignore quota / privacy errors */
  }
}

export const useColumnVisibility = ({ storageKey, columns, lockedKeys = [], defaultHiddenKeys = [] }) => {
  const lockedSet = new Set(lockedKeys)

  const togglableKeys = columns.map((c) => c.key).filter((k) => !lockedSet.has(k))

  const stored = readHidden(storageKey, togglableKeys)
  const initialHidden = stored ?? defaultHiddenKeys.filter((k) => togglableKeys.includes(k))
  const hiddenKeys = ref(new Set(initialHidden))

  const isVisible = (key) => !hiddenKeys.value.has(key)

  const visibleColumns = computed(() => columns.filter((c) => isVisible(c.key)))

  const visibleColumnKeys = computed(() => visibleColumns.value.map((c) => c.key))

  const toggleColumn = (key) => {
    if (lockedSet.has(key)) return
    const next = new Set(hiddenKeys.value)
    if (next.has(key)) next.delete(key)
    else next.add(key)
    hiddenKeys.value = next
  }

  const resetColumns = () => {
    hiddenKeys.value = new Set()
  }

  watch(hiddenKeys, (keys) => {
    writeHidden(storageKey, [...keys])
  })

  const filterWidths = (allWidths) => {
    const visibleIndices = []
    let hiddenSum = 0
    columns.forEach((col, i) => {
      if (isVisible(col.key)) visibleIndices.push(i)
      else hiddenSum += allWidths[i] || 0
    })
    if (visibleIndices.length === 0) return []
    if (hiddenSum === 0) return visibleIndices.map(i => allWidths[i])
    const visibleSum = visibleIndices.reduce((s, i) => s + (allWidths[i] || 0), 0) || 1
    return visibleIndices.map(i => allWidths[i] + ((allWidths[i] || 0) / visibleSum) * hiddenSum)
  }

  return {
    isVisible,
    visibleColumns,
    visibleColumnKeys,
    toggleColumn,
    resetColumns,
    hiddenKeys,
    lockedKeys: lockedSet,
    togglableColumns: columns.filter(c => !lockedSet.has(c.key)),
    filterWidths,
  }
}
