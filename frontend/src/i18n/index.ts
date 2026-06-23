import { createI18n } from 'vue-i18n'
import en from './locales/en'
import vi from './locales/vi'

export const SUPPORTED_LOCALES = ['vi', 'en'] as const
export type LocaleCode = (typeof SUPPORTED_LOCALES)[number]

const STORAGE_KEY = 'locale'
const DEFAULT_LOCALE: LocaleCode = 'vi'

// BCP-47 tags used for Intl date/number formatting per UI locale.
const BCP47: Record<LocaleCode, string> = { vi: 'vi-VN', en: 'en-US' }

const isSupported = (value: unknown): value is LocaleCode =>
  typeof value === 'string' && (SUPPORTED_LOCALES as readonly string[]).includes(value)

const readStoredLocale = (): LocaleCode => {
  const stored = localStorage.getItem(STORAGE_KEY)
  return isSupported(stored) ? stored : DEFAULT_LOCALE
}

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: readStoredLocale(),
  fallbackLocale: 'en',
  messages: { en, vi },
})

export default i18n

// Standalone translator + key-existence check for non-component code (api.js, utils).
export const t = i18n.global.t
export const te = i18n.global.te

export const activeLocale = (): LocaleCode => i18n.global.locale.value as LocaleCode

// BCP-47 tag for the active locale, for toLocaleDateString/Intl formatting.
export const activeDateLocale = (): string => BCP47[activeLocale()] ?? BCP47[DEFAULT_LOCALE]

export const setLocale = (code: LocaleCode) => {
  if (!isSupported(code)) return
  i18n.global.locale.value = code
  localStorage.setItem(STORAGE_KEY, code)
  document.documentElement.setAttribute('lang', code)
}

document.documentElement.setAttribute('lang', activeLocale())
