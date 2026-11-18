<template>
  <div class="lang-switch btn-group btn-group-sm" role="group" :aria-label="t('lang.switch')">
    <button type="button" class="btn" :class="locale === 'km' ? 'btn-primary' : 'btn-outline-secondary'" @click="setLocale('km')">
      🇰🇭 ខ្មែរ
    </button>
    <button type="button" class="btn" :class="locale === 'en' ? 'btn-primary' : 'btn-outline-secondary'" @click="setLocale('en')">
      🇬🇧 EN
    </button>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { setLocale } from '@/i18n'

const { t, locale } = useI18n()
</script>
