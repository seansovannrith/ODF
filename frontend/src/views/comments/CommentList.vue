<template>
  <div>
    <button v-if="auth.can('comment_grand_edit')" class="btn btn-success mb-3" @click="openAdd">
      <i class="bi bi-plus-lg"></i> {{ t('comment.add') }}
    </button>

    <div class="card">
      <div class="card-header">{{ t('common.list', { name: t('comment.plural') }) }}</div>
      <div class="card-body">
        <DataTable
          ref="table" :columns="columns" :fetcher="fetch" :initial-per-page="100" export-name="comments"
          :selectable="auth.can('comment_delete')" :bulk-delete="auth.can('comment_delete') ? bulkDelete : null"
        >
          <template #cell-comment="{ row }"><span class="clamp-2">{{ stripHtml(row.comment) }}</span></template>
          <template #[docSlot]="{ row }">
            <RouterLink :to="`/documents/${row.document_id}`">{{ row.document?.document_code || row.document?.incoming_code }}</RouterLink>
          </template>
          <template #cell-status="{ row }">
            <span class="badge" :class="row.status === 'done' ? 'bg-success' : row.status === 'current' ? 'bg-warning text-dark' : 'bg-light text-muted border'">
              {{ t(`doc.${row.status}`) }}
            </span>
          </template>
          <template #actions="{ row }">
            <div class="d-flex gap-1">
              <RouterLink v-if="auth.can('comment_show')" :to="`/comments/${row.id}`" class="btn btn-primary btn-xs">{{ t('common.view') }}</RouterLink>
              <button v-if="canEdit(row)" class="btn btn-info btn-xs text-white" @click="openEdit(row)">{{ t('common.edit') }}</button>
              <button v-if="auth.can('comment_delete') && !row.is_final" class="btn btn-danger btn-xs" @click="remove(row)">{{ t('common.delete') }}</button>
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Add / edit comment -->
    <AppModal :show="!!mode" :title="mode === 'add' ? t('comment.add') : t('doc.editComment')" @close="mode = null">
      <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

      <template v-if="mode === 'add'">
        <p class="small text-muted"><i class="bi bi-info-circle"></i> {{ t('comment.addHint') }}</p>
        <div class="mb-3">
          <label class="form-label">{{ t('comment.document') }} <span class="text-danger">*</span></label>
          <input v-model="docQuery" class="form-control mb-1" :placeholder="t('comment.searchDocument')" @input="searchDocs" />
          <select v-model="form.document_id" class="form-select" size="5">
            <option v-for="d in docOptions" :key="d.id" :value="d.id">
              {{ d.document_code || d.incoming_code }} — {{ d.from_source }}
            </option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ t('comment.user') }} <span class="text-danger">*</span></label>
          <select v-model="form.user_id" class="form-select">
            <option :value="null">{{ t('common.choose') }}</option>
            <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ ln(u) }} — {{ ln(u.position) }}</option>
          </select>
        </div>
      </template>
      <div v-else class="mb-2 small text-muted">
        {{ current?.document?.document_code }} · {{ ln(current?.user) }}
      </div>

      <label class="form-label">{{ t('comment.comment') }}</label>
      <RichEditor v-model="form.comment" min-height="120px" />

      <template v-if="mode === 'add' || auth.can('comment_grand_edit')">
        <div class="form-check mt-3">
          <input id="c-submit" v-model="form.is_submit" type="checkbox" class="form-check-input" />
          <label for="c-submit" class="form-check-label">{{ t('comment.submit') }}</label>
        </div>
        <div class="form-check">
          <input id="c-esign" v-model="form.is_esign" type="checkbox" class="form-check-input" />
          <label for="c-esign" class="form-check-label">{{ t('comment.isEsign') }}</label>
        </div>
      </template>

      <template #footer>
        <button class="btn btn-secondary" @click="mode = null">{{ t('common.close') }}</button>
        <button class="btn btn-danger" :disabled="busy" @click="save">{{ t('common.save') }}</button>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '@/api/http'
