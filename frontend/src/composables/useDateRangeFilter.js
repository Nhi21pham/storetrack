import { ref, computed } from 'vue'

// Client-side date-range filtering shared by the list pages. Holds the chosen
// date column (created/updated) plus the From/To bounds, and exposes a
// predicate that compares the date part only, inclusive of both ends.
export function useDateRangeFilter(defaultField = 'created_at') {
  const startDate = ref('')
  const endDate = ref('')
  const dateField = ref(defaultField)

  const isActive = computed(() => !!startDate.value || !!endDate.value)

  const inDateRange = (item) => {
    if (!startDate.value && !endDate.value) return true
    const raw = item[dateField.value]
    if (!raw) return false
    const day = String(raw).slice(0, 10)
    if (startDate.value && day < startDate.value) return false
    if (endDate.value && day > endDate.value) return false
    return true
  }

  const clear = () => {
    startDate.value = ''
    endDate.value = ''
  }

  return { startDate, endDate, dateField, isActive, inDateRange, clear }
}
