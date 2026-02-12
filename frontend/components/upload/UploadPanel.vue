<script setup lang="ts">
import { ref, computed } from 'vue'
import { useUploadStore } from '@/stores/uploadStore'
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import DropZone from './DropZone.vue'
import UploadProgress from './UploadProgress.vue'
import UrlUpload from './UrlUpload.vue'

const emit = defineEmits<{ close: [] }>()

const uploadStore = useUploadStore()
const fileStore = useFileStore()
const configStore = useConfigStore()
const { t } = configStore

const activeTab = ref<'file' | 'url'>('file')
const fileInput = ref<HTMLInputElement>()

function onFilesSelected(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files) {
    uploadStore.addFiles(input.files)
  }
  input.value = ''
}

function onFilesDrop(files: FileList) {
  uploadStore.addFiles(files)
}

function startUpload() {
  uploadStore.startUpload(fileStore.currentPath)
}

function onClose() {
  if (!uploadStore.isUploading) {
    uploadStore.clearAll()
  }
  emit('close')
}

const canUpload = computed(() =>
  uploadStore.queue.some(i => i.status === 'pending') && !uploadStore.isUploading
)
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-40 flex flex-col">
      <div class="fixed inset-0 bg-black/40" @click="onClose" />
      <div class="relative mx-auto mt-12 mb-12 w-full max-w-2xl bg-white dark:bg-neutral-800 rounded-xl shadow-2xl z-10 flex flex-col max-h-[80vh]">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-neutral-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            {{ t('Upload_file') }}
          </h2>
          <button
            @click="onClose"
            class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-neutral-700 text-gray-500 dark:text-gray-400"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 dark:border-neutral-700">
          <button
            @click="activeTab = 'file'"
            :class="[
              'px-4 py-2 text-sm font-medium transition-colors',
              activeTab === 'file'
                ? 'text-rfm-primary border-b-2 border-rfm-primary'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
            ]"
          >
            {{ t('Upload_base') }}
          </button>
          <button
            v-if="configStore.config?.urlUpload"
            @click="activeTab = 'url'"
            :class="[
              'px-4 py-2 text-sm font-medium transition-colors',
              activeTab === 'url'
                ? 'text-rfm-primary border-b-2 border-rfm-primary'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
            ]"
          >
            {{ t('Upload_url') }}
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-5">
          <!-- File upload tab -->
          <div v-if="activeTab === 'file'">
            <DropZone @drop="onFilesDrop" />

            <div class="mt-3 flex gap-2">
              <input
                ref="fileInput"
                type="file"
                multiple
                class="hidden"
                @change="onFilesSelected"
              />
              <button
                @click="fileInput?.click()"
                class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors"
              >
                {{ t('Upload_add_files') }}
              </button>
              <button
                v-if="canUpload"
                @click="startUpload"
                class="px-4 py-2 text-sm rounded-lg bg-rfm-primary hover:bg-rfm-primary-hover text-white transition-colors"
              >
                {{ t('Upload_start') }}
              </button>
              <button
                v-if="uploadStore.completedCount > 0"
                @click="uploadStore.clearCompleted()"
                class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors ml-auto"
              >
                {{ t('Clear_Completed') }}
              </button>
            </div>

            <!-- Upload queue -->
            <div v-if="uploadStore.hasItems" class="mt-4 space-y-2">
              <UploadProgress
                v-for="item in uploadStore.queue"
                :key="item.id"
                :item="item"
                @remove="uploadStore.removeItem(item.id)"
              />
            </div>
          </div>

          <!-- URL upload tab -->
          <UrlUpload v-else-if="activeTab === 'url'" />
        </div>

        <!-- Footer -->
        <div class="px-5 py-3 border-t border-gray-200 dark:border-neutral-700 flex justify-end">
          <button
            @click="onClose"
            class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors"
          >
            {{ t('Return_Files_List') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
