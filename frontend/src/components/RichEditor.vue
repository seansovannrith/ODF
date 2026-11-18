<template>
  <div class="rich-editor" :class="{ focused }">
    <div class="re-toolbar">
      <select class="form-select form-select-sm re-block" @change="block($event)">
        <option value="p">Paragraph</option>
        <option value="h3">Heading</option>
        <option value="h4">Sub heading</option>
      </select>
      <span class="re-sep"></span>
      <button type="button" title="Bold" @mousedown.prevent="cmd('bold')"><i class="bi bi-type-bold"></i></button>
      <button type="button" title="Italic" @mousedown.prevent="cmd('italic')"><i class="bi bi-type-italic"></i></button>
      <button type="button" title="Underline" @mousedown.prevent="cmd('underline')"><i class="bi bi-type-underline"></i></button>
      <button type="button" title="Link" @mousedown.prevent="link"><i class="bi bi-link-45deg"></i></button>
      <button type="button" title="Bullet list" @mousedown.prevent="cmd('insertUnorderedList')"><i class="bi bi-list-ul"></i></button>
      <button type="button" title="Numbered list" @mousedown.prevent="cmd('insertOrderedList')"><i class="bi bi-list-ol"></i></button>
      <span class="re-sep"></span>
      <button type="button" title="Indent" @mousedown.prevent="cmd('indent')"><i class="bi bi-text-indent-left"></i></button>
      <button type="button" title="Outdent" @mousedown.prevent="cmd('outdent')"><i class="bi bi-text-indent-right"></i></button>
      <button type="button" title="Quote" @mousedown.prevent="cmd('formatBlock', 'blockquote')"><i class="bi bi-quote"></i></button>
      <span class="re-sep"></span>
      <button type="button" title="Undo" @mousedown.prevent="cmd('undo')"><i class="bi bi-arrow-counterclockwise"></i></button>
      <button type="button" title="Redo" @mousedown.prevent="cmd('redo')"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
    <div
      ref="area" class="re-area" contenteditable="true" :style="{ minHeight: minHeight }"
      @input="emitValue" @focus="focused = true" @blur="focused = false; emitValue()"
    ></div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'

/** Lightweight rich text editor (no external dependency). v-model = HTML string. */
const props = defineProps({
  modelValue: { type: String, default: '' },
  minHeight: { type: String, default: '180px' },
})
const emit = defineEmits(['update:modelValue'])
const area = ref(null)
const focused = ref(false)

onMounted(() => { area.value.innerHTML = props.modelValue || '' })
watch(() => props.modelValue, (v) => {
  if (area.value && v !== area.value.innerHTML && !focused.value) area.value.innerHTML = v || ''
})

function emitValue() {
  const html = area.value.innerHTML
  emit('update:modelValue', html === '<br>' ? '' : html)
}
function cmd(name, value = null) {
  area.value.focus()
  document.execCommand(name, false, value)
  emitValue()
}
function block(e) {
  cmd('formatBlock', e.target.value)
  e.target.value = 'p'
}
function link() {
  const url = prompt('URL')
  if (url) cmd('createLink', url)
}
</script>
