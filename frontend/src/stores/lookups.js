import { defineStore } from 'pinia'
import http from '@/api/http'

/** Dropdown data (titles, positions, users ...) loaded once and reused by every form. */
export const useLookups = defineStore('lookups', {
  state: () => ({
    loaded: false,
    departments: [], titles: [], positions: [], categories: [], organisations: [], document_types: [], users: [],
    approver_ids: [], roles: [], permissions: [],
    branding: { app_name: 'ODF', app_full_name: 'Organization Document Flow', logo_url: null },
  }),
  actions: {
    async load(force = false) {
      if (this.loaded && !force) return
      const { data } = await http.get('/lookups')
      Object.assign(this, data)
      this.loaded = true
    },
    async loadBranding() {
      try {
        const { data } = await http.get('/branding')
        this.branding = data
      } catch (_) { /* keep defaults */ }
    },
  },
})
