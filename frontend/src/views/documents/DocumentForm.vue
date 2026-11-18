<template>
  <div class="card">
    <div class="card-header">{{ id ? t('doc.editTitle') : t('doc.create') }}</div>
    <form class="card-body" @submit.prevent="save">
      <div v-if="error" class="alert alert-danger">{{ error }}</div>

      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">{{ t('doc.letterNo') }}</label>
          <input v-model="form.letter_no" class="form-control" />
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">{{ t('doc.incomingCode') }}</label>
          <input v-model="form.incoming_code" class="form-control" :placeholder="id ? '' : t('doc.autoCode')" />
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">{{ t('doc.documentCode') }}</label>
          <input v-model="form.document_code" class="form-control" placeholder="52-59-0040" />
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ t('doc.from') }}</label>
        <input v-model="form.from_source" class="form-control" list="org-list" />
        <datalist id="org-list">
          <option v-for="o in lookups.organisations" :key="o.id" :value="ln(o)" />
        </datalist>
      </div>

      <!-- Flow -->
      <fieldset class="flow-box mb-3" :disabled="locked">
        <legend>{{ t('doc.flow') }}</legend>
        <p class="small text-muted mb-2"><i class="bi bi-info-circle"></i> {{ t('doc.flowHint') }}</p>
        <label class="form-label">{{ t('doc.commenters') }}</label>
        <ReviewerPicker v-model="form.reviewers" :users="lookups.users" :exclude="form.final_approver_id ? [form.final_approver_id] : []" :approver-ids="lookups.approver_ids || []" />

        <label class="form-label mt-3">{{ t('doc.finalApprover') }} <span class="text-danger">*</span></label>
        <select v-model="form.final_approver_id" class="form-select" required>
          <option :value="null">{{ t('doc.chooseBoss') }}</option>
          <option v-for="u in bosses" :key="u.id" :value="u.id">{{ ln(u) }} — {{ ln(u.position) }}</option>
        </select>
      </fieldset>

      <div class="mb-3">
        <label class="form-label">{{ t('doc.description') }}</label>
        <RichEditor v-model="form.description" />
      </div>

      <div class="mb-3">
        <label class="form-label">{{ t('doc.attachments') }}</label>
        <ul v-if="existing.length" class="list-unstyled mb-2">
          <li v-for="a in existing" :key="a.id" class="d-flex align-items-center gap-2">
            <i class="bi bi-paperclip"></i>
            <a href="#" @click.prevent="downloadProtectedFile(`/attachments/${a.id}`, a.original_name)">{{ a.original_name }}</a>
            <small class="text-muted">({{ fileSize(a.size) }})</small>
            <button type="button" class="btn btn-link btn-sm text-danger p-0" @click="removeAttachment(a)"><i class="bi bi-trash"></i></button>
          </li>
        </ul>
        <FileDrop v-model="files" />
      </div>

      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">{{ t('doc.documentType') }} <span class="text-danger">*</span></label>
          <select v-model="form.document_type_id" class="form-select" required>
            <option v-for="d in lookups.document_types" :key="d.id" :value="d.id">{{ ln(d) }}</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">{{ t('doc.category') }}</label>
          <select v-model="form.category_id" class="form-select">
            <option :value="null">{{ t('common.choose') }}</option>
            <option v-for="c in lookups.categories" :key="c.id" :value="c.id">{{ ln(c) }}</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">{{ t('doc.organisation') }}</label>
          <select v-model="form.organisation_id" class="form-select">
            <option :value="null">{{ t('common.choose') }}</option>
            <option v-for="o in lookups.organisations" :key="o.id" :value="o.id">{{ ln(o) }}</option>
          </select>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label fw-bold">{{ t('doc.dateline') }}</label>
          <input v-model="form.dateline" type="datetime-local" class="form-control" />
        </div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-danger" :disabled="busy">
          <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span>{{ t('common.save') }}
        </button>
        <RouterLink :to="id ? `/documents/${id}` : '/documents'" class="btn btn-secondary">{{ t('common.cancel') }}</RouterLink>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import http, { errorMessage, toFormData } from '@/api/http'
import { useLookups } from '@/stores/lookups'
import { useToast } from '@/stores/toast'
import RichEditor from '@/components/RichEditor.vue'
import ReviewerPicker from '@/components/ReviewerPicker.vue'
import FileDrop from '@/components/FileDrop.vue'
import { fileSize, ln, downloadProtectedFile, toInputDateTime } from '@/utils/helpers'

const props = defineProps({ id: { type: [String, Number], default: null } })
const { t } = useI18n()
const router = useRouter()
const lookups = useLookups()
const toast = useToast()

const form = reactive({
  letter_no: '', incoming_code: '', document_code: '', from_source: '', description: '',
  document_type_id: null, category_id: null, organisation_id: null,
  dateline: '',
  reviewers: [], final_approver_id: null,
})
const files = ref([])
const existing = ref([])
const locked = ref(false) // flow can't change once the Boss completed
const busy = ref(false)
const error = ref('')

// only users whose role has the document_approve_access permission can be the Boss
const bosses = computed(() => lookups.users.filter((u) => (lookups.approver_ids || []).includes(u.id)))

onMounted(async () => {
  await lookups.load(true)
  if (!props.id) {
    form.document_type_id = lookups.document_types[0]?.id ?? null
    if (bosses.value.length === 1) form.final_approver_id = bosses.value[0].id
    return
  }
  const { data } = await http.get(`/documents/${props.id}`)
  const d = data.document
  Object.assign(form, {
    letter_no: d.letter_no ?? '', incoming_code: d.incoming_code ?? '', document_code: d.document_code ?? '',
    from_source: d.from_source ?? '', description: d.description ?? '',
    document_type_id: d.document_type_id, category_id: d.category_id, organisation_id: d.organisation_id,
    dateline: toInputDateTime(d.dateline),
    // everyone who has not commented yet (done steps stay as they are)
    reviewers: d.comments.filter((c) => !c.is_final && c.status !== 'done').map((c) => c.user_id),
    final_approver_id: d.comments.find((c) => c.is_final)?.user_id ?? null,
  })
  existing.value = d.attachments
  locked.value = d.is_complete
})

async function save() {
  busy.value = true
  error.value = ''
  try {
    const payload = { ...form, attachments: files.value }
    if (!payload.dateline) payload.dateline = null
    if (locked.value) { delete payload.reviewers; delete payload.final_approver_id }
    const fd = toFormData(payload)
    const url = props.id ? `/documents/${props.id}/update` : '/documents'
    const { data } = await http.post(url, fd)
    toast.success(t('common.saved'))
    router.push(`/documents/${props.id || data.id}`)
  } catch (e) {
    error.value = errorMessage(e, t('common.error'))
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    busy.value = false
  }
}

async function removeAttachment(a) {
  if (!confirm(t('common.confirmDelete'))) return
  await http.delete(`/attachments/${a.id}`)
  existing.value = existing.value.filter((x) => x.id !== a.id)
}
</script>
