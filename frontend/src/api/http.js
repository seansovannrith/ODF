import axios from 'axios'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem('odf_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  config.headers['Accept-Language'] = localStorage.getItem('odf_locale') || 'km'
  return config
})

http.interceptors.response.use(
  (r) => r,
  (error) => {
    if (error.response?.status === 401 && !error.config.url.endsWith('/login')) {
      localStorage.removeItem('odf_token')
      if (!location.pathname.startsWith('/login')) location.href = '/login'
    }
    return Promise.reject(error)
  },
)

/** Human readable error message from a Laravel response */
export function errorMessage(e, fallback = 'Error') {
  const d = e?.response?.data
  if (d?.errors) return Object.values(d.errors).flat().join(' ')
  return d?.message || e?.message || fallback
}

/** Build FormData; booleans become 1/0 (what Laravel's "boolean" rule expects), arrays become key[] */
export function toFormData(obj) {
  const fd = new FormData()
  Object.entries(obj).forEach(([k, v]) => {
    if (v === undefined) return
    if (Array.isArray(v)) v.forEach((item) => fd.append(`${k}[]`, item))
    else if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
    else if (v === null) fd.append(k, '')
    else fd.append(k, v)
  })
  return fd
}

export default http
