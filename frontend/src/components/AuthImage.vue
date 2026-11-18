<template>
  <img v-if="src" :src="src" v-bind="$attrs" />
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import http from '@/api/http'

/** <img> for files behind the API token (e.g. signatures). url is relative to /api */
const props = defineProps({ url: { type: String, default: null } })
const src = ref(null)

async function load() {
  if (src.value) URL.revokeObjectURL(src.value)
  src.value = null
  if (!props.url) return
  try {
    const path = props.url.replace(/^.*\/api/, '')
    const { data } = await http.get(path, { responseType: 'blob' })
    src.value = URL.createObjectURL(data)
  } catch (_) { src.value = null }
}
watch(() => props.url, load, { immediate: true })
onBeforeUnmount(() => src.value && URL.revokeObjectURL(src.value))
</script>
