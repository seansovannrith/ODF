<template>
  <div class="card user-activity">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="ua-logo">A</span>
      <span>{{ t('ulog.title') }}</span>
    </div>
    <div class="card-body">
      <!-- Filters -->
      <form class="row g-2 align-items-end mb-3" @submit.prevent="load(1)">
        <div class="col-md-3 small text-muted">{{ t('ulog.showing', { from, to, total }) }}</div>
        <div class="col-md-2">
          <label class="form-label small text-uppercase mb-0">{{ t('ulog.user') }}</label>
          <input v-model="f.user" class="form-control form-control-sm" :placeholder="t('ulog.userPlaceholder')" />
        </div>
        <div class="col-md-2">
          <label class="form-label small text-uppercase mb-0">{{ t('ulog.logType') }}</label>
          <select v-model="f.log_type" class="form-select form-select-sm">
            <option value=""></option>
            <option v-for="lt in logTypes" :key="lt" :value="lt">{{ t(`ulog.types.${lt}`) }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small text-uppercase mb-0">{{ t('ulog.table') }}</label>
          <select v-model="f.table" class="form-select form-select-sm">
            <option value=""></option>
            <option v-for="tb in tables" :key="tb" :value="tb">{{ tb }}</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label small text-uppercase mb-0">{{ t('ulog.from') }}</label>
          <input v-model="f.from" type="date" class="form-control form-control-sm" />
        </div>
        <div class="col-md-1">
          <label class="form-label small text-uppercase mb-0">{{ t('ulog.to') }}</label>
          <input v-model="f.to" type="date" class="form-control form-control-sm" />
        </div>
        <div class="col-md-1">
          <button class="btn btn-warning btn-sm w-100 fw-semibold">{{ t('ulog.filter') }}</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-hover align-middle ua-table">
          <thead>
            <tr><th>{{ t('common.id') }}</th><th>{{ t('ulog.date') }}</th><th>{{ t('ulog.logType') }}</th><th>{{ t('ulog.doneBy') }}</th><th class="text-end">{{ t('common.actions') }}</th></tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
            <tr v-else-if="!rows.length"><td colspan="5" class="text-center py-4">{{ t('common.noData') }}</td></tr>
            <tr v-for="r in rows" v-show="!loading" :key="r.id">
              <td>{{ r.id }}</td>
              <td>{{ formatDate(r.created_at) }}<div class="small text-muted">{{ ago(r.created_at) }}</div></td>
              <td>
                <span class="badge rounded-pill" :class="typeClass(r.log_type)">{{ t(`ulog.types.${r.log_type}`) }}</span>
                <span v-if="r.table_name" class="ms-2">{{ t(r.log_type === 'create' ? 'ulog.to_table' : 'ulog.from_table', { table: r.table_name }) }}</span>
              </td>
              <td>
                <strong>{{ r.user?.name || '—' }}</strong>
                <div class="small text-muted">{{ r.user?.email }}</div>
              </td>
              <td class="text-end"><button class="btn btn-light border btn-sm" @click="show(r)">{{ t('ulog.show') }}</button></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end">
        <ul class="pagination pagination-sm">
          <li class="page-item" :class="{ disabled: page === 1 }"><a class="page-link" href="#" @click.prevent="load(1)">{{ t('ulog.first') }}</a></li>
          <li class="page-item" :class="{ disabled: page === 1 }"><a class="page-link" href="#" @click.prevent="load(page - 1)">{{ t('common.previous') }}</a></li>
          <li v-for="p in pages" :key="p.key" class="page-item" :class="{ active: p.n === page, disabled: !p.n }">
            <a class="page-link" href="#" @click.prevent="p.n && load(p.n)">{{ p.n || '…' }}</a>
          </li>
          <li class="page-item" :class="{ disabled: page >= last }"><a class="page-link" href="#" @click.prevent="load(page + 1)">{{ t('common.next') }}</a></li>
          <li class="page-item" :class="{ disabled: page >= last }"><a class="page-link" href="#" @click.prevent="load(last)">{{ t('ulog.last') }}</a></li>
        </ul>
      </div>

      <div v-if="auth.can('user_log_delete')" class="purge-bar">
        <span>{{ t('ulog.purgeText') }}</span>
        <button class="btn btn-danger btn-sm px-4" @click="purge">{{ t('common.delete') }}</button>
      </div>
    </div>

    <AppModal :show="!!current" :title="t('ulog.detail')" @close="current = null">
      <template v-if="current">
        <table class="table table-bordered detail-table">
          <tbody>
            <tr><th>{{ t('common.id') }}</th><td>{{ current.id }}</td></tr>
            <tr><th>{{ t('ulog.date') }}</th><td>{{ formatDate(current.created_at) }}</td></tr>
            <tr><th>{{ t('ulog.logType') }}</th><td>{{ t(`ulog.types.${current.log_type}`) }}</td></tr>
            <tr><th>{{ t('ulog.table') }}</th><td>{{ current.table_name }} <span v-if="current.subject_id">#{{ current.subject_id }}</span></td></tr>
            <tr><th>{{ t('ulog.doneBy') }}</th><td>{{ current.user?.name }} {{ current.user?.email }}</td></tr>
            <tr><th>IP</th><td>{{ current.ip }}</td></tr>
            <tr><th>Browser</th><td class="small">{{ current.user_agent }}</td></tr>
          </tbody>
        </table>
        <pre v-if="current.data" class="json-box">{{ JSON.stringify(current.data, null, 2) }}</pre>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '@/api/http'
import { useAuth } from '@/stores/auth'
import { useToast } from '@/stores/toast'
import AppModal from '@/components/AppModal.vue'
import { formatDate } from '@/utils/helpers'

const { t } = useI18n()
const auth = useAuth()
const toast = useToast()

const logTypes = ['login', 'logout', 'create', 'edit', 'delete']
const f = reactive({ user: '', log_type: '', table: '', from: '', to: '' })
const rows = ref([])
const tables = ref([])
const total = ref(0)
const page = ref(1)
const perPage = 10
const loading = ref(false)
const current = ref(null)

const last = computed(() => Math.max(1, Math.ceil(total.value / perPage)))
const from = computed(() => (total.value ? (page.value - 1) * perPage + 1 : 0))
const to = computed(() => Math.min(page.value * perPage, total.value))
const pages = computed(() => {
  const out = []
  for (let n = 1; n <= last.value; n++) {
    if (n <= 2 || n > last.value - 2 || Math.abs(n - page.value) <= 3) out.push({ key: n, n })
    else if (out[out.length - 1]?.n) out.push({ key: `g${n}`, n: 0 })
  }
  return out
})

const typeClass = (lt) => ({ login: 'bg-secondary', logout: 'bg-dark', create: 'bg-info', edit: 'bg-warning text-dark', delete: 'bg-danger' }[lt] || 'bg-secondary')

function ago(value) {
  const s = Math.floor((Date.now() - new Date(value)) / 1000)
  if (s < 60) return t('ulog.ago.seconds', { n: s })
  if (s < 3600) return t('ulog.ago.minutes', { n: Math.floor(s / 60) })
  if (s < 86400) return t('ulog.ago.hours', { n: Math.floor(s / 3600) })
  return t('ulog.ago.days', { n: Math.floor(s / 86400) })
}

async function load(p = page.value) {
  page.value = Math.min(Math.max(1, p), last.value || 1)
  loading.value = true
  try {
    const { data } = await http.get('/user-logs', { params: { ...f, page: p, per_page: perPage } })
    rows.value = data.data
    total.value = data.total
    tables.value = data.tables
    page.value = p
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

async function show(r) { current.value = (await http.get(`/user-logs/${r.id}`)).data }

async function purge() {
  if (!confirm(t('ulog.purgeConfirm'))) return
  try {
    const { data } = await http.post('/user-logs/purge', { days: 30 })
    toast.success(t('ulog.purged', { n: data.deleted }))
    load(1)
  } catch (e) { toast.error(errorMessage(e)) }
}

onMounted(() => load(1))
</script>

<style scoped>
.ua-logo { background: #f5b301; color: #222; font-weight: 700; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 3px; }
.ua-table thead th { background: #6c757d; color: #fff; text-transform: uppercase; font-weight: 500; }
.purge-bar { background: #f1f1f1; padding: 12px 16px; display: flex; justify-content: flex-end; align-items: center; gap: 14px; color: #777; }
.json-box { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; max-height: 360px; overflow: auto; font-size: 12px; }
</style>
