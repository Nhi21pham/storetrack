import {
  ACTION_COLORS,
  ACTION_VERB_REGEX,
} from '@/features/audit/constants'

export { formatDateTime } from '@/utils/datetime'

export const actorTitle = (log) => {
  const name = log.actor_name || 'System'
  return log.actor_email ? `${name} (${log.actor_email})` : name
}

const escapeHtml = (s) =>
  String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

// Backend prepends "<actor>(<email>) has " to the message; strip it for display
// since we render the actor in its own column.
export const stripActorPrefix = (log) => {
  const actor = log.actor_name && log.actor_email ? `${log.actor_name}(${log.actor_email})` : null
  let detail = log.message
  if (actor) {
    const prefix = `${actor} has `
    if (detail.startsWith(prefix)) detail = detail.slice(prefix.length)
  }
  return detail.replace(/\.$/, '').trim()
}

export const actionTitle = (log) => stripActorPrefix(log)

export const renderAction = (log) => {
  const detail = stripActorPrefix(log)
  if (!detail) return ''
  const escaped = escapeHtml(detail)
    .replace(/(\S+\([^)]+\))/g, '<strong>$1</strong>')
    .replace(/\b([A-Za-z][\w.+-]*@[\w.-]+\.[A-Za-z]{2,})\b/g, '<strong>$1</strong>')
  return escaped.replace(
    ACTION_VERB_REGEX,
    (m) => `<span class="verb" style="color:${ACTION_COLORS[m] ?? '#374151'}">${m}</span>`,
  )
}
