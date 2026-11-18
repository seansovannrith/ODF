<template>
  <div>
    <h4 class="page-title">{{ t('dashboard.title') }}</h4>

    <div class="row g-3 mb-3">
      <div v-for="c in cards" :key="c.key" class="col-6 col-lg-3">
        <RouterLink :to="c.to" class="stat-card" :class="c.color">
          <div>
            <div class="stat-value">{{ data[c.key] ?? '–' }}</div>
            <div class="stat-label">{{ t(c.label) }}</div>
          </div>
          <i class="bi stat-icon" :class="c.icon"></i>
        </RouterLink>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><i class="bi bi-inbox me-1"></i> {{ t('dashboard.myTurnList') }}</div>
          <ul class="list-group list-group-flush">
            <li v-if="!data.my_turn_list?.length" class="list-group-item text-muted">{{ t('dashboard.nothing') }}</li>
            <RouterLink v-for="d in data.my_turn_list" :key="d.id" :to="`/documents/${d.id}`" class="list-group-item list-group-item-action">
              <div class="d-flex justify-content-between">
                <strong>{{ d.document_code || d.incoming_code }}</strong>
                <small v-if="d.dateline" :class="isOverdue(d.dateline) ? 'text-danger' : 'text-muted'">
                  <i class="bi bi-alarm"></i> {{ formatDate(d.dateline) }}
                </small>
              </div>
              <div class="small text-muted text-truncate">{{ d.from_source }}</div>
            </RouterLink>
          </ul>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3" v-if="data.due_soon?.length">
          <div class="card-header text-warning-emphasis"><i class="bi bi-alarm me-1"></i> {{ t('dashboard.dueSoon') }}</div>
          <ul class="list-group list-group-flush">
            <RouterLink v-for="d in data.due_soon" :key="d.id" :to="`/documents/${d.id}`" class="list-group-item list-group-item-action d-flex justify-content-between">
              <span>{{ d.document_code || d.incoming_code }} <small class="text-muted">{{ d.from_source }}</small></span>
              <small>{{ formatDate(d.dateline) }}</small>
            </RouterLink>
          </ul>
        </div>

        <div class="card">
          <div class="card-header"><i class="bi bi-clock-history me-1"></i> {{ t('dashboard.recent') }}</div>
          <ul class="list-group list-group-flush">
            <RouterLink v-for="d in data.recent" :key="d.id" :to="`/documents/${d.id}`" class="list-group-item list-group-item-action">
              <div class="d-flex justify-content-between align-items-center">
                <strong>{{ d.document_code || d.incoming_code }}</strong>
                <span class="badge" :class="statusClass(d)">{{ statusText(d) }}</span>
              </div>
              <div class="small text-muted d-flex justify-content-between">
                <span class="text-truncate">{{ d.from_source }}</span>
                <span v-if="d.current_comment">{{ t('dashboard.currentlyWith') }}: {{ ln(d.current_comment.user) }}</span>
              </div>
            </RouterLink>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import http from '@/api/http'
import { formatDate, ln } from '@/utils/helpers'

const { t } = useI18n()
const data = ref({})

const cards = [
  { key: 'my_turn', label: 'dashboard.myTurn', icon: 'bi-inbox-fill', color: 'bg-c-red', to: '/documents?tab=inbox' },
  { key: 'in_review', label: 'dashboard.inReview', icon: 'bi-arrow-repeat', color: 'bg-c-blue', to: '/documents?tab=active' },
  { key: 'completed', label: 'dashboard.completed', icon: 'bi-check2-circle', color: 'bg-c-green', to: '/documents?tab=completed' },
  { key: 'overdue', label: 'dashboard.overdue', icon: 'bi-alarm-fill', color: 'bg-c-orange', to: '/documents?tab=active' },
]

const isOverdue = (d) => new Date(d) < new Date()
const statusText = (d) => t(`doc.statuses.${d.is_closed ? 'closed' : d.status}`)
const statusClass = (d) => (d.is_closed ? 'bg-danger' : d.is_complete ? 'bg-success' : d.status === 'returned' ? 'bg-warning text-dark' : 'bg-info')

onMounted(async () => { data.value = (await http.get('/dashboard')).data })
</script>
