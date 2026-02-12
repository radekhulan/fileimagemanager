<script setup lang="ts">
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'

const fileStore = useFileStore()
const configStore = useConfigStore()
const { t } = configStore

function navigateToRoot() {
  fileStore.navigate('')
}

function navigateToSegment(path: string) {
  fileStore.navigate(path)
}

function refresh() {
  fileStore.refresh()
}
</script>

<template>
  <nav
    class="flex items-center gap-1 px-3 py-1.5 text-sm bg-gray-50 dark:bg-neutral-800 border-b border-gray-200 dark:border-neutral-700 overflow-x-auto whitespace-nowrap"
  >
    <!-- Home / Root icon -->
    <button
      class="shrink-0 rounded p-1 text-gray-500 dark:text-gray-400 hover:text-rfm-primary hover:bg-gray-200 dark:hover:bg-neutral-700 transition-colors"
      :title="t('Home')"
      @click="navigateToRoot"
    >
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
        <polyline points="9 22 9 12 15 12 15 22" />
      </svg>
    </button>

    <!-- Breadcrumb segments -->
    <template
      v-for="(segment, index) in fileStore.breadcrumb"
      :key="segment.path"
    >
      <!-- Chevron separator -->
      <svg
        class="shrink-0 w-3.5 h-3.5 text-gray-400 dark:text-gray-500"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <polyline points="9 18 15 12 9 6" />
      </svg>

      <!-- Segment link -->
      <button
        class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
        :class="index === fileStore.breadcrumb.length - 1
          ? 'text-gray-900 dark:text-gray-100 bg-gray-200/60 dark:bg-neutral-700/60'
          : 'text-gray-600 dark:text-gray-400 hover:text-rfm-primary hover:bg-gray-200 dark:hover:bg-neutral-700'"
        @click="navigateToSegment(segment.path)"
      >
        {{ segment.name }}
      </button>
    </template>

    <!-- Spacer -->
    <div class="flex-1 min-w-4" />

    <!-- Refresh button -->
    <button
      class="shrink-0 rounded p-1 text-gray-500 dark:text-gray-400 hover:text-rfm-primary hover:bg-gray-200 dark:hover:bg-neutral-700 transition-colors"
      :class="{ 'rfm-spinner': fileStore.loading }"
      :title="t('Refresh')"
      @click="refresh"
    >
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="23 4 23 10 17 10" />
        <polyline points="1 20 1 14 7 14" />
        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
      </svg>
    </button>

    <!-- File/Folder counts -->
    <div class="shrink-0 ml-1 text-xs text-gray-500 dark:text-gray-400 border-l border-gray-300 dark:border-neutral-600 pl-2">
      <span>{{ fileStore.fileCount }} {{ t('Files') }}</span>
      <span class="mx-1 text-gray-300 dark:text-neutral-600">-</span>
      <span>{{ fileStore.folderCount }} {{ t('Folders') }}</span>
    </div>
  </nav>
</template>
