<template>
  <div class="login-page">
    <div class="position-absolute top-0 end-0 p-3"><LanguageSwitch /></div>
    <div class="login-box">
      <div class="text-center mb-3">
        <img :src="lookups.branding.logo_url || appLogo" class="login-logo" :alt="lookups.branding.app_name || 'ODF'" />
        <h1 class="login-title">{{ lookups.branding.app_name || 'ODF' }}</h1>
        <div class="text-muted">{{ t('app.fullName') }}</div>
      </div>
      <form class="card card-body shadow-sm" @submit.prevent="submit">
        <p class="text-center mb-3">{{ t('auth.login') }}</p>
        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
        <template v-if="!needCode">
          <div class="mb-3 input-group">
            <input v-model="email" type="text" class="form-control form-control-lg" :placeholder="t('auth.emailOrUsername')" required autofocus />
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          </div>
          <div class="mb-3 input-group">
            <input v-model="password" type="password" class="form-control form-control-lg" :placeholder="t('auth.password')" required />
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
          </div>
        </template>
        <div v-else class="mb-3">
          <label class="form-label small">{{ t('auth.codeHint') }}</label>
          <div class="input-group">
            <input
              ref="codeInput" v-model="code" class="form-control form-control-lg text-center fs-4" inputmode="numeric"
              autocomplete="one-time-code" maxlength="6" placeholder="••••••" required
            />
            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
          </div>
          <a href="#" class="small" @click.prevent="needCode = false; code = ''">← {{ t('common.back') }}</a>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <label class="form-check">
            <input v-model="remember" type="checkbox" class="form-check-input" />
            <span class="form-check-label fw-semibold">{{ t('auth.remember') }}</span>
          </label>
          <button class="btn btn-primary px-4" :disabled="busy">
            <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span>{{ t('auth.login') }}
          </button>
        </div>
        <small class="text-muted mt-3">{{ t('auth.forgot') }}</small>
      </form>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/stores/auth'
import { useLookups } from '@/stores/lookups'
import { errorMessage } from '@/api/http'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import appLogo from '@/assets/odf-logo.png'

const { t } = useI18n()
const auth = useAuth()
const lookups = useLookups()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const remember = ref(false)
const busy = ref(false)
const error = ref('')
const needCode = ref(false)
const code = ref('')
const codeInput = ref(null)

onMounted(() => lookups.loadBranding())

async function submit() {
  busy.value = true
  error.value = ''
  try {
    const res = await auth.login(email.value, password.value, remember.value, needCode.value ? code.value : null)
    if (res.twoFactor) {
      needCode.value = true
      await nextTick()
      codeInput.value?.focus()
      return
    }
    router.push(route.query.redirect || '/dashboard')
  } catch (e) {
    error.value = errorMessage(e, t('auth.failed'))
  } finally {
    busy.value = false
  }
}
</script>
