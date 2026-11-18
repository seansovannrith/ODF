<template>
  <div class="odf-table">
    <!-- Toolbar: Show [10] entries | Select all | ... | Search -->
    <div class="dt-toolbar">
      <label class="dt-length">
        {{ t('common.show') }}
        <select v-model.number="perPage" class="form-select form-select-sm">
          <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
        </select>
        {{ t('common.entries') }}
      </label>

      <div class="btn-group dt-buttons flex-wrap">
        <template v-if="selectable">
          <button class="btn btn-primary btn-sm" @click="selectAll">{{ t('common.selectAll') }}</button>
          <button class="btn btn-primary btn-sm opacity-75" @click="selected = []">{{ t('common.deselectAll') }}</button>
        </template>
        <button class="btn btn-light btn-sm" @click="exportAs('copy')">{{ t('common.copy') }}</button>
        <button class="btn btn-light btn-sm" @click="exportAs('csv')">{{ t('common.csv') }}</button>
        <button class="btn btn-light btn-sm" @click="exportAs('excel')">{{ t('common.excel') }}</button>
        <button class="btn btn-light btn-sm" @click="exportAs('pdf')">{{ t('common.pdf') }}</button>
        <button class="btn btn-light btn-sm" @click="exportAs('print')">{{ t('common.print') }}</button>
        <div class="position-relative">
          <button class="btn btn-light btn-sm" @click="showCols = !showCols">{{ t('common.columns') }} <i class="bi bi-caret-down-fill small"></i></button>
          <div v-if="showCols" class="dt-cols-menu shadow" @mouseleave="showCols = false">
            <label v-for="c in columns" :key="c.key" class="d-flex gap-2 align-items-center">
              <input type="checkbox" :checked="!hidden.includes(c.key)" @change="toggleCol(c.key)" />
              {{ label(c) }}
            </label>
          </div>
        </div>
        <button v-if="bulkDelete" class="btn btn-danger btn-sm" @click="deleteSelected">{{ t('common.deleteSelected') }}</button>
      </div>

      <label class="dt-search ms-auto">
        {{ t('common.search') }}:
        <input v-model="search" type="search" class="form-control form-control-sm" />
      </label>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-striped table-hover align-middle mb-0">
        <thead>
          <tr>
            <th v-if="selectable" style="width: 40px"></th>
            <th
              v-for="c in visibleColumns" :key="c.key" :style="c.width ? { width: c.width + 'px' } : null"
              :class="{ sortable: c.sortable }" @click="c.sortable && toggleSort(c.key)"
            >
              <span>{{ label(c) }}</span>
              <i v-if="c.sortable" class="bi sort-icon" :class="sortIcon(c.key)"></i>
            </th>
            <th v-if="$slots.actions" style="width: 1%"></th>
          </tr>
          <tr v-if="hasColumnFilters" class="filter-row">
            <th v-if="selectable"></th>
            <th v-for="c in visibleColumns" :key="c.key">
              <select v-if="c.searchable && c.filterOptions" v-model="filters[c.key]" class="form-select form-select-sm">
                <option value="">{{ t('common.all') }}</option>
                <option v-for="o in (typeof c.filterOptions === 'function' ? c.filterOptions() : c.filterOptions)" :key="o.value" :value="o.value">{{ o.raw ? o.label : t(o.label) }}</option>
              </select>
              <input v-else-if="c.searchable" v-model="filters[c.key]" class="form-control form-control-sm" :placeholder="t('common.searchColumn')" />
            </th>
            <th v-if="$slots.actions"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td :colspan="colspan" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>{{ t('common.loading') }}</td></tr>
          <tr v-else-if="!rows.length"><td :colspan="colspan" class="text-center py-4">{{ t('common.noData') }}</td></tr>
          <template v-else>
          <tr v-for="row in rows" :key="row.id" :class="rowClass ? rowClass(row) : null">
            <td v-if="selectable" class="text-center"><input v-model="selected" type="checkbox" class="form-check-input" :value="row.id" /></td>
            <td v-for="c in visibleColumns" :key="c.key">
              <slot :name="`cell-${c.key}`" :row="row" :value="row[c.key]">
                <template v-if="c.type === 'badges'">
                  <span v-for="b in row[c.key] || []" :key="b.id" class="badge bg-info me-1 mb-1">{{ b[c.badgeKey || 'title'] }}</span>
                </template>
                <input v-else-if="c.type === 'boolean'" type="checkbox" class="form-check-input" :checked="!!row[c.key]" disabled />
                <template v-else>{{ display(row, c) }}</template>
              </slot>
            </td>
            <td v-if="$slots.actions" class="text-nowrap"><slot name="actions" :row="row" /></td>
          </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div class="dt-footer">
      <div>{{ t('common.showing', { from: total ? (page - 1) * perPage + 1 : 0, to: Math.min(page * perPage, total), total }) }}</div>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item" :class="{ disabled: page <= 1 }"><a class="page-link" href="#" @click.prevent="go(page - 1)">{{ t('common.previous') }}</a></li>
        <li v-for="p in pageList" :key="p.key" class="page-item" :class="{ active: p.n === page, disabled: !p.n }">
          <a class="page-link" href="#" @click.prevent="p.n && go(p.n)">{{ p.n || '…' }}</a>
        </li>
        <li class="page-item" :class="{ disabled: page >= lastPage }"><a class="page-link" href="#" @click.prevent="go(page + 1)">{{ t('common.next') }}</a></li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/stores/toast'
