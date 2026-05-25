import { ref, computed, watch } from 'vue'

/**
 * Client-side pagination for an already-loaded list.
 * Pass a ref/computed of the full source array (after any filtering/sorting).
 *
 * Returns { currentPage, perPage, total, totalPages, paginated, setPerPage, resetPage }
 * Bind currentPage / perPage / totalPages / total to <Pagination> and use `paginated` in v-for.
 */
export function useClientPagination(sourceRef, { defaultPerPage = 20 } = {}) {
  const currentPage = ref(1)
  const perPage = ref(defaultPerPage)

  const total = computed(() => sourceRef.value.length)
  const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))

  const paginated = computed(() => {
    const start = (currentPage.value - 1) * perPage.value
    return sourceRef.value.slice(start, start + perPage.value)
  })

  watch(total, () => {
    if (currentPage.value > totalPages.value) currentPage.value = totalPages.value
  })

  const setPerPage = (value) => {
    perPage.value = value
    currentPage.value = 1
  }

  const resetPage = () => { currentPage.value = 1 }

  return {
    currentPage,
    perPage,
    total,
    totalPages,
    paginated,
    setPerPage,
    resetPage,
  }
}
