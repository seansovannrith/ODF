<template>
  <div class="print-wrap">
    <div class="print-toolbar no-print">
      <LanguageSwitch />
      <button class="btn btn-primary" @click="print"><i class="bi bi-printer"></i> {{ t('common.print') }} / {{ t('common.pdf') }}</button>
      <button class="btn btn-secondary" @click="close">{{ t('common.close') }}</button>
    </div>

    <div v-if="doc" class="paper">
      <!-- Kingdom header -->
      <div class="kingdom">
        <div class="moul">{{ t('doc.kingdom') }}</div>
        <div class="moul small-moul">{{ t('doc.nation') }}</div>
        <div class="kingdom-line"></div>
      </div>

      <div class="head-row">
        <div class="org">
          <div>{{ brand.ministry_kh }}</div>
          <div class="org-box">
            <img v-if="brand.logo_url" :src="brand.logo_url" alt="" class="org-logo" />
            <div class="moul org-kh">{{ brand.org_name_kh }}</div>
            <div class="org-en">{{ brand.org_name_en }}</div>
          </div>
        </div>
        <div class="meta">
          <div>{{ t('doc.printLetterNo') }}: {{ doc.letter_no || t('doc.none') }}</div>
          <div>{{ t('doc.printIncoming') }}: {{ doc.incoming_code }}</div>
          <div>{{ t('doc.printCode') }}: {{ doc.document_code }}</div>
          <div>{{ t('doc.printDate') }}: {{ formatDate(doc.created_at) }}</div>
          <div>{{ t('doc.printBy') }}: {{ ln(doc.creator) }}</div>
        </div>
      </div>

      <h2 class="moul print-title">{{ t('doc.printTitle') }}</h2>

      <div class="mb-1"><strong>{{ t('doc.from') }}:</strong> {{ doc.from_source }}</div>
      <div class="rich" v-html="doc.description"></div>
      <hr />

      <!-- Reviewers in order -->
      <div v-for="c in reviewers" :key="c.id" class="p-comment">
        <div class="p-head">
          <span class="moul-sm">{{ personLabel(c.user) }}</span>
          <em class="p-date">{{ formatDate(c.submitted_at) }}</em>
        </div>
        <div class="rich p-body" v-html="c.comment || ''"></div>
      </div>

      <!-- Boss: decision + signature -->
      <div v-if="boss" class="boss-block">
        <div class="moul boss-position">{{ ln(boss.user?.position) }}</div>
        <div v-if="boss.status === 'done'">
          <div class="rich p-body" v-html="boss.comment || ''"></div>
          <div class="sign-area">
            <em class="p-date">{{ formatDate(boss.submitted_at) }}</em>
            <AuthImage v-if="boss.is_esign" :url="boss.user?.signature_url" class="print-signature" />
            <div v-else class="sign-space"></div>
            <div class="moul-sm">{{ personLabel(boss.user, { withPosition: false }) }}</div>
          </div>
        </div>
        <div v-else class="text-muted fst-italic text-center no-print">{{ t('doc.waiting') }}…</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '@/api/http'
import { useLookups } from '@/stores/lookups'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import AuthImage from '@/components/AuthImage.vue'
import { formatDate, ln, personLabel } from '@/utils/helpers'

const props = defineProps({ id: { type: [String, Number], required: true } })
const { t } = useI18n()
const lookups = useLookups()
const doc = ref(null)

const brand = computed(() => lookups.branding)
const reviewers = computed(() => (doc.value?.comments || []).filter((c) => !c.is_final && c.status === 'done'))
const boss = computed(() => (doc.value?.comments || []).find((c) => c.is_final))

function print() { window.print() }
function close() { window.close() }

onMounted(async () => {
  await lookups.loadBranding()
  doc.value = (await http.get(`/documents/${props.id}`)).data.document
  document.title = `${doc.value.document_code || doc.value.incoming_code} - Print`
})
</script>

<style scoped>
.print-wrap { background: #525659; min-height: 100vh; padding: 24px 0; }
.print-toolbar { display: flex; gap: 8px; justify-content: center; margin-bottom: 16px; }
.paper {
  background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 18mm 18mm 20mm;
  font-family: 'Battambang', 'Kantumruy Pro', serif; font-size: 14px; color: #000; line-height: 1.9;
}
.moul { font-family: 'Moul', serif; }
.moul-sm { font-family: 'Moul', serif; font-size: 13px; }
.kingdom { text-align: center; }
.kingdom .moul { font-size: 17px; }
.kingdom .small-moul { font-size: 15px; }
.kingdom-line { width: 120px; margin: 4px auto 0; border-top: 1px solid #000; }
.head-row { display: flex; justify-content: space-between; margin-top: 14px; }
.org-box { display: inline-block; border: 1.5px solid #000; padding: 6px 14px; text-align: center; margin-top: 4px; }
.org-logo { height: 46px; display: block; margin: 0 auto 2px; }
.org-kh { font-size: 15px; line-height: 1.6; }
.org-en { font-family: 'Times New Roman', serif; font-weight: bold; font-size: 15px; }
.meta { font-size: 13.5px; }
.print-title { text-align: center; font-size: 18px; text-decoration: underline; margin: 16px 0 10px; }
.p-comment { margin-bottom: 8px; }
.p-head { display: flex; justify-content: space-between; align-items: baseline; }
.p-date { font-family: Arial, sans-serif; font-size: 12px; }
.p-body { padding-left: 28px; }
.boss-block { margin-top: 18px; }
.boss-position { text-align: center; font-size: 17px; margin-bottom: 6px; }
.sign-area { text-align: center; margin-left: auto; width: 55%; margin-top: 6px; }
.print-signature { max-height: 90px; max-width: 220px; display: block; margin: 4px auto; }
.sign-space { height: 70px; }
.rich :deep(ul) { margin-bottom: 0; }
@media print {
  @page { size: A4; margin: 0; }
  .print-wrap { background: #fff; padding: 0; }
  .paper { box-shadow: none; margin: 0; }
}
</style>