import { errorMessage } from '@/api/http'
import { stripHtml } from '@/utils/helpers'
import { cellText } from '@/views/settings/resources'

/**
 * Server-side table similar to jQuery DataTables used in the old system.
 * fetcher(params) must return { data: [], total: n } (see backend App\Support\TableQuery).
 */
const props = defineProps({
  columns: { type: Array, required: true },
  fetcher: { type: Function, required: true },
  selectable: { type: Boolean, default: true },
  bulkDelete: { type: Function, default: null },
  exportName: { type: String, default: 'export' },
  defaultSort: { type: String, default: 'id' },
  defaultDir: { type: String, default: 'desc' },
  initialPerPage: { type: Number, default: 10 },
  rowClass: { type: Function, default: null },
})

const { t } = useI18n()
const toast = useToast()

const rows = ref([])
const total = ref(0)
const loading = ref(false)
const page = ref(1)
const perPage = ref(props.initialPerPage)
const search = ref('')
const sort = ref(props.defaultSort)
const dir = ref(props.defaultDir)
const filters = reactive({})
const selected = ref([])
const hidden = ref([])
const showCols = ref(false)

const visibleColumns = computed(() => props.columns.filter((c) => !hidden.value.includes(c.key)))
const hasColumnFilters = computed(() => props.columns.some((c) => c.searchable))
const colspan = computed(() => visibleColumns.value.length + (props.selectable ? 1 : 0) + 1)
const lastPage = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))
const pageList = computed(() => {
  const last = lastPage.value, cur = page.value, out = []
  for (let n = 1; n <= last; n++) {
    if (n === 1 || n === last || Math.abs(n - cur) <= 2) out.push({ key: n, n })
    else if (out[out.length - 1]?.n) out.push({ key: `gap${n}`, n: 0 })
  }
  return out
})

const label = (c) => (c.label?.includes('.') ? t(c.label) : c.label)
const sortIcon = (key) => (sort.value !== key ? 'bi-arrow-down-up text-muted' : dir.value === 'asc' ? 'bi-sort-up' : 'bi-sort-down')

function display(row, c) {
  return cellText(row, c, t)
}

function params(extra = {}) {
  const p = { page: page.value, per_page: perPage.value, search: search.value, sort: sort.value, dir: dir.value }
  Object.entries(filters).forEach(([k, v]) => { if (v) p[`filters[${k}]`] = v })
  return { ...p, ...extra }
}

