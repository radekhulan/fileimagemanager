<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'

const fileStore = useFileStore()
const configStore = useConfigStore()
const { t } = configStore

const sortOpen = ref(false)
const sortRef = ref<HTMLElement | null>(null)

const sortOptions = [
  { preset: 'name' as const, label: () => t('Sort_name') },
  { preset: 'newest' as const, label: () => t('Sort_newest') },
  { preset: 'oldest' as const, label: () => t('Sort_oldest') },
  { preset: 'largest' as const, label: () => t('Sort_largest') },
  { preset: 'smallest' as const, label: () => t('Sort_smallest') },
]

function navigateToRoot() {
  fileStore.navigate('')
}

function navigateToSegment(path: string) {
  fileStore.navigate(path)
}

function refresh() {
  fileStore.refresh()
}

function toggleSort() {
  sortOpen.value = !sortOpen.value
}

function pickSort(preset: 'name' | 'newest' | 'oldest' | 'largest' | 'smallest') {
  sortOpen.value = false
  fileStore.setSortPreset(preset)
}

function onClickOutside(e: MouseEvent) {
  if (sortRef.value && !sortRef.value.contains(e.target as Node)) {
    sortOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', onClickOutside, true))
onUnmounted(() => document.removeEventListener('click', onClickOutside, true))
</script>

<template>
  <nav
    class="flex items-center gap-1 px-3 py-1.5 text-sm bg-gray-50 dark:bg-neutral-800 border-b border-gray-200 dark:border-neutral-700 whitespace-nowrap"
  >
    <!-- Scrollable breadcrumb area -->
    <div class="flex items-center gap-1 min-w-0 flex-1 overflow-x-auto">
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
    </div>

    <!-- Right-side controls (outside overflow) -->
    <div class="flex items-center gap-1 shrink-0">
      <!-- Sort button + dropdown -->
      <div ref="sortRef" class="relative">
        <button
          class="rounded p-1 text-gray-500 dark:text-gray-400 hover:text-rfm-primary hover:bg-gray-200 dark:hover:bg-neutral-700 transition-colors"
          :title="t('Sort')"
          @click="toggleSort"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 5h10" />
            <path d="M11 9h7" />
            <path d="M11 13h4" />
            <path d="M3 17l3 3 3-3" />
            <path d="M6 18V4" />
          </svg>
        </button>

        <div
          v-if="sortOpen"
          class="absolute right-0 top-full mt-1 z-50 min-w-[10rem] rounded-md border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 shadow-lg py-1"
        >
          <button
            v-for="opt in sortOptions"
            :key="opt.preset"
            class="flex items-center w-full px-3 py-1.5 text-xs transition-colors"
            :class="fileStore.sortPreset === opt.preset
              ? 'text-rfm-primary font-semibold bg-gray-100 dark:bg-neutral-700/60'
              : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-700'"
            @click="pickSort(opt.preset)"
          >
            <svg
              v-if="fileStore.sortPreset === opt.preset"
              class="w-3.5 h-3.5 mr-2 shrink-0"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span :class="{ 'ml-[1.375rem]': fileStore.sortPreset !== opt.preset }">{{ opt.label() }}</span>
          </button>
        </div>
      </div>

      <!-- Separator -->
      <div class="h-4 border-l border-gray-300 dark:border-neutral-600" />

      <!-- Refresh button -->
      <button
        class="rounded p-1 text-gray-500 dark:text-gray-400 hover:text-rfm-primary hover:bg-gray-200 dark:hover:bg-neutral-700 transition-colors"
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
      <div class="ml-1 text-xs text-gray-500 dark:text-gray-400 border-l border-gray-300 dark:border-neutral-600 pl-2">
        <span>{{ fileStore.fileCount }} {{ t('Files') }}</span>
        <span class="mx-1 text-gray-300 dark:text-neutral-600">-</span>
        <span>{{ fileStore.folderCount }} {{ t('Folders') }}</span>
      </div>
    </div>
  </nav>
</template>
