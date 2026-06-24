import {
  ACTION_COLORS,
  ACTION_VERB_REGEX,
} from '@/features/audit/constants'
import { t, te } from '@/i18n'
import { formatMoney } from '@/features/invoices/constants'

export { formatDateTime } from '@/utils/datetime'

export const actorTitle = (log) => {
  const name = log.actor_name || t('audit.system')
  return log.actor_email ? `${name} (${log.actor_email})` : name
}

const escapeHtml = (s) =>
  String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

// --- Localized rendering -----------------------------------------------------
// Each audit row stores object_type + action + structured metadata, so the
// human sentence is rebuilt in the viewer's language from a template rather
// than the English string frozen at write time. Rows whose shape we don't
// recognize fall back to the stored English message.

const roleLabel = (role) => {
  if (!role) return ''
  const key = `enums.role.${String(role).toUpperCase()}`
  return te(key) ? t(key) : String(role)
}

const localizedOr = (key, raw) => (raw != null && te(key) ? t(key) : raw)

// The API exposes metadata as a JSON string (metadata_json); parse it once.
const metaOf = (log) => {
  if (log.metadata && typeof log.metadata === 'object') return log.metadata
  if (typeof log.metadata_json === 'string') {
    try { return JSON.parse(log.metadata_json) } catch { return {} }
  }
  return {}
}

// Build the auditMessages.* key from the log's structured fields.
const templateKey = (log) => {
  const o = log.object_type
  const a = log.action
  const m = metaOf(log)

  if (o === 'tag' && m.value != null && m.tag_value_id != null) {
    if (a === 'created') return 'auditMessages.tag.value_added'
    if (a === 'updated') return 'auditMessages.tag.value_updated'
    if (a === 'deleted') return 'auditMessages.tag.value_deleted'
  }
  if (o === 'payment' && m.invoices) {
    return `auditMessages.payment.${a}_for`
  }
  if (a === 'exported') {
    // A store/business object with an export action is the audit-log export.
    if (o === 'store') return 'auditMessages.store.exported_store'
    if (o === 'business') return 'auditMessages.business.exported_business'
    return `auditMessages.${o}.exported_${m.store_id ? 'store' : 'business'}`
  }
  return `auditMessages.${o}.${a}`
}

// Localize the variable parts referenced by the templates.
const buildParams = (log) => {
  const m = { ...metaOf(log) }
  if (m.amount != null) m.amount = formatMoney(m.amount)
  if (m.type) {
    m.invoiceType = localizedOr(`enums.invoiceType.${String(m.type).toUpperCase()}`, m.type).toLowerCase()
  }
  m.invoiceLabel = t(`auditMessages.invoiceLabel.${m.type || 'all'}`)
  if (m.role) m.role = roleLabel(m.role)
  if (m.old_role) m.old_role = roleLabel(m.old_role)
  if (m.new_role) m.new_role = roleLabel(m.new_role)
  if (m.party_type) m.partyType = localizedOr(`auditMessages.partyType.${m.party_type}`, m.party_type)
  if (m.report) m.report = localizedOr(`auditMessages.report_label.${m.report}`, m.report)
  return m
}

// Wrap the **verb** marker in a colored, bold span (color keyed by action).
// The verb is uppercased so it reads consistently in both languages.
const decorate = (raw, action) => {
  const color = ACTION_COLORS[String(action || '').toUpperCase()] ?? '#374151'
  return escapeHtml(raw).replace(
    /\*\*(.+?)\*\*/g,
    (_, verb) => `<span class="verb" style="color:${color}">${verb.toUpperCase()}</span>`,
  )
}

// Plain (marker-free) localized detail, for the row's hover title.
const localizedPlain = (log) => {
  const key = templateKey(log)
  if (!te(key)) return null
  return t(key, buildParams(log)).replace(/\*\*/g, '')
}

// --- Legacy fallback (stored English message) --------------------------------
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

const legacyRender = (log) => {
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

export const actionTitle = (log) => localizedPlain(log) ?? stripActorPrefix(log)

export const renderAction = (log) => {
  const key = templateKey(log)
  if (te(key)) return decorate(t(key, buildParams(log)), log.action)
  return legacyRender(log)
}
