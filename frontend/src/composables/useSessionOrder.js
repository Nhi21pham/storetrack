import { computed } from 'vue'

// Keeps a list's display order stable across in-session refetches. Once a row has
// been given a slot it keeps it, so saving an edit doesn't bump the row to the top
// and cost you your place while working down the list. Rows seen for the first time
// mid-session (e.g. a just-created record) surface at the top.
//
// Feed it a ref whose value is already in the desired seed order (newest-first).
// A wholly new dataset — the first load, or switching store/business so every id
// changes — re-seeds from that order automatically. Callers whose seed order can
// change while the ids stay the same (e.g. a store/all-stores view toggle) should
// call `reseed()` on that change to re-honour the new order.
export function useSessionOrder(sourceRef) {
  const rankById = new Map()
  let topRank = 0

  const reseed = () => {
    rankById.clear()
    topRank = 0
  }

  const ordered = computed(() => {
    const items = sourceRef.value
    const seenAny = items.some((item) => rankById.has(item.id))

    if (!seenAny) {
      // First load, or an entirely fresh dataset: take the seed order as-is.
      reseed()
      items.forEach((item, index) => rankById.set(item.id, index))
    } else {
      // New rows appearing mid-session go on top, keeping their incoming order.
      const unseen = items.filter((item) => !rankById.has(item.id))
      for (let i = unseen.length - 1; i >= 0; i--) {
        rankById.set(unseen[i].id, --topRank)
      }
    }

    return [...items].sort((a, b) => rankById.get(a.id) - rankById.get(b.id))
  })

  return { ordered, reseed }
}
