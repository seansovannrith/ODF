<template>
  <div>
    <div
      class="file-drop" :class="{ over }"
      @click="input.click()" @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="drop"
    >
      <i class="bi bi-cloud-arrow-up fs-2 d-block mb-1"></i>
      {{ t('doc.dropFiles') }}
      <div class="small text-muted">{{ t('doc.orClick') }}</div>
      <input ref="input" type="file" multiple class="d-none" @change="choose" />
    </div>
    <ul v-if="modelValue.length" class="list-unstyled mt-2 mb-0">
      <li v-for="(f, i) in modelValue" :key="i" class="d-flex align-items-center gap-2 py-1">
        <i class="bi bi-file-earmark"></i> {{ f.name }} <small class="text-muted">({{ fileSize(f.size) }})</small>
        <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-auto" @click="remove(i)"><i class="bi bi-x-lg"></i></button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { fileSize } from '@/utils/helpers'

const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const input = ref(null)
const over = ref(false)

function add(files) { emit('update:modelValue', [...props.modelValue, ...Array.from(files)]) }
function drop(e) { over.value = false; add(e.dataTransfer.files) }
function choose(e) { add(e.target.files); e.target.value = '' }
function remove(i) { const v = [...props.modelValue]; v.splice(i, 1); emit('update:modelValue', v) }
</script>
