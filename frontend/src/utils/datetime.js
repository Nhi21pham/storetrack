// Single source of truth for the user-facing datetime format we use across
// every feature (audit log timestamps, invitation history, customer "Created" rows).
// "Aug 12, 2026, 03:45 PM" — short month, numeric day/year, 12-hour clock.
export const formatDateTime = (isoString) => {
  if (!isoString) return '—'
  return new Date(isoString).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
