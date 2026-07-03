import { ref } from 'vue'

// Briefly flags rows as just-edited so a table can flash a highlight on them —
// after a save reloads the list, this marks where you were so you don't have to
// hunt for the row again. Each id clears itself after `duration` (kept a touch
// longer than the CSS glow so the animation always finishes).
export function useRowHighlight({ duration = 2000 } = {}) {
  const flagged = ref(new Set())
  const timers = new Map()

  const mark = (id) => {
    if (id == null) return
    const key = String(id)
    const next = new Set(flagged.value)
    next.add(key)
    flagged.value = next
    clearTimeout(timers.get(key))
    timers.set(key, setTimeout(() => {
      const after = new Set(flagged.value)
      after.delete(key)
      flagged.value = after
      timers.delete(key)
    }, duration))
  }

  const markMany = (ids) => (ids || []).forEach(mark)
  const isRecent = (id) => flagged.value.has(String(id))

  return { mark, markMany, isRecent }
}
