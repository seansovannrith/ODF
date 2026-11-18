<template>
  <RouterView />
  <div class="toast-stack">
    <div v-for="t in toasts.items" :key="t.id" class="toast-item" :class="`toast-${t.type}`">
      <i class="bi" :class="t.type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'"></i>
      <span>{{ t.text }}</span>
    </div>
  </div>
</template>

<script setup>
import { watchEffect } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/stores/toast'

const toasts = useToast()
const { locale } = useI18n()

// Switch the Khmer font on <html> when the language changes
watchEffect(() => {
  document.documentElement.lang = locale.value
  document.documentElement.classList.toggle('lang-km', locale.value === 'km')
})
</script>
