import { ref, computed, watch } from 'vue'
import { fetchStoreAllInvitations } from '@/features/users/services/userService'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { resolvedTimestamp } from '@/features/users/utils/format'

const getSortValue = (inv, key) => {
  if (key === 'store_name')    return inv.store.name.toLowerCase()
  if (key === 'invitee_email') return (inv.invitee_name || inv.invitee_email).toLowerCase()
  if (key === 'resolved_at')   return resolvedTimestamp(inv) || ''
  if (key === 'role')          return inv.role
  if (key === 'status')        return inv.status
  return inv[key] ?? ''
}

export const useInvitationHistory = ({ ownedStores }) => {
  const loading        = ref(true)
  const refreshing     = ref(false)
  const allInvitations = ref([])
  const activeFilter   = ref('ALL')
  const currentPage    = ref(1)
  const pageSize       = ref(20)

  const sort = useSortCriteria()

  const filteredInvitations = computed(() =>
    activeFilter.value === 'ALL'
      ? allInvitations.value
      : allInvitations.value.filter((i) => i.status === activeFilter.value),
  )

  const sortedInvitations = computed(() =>
    sort.sortItems(filteredInvitations.value, getSortValue),
  )

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(sortedInvitations.value.length / pageSize.value)),
  )

  const paginatedInvitations = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value
    return sortedInvitations.value.slice(start, start + pageSize.value)
  })

  const countByStatus = (status) =>
    status === 'ALL'
      ? allInvitations.value.length
      : allInvitations.value.filter((i) => i.status === status).length

  watch([activeFilter, pageSize], () => { currentPage.value = 1 })
  watch(sort.sortCriteria, () => { currentPage.value = 1 }, { deep: true })

  const fetchInvitations = async () => {
    if (!loading.value) refreshing.value = true
    try {
      const results = await Promise.all(
        ownedStores.value.map((store) => fetchStoreAllInvitations(store.id)),
      )
      allInvitations.value = results
        .flat()
        .map((inv) => ({
          ...inv,
          status: inv.status?.toUpperCase(),
          role:   inv.role?.toUpperCase(),
        }))
        .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
    } finally {
      loading.value = false
      refreshing.value = false
    }
  }

  return {
    loading, refreshing,
    activeFilter, currentPage, pageSize,
    filteredInvitations, sortedInvitations, paginatedInvitations, totalPages,
    countByStatus,
    sort,
    fetchInvitations,
  }
}
