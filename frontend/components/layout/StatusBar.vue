<script setup lang="ts">
import { computed } from 'vue'
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import { formatFileSize } from '@/utils/filesize'

const fileStore = useFileStore()
const configStore = useConfigStore()
const { t } = configStore

const config = computed(() => configStore.config)

const formattedTotalSize = computed(() => formatFileSize(fileStore.totalSize))
</script>

<template>
  <footer
    class="flex items-center justify-between px-3 py-1 text-xs bg-gray-50 dark:bg-neutral-800 border-t border-gray-200 dark:border-neutral-700"
  >
    <!-- Left: counts -->
    <div class="flex items-center gap-3 text-gray-500 dark:text-gray-400">
      <!-- Files count -->
      <span class="inline-flex items-center gap-1">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
        </svg>
        {{ fileStore.fileCount }} {{ t('Files') }}
      </span>

      <!-- Folders count -->
      <span class="inline-flex items-center gap-1">
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
        </svg>
        {{ fileStore.folderCount }} {{ t('Folders') }}
      </span>

      <!-- Loading more indicator -->
      <span
        v-if="fileStore.loadingMore"
        class="inline-flex items-center gap-1 text-blue-500 dark:text-blue-400"
      >
        <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 12a9 9 0 1 1-6.219-8.56" />
        </svg>
        {{ t('Loaded') }} {{ fileStore.items.length }} / {{ fileStore.totalItems }}
      </span>

      <!-- Total size -->
      <span
        v-if="config?.showTotalSize"
        class="inline-flex items-center gap-1"
      >
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
        </svg>
        {{ formattedTotalSize }}
      </span>
    </div>

    <!-- Right: current path -->
    <div
      class="hidden sm:block text-gray-400 dark:text-gray-500 truncate max-w-xs"
      :title="fileStore.currentPath || '/'"
    >
      {{ fileStore.currentPath || '/' }}
    </div>
  </footer>
</template>
