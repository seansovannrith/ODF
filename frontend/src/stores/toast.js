import { defineStore } from 'pinia'

let seq = 0

export const useToast = defineStore('toast', {
  state: () => ({ items: [] }),
  actions: {
    push(text, type = 'success') {
      const id = ++seq
      this.items.push({ id, text, type })
      setTimeout(() => { this.items = this.items.filter((t) => t.id !== id) }, 3500)
    },
    success(text) { this.push(text, 'success') },
    error(text) { this.push(text, 'danger') },
  },
})
