<template>
  <div class="reviewer-picker">
    <div class="d-flex gap-1 mb-2">
      <button type="button" class="btn btn-info btn-sm text-white" @click="selectAll">{{ t('common.selectAll') }}</button>
      <button type="button" class="btn btn-info btn-sm text-white" @click="emit('update:modelValue', [])">{{ t('common.deselectAll') }}</button>
    </div>

    <div class="input-group mb-2">
      <input v-model="query" class="form-control" :placeholder="t('common.search') + '...'" @keydown.enter.prevent="addFirst" />
      <select v-model="pick" class="form-select" style="max-width: 55%">
        <option :value="null">{{ t('common.choose') }}</option>
        <option v-for="u in available" :key="u.id" :value="u.id">{{ optionLabel(u) }}</option>
      </select>
      <button type="button" class="btn btn-secondary" :disabled="!pick" @click="add(pick)">
        <i class="bi bi-plus-lg"></i> {{ t('doc.addReviewer') }}
      </button>
    </div>

    <ol v-if="chosen.length" class="list-group list-group-numbered">
      <li v-for="(u, i) in chosen" :key="u.id" class="list-group-item d-flex align-items-center gap-2">
        <span class="badge bg-info me-1">{{ ln(u) }}</span>
        <small class="text-muted">{{ ln(u.position) }}</small>
        <div class="ms-auto btn-group btn-group-sm">
          <button type="button" class="btn btn-outline-secondary" :title="t('doc.moveUp')" :disabled="i === 0" @click="move(i, -1)"><i class="bi bi-arrow-up"></i></button>
          <button type="button" class="btn btn-outline-secondary" :title="t('doc.moveDown')" :disabled="i === chosen.length - 1" @click="move(i, 1)"><i class="bi bi-arrow-down"></i></button>
          <button type="button" class="btn btn-outline-danger" @click="remove(i)"><i class="bi bi-x-lg"></i></button>
        </div>
      </li>
    </ol>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ln } from '@/utils/helpers'

/** Ordered list of user ids: who comments first, second, ... */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  exclude: { type: Array, default: () => [] }, // e.g. the Boss
  approverIds: { type: Array, default: () => [] }, // "Select all" skips possible Bosses
})
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const query = ref('')
const pick = ref(null)

const byId = computed(() => Object.fromEntries(props.users.map((u) => [u.id, u])))
const chosen = computed(() => props.modelValue.map((id) => byId.value[id]).filter(Boolean))
const available = computed(() => {
  const q = query.value.toLowerCase()
  return props.users.filter((u) =>
    !props.modelValue.includes(u.id) && !props.exclude.includes(u.id) &&
    (!q || `${u.name} ${u.name_kh || ''} ${u.position?.name || ''} ${u.position?.name_kh || ''}`.toLowerCase().includes(q)))
})

const optionLabel = (u) => [ln(u), ln(u.position)].filter(Boolean).join(' — ')
function add(id) { if (id) emit('update:modelValue', [...props.modelValue, id]); pick.value = null; query.value = '' }
function addFirst() { if (available.value[0]) add(available.value[0].id) }
function remove(i) { const v = [...props.modelValue]; v.splice(i, 1); emit('update:modelValue', v) }
function move(i, d) {
  const v = [...props.modelValue];
  [v[i], v[i + d]] = [v[i + d], v[i]]
  emit('update:modelValue', v)
}
function selectAll() {
  emit('update:modelValue', [...props.modelValue, ...props.users.filter((u) => !props.approverIds.includes(u.id) && !props.modelValue.includes(u.id) && !props.exclude.includes(u.id)).map((u) => u.id)])
}
</script>
