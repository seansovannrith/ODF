<template>
  <div v-if="doc">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <span>{{ t('doc.show') }}</span>
        <span class="badge" :class="statusClass">{{ statusText }}</span>
        <div class="ms-auto d-flex gap-2 flex-wrap">
          <RouterLink v-if="perm.can_print" :to="`/documents/${doc.id}/print`" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer"></i> {{ t('common.print') }}
          </RouterLink>
          <RouterLink v-if="perm.can_edit" :to="`/documents/${doc.id}/edit`" class="btn btn-info btn-sm text-white">
            <i class="bi bi-pencil"></i> {{ t('common.edit') }}
          </RouterLink>
          <button v-if="perm.can_close" class="btn btn-sm" :class="doc.is_closed ? 'btn-success' : 'btn-danger'" @click="toggleClose">
            <i class="bi" :class="doc.is_closed ? 'bi-unlock' : 'bi-lock'"></i> {{ doc.is_closed ? t('doc.reopen') : t('doc.closeIt') }}
          </button>
        </div>
      </div>
      <div class="card-body">
        <table class="table table-bordered table-striped detail-table mb-0">
          <tbody>
            <tr><th>{{ t('doc.letterNo') }}</th><td>{{ doc.letter_no || t('doc.none') }}</td></tr>
            <tr><th>{{ t('doc.incomingCode') }}</th><td>{{ doc.incoming_code }}</td></tr>
            <tr><th>{{ t('doc.documentCode') }}</th><td>{{ doc.document_code }}</td></tr>
            <tr><th>{{ t('doc.from') }}</th><td>{{ doc.from_source }}</td></tr>
            <tr><th>{{ t('doc.description') }}</th><td class="rich" v-html="doc.description"></td></tr>
            <tr>
              <th>{{ t('doc.commenters') }}</th>
              <td>
                <div class="flow-steps">
                  <span v-for="c in doc.comments" :key="c.id" class="flow-step" :class="perm.can_status_view ? `st-${c.status}` : 'st-plain'" :title="t(`doc.${c.status}`)">
                    <i class="bi" :class="stepIcon(c)"></i>
                    {{ ln(c.user) }}
                    <i v-if="c.is_final" class="bi bi-star-fill text-warning ms-1"></i>
                  </span>
                </div>
              </td>
            </tr>
            <tr>
              <th>{{ t('doc.attachments') }}</th>
              <td>
                <div v-for="a in doc.attachments" :key="a.id" class="mb-1">
                  <a href="#" @click.prevent="downloadProtectedFile(`/attachments/${a.id}`, a.original_name)">{{ a.original_name }} ({{ fileSize(a.size) }})</a>
                  <button class="btn btn-success btn-xs ms-2" @click="downloadProtectedFile(`/attachments/${a.id}`, a.original_name)">
                    <i class="bi bi-download"></i>
                  </button>
                </div>
                <div v-if="perm.can_upload" class="mt-2">
                  <FileDrop v-model="newFiles" />
                  <button v-if="newFiles.length" class="btn btn-sm btn-primary mt-2" @click="uploadFiles">{{ t('doc.uploadMore') }}</button>
                </div>
              </td>
            </tr>
            <tr><th>{{ t('doc.documentType') }}</th><td>{{ ln(doc.document_type) }}</td></tr>
            <tr v-if="doc.category"><th>{{ t('doc.category') }}</th><td>{{ ln(doc.category) }}</td></tr>
            <tr><th>{{ t('doc.dateline') }}</th><td :class="{ 'text-danger fw-bold': overdue }">{{ formatDate(doc.dateline) }}</td></tr>
            <tr><th>{{ t('doc.dateComplete') }}</th><td>{{ formatDate(doc.date_complete) }}</td></tr>
            <tr><th>{{ t('doc.complete') }}</th><td><input type="checkbox" class="form-check-input" :checked="doc.is_complete" disabled /></td></tr>
            <tr><th>{{ t('doc.closedDoc') }}</th><td><input type="checkbox" class="form-check-input" :checked="doc.is_closed" disabled /></td></tr>
            <tr><th>{{ t('doc.createdBy') }}</th><td>{{ ln(doc.creator) }}</td></tr>
            <tr><th>{{ t('doc.createdAt') }}</th><td>{{ formatDate(doc.created_at) }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- It is my turn: write comment / complete & sign / return -->
    <div v-if="perm.is_my_turn" class="card mb-3 my-turn-card">
      <div class="card-header text-white" :class="perm.is_final_step ? 'bg-success' : 'bg-primary'">
        <i class="bi" :class="perm.is_final_step ? 'bi-pen' : 'bi-chat-dots'"></i>
        {{ perm.is_final_step ? t('doc.yourTurnBoss') : t('doc.yourTurn') }}
      </div>
      <div class="card-body">
        <label class="form-label fw-semibold">{{ t('doc.yourComment') }}</label>
        <RichEditor v-model="myComment" min-height="140px" />

        <template v-if="perm.is_final_step">
          <div class="form-check mt-3">
            <input id="esign" v-model="esign" type="checkbox" class="form-check-input" :disabled="!auth.user?.signature" />
            <label for="esign" class="form-check-label">{{ t('doc.esign') }}</label>
            <div v-if="!auth.user?.signature" class="small text-danger">
              {{ t('doc.noSignature') }} <RouterLink to="/profile/password">→</RouterLink>
            </div>
          </div>
          <div class="form-check">
            <input id="closeAfter" v-model="closeAfter" type="checkbox" class="form-check-input" />
            <label for="closeAfter" class="form-check-label">{{ t('doc.closeAfter') }}</label>
          </div>
        </template>

        <div class="d-flex flex-wrap gap-2 mt-3 align-items-end">
          <button class="btn" :class="perm.is_final_step ? 'btn-success' : 'btn-primary'" :disabled="busy" @click="submit">
            <i class="bi" :class="perm.is_final_step ? 'bi-check2-circle' : 'bi-send'"></i>
            {{ perm.is_final_step ? t('doc.completeSign') : t('doc.submit') }}
          </button>

          <div v-if="perm.return_targets.length" class="d-flex gap-2 ms-auto align-items-end flex-wrap">
            <div>
              <label class="form-label small mb-0">{{ t('doc.returnTo') }}</label>
              <select v-model="returnTo" class="form-select form-select-sm">
                <option :value="null">{{ t('common.choose') }}</option>
                <option v-for="r in perm.return_targets" :key="r.id" :value="r.id">{{ ln(r) }}</option>
              </select>
            </div>
            <input v-model="returnNote" class="form-control form-control-sm" style="width: 220px" :placeholder="t('doc.returnNote')" />
            <button class="btn btn-warning btn-sm" :disabled="!returnTo || busy" @click="sendBack">
              <i class="bi bi-arrow-return-left"></i> {{ t('doc.returnBtn') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Comments timeline + history -->
    <div class="card card-outline-primary mb-3">
      <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs">
          <li class="nav-item"><a href="#" class="nav-link" :class="{ active: tab === 'comments' }" @click.prevent="tab = 'comments'">{{ t('comment.plural') }}</a></li>
          <li class="nav-item"><a href="#" class="nav-link" :class="{ active: tab === 'history' }" @click.prevent="tab = 'history'">{{ t('doc.history') }}</a></li>
        </ul>
      </div>
      <div class="card-body">
        <template v-if="tab === 'comments'">
          <div v-for="c in doc.comments" :key="c.id" class="comment-item" :class="{ 'is-final': c.is_final }">
            <div class="d-flex justify-content-between align-items-start">
              <h6 class="comment-head">{{ personLabel(c.user) }}</h6>
              <small v-if="c.submitted_at" class="text-muted text-nowrap">{{ formatDate(c.submitted_at) }}</small>
            </div>
            <div class="d-flex gap-2 align-items-start">
              <span class="avatar-lg"><i class="bi bi-person-fill"></i></span>
              <div v-if="c.status === 'done'" class="flex-grow-1">
                <div v-if="editing === c.id">
                  <RichEditor v-model="editText" min-height="100px" />
                  <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-primary btn-sm" @click="saveEdit(c)">{{ t('common.save') }}</button>
                    <button class="btn btn-secondary btn-sm" @click="editing = null">{{ t('common.cancel') }}</button>
                  </div>
                </div>
                <div v-else class="bubble">
                  <div class="rich" v-html="c.comment || '—'"></div>
                  <div v-if="c.is_esign" class="mt-2 signed">
                    <AuthImage :url="c.user?.signature_url" class="signature-img" />
                    <span class="badge bg-success"><i class="bi bi-patch-check"></i> {{ t('doc.signed') }}</span>
                  </div>
                </div>
              </div>
              <div v-else-if="perm.can_status_view" class="pt-2">
                <span v-if="c.status === 'current'" class="badge bg-warning text-dark"><i class="bi bi-clock"></i> {{ t('doc.current') }}</span>
                <span v-else class="badge bg-light text-muted border"><i class="bi bi-hourglass"></i> {{ t('doc.waiting') }}</span>
              </div>
            </div>
            <div class="comment-tools">
              <button v-if="perm.can_remove_step && !c.is_final && !doc.is_complete" class="btn btn-link btn-sm" :title="t('common.delete')" @click="removeStep(c)"><i class="bi bi-x-lg"></i></button>
              <button v-if="canEdit(c)" class="btn btn-link btn-sm" :title="t('doc.editComment')" @click="startEdit(c)"><i class="bi bi-pencil-square"></i></button>
              <button v-if="perm.can_manage_flow && c.status === 'done'" class="btn btn-link btn-sm" :title="t('doc.reopen')" @click="resetStep(c)"><i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
          </div>
        </template>

        <ul v-else class="list-unstyled history">
          <li v-for="h in doc.histories" :key="h.id">
            <small class="text-muted">{{ formatDate(h.created_at) }}</small>
            <strong class="mx-2">{{ t(`doc.historyActions.${h.action}`) }}</strong>
            <span>{{ ln(h.user) }}</span>
            <div v-if="h.note" class="small text-muted ms-3">{{ h.note }}</div>
          </li>
        </ul>
      </div>
    </div>

    <RouterLink to="/documents" class="btn btn-secondary">{{ t('common.back') }}</RouterLink>
  </div>
  <div v-else class="text-center py-5"><span class="spinner-border"></span></div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http, { errorMessage, toFormData } from '@/api/http'
import { useAuth } from '@/stores/auth'
import { useToast } from '@/stores/toast'
import RichEditor from '@/components/RichEditor.vue'
import FileDrop from '@/components/FileDrop.vue'
import AuthImage from '@/components/AuthImage.vue'
import { fileSize, formatDate, ln, downloadProtectedFile, personLabel, stripHtml } from '@/utils/helpers'

const props = defineProps({ id: { type: [String, Number], required: true } })
const { t } = useI18n()
const auth = useAuth()
const toast = useToast()

const doc = ref(null)
const perm = ref({})
const tab = ref('comments')
const myComment = ref('')
const esign = ref(false)
const closeAfter = ref(false)
const returnTo = ref(null)
const returnNote = ref('')
const busy = ref(false)
const newFiles = ref([])
const editing = ref(null)
const editText = ref('')

const overdue = computed(() => doc.value?.dateline && !doc.value.is_complete && new Date(doc.value.dateline) < new Date())
const statusText = computed(() => t(`doc.statuses.${doc.value.is_closed ? 'closed' : doc.value.status}`))
const statusClass = computed(() => (doc.value.is_closed ? 'bg-danger' : doc.value.is_complete ? 'bg-success' : doc.value.status === 'returned' ? 'bg-warning text-dark' : 'bg-info'))
const stepIcon = (c) => (c.status === 'done' ? (c.is_esign ? 'bi-patch-check-fill' : 'bi-check-circle-fill') : c.status === 'current' ? 'bi-arrow-right-circle-fill' : 'bi-circle')

function canEdit(c) {
  if (c.status !== 'done') return false
  if (auth.can('comment_grand_edit')) return true
  if (!auth.can('comment_edit') || c.user_id !== auth.user?.id || !c.submitted_at) return false
  return Date.now() - new Date(c.submitted_at).getTime() < (perm.value.edit_hour || 24) * 3600_000
}

async function load() {
  const { data } = await http.get(`/documents/${props.id}`)
  doc.value = data.document
  perm.value = data.permissions
}

async function run(fn, okText) {
  busy.value = true
  try {
    await fn()
    toast.success(okText || t('common.saved'))
    await load()
  } catch (e) {
    toast.error(errorMessage(e, t('common.error')))
  } finally {
    busy.value = false
  }
}

function submit() {
  if (!perm.value.is_final_step && !stripHtml(myComment.value)) return toast.error(t('doc.commentRequired'))
  if (perm.value.is_final_step && !confirm(t('doc.confirmComplete'))) return
  run(async () => {
    await http.post(`/documents/${props.id}/submit`, { comment: myComment.value, esign: esign.value, close: closeAfter.value })
    myComment.value = ''
  })
}
function sendBack() {
  run(() => http.post(`/documents/${props.id}/return`, { comment_id: returnTo.value, note: returnNote.value }))
}
function toggleClose() {
  run(() => http.post(`/documents/${props.id}/close`, { closed: !doc.value.is_closed }))
}
function uploadFiles() {
  run(async () => {
    await http.post(`/documents/${props.id}/attachments`, toFormData({ attachments: newFiles.value }))
    newFiles.value = []
  })
}
function startEdit(c) { editing.value = c.id; editText.value = c.comment || '' }
function saveEdit(c) { run(async () => { await http.put(`/comments/${c.id}`, { comment: editText.value }); editing.value = null }) }
function resetStep(c) { if (confirm(t('doc.confirmReset'))) run(() => http.post(`/comments/${c.id}/reset`)) }
function removeStep(c) { if (confirm(t('doc.confirmRemove'))) run(() => http.delete(`/comments/${c.id}`)) }

onMounted(load)
</script>
