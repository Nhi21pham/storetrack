import { ref } from 'vue'
import { usePersistentRef } from '@/composables/usePersistentRef'

// Pass a storageKey to persist the active sort across reloads/edits.
export function useSortCriteria(storageKey = null) {
  const sortCriteria = storageKey ? usePersistentRef(`sort:${storageKey}`, []) : ref([])

  const getSortInfo = (key) => sortCriteria.value.find(c => c.key === key)
  const sortRank    = (key) => sortCriteria.value.findIndex(c => c.key === key) + 1

  const toggleSort = (key, dir) => {
    const idx = sortCriteria.value.findIndex(c => c.key === key)
    if (idx === -1) {
      sortCriteria.value = [...sortCriteria.value, { key, dir }]
    } else if (sortCriteria.value[idx].dir === dir) {
      sortCriteria.value = sortCriteria.value.filter((_, i) => i !== idx)
    } else {
      sortCriteria.value = sortCriteria.value.map((c, i) => i === idx ? { ...c, dir } : c)
    }
  }

  const clearSort = () => { sortCriteria.value = [] }

  /**
   * Sort an array by the active criteria.
   * @param {Array} items
   * @param {(item: any, key: string) => any} getValueFn  Maps an item + key to a comparable value.
   */
  const sortItems = (items, getValueFn) => {
    if (!sortCriteria.value.length) return items
    return [...items].sort((a, b) => {
      for (const { key, dir } of sortCriteria.value) {
        const va = getValueFn(a, key)
        const vb = getValueFn(b, key)
        if (va < vb) return dir === 'asc' ? -1 : 1
        if (va > vb) return dir === 'asc' ? 1 : -1
      }
      return 0
    })
  }

  return { sortCriteria, getSortInfo, sortRank, toggleSort, clearSort, sortItems }
}
