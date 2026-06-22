import { t } from '@/i18n'
import { ErrorCode } from '@/utils/errorCodes'

// Maps a thrown AppError to a localized, user-facing message. Prefers a
// translation keyed by the machine-readable error code; for validation errors
// the backend message carries the specific field detail, so that wins. Falls
// back to the backend message, then a generic message.
export const translateError = (err) => {
  const code = err?.code

  if (code === ErrorCode.VALIDATION_ERROR && err?.message) {
    return err.message
  }

  if (code) {
    const key = `errors.${code}`
    const translated = t(key)
    if (translated !== key) return translated
  }

  return err?.message || t('errors.fallback')
}
