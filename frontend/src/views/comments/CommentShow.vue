<template>
  <div class="card">
    <div class="card-header">{{ t('comment.show') }}</div>
    <div v-if="c" class="card-body">
      <RouterLink to="/comments" class="btn btn-light border mb-3">{{ t('common.back') }}</RouterLink>
      <table class="table table-bordered table-striped detail-table">
        <tbody>
          <tr><th>{{ t('common.id') }}</th><td>{{ c.id }}</td></tr>
          <tr><th>{{ t('comment.comment') }}</th><td class="rich" v-html="c.comment || ''"></td></tr>
          <tr><th>{{ t('comment.submit') }}</th><td><input type="checkbox" class="form-check-input" :checked="c.is_submit" disabled /></td></tr>
          <tr>
            <th>{{ t('comment.document') }}</th>
            <td><RouterLink :to="`/documents/${c.document_id}`">{{ c.document?.document_code || c.document?.incoming_code }}</RouterLink></td>
          </tr>
          <tr><th>{{ t('comment.user') }}</th><td>{{ ln(c.user) }}</td></tr>
          <tr><th>{{ t('comment.status') }}</th><td>{{ t(`doc.${c.status}`) }}</td></tr>
          <tr><th>{{ t('comment.submittedAt') }}</th><td>{{ formatDate(c.submitted_at) }}</td></tr>
          <tr><th>{{ t('comment.isEsign') }}</th><td><input type="checkbox" class="form-check-input" :checked="c.is_esign" disabled /></td></tr>
        </tbody>
      </table>
      <RouterLink to="/comments" class="btn btn-light border">{{ t('common.back') }}</RouterLink>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '@/api/http'
import { formatDate, ln } from '@/utils/helpers'

const props = defineProps({ id: { type: [String, Number], required: true } })
const { t } = useI18n()
const c = ref(null)
onMounted(async () => { c.value = (await http.get(`/comments/${props.id}`)).data })
</script>
