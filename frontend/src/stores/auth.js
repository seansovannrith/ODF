import { defineStore } from 'pinia'
import http from '@/api/http'

export const useAuth = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('odf_token'),
  }),
  getters: {
    isLoggedIn: (s) => !!s.token,
    permissions: (s) => s.user?.permissions || [],
    /** auth.can('document_create') – same permission names as the backend */
    can() { return (permission) => this.permissions.includes(permission) },
    canAny() { return (list) => list.some((p) => this.permissions.includes(p)) },
  },
  actions: {
    /** Returns { twoFactor: true } when the 6-digit code is still needed */
    async login(email, password, remember, code = null) {
      const { data } = await http.post('/login', { email, password, remember, code })
      if (data.two_factor_required) return { twoFactor: true }
      this.token = data.token
      this.user = data.user
      localStorage.setItem('odf_token', data.token)
      return { twoFactor: false }
    },
    async fetchMe() {
      if (!this.token) return null
      const { data } = await http.get('/me')
      this.user = data
      return data
    },
    async logout() {
      try { await http.post('/logout') } catch (_) { /* token may already be gone */ }
      this.token = null
      this.user = null
      localStorage.removeItem('odf_token')
    },
  },
})
