import { createI18n } from 'vue-i18n'
import de from '@/locales/de'
import en from '@/locales/en'

const LOCALE_KEY = 'hero-locale'

function getDefaultLocale() {
  try {
    const saved = localStorage.getItem(LOCALE_KEY)
    if (saved === 'en' || saved === 'de') return saved
  } catch (_) {}
  return 'de'
}

export const i18n = createI18n({
  legacy: false,
  locale: getDefaultLocale(),
  fallbackLocale: 'en',
  messages: { de, en },
})

export function setLocale(locale) {
  if (locale !== 'de' && locale !== 'en') return
  i18n.global.locale.value = locale
  try {
    localStorage.setItem(LOCALE_KEY, locale)
  } catch (_) {}
}