import { useAuth } from '@/stores/auth'
import { useLookups } from '@/stores/lookups'
import { useToast } from '@/stores/toast'
import DataTable from '@/components/DataTable.vue'
import AppModal from '@/components/AppModal.vue'
import RichEditor from '@/components/RichEditor.vue'
import { formatDate, ln, stripHtml } from '@/utils/helpers'

const { t } = useI18n()
const auth = useAuth()
const lookups = useLookups()
const toast = useToast()

const table = ref(null)
const mode = ref(null)
const current = ref(null)
const busy = ref(false)
const error = ref('')
const form = reactive({ document_id: null, user_id: null, comment: '', is_submit: false, is_esign: false })
const docQuery = ref('')
const docOptions = ref([])

// slot names containing a dot must be dynamic (a dot would be read as a modifier)
const docSlot = 'cell-document.document_code'
const yesNo = [{ value: '1', label: 'common.yes' }, { value: '0', label: 'common.no' }]
const columns = [
  { key: 'id', label: 'common.id', sortable: true, searchable: true, width: 110 },
  { key: 'is_submit', label: 'comment.submit', sortable: true, searchable: true, type: 'boolean', filterOptions: yesNo },
  { key: 'document.document_code', label: 'comment.document', searchable: true, format: (r) => r.document?.document_code || r.document?.incoming_code },
  {
    key: 'user_id', label: 'comment.user', searchable: true, format: (r) => ln(r.user),
    filterOptions: () => lookups.users.map((u) => ({ value: u.id, label: ln(u), raw: true })),
  },
  { key: 'is_esign', label: 'comment.isEsign', sortable: true, searchable: true, type: 'boolean', filterOptions: yesNo },
  { key: 'comment', label: 'comment.comment', searchable: true, format: (r) => stripHtml(r.comment) },
  {
    key: 'status', label: 'comment.status', sortable: true, searchable: true, format: (r) => t(`doc.${r.status}`),
    filterOptions: [{ value: 'current', label: 'doc.current' }, { value: 'waiting', label: 'doc.waiting' }, { value: 'done', label: 'doc.done' }],
  },
  { key: 'submitted_at', label: 'comment.submittedAt', sortable: true, format: (r) => formatDate(r.submitted_at) },
]

onMounted(() => lookups.load())

const fetch = async (params) => (await http.get('/comments', { params })).data
const bulkDelete = (ids) => http.post('/comments/bulk-delete', { ids })

function canEdit(row) {
  if (auth.can('comment_grand_edit')) return true
  return auth.can('comment_edit') && row.user_id === auth.user?.id && row.status === 'done'
}

let timer
function searchDocs() {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    const { data } = await http.get('/documents', { params: { tab: 'all', search: docQuery.value, per_page: 20 } })
    docOptions.value = data.data
  }, 300)
}

function openAdd() {
  Object.assign(form, { document_id: null, user_id: null, comment: '', is_submit: false, is_esign: false })
  error.value = ''
  docQuery.value = ''
  searchDocs()
  current.value = null
  mode.value = 'add'
}
function openEdit(row) {
  Object.assign(form, { comment: row.comment || '', is_submit: row.is_submit, is_esign: row.is_esign })
  error.value = ''
  current.value = row
  mode.value = 'edit'
}

async function save() {
  busy.value = true
  error.value = ''
  try {
    if (mode.value === 'add') {
      await http.post('/comments', form)
    } else {
      await http.put(`/comments/${current.value.id}`, { comment: form.comment, is_submit: form.is_submit, is_esign: form.is_esign })
    }
    toast.success(t('common.saved'))
    mode.value = null
    table.value.reload()
  } catch (e) {
    error.value = errorMessage(e, t('common.error'))
  } finally {
    busy.value = false
  }
}

async function remove(row) {
  if (!confirm(t('doc.confirmRemove'))) return
  try {
    await http.delete(`/comments/${row.id}`)
    toast.success(t('common.deleted'))
    table.value.reload()
  } catch (e) { toast.error(errorMessage(e)) }
}
</script>
