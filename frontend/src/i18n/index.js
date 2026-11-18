import { createI18n } from 'vue-i18n'
import en from './en'
import km from './km'

const saved = localStorage.getItem('odf_locale')

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: saved || 'km',
  fallbackLocale: 'en',
  messages: { en, km },
})

export function setLocale(lang) {
  i18n.global.locale.value = lang
  localStorage.setItem('odf_locale', lang)
}

export default i18n
