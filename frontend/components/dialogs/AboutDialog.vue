<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useUiStore } from '@/stores/uiStore'
import { configApi } from '@/api/config'

const ui = useUiStore()
const version = __APP_VERSION__

const loading = ref(true)
const info = ref<{
  imageDriver: string
  phpVersion: string
  maxUploadSize: string
  serverSoftware: string
  imagickVersion?: string
} | null>(null)

onMounted(async () => {
  try {
    info.value = await configApi.getAboutInfo()
  } catch {
    info.value = null
  } finally {
    loading.value = false
  }
})

function onClose() {
  ui.showAboutDialog = false
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="onClose">
      <div class="fixed inset-0 bg-black/50" @click="onClose" />
      <div class="relative bg-white dark:bg-neutral-800 rounded-xl shadow-2xl max-w-sm w-full p-6 z-10">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-0.5">
          File &amp; Image Manager
        </h3>
        <p class="text-xs text-gray-400 dark:text-gray-500 mb-1">v{{ version }}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
          by Radek Hul&aacute;n
        </p>

        <a
          href="https://github.com/radekhulan/fileimagemanager"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1.5 text-sm text-rfm-primary hover:underline mb-5"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z" />
          </svg>
          GitHub
        </a>

        <!-- Server info -->
        <div v-if="loading" class="flex justify-center py-4">
          <svg class="w-5 h-5 text-rfm-primary rfm-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83" />
          </svg>
        </div>
        <div v-else-if="info" class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Image Driver</span>
            <span class="text-gray-900 dark:text-gray-100 font-medium">
              {{ info.imageDriver === 'imagick' ? 'Imagick' : 'GD' }}
            </span>
          </div>
          <div v-if="info.imagickVersion" class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Imagick Version</span>
            <span class="text-gray-900 dark:text-gray-100 font-medium text-right text-xs leading-5">{{ info.imagickVersion }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">PHP Version</span>
            <span class="text-gray-900 dark:text-gray-100 font-medium">{{ info.phpVersion }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Max Upload Size</span>
            <span class="text-gray-900 dark:text-gray-100 font-medium">{{ info.maxUploadSize }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Server</span>
            <span class="text-gray-900 dark:text-gray-100 font-medium text-right text-xs leading-5">{{ info.serverSoftware }}</span>
          </div>
        </div>

        <div class="flex justify-end mt-5">
          <button
            @click="onClose"
            class="px-4 py-2 text-sm rounded-lg bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-gray-300 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
