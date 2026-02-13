<script setup lang="ts">
import { onUnmounted } from 'vue'
import type { UploadItem } from '@/stores/uploadStore'
import { formatFileSize } from '@/utils/filesize'

const props = defineProps<{ item: UploadItem }>()
defineEmits<{ remove: [] }>()

const previewUrl = props.item.file.type.startsWith('image/')
  ? URL.createObjectURL(props.item.file)
  : null

onUnmounted(() => {
  if (previewUrl) URL.revokeObjectURL(previewUrl)
})
</script>

<template>
  <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-50 dark:bg-neutral-700/50">
    <!-- Status icon -->
    <div class="flex-shrink-0">
      <svg v-if="item.status === 'done'" class="w-5 h-5 text-rfm-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
      </svg>
      <svg v-else-if="item.status === 'error'" class="w-5 h-5 text-rfm-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
      <svg v-else-if="item.status === 'uploading'" class="w-5 h-5 text-rfm-primary rfm-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83" />
      </svg>
      <svg v-else class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
      </svg>
    </div>

    <!-- Thumbnail preview -->
    <img
      v-if="previewUrl"
      :src="previewUrl"
      class="w-10 h-10 rounded object-cover flex-shrink-0"
      alt=""
    />

    <!-- File info -->
    <div class="flex-1 min-w-0">
      <p class="text-sm text-gray-900 dark:text-gray-100 truncate">{{ item.name }}</p>
      <p v-if="item.error" class="text-xs text-rfm-danger">{{ item.error }}</p>
      <p v-else class="text-xs text-gray-500 dark:text-gray-400">{{ formatFileSize(item.size) }}</p>
    </div>

    <!-- Progress bar -->
    <div v-if="item.status === 'uploading'" class="w-24 h-1.5 bg-gray-200 dark:bg-neutral-600 rounded-full overflow-hidden">
      <div
        class="h-full bg-rfm-primary rounded-full transition-all duration-300"
        :style="{ width: `${item.progress}%` }"
      />
    </div>

    <!-- Progress percentage -->
    <span v-if="item.status === 'uploading'" class="text-xs text-gray-500 dark:text-gray-400 w-8 text-right">
      {{ item.progress }}%
    </span>

    <!-- Remove button -->
    <button
      v-if="item.status !== 'uploading'"
      @click="$emit('remove')"
      class="flex-shrink-0 p-1 rounded hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>
