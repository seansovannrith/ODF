<template>
  <div>
    <button v-if="can('create')" class="btn btn-success mb-3" @click="openForm()">
      <i class="bi bi-plus-lg"></i> {{ t('common.addItem', { name: name }) }}
    </button>

    <div class="card">
      <div class="card-header">{{ t('common.list', { name: name }) }}</div>
      <div class="card-body">
        <DataTable
          ref="table" :columns="res.columns" :fetcher="fetch" :selectable="can('delete')"
          :bulk-delete="can('delete') ? bulkDelete : null" :export-name="resourceKey" :initial-per-page="perPage"
        >
          <template #cell-photo="{ row }">
            <img v-if="row.photo" :src="`/storage/${row.photo}`" class="thumb" alt="" />
          </template>
          <template #actions="{ row }">
            <div class="d-flex gap-1">
              <button v-if="can('show')" class="btn btn-primary btn-xs" @click="view(row)">{{ t('common.view') }}</button>
              <button v-if="can('edit')" class="btn btn-info btn-xs text-white" @click="openForm(row)">{{ t('common.edit') }}</button>
              <button v-if="can('delete')" class="btn btn-danger btn-xs" @click="remove(row)">{{ t('common.delete') }}</button>
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Add / Edit / View -->
    <AppModal :show="!!mode" :title="modalTitle" :size="hasChecklist ? 'modal-xl' : 'modal-lg'" @close="mode = null">
      <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

      <table v-if="mode === 'view'" class="table table-bordered detail-table mb-0">
        <tbody>
          <tr><th>{{ t('common.id') }}</th><td>{{ current.id }}</td></tr>
          <tr v-for="f in visibleFields" :key="f.key">
            <th>{{ t(f.label) }}</th>
            <td>
              <img v-if="f.type === 'file' && current.photo" :src="`/storage/${current.photo}`" class="thumb-lg" alt="" />
              <template v-else-if="f.type === 'multiselect' || f.type === 'checklist'">
                <span v-for="o in selectedOptions(f)" :key="o.value" class="badge bg-info me-1 mb-1">{{ o.label }}</span>
              </template>
              <span v-else>{{ viewValue(f) }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <form v-else id="crud-form" @submit.prevent="save">
        <div v-for="f in visibleFields" :key="f.key" class="mb-3">
          <template v-if="f.type === 'checkbox'">
            <div class="form-check">
              <input :id="f.key" v-model="form[f.key]" type="checkbox" class="form-check-input" />
              <label :for="f.key" class="form-check-label">{{ t(f.label) }}</label>
            </div>
            <small v-if="f.hint" class="text-muted">{{ t(f.hint) }}</small>
          </template>

          <template v-else-if="f.type === 'multiselect'">
            <label class="form-label">{{ t(f.label) }} <span v-if="f.required" class="text-danger">*</span></label>
            <div class="check-box-list">
              <label v-for="o in optionsFor(f)" :key="o.value" class="form-check">
                <input v-model="form[f.key]" type="checkbox" class="form-check-input" :value="o.value" />
                <span class="form-check-label">{{ o.label }}</span>
              </label>
            </div>
          </template>

          <template v-else-if="f.type === 'checklist'">
            <div class="d-flex align-items-center gap-2 mb-2">
              <label class="form-label mb-0">{{ t(f.label) }}</label>
              <button type="button" class="btn btn-info btn-xs text-white" @click="form[f.key] = optionsFor(f).map((o) => o.value)">{{ t('common.selectAll') }}</button>
              <button type="button" class="btn btn-info btn-xs text-white" @click="form[f.key] = []">{{ t('common.deselectAll') }}</button>
              <input v-model="checkFilter" class="form-control form-control-sm ms-auto" style="max-width: 220px" :placeholder="t('common.search')" />
            </div>
            <div class="checklist-groups">
              <div v-for="g in groupedOptions(f)" :key="g.name" class="checklist-group">
                <div class="d-flex justify-content-between align-items-center">
                  <strong>{{ g.name }}</strong>
                  <input type="checkbox" class="form-check-input" :checked="g.items.every((o) => form[f.key].includes(o.value))" @change="toggleGroup(f, g, $event.target.checked)" />
                </div>
                <label v-for="o in g.items" :key="o.value" class="form-check small">
                  <input v-model="form[f.key]" type="checkbox" class="form-check-input" :value="o.value" />
                  <span class="form-check-label">{{ o.label }}</span>
                </label>
              </div>
            </div>
          </template>

          <template v-else>
            <label class="form-label">{{ t(f.label) }} <span v-if="f.required" class="text-danger">*</span></label>
            <textarea v-if="f.type === 'textarea'" v-model="form[f.key]" class="form-control" rows="3"></textarea>
            <select v-else-if="f.type === 'select'" v-model="form[f.key]" class="form-select" :required="f.required">
              <option :value="null">{{ t('common.choose') }}</option>
              <option v-for="o in optionsFor(f)" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <input v-else-if="f.type === 'file'" type="file" accept="image/*" class="form-control" @change="file = $event.target.files[0]" />
            <input
              v-else v-model="form[f.key]" class="form-control" :required="f.required && !(f.type === 'password' && current)"
              :type="['password', 'email', 'number', 'date'].includes(f.type) ? f.type : 'text'"
            />
            <small v-if="f.hint && (f.type !== 'password' || current)" class="text-muted">{{ t(f.hint) }}</small>
          </template>
        </div>
      </form>

      <template #footer>
        <button class="btn btn-secondary" @click="mode = null">{{ t('common.close') }}</button>
        <button v-if="mode === 'view' && can('edit')" class="btn btn-info text-white" @click="openForm(current)">{{ t('common.edit') }}</button>
        <button v-if="mode !== 'view'" form="crud-form" class="btn btn-danger" :disabled="busy">{{ t('common.save') }}</button>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage, toFormData } from '@/api/http'
