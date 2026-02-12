<script setup lang="ts">
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import type { SortField } from '@/types/files'

const fileStore = useFileStore()
const configStore = useConfigStore()
const { t } = configStore

interface SortColumn {
  field: SortField | null
  labelKey: string
  class: string
}

const columns: SortColumn[] = [
  { field: 'name', labelKey: 'Filename', class: 'col-span-4 sm:col-span-3' },
  { field: 'date', labelKey: 'Date', class: 'col-span-2 hidden sm:block' },
  { field: 'size', labelKey: 'Size', class: 'col-span-2 hidden sm:block' },
  { field: null, labelKey: 'Dimension', class: 'col-span-2 hidden md:block' },
  { field: 'extension', labelKey: 'Type', class: 'col-span-1 hidden md:block' },
  { field: null, labelKey: 'Actions', class: 'col-span-2 sm:col-span-1 text-right' },
]

function onSort(field: SortField | null) {
  if (field) {
    fileStore.changeSort(field)
  }
}

function isCurrentSort(field: SortField | null): boolean {
  return field !== null && fileStore.sortBy === field
}
</script>

<template>
  <div
    class="sticky top-0 z-20 grid grid-cols-6 sm:grid-cols-9 md:grid-cols-12 gap-1 px-3 py-1.5 bg-gray-100 dark:bg-neutral-700 border-b border-gray-200 dark:border-neutral-600"
  >
    <div
      v-for="col in columns"
      :key="col.labelKey"
      :class="col.class"
    >
      <button
        v-if="col.field"
        class="inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wide transition-colors"
        :class="isCurrentSort(col.field)
          ? 'text-rfm-primary'
          : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
        @click="onSort(col.field)"
      >
        {{ t(col.labelKey) }}

        <!-- Sort direction arrow -->
        <template v-if="isCurrentSort(col.field)">
          <!-- Ascending arrow (up) -->
          <svg
            v-if="!fileStore.descending"
            class="w-3 h-3"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <polyline points="18 15 12 9 6 15" />
          </svg>
          <!-- Descending arrow (down) -->
          <svg
            v-else
            class="w-3 h-3"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <polyline points="6 9 12 15 18 9" />
          </svg>
        </template>
      </button>

      <!-- Non-sortable column label -->
      <span
        v-else
        class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
      >
        {{ t(col.labelKey) }}
      </span>
    </div>
  </div>
</template>
