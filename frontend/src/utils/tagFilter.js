import { t } from '@/i18n'

// Broad tag filters: "has any tag" / "has no tags".
export const TAG_TAGGED = 'tagged'
export const TAG_NONE = 'none'

const matchesSpecific = (rowTags, selected) => {
  const [kind, id] = selected.split(':')
  if (kind === 'tag') return rowTags.some((tg) => String(tg.tag_id) === id)
  if (kind === 'val') return rowTags.some((tg) => String(tg.tag_value_id) === id)
  return true
}

// A row matches when it satisfies ANY selected criterion (OR): "No tags", "All
// tags", or any specific tag/value — so "Color" + "Shape" shows rows with either.
export const matchesTagFilter = (rowTags, selected) => {
  if (!selected.length) return true
  const tags = rowTags || []
  return selected.some((sel) => {
    if (sel === TAG_NONE) return tags.length === 0
    if (sel === TAG_TAGGED) return tags.length > 0
    return matchesSpecific(tags, sel)
  })
}

// Tag options from the tags present in the given rows, mirroring the products
// filter: broad "(All tags)"/"(No tags)" first, then each tag's "(any)" + values.
export const buildTagOptions = (rows) => {
  const byTag = new Map()
  for (const r of rows) {
    for (const tg of (r.tags || [])) {
      if (!byTag.has(tg.tag_id)) byTag.set(tg.tag_id, { name: tg.tag_name, values: new Map() })
      if (tg.tag_value_id != null) byTag.get(tg.tag_id).values.set(tg.tag_value_id, tg.value)
    }
  }
  const opts = [
    { value: TAG_TAGGED, label: t('reports.filters.allTags') },
    { value: TAG_NONE, label: t('reports.filters.noTags') },
  ]
  for (const [tagId, { name, values }] of byTag) {
    opts.push({ value: `tag:${tagId}`, label: `${name} (any)` })
    for (const [valueId, value] of values) {
      opts.push({ value: `val:${valueId}`, label: `${name}: ${value}` })
    }
  }
  return opts
}

// Splits a selected tag-filter array into export query params the backend
// understands: broad flags plus specific tag/value id lists.
export const tagFilterToParams = (selected) => {
  const params = {}
  if (!selected || !selected.length) return params
  if (selected.includes(TAG_NONE)) params.tags_none = true
  if (selected.includes(TAG_TAGGED)) params.tags_any = true
  const tagIds = []
  const tagValueIds = []
  for (const sel of selected) {
    const [kind, id] = sel.split(':')
    if (kind === 'tag') tagIds.push(id)
    else if (kind === 'val') tagValueIds.push(id)
  }
  if (tagIds.length) params.tag_ids = tagIds
  if (tagValueIds.length) params.tag_value_ids = tagValueIds
  return params
}
