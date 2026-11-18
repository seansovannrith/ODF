<template>
  <div>
    <RouterLink v-if="auth.can('document_create')" to="/documents/create" class="btn btn-success mb-3">
      <i class="bi bi-plus-lg"></i> {{ t('doc.add') }}
    </RouterLink>

    <div class="card">
      <div class="card-header">{{ t('doc.list') }}</div>
      <div class="card-body">
        <div class="btn-group mb-3">
          <RouterLink
            v-for="tb in tabs" :key="tb" :to="{ query: { tab: tb } }"
            class="btn btn-primary" :class="{ 'tab-active': tab === tb }"
          >{{ t(`doc.tabs.${tb}`) }}</RouterLink>
        </div>

        <DataTable
          ref="table" :key="tab" :columns="columns" :fetcher="fetch" :selectable="auth.can('document_delete_all')"
          :bulk-delete="auth.can('document_delete_all') ? bulkDelete : null" :initial-per-page="25"
          export-name="documents" :row-class="rowClass"
        >
          <template #cell-description="{ row }">
            <span class="clamp-2" :title="stripHtml(row.description)">{{ stripHtml(row.description) }}</span>
          </template>
          <template #cell-attachments="{ row }">
            <div v-for="a in row.attachments" :key="a.id">
              <a href="#" class="small" @click.prevent="downloadProtectedFile(`/attachments/${a.id}`, a.original_name)">{{ a.original_name }} ({{ fileSize(a.size) }})</a>
            </div>
          </template>
          <template #cell-current="{ row }">
            <span v-if="row.current_comment && !row.is_complete" class="badge" :class="row.current_comment.user_id === auth.user?.id ? 'bg-danger' : 'bg-info'">
              {{ ln(row.current_comment.user) }}
            </span>
          </template>
          <template #cell-dateline="{ row }">
            <span v-if="row.dateline" :class="{ 'text-danger fw-semibold': !row.is_complete && new Date(row.dateline) < new Date() }">
              {{ formatDate(row.dateline) }}
            </span>
          </template>
          <template #cell-status="{ row }">
            <span v-if="row.is_closed" class="badge bg-danger">{{ t('doc.statuses.closed') }}</span>
            <span v-else class="badge" :class="row.is_complete ? 'bg-success' : row.status === 'returned' ? 'bg-warning text-dark' : 'bg-secondary'">
              {{ t(`doc.statuses.${row.status}`) }}
            </span>
          </template>
          <template #actions="{ row }">
            <div class="d-flex flex-column gap-1">
              <RouterLink :to="`/documents/${row.id}`" class="btn btn-primary btn-xs">{{ t('common.view') }}</RouterLink>
              <RouterLink v-if="auth.can('document_edit')" :to="`/documents/${row.id}/edit`" class="btn btn-info btn-xs text-white">{{ t('common.edit') }}</RouterLink>
              <button v-if="auth.can('document_delete')" class="btn btn-danger btn-xs" @click="remove(row)">{{ t('common.delete') }}</button>
            </div>
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import http, { errorMessage } from '@/api/http'
import { useAuth } from '@/stores/auth'
import { useToast } from '@/stores/toast'
import DataTable from '@/components/DataTable.vue'
import { formatDate, fileSize, ln, downloadProtectedFile, stripHtml } from '@/utils/helpers'

const { t } = useI18n()
const auth = useAuth()
const toast = useToast()
const route = useRoute()

const table = ref(null)
const tabs = computed(() => ['active', 'inbox', 'closed', 'completed', 'all'].filter((x) => x !== 'closed' || auth.can('document_complete_access')))
const tab = computed(() => route.query.tab || 'active')

const columns = [
  { key: 'letter_no', label: 'doc.letterNo', sortable: true, searchable: true, format: (r) => r.letter_no || t('doc.none') },
  { key: 'incoming_code', label: 'doc.incomingCode', sortable: true, searchable: true },
  { key: 'document_code', label: 'doc.documentCode', sortable: true, searchable: true },
  { key: 'from_source', label: 'doc.from', sortable: true, searchable: true },
  { key: 'description', label: 'doc.description', searchable: true, format: (r) => stripHtml(r.description) },
  { key: 'attachments', label: 'doc.attachments', format: (r) => r.attachments.map((a) => a.original_name).join(', ') },
  { key: 'current', label: 'doc.currentStep', format: (r) => (r.current_comment && !r.is_complete ? ln(r.current_comment.user) : '') },
  { key: 'dateline', label: 'doc.dateline', sortable: true, format: (r) => formatDate(r.dateline) },
  { key: 'status', label: 'doc.status', format: (r) => t(`doc.statuses.${r.is_closed ? 'closed' : r.status}`) },
]

const fetch = async (params) => (await http.get('/documents', { params: { ...params, tab: tab.value } })).data
const bulkDelete = (ids) => http.post('/documents/bulk-delete', { ids })
const rowClass = (r) => (r.current_comment?.user_id === auth.user?.id && !r.is_complete ? 'row-my-turn' : null)

async function remove(row) {
  if (!confirm(t('common.confirmDelete'))) return
  try {
    await http.delete(`/documents/${row.id}`)
    toast.success(t('common.deleted'))
    table.value?.reload()
  } catch (e) { toast.error(errorMessage(e)) }
}
</script>