import { useAuth } from '@/stores/auth'
import { useLookups } from '@/stores/lookups'
import { useToast } from '@/stores/toast'
import DataTable from '@/components/DataTable.vue'
import AppModal from '@/components/AppModal.vue'
import { getResource } from './resources'
import { formatDate, ln } from '@/utils/helpers'

/** Generic list + modal form used by Settings and User management screens. */
const props = defineProps({ resourceKey: { type: String, required: true } })
const { t } = useI18n()
const auth = useAuth()
const lookups = useLookups()
const toast = useToast()

const res = computed(() => getResource(props.resourceKey))
const name = computed(() => t(res.value.title))
const perPage = computed(() => (props.resourceKey === 'permissions' ? 100 : 10))
const table = ref(null)
const mode = ref(null) // 'add' | 'edit' | 'view'
const current = ref(null)
const form = ref({})
const file = ref(null)
const busy = ref(false)
const error = ref('')
const checkFilter = ref('')

const can = (action) => auth.can(`${res.value.perm}_${action}`)
const hasChecklist = computed(() => res.value.fields.some((f) => f.type === 'checklist'))
const visibleFields = computed(() => res.value.fields.filter((f) => !(f.editOnly && !current.value)))
const modalTitle = computed(() => t(mode.value === 'view' ? 'common.viewItem' : mode.value === 'edit' ? 'common.editItem' : 'common.addItem', { name: name.value }))

onMounted(() => lookups.load(true))

const fetch = async (params) => (await http.get(res.value.endpoint, { params })).data
const bulkDelete = (ids) => http.post(`${res.value.endpoint}/bulk-delete`, { ids })

function optionsFor(f) {
  if (f.options) return f.options.map((o) => ({ value: o.value, label: t(o.label) }))
  return (lookups[f.lookup] || []).map((o) => ({ value: o.id, label: f.labelKey ? o[f.labelKey] : ln(o) }))
}
function selectedOptions(f) {
  const ids = fieldValue(f, current.value) || []
  return optionsFor(f).filter((o) => ids.includes(o.value))
}
/** permissions grouped by their prefix: document_type_create -> "document_type" */
function groupedOptions(f) {
  const q = checkFilter.value.toLowerCase()
  const groups = {}
  optionsFor(f).filter((o) => !q || o.label.toLowerCase().includes(q)).forEach((o) => {
    const name = o.label.includes('_') ? o.label.slice(0, o.label.lastIndexOf('_')) : o.label
    ;(groups[name] ||= []).push(o)
  })
  return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b)).map(([name, items]) => ({ name, items }))
}
function toggleGroup(f, g, on) {
  const ids = g.items.map((o) => o.value)
  form.value[f.key] = on ? [...new Set([...form.value[f.key], ...ids])] : form.value[f.key].filter((v) => !ids.includes(v))
}

function fieldValue(f, row) {
  if (!row) return undefined
  return f.fromRow ? f.fromRow(row) : row[f.key]
}
function viewValue(f) {
  const v = fieldValue(f, current.value)
  if (f.type === 'checkbox') return v ? '✔' : '✘'
  if (f.type === 'select') return optionsFor(f).find((o) => o.value === v)?.label ?? ''
  if (f.type === 'password') return '••••••'
  if (f.type === 'date') return formatDate(v, false)
  return v ?? ''
}

function emptyValue(f) {
  if (f.type === 'checkbox') return f.default ?? false
  if (f.type === 'multiselect' || f.type === 'checklist') return []
  return f.default ?? null
}
function openForm(row = null) {
  error.value = ''
  file.value = null
  checkFilter.value = ''
  current.value = row
  mode.value = row ? 'edit' : 'add'
  const f = {}
  res.value.fields.forEach((fd) => {
    if (fd.type === 'file' || (fd.editOnly && !row)) return
    if (!row) { f[fd.key] = emptyValue(fd); return }
    if (fd.type === 'password') { f[fd.key] = ''; return }
    const v = fieldValue(fd, row)
    f[fd.key] = v === undefined || v === null ? emptyValue(fd) : (fd.type === 'date' ? String(v).slice(0, 10) : v)
  })
  form.value = f
}
function view(row) { current.value = row; mode.value = 'view' }

async function save() {
  busy.value = true
  error.value = ''
  try {
    const id = current.value?.id
    const url = id ? `${res.value.endpoint}/${id}` : res.value.endpoint
    if (res.value.multipart) {
      const fd = toFormData({ ...form.value, ...(file.value ? { photo: file.value } : {}) })
      await http.post(url, fd)
    } else if (id) {
      await http.put(url, form.value)
    } else {
      await http.post(url, form.value)
    }
    toast.success(t('common.saved'))
    mode.value = null
    table.value.reload()
    lookups.load(true)
    if (props.resourceKey === 'roles' || props.resourceKey === 'users') auth.fetchMe()
  } catch (e) {
    error.value = errorMessage(e, t('common.error'))
  } finally {
    busy.value = false
  }
}

async function remove(row) {
  if (!confirm(t('common.confirmDelete'))) return
  try {
    await http.delete(`${res.value.endpoint}/${row.id}`)
    toast.success(t('common.deleted'))
    table.value.reload()
  } catch (e) { toast.error(errorMessage(e)) }
}
</script>
