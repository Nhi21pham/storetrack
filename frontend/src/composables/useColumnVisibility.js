import { ref, computed, watch } from 'vue'

const STORAGE_PREFIX = 'columns:'

const readStored = (storageKey) => {
  try {
    const raw = localStorage.getItem(STORAGE_PREFIX + storageKey)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : null
  } catch {
    return null
  }
}

const writeStored = (storageKey, keys) => {
  try {
    localStorage.setItem(STORAGE_PREFIX + storageKey, JSON.stringify(keys))
  } catch {
    /* ignore quota / privacy errors */
  }
}

export const useColumnVisibility = ({ storageKey, columns, lockedKeys = [] }) => {
  const lockedSet = new Set(lockedKeys)

  const allKeys = columns.map(c => c.key)
  const togglableKeys = allKeys.filter(k => !lockedSet.has(k))

  const stored = readStored(storageKey)
  const initialHidden = stored
    ? togglableKeys.filter(k => !stored.includes(k))
    : []

  const hiddenKeys = ref(new Set(initialHidden))

  const isVisible = (key) => !hiddenKeys.value.has(key)

  const visibleColumns = computed(() => columns.filter(c => isVisible(c.key)))

  const visibleColumnKeys = computed(() => visibleColumns.value.map(c => c.key))

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

  watch(visibleColumnKeys, (keys) => {
    writeStored(storageKey, keys)
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
