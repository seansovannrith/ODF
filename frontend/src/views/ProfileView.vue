<template>
  <div class="row g-3">
    <!-- My profile -->
    <div class="col-lg-6">
      <form class="card h-100" @submit.prevent="saveProfile">
        <div class="card-header">{{ t('profile.myProfile') }}</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label fw-bold">{{ t('profile.photo') }}</label>
            <div class="d-flex align-items-center gap-3">
              <div class="profile-photo-frame">
                <AuthImage v-if="auth.user?.photo_url" :key="photoVersion" :url="auth.user.photo_url" class="profile-photo" :alt="t('profile.photo')" />
                <i v-else class="bi bi-person-fill profile-photo-placeholder"></i>
              </div>
              <div class="flex-grow-1">
                <input type="file" accept="image/*" class="form-control" @change="uploadPhoto" />
                <div class="form-text">{{ t('profile.photoHint') }}</div>
                <button v-if="auth.user?.photo_url" type="button" class="btn btn-sm btn-outline-danger mt-2" @click="removePhoto">
                  {{ t('profile.remove') }}
                </button>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ t('common.name') }} <span class="text-danger">*</span></label>
            <input v-model="profile.name" class="form-control" required />
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ t('common.nameKh') }}</label>
            <input v-model="profile.name_kh" class="form-control" />
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ t('user.email') }} <span class="text-danger">*</span></label>
            <input v-model="profile.email" type="email" class="form-control" required />
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ t('user.phone') }}</label>
            <input v-model="profile.phone" class="form-control" />
          </div>
          <div class="text-muted small mb-3">
            {{ t('user.roles') }}:
            <span v-for="r in auth.user?.roles" :key="r.id" class="badge bg-info me-1">{{ r.title }}</span>
            · {{ t('user.position') }}: <strong>{{ ln(auth.user?.position) || '—' }}</strong>
          </div>
          <button class="btn btn-danger">{{ t('common.save') }}</button>
        </div>
      </form>
    </div>

    <!-- Change password -->
    <div v-if="auth.can('profile_password_edit')" class="col-lg-6">
      <form class="card h-100" @submit.prevent="savePassword">
        <div class="card-header">{{ t('profile.changePassword') }}</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label fw-bold">{{ t('profile.current') }} <span class="text-danger">*</span></label>
            <input v-model="pw.current_password" type="password" class="form-control" required />
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ t('profile.newPassword') }} <span class="text-danger">*</span></label>
            <input v-model="pw.password" type="password" class="form-control" minlength="8" required />
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ t('profile.repeat') }} <span class="text-danger">*</span></label>
            <input v-model="pw.password_confirmation" type="password" class="form-control" minlength="8" required />
          </div>
          <button class="btn btn-danger">{{ t('common.save') }}</button>
        </div>
      </form>
    </div>

    <!-- Two-factor authentication -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          {{ t('profile.twoFactor') }}
          <span class="badge" :class="auth.user?.two_factor_enabled ? 'bg-success' : 'bg-secondary'">
            {{ auth.user?.two_factor_enabled ? t('profile.on') : t('profile.off') }}
          </span>
        </div>
        <div class="card-body">
          <template v-if="auth.user?.two_factor_enabled">
            <p class="small text-muted">{{ t('profile.twoFactorOnText') }}</p>
            <div class="d-flex gap-2">
              <input v-model="twoFa.password" type="password" class="form-control" :placeholder="t('auth.password')" />
              <button class="btn btn-outline-danger text-nowrap" @click="disable2fa">{{ t('profile.disable') }}</button>
            </div>
          </template>
          <template v-else-if="twoFa.secret">
            <p class="small">{{ t('profile.scanQr') }}</p>
            <div class="d-flex gap-3 align-items-start flex-wrap">
              <img v-if="twoFa.qr" :src="twoFa.qr" alt="QR" class="qr-img" />
              <div>
                <div class="small text-muted">{{ t('profile.orKey') }}</div>
                <code class="d-block mb-2 user-select-all">{{ twoFa.secret }}</code>
                <div class="d-flex gap-2">
                  <input v-model="twoFa.code" class="form-control" inputmode="numeric" maxlength="6" placeholder="123456" style="width: 130px" />
                  <button class="btn btn-success" @click="confirm2fa">{{ t('profile.confirm') }}</button>
                </div>
              </div>
            </div>
          </template>
          <template v-else>
            <p class="small text-muted">{{ t('profile.twoFactorOffText') }}</p>
            <button class="btn btn-danger" @click="setup2fa">{{ t('profile.enable') }}</button>
          </template>
        </div>
      </div>
    </div>

    <!-- Signature -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">{{ t('profile.signature') }}</div>
        <div class="card-body">
          <p class="small text-muted">{{ t('profile.signatureHint') }}</p>
          <div class="signature-box mb-3">
            <AuthImage v-if="auth.user?.signature_url" :key="sigVersion" :url="auth.user.signature_url" class="signature-img" />
            <span v-else class="text-muted">—</span>
          </div>
          <div class="d-flex gap-2">
            <input type="file" accept="image/*" class="form-control" @change="uploadSignature" />
            <button v-if="auth.user?.signature" class="btn btn-outline-danger" @click="removeSignature">{{ t('profile.remove') }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete account -->
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">{{ t('profile.deleteAccount') }}</div>
        <div class="card-body">
          <p class="small text-muted">{{ t('profile.deleteText') }}</p>
          <div class="d-flex gap-2">
            <input v-model="deletePw" type="password" class="form-control" :placeholder="t('auth.password')" />
            <button class="btn btn-danger" :disabled="!deletePw" @click="deleteAccount">{{ t('common.delete') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import http, { errorMessage, toFormData } from '@/api/http'
import { useAuth } from '@/stores/auth'
import { useToast } from '@/stores/toast'
import AuthImage from '@/components/AuthImage.vue'
import { ln } from '@/utils/helpers'

const { t } = useI18n()
const auth = useAuth()
const toast = useToast()
const router = useRouter()
const sigVersion = ref(0)
const photoVersion = ref(0)
const deletePw = ref('')

const profile = reactive({
  name: auth.user?.name || '', name_kh: auth.user?.name_kh || '', email: auth.user?.email || '', phone: auth.user?.phone || '',
})
const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const twoFa = reactive({ secret: '', qr: '', code: '', password: '' })

async function run(fn, ok = t('common.saved')) {
  try { await fn(); if (ok) toast.success(ok) } catch (e) { toast.error(errorMessage(e)) }
}

const saveProfile = () => run(async () => { auth.user = (await http.put('/profile', profile)).data })
async function uploadPhoto(e) {
  const file = e.target.files[0]
  if (!file) return
  await run(async () => {
    auth.user = (await http.post('/profile/photo', toFormData({ photo: file }))).data
    photoVersion.value++
  })
  e.target.value = ''
}
const removePhoto = () => run(async () => {
  auth.user = (await http.delete('/profile/photo')).data
  photoVersion.value++
})
const savePassword = () => run(async () => {
  await http.put('/profile/password', pw)
  Object.assign(pw, { current_password: '', password: '', password_confirmation: '' })
}, t('profile.changed'))

const setup2fa = () => run(async () => {
  const { data } = await http.post('/profile/two-factor/setup')
  twoFa.secret = data.secret
  twoFa.qr = await QRCode.toDataURL(data.otpauth_url, { width: 180, margin: 1 })
}, null)
const confirm2fa = () => run(async () => {
  auth.user = (await http.post('/profile/two-factor/confirm', { code: twoFa.code })).data
  Object.assign(twoFa, { secret: '', qr: '', code: '' })
})
const disable2fa = () => run(async () => {
  auth.user = (await http.post('/profile/two-factor/disable', { password: twoFa.password })).data
  twoFa.password = ''
})

async function uploadSignature(e) {
  const f = e.target.files[0]
  if (!f) return
  await run(async () => {
    auth.user = (await http.post('/profile/signature', toFormData({ signature: f }))).data
    sigVersion.value++
  })
  e.target.value = ''
}
const removeSignature = () => run(async () => { auth.user = (await http.delete('/profile/signature')).data })

async function deleteAccount() {
  if (!confirm(t('profile.deleteConfirm'))) return
  await run(async () => {
    await http.delete('/profile', { data: { password: deletePw.value } })
    await auth.logout()
    router.push('/login')
  }, null)
}
</script>
