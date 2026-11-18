<template>
  <div>
    <div class="card">
      <div class="card-header">{{ t('common.list', { name: t('audit.singular') }) }}</div>
      <div class="card-body">
        <DataTable :columns="columns" :fetcher="fetch" :selectable="false" :initial-per-page="100" export-name="audit-logs">
          <template #cell-description="{ row }">
            <span class="badge" :class="badge(row.description)">{{ t(`audit.events.${row.description}`) }}</span>
          </template>
          <template #actions="{ row }">
            <button v-if="auth.can('audit_log_show')" class="btn btn-primary btn-xs" @click="show(row)">{{ t('common.view') }}</button>
          </template>
        </DataTable>
      </div>
    </div>

    <AppModal :show="!!current" :title="t('common.viewItem', { name: t('audit.singular') })" size="modal-xl" @close="current = null">
      <template v-if="current">
        <table class="table table-bordered detail-table">
          <tbody>
            <tr><th>{{ t('common.id') }}</th><td>{{ current.id }}</td></tr>
            <tr><th>{{ t('audit.description') }}</th><td>{{ t(`audit.events.${current.description}`) }}</td></tr>
            <tr><th>{{ t('audit.subjectId') }}</th><td>{{ current.subject_id }}</td></tr>
            <tr><th>{{ t('audit.subjectType') }}</th><td>{{ current.subject_type }}</td></tr>
            <tr><th>{{ t('audit.user') }}</th><td>{{ current.user_id }} {{ current.user ? `– ${current.user.name} (${current.user.email})` : '' }}</td></tr>
            <tr><th>{{ t('audit.host') }}</th><td>{{ current.host }}</td></tr>
            <tr><th>{{ t('common.createdAt') }}</th><td>{{ formatDate(current.created_at) }}</td></tr>
          </tbody>
        </table>

        <h6>{{ t('audit.properties') }}</h6>
        <!-- updated: old -> new -->
        <table v-if="current.properties?.new" class="table table-sm table-bordered">
          <thead><tr><th>{{ t('audit.field') }}</th><th>{{ t('audit.old') }}</th><th>{{ t('audit.new') }}</th></tr></thead>
          <tbody>
            <tr v-for="(v, k) in current.properties.new" :key="k">
              <td><code>{{ k }}</code></td>
              <td class="text-danger small">{{ short(current.properties.old?.[k]) }}</td>
              <td class="text-success small">{{ short(v) }}</td>
            </tr>
          </tbody>
        </table>
        <table v-else class="table table-sm table-bordered">
          <tbody>
            <tr v-for="(v, k) in current.properties" :key="k"><td style="width: 220px"><code>{{ k }}</code></td><td class="small">{{ short(v) }}</td></tr>
          </tbody>
        </table>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '@/api/http'
import { useAuth } from '@/stores/auth'
import DataTable from '@/components/DataTable.vue'
import AppModal from '@/components/AppModal.vue'
import { formatDate, stripHtml } from '@/utils/helpers'

const { t } = useI18n()
const auth = useAuth()
const current = ref(null)

const columns = [
  { key: 'id', label: 'common.id', sortable: true, width: 100 },
  {
    key: 'description', label: 'audit.description', sortable: true, searchable: true, format: (r) => r.description,
    filterOptions: ['created', 'updated', 'deleted'].map((e) => ({ value: e, label: `audit.events.${e}` })),
  },
  { key: 'subject_id', label: 'audit.subjectId', sortable: true, searchable: true },
  { key: 'subject_type', label: 'audit.subjectType', sortable: true, searchable: true },
  { key: 'user_id', label: 'audit.userId', sortable: true, searchable: true, format: (r) => (r.user ? `${r.user_id} – ${r.user.name}` : r.user_id ?? '') },
  { key: 'host', label: 'audit.host', sortable: true, searchable: true },
  { key: 'created_at', label: 'common.createdAt', sortable: true, type: 'datetime' },
]

const badge = (d) => ({ created: 'bg-success', updated: 'bg-warning text-dark', deleted: 'bg-danger' }[d] || 'bg-secondary')
const short = (v) => {
  if (v === null || v === undefined) return '—'
  const s = typeof v === 'object' ? JSON.stringify(v) : stripHtml(String(v))
  return s.length > 300 ? s.slice(0, 300) + '…' : s
}

const fetch = async (params) => (await http.get('/audit-logs', { params })).data
async function show(row) { current.value = (await http.get(`/audit-logs/${row.id}`)).data }
</script>
