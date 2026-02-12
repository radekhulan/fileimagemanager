<script setup lang="ts">
import { ref } from 'vue'
import { useConfigStore } from '@/stores/configStore'

const { t } = useConfigStore()
const emit = defineEmits<{ drop: [files: FileList] }>()
const isDragging = ref(false)

function onDragOver(e: DragEvent) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
  if (e.dataTransfer?.files.length) {
    emit('drop', e.dataTransfer.files)
  }
}
</script>

<template>
  <div
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
    :class="[
      'border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer',
      isDragging
        ? 'border-rfm-primary bg-blue-50 dark:bg-blue-900/20 drop-zone-active'
        : 'border-gray-300 dark:border-neutral-600 hover:border-gray-400 dark:hover:border-neutral-500'
    ]"
  >
    <svg class="w-12 h-12 mx-auto mb-3 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
    </svg>
    <p class="text-sm text-gray-600 dark:text-gray-400">
      {{ t('Upload_drop_here') }}
    </p>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
      {{ t('Upload_drop_or_click') }}
    </p>
  </div>
</template>
