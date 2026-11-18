import http from '@/api/http'
import i18n from '@/i18n'

const KH_DIGITS = ['០', '១', '២', '៣', '៤', '៥', '៦', '៧', '៨', '៩']
const KH_MONTHS = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ']
const EN_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const isKm = () => i18n.global.locale.value === 'km'

/** Pick the Khmer or English value of a record: ln(title) -> name_kh or name */
export function ln(obj, field = 'name') {
  if (!obj) return ''
  return (isKm() && obj[`${field}_kh`]) || obj[field] || obj[`${field}_kh`] || ''
}

export function khDigits(value) {
  return String(value ?? '').replace(/[0-9]/g, (d) => KH_DIGITS[d])
}

const pad = (n) => String(n).padStart(2, '0')

/** 2026-11-10 08:11 (English) or ១០ វិច្ឆិកា ២០២៦ ០៨:១១ (Khmer) */
export function formatDate(value, withTime = true, forceKhmer = false) {
  if (!value) return ''
  const d = new Date(value)
  if (isNaN(d)) return value
  const time = `${pad(d.getHours())}:${pad(d.getMinutes())}`
  if (forceKhmer || isKm()) {
    const s = `${pad(d.getDate())} ${KH_MONTHS[d.getMonth()]} ${d.getFullYear()}${withTime ? ' ' + time : ''}`
    return khDigits(s)
  }
  return `${pad(d.getDate())}-${EN_MONTHS[d.getMonth()]}-${d.getFullYear()}${withTime ? ' ' + time : ''}`
}

/** Value for <input type="datetime-local"> */
export function toInputDateTime(value) {
  if (!value) return ''
  const d = new Date(value)
  if (isNaN(d)) return ''
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** "ប្រធានការិយាល័យរដ្ឋបាល៖ លោក ម៉ៅ រាសី" / "Chief of Administration Office: Mr. Mao Reasey" */
export function personLabel(user, { withPosition = true } = {}) {
  if (!user) return ''
  const km = isKm()
  const title = km ? (user.title?.name_kh || '') : (user.title?.abbr || user.title?.name || '')
  const name = km ? (user.name_kh || user.name) : user.name
  const person = [title, name].filter(Boolean).join(' ')
  if (!withPosition || !user.position) return person
  return `${ln(user.position)}${km ? '៖' : ':'} ${person}`
}

export function stripHtml(html) {
  const div = document.createElement('div')
  div.innerHTML = html ?? ''
  return (div.textContent || '').replace(/\s+/g, ' ').trim()
}

export function fileSize(bytes) {
  if (!bytes) return '0 KB'
  return bytes > 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(2)} MB` : `${(bytes / 1024).toFixed(2)} KB`
}

/** Download a file from an authenticated API url without rendering it in the app origin. */
export async function downloadProtectedFile(url, filename = 'download') {
  const { data } = await http.get(url, { responseType: 'blob' })
  const blobUrl = URL.createObjectURL(new Blob([data], { type: 'application/octet-stream' }))
  const a = document.createElement('a')
  a.href = blobUrl
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000)
}
