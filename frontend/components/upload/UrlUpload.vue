<script setup lang="ts">
import { ref } from 'vue'
import { useUploadStore } from '@/stores/uploadStore'
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'

const uploadStore = useUploadStore()
const fileStore = useFileStore()
const configStore = useConfigStore()
const { t } = configStore

const url = ref('')
const uploading = ref(false)
const error = ref('')

async function onSubmit() {
  if (!url.value.trim()) return
  error.value = ''
  uploading.value = true
  try {
    await uploadStore.uploadFromUrl(url.value.trim(), fileStore.currentPath)
    url.value = ''
    await fileStore.refresh()
  } catch (err: any) {
    error.value = err?.response?.data?.error || err?.message || t('Upload_Failed')
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
      {{ t('Upload_url') }}
    </p>

    <div class="flex gap-2">
      <input
        v-model="url"
        type="url"
        placeholder="https://example.com/image.jpg"
        class="flex-1 px-3 py-2 border border-gray-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-700 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-rfm-primary"
        :disabled="uploading"
        @keydown.enter="onSubmit"
      />
      <button
        @click="onSubmit"
        :disabled="uploading || !url.trim()"
        class="px-4 py-2 text-sm rounded-lg bg-rfm-primary hover:bg-rfm-primary-hover text-white transition-colors disabled:opacity-50 whitespace-nowrap"
      >
        <span v-if="uploading" class="inline-flex items-center gap-2">
          <svg class="w-4 h-4 rfm-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83" />
          </svg>
          {{ t('Uploading') }}
        </span>
        <span v-else>{{ t('Upload_start') }}</span>
      </button>
    </div>

    <p v-if="error" class="text-sm text-rfm-danger">{{ error }}</p>
  </div>
</template>
