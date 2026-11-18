<template>
  <Teleport to="body">
    <div v-if="show" class="modal d-block" tabindex="-1" @mousedown.self="emit('close')">
      <div class="modal-dialog modal-dialog-scrollable" :class="size">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ title }}</h5>
            <button type="button" class="btn-close" @click="emit('close')"></button>
          </div>
          <div class="modal-body"><slot /></div>
          <div v-if="$slots.footer" class="modal-footer"><slot name="footer" /></div>
        </div>
      </div>
    </div>
    <div v-if="show" class="modal-backdrop show"></div>
  </Teleport>
</template>

<script setup>
defineProps({
  show: Boolean,
  title: { type: String, default: '' },
  size: { type: String, default: 'modal-lg' },
})
const emit = defineEmits(['close'])
</script>