async function load() {
  loading.value = true
  try {
    const res = await props.fetcher(params())
    rows.value = res.data
    total.value = res.total
  } catch (e) {
    toast.error(errorMessage(e, t('common.error')))
  } finally {
    loading.value = false
  }
}

function go(n) { if (n >= 1 && n <= lastPage.value) { page.value = n; load() } }
function toggleSort(key) {
  if (sort.value === key) dir.value = dir.value === 'asc' ? 'desc' : 'asc'
  else { sort.value = key; dir.value = 'asc' }
  load()
}
function toggleCol(key) {
  hidden.value = hidden.value.includes(key) ? hidden.value.filter((k) => k !== key) : [...hidden.value, key]
}
function selectAll() { selected.value = rows.value.map((r) => r.id) }

async function deleteSelected() {
  if (!selected.value.length) return toast.error(t('common.nothingSelected'))
  if (!confirm(t('common.confirmDeleteSelected', { n: selected.value.length }))) return
  try {
    await props.bulkDelete(selected.value)
    selected.value = []
    toast.success(t('common.deleted'))
    load()
  } catch (e) { toast.error(errorMessage(e)) }
}

// ---- Copy / CSV / Excel / PDF / Print: exports every matching row, not just the current page ----
function exportText(row, c) {
  const v = display(row, c)
  return typeof v === 'string' ? stripHtml(v) : String(v ?? '')
}
async function exportAs(kind) {
  let all
  try {
    all = (await props.fetcher(params({ page: 1, per_page: 0 }))).data
  } catch (e) { return toast.error(errorMessage(e)) }

  const cols = visibleColumns.value.filter((c) => c.type !== 'image')
  const head = cols.map(label)
  const body = all.map((r) => cols.map((c) => exportText(r, c)))

  if (kind === 'copy') {
    await navigator.clipboard.writeText([head, ...body].map((r) => r.join('\t')).join('\n'))
    return toast.success(t('common.copied'))
  }
  if (kind === 'csv') {
    const esc = (s) => `"${String(s).replace(/"/g, '""')}"`
    const csv = '﻿' + [head, ...body].map((r) => r.map(esc).join(',')).join('\r\n')
    return saveBlob(csv, `${props.exportName}.csv`, 'text/csv;charset=utf-8')
  }
  const html = tableHtml(head, body)
  if (kind === 'excel') {
    const doc = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>${html}</body></html>`
    return saveBlob(doc, `${props.exportName}.xls`, 'application/vnd.ms-excel')
  }
  // pdf + print: open a clean printable page (choose "Save as PDF" in the print dialog)
  const w = window.open('', '_blank')
  w.document.write(`<html><head><meta charset="UTF-8"><title>${props.exportName}</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro&display=swap" rel="stylesheet">
    <style>body{font-family:'Kantumruy Pro',sans-serif;font-size:12px}table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #999;padding:4px 6px;text-align:left}th{background:#eee}</style></head>
    <body><h3>${props.exportName}</h3>${html}</body></html>`)
  w.document.close()
  setTimeout(() => w.print(), 600)
}
function tableHtml(head, body) {
  const e = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
  return `<table><thead><tr>${head.map((h) => `<th>${e(h)}</th>`).join('')}</tr></thead><tbody>${
    body.map((r) => `<tr>${r.map((c) => `<td>${e(c)}</td>`).join('')}</tr>`).join('')}</tbody></table>`
}
function saveBlob(content, name, type) {
  const a = document.createElement('a')
  a.href = URL.createObjectURL(new Blob([content], { type }))
  a.download = name
  a.click()
  setTimeout(() => URL.revokeObjectURL(a.href), 10_000)
}

// ---- reload on changes (search is debounced) ----
let timer
watch([search, () => ({ ...filters })], () => {
  clearTimeout(timer)
  timer = setTimeout(() => { page.value = 1; load() }, 350)
}, { deep: true })
watch(perPage, () => { page.value = 1; load() })

onMounted(load)
defineExpose({ reload: load })
</script>
