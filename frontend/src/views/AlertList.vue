<template>
  <div>
    <button class="btn btn-success mb-3" @click="openForm">
      <i class="bi bi-plus-lg"></i> {{ t('common.addItem', { name: t('alert.singular') }) }}
    </button>
    <div class="card">
      <div class="card-header">{{ t('common.list', { name: t('alert.plural') }) }}</div>
      <div class="card-body">
        <DataTable ref="table" :columns="columns" :fetcher="fetch" :bulk-delete="bulkDelete" export-name="user-alerts">
          <template #actions="{ row }">
            <button class="btn btn-danger btn-xs" @click="remove(row)">{{ t('common.delete') }}</button>
          </template>
        </DataTable>
      </div>
    </div>

    <AppModal :show="show" :title="t('common.addItem', { name: t('alert.singular') })" @close="show = false">
      <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
      <form id="alert-form" @submit.prevent="save">
        <div class="mb-3">
          <label class="form-label">{{ t('alert.text') }} *</label>
          <input v-model="form.alert_text" class="form-control" required maxlength="255" />
        </div>
        <div class="mb-3">
          <label class="form-label">{{ t('alert.link') }}</label>
          <input v-model="form.alert_link" class="form-control" placeholder="/documents/1" />
        </div>
        <label class="form-label">{{ t('alert.users') }} *</label>
        <div class="d-flex gap-1 mb-2">
          <button type="button" class="btn btn-info btn-sm text-white" @click="form.user_ids = lookups.users.map((u) => u.id)">{{ t('common.selectAll') }}</button>
          <button type="button" class="btn btn-info btn-sm text-white" @click="form.user_ids = []">{{ t('common.deselectAll') }}</button>
        </div>
        <select v-model="form.user_ids" multiple class="form-select" size="8" required>
          <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ ln(u) }}</option>
        </select>
      </form>
      <template #footer>
        <button class="btn btn-secondary" @click="show = false">{{ t('common.close') }}</button>
        <button form="alert-form" class="btn btn-danger">{{ t('common.save') }}</button>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '@/api/http'
import { useLookups } from '@/stores/lookups'
import { useToast } from '@/stores/toast'
import DataTable from '@/components/DataTable.vue'
import AppModal from '@/components/AppModal.vue'
import { formatDate, ln } from '@/utils/helpers'

const { t } = useI18n()
const lookups = useLookups()
const toast = useToast()
const table = ref(null)
const show = ref(false)
const error = ref('')
const form = reactive({ alert_text: '', alert_link: '', user_ids: [] })

const columns = [
  { key: 'id', label: 'common.id', sortable: true, width: 80 },
  { key: 'alert_text', label: 'alert.text', sortable: true, searchable: true },
  { key: 'alert_link', label: 'alert.link', searchable: true },
  { key: 'users', label: 'alert.users', format: (r) => r.users.map((u) => u.name).join(', ') },
  { key: 'created_at', label: 'common.createdAt', sortable: true, format: (r) => formatDate(r.created_at) },
]

onMounted(() => lookups.load())
const fetch = async (params) => (await http.get('/user-alerts', { params })).data
const bulkDelete = (ids) => http.post('/user-alerts/bulk-delete', { ids })

function openForm() { Object.assign(form, { alert_text: '', alert_link: '', user_ids: [] }); error.value = ''; show.value = true }
async function save() {
  try {
    await http.post('/user-alerts', form)
    show.value = false
    toast.success(t('common.saved'))
    table.value.reload()
  } catch (e) { error.value = errorMessage(e) }
}
async function remove(row) {
  if (!confirm(t('common.confirmDelete'))) return
  await http.delete(`/user-alerts/${row.id}`)
  table.value.reload()
}
</script>
