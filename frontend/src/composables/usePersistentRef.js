import { ref, watch } from 'vue'

const STORAGE_PREFIX = 'filters:'

// Reads the stored value, falling back to the default when it's missing, corrupt,
// or a different shape than the default (e.g. a string left over from before a
// filter became multi-select) — so shape changes migrate cleanly.
const read = (key, fallback) => {
  try {
    const raw = localStorage.getItem(STORAGE_PREFIX + key)
    if (raw == null) return fallback
    const parsed = JSON.parse(raw)
    if (Array.isArray(parsed) !== Array.isArray(fallback)) return fallback
    if (typeof parsed !== typeof fallback) return fallback
    return parsed
  } catch {
    return fallback
  }
}

/** A ref that mirrors its value to localStorage under `filters:<key>`. */
export function usePersistentRef(key, defaultValue) {
  const state = ref(read(key, defaultValue))
  watch(state, (val) => {
    try {
      localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(val))
    } catch {
      /* ignore quota / privacy errors */
    }
  }, { deep: true })
  return state
}
