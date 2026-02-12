<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useConfigStore } from '@/stores/configStore'
import { useFileStore } from '@/stores/fileStore'
import { useUiStore } from '@/stores/uiStore'
import { useClipboardStore } from '@/stores/clipboardStore'
import type { TypeFilter, ViewMode } from '@/types/files'
import { foldersApi, operationsApi } from '@/api/files'

const configStore = useConfigStore()
const fileStore = useFileStore()
const ui = useUiStore()
const clipboard = useClipboardStore()

const { t } = configStore

const searchQuery = ref('')
let searchDebounce: ReturnType<typeof setTimeout> | null = null

function onSearchInput() {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    fileStore.setTextFilter(searchQuery.value)
    fileStore.refresh()
  }, 300)
}

function clearSearch() {
  searchQuery.value = ''
  fileStore.setTextFilter('')
  fileStore.refresh()
}

// Action handlers
function openUploadPanel() {
  ui.showUploadPanel = true
}

async function createNewFolder() {
  const name = await ui.prompt(t('New_Folder'), t('Folder_name'), '')
  if (name) {
    // Handled by parent/API - dispatch through store pattern
    await foldersApi.create(fileStore.currentPath, name)
    await fileStore.refresh()
  }
}

async function createNewFile() {
  const name = await ui.prompt(t('New_File'), t('File_name'), '')
  if (name) {
    await operationsApi.createFile(fileStore.currentPath, name)
    await fileStore.refresh()
  }
}

async function pasteItems() {
  await clipboard.paste(fileStore.currentPath)
}

async function clearClipboard() {
  await clipboard.clear()
}

async function deleteSelected() {
  const count = fileStore.selectionCount
  const confirmed = await ui.confirm(
    t('Confirm_delete'),
    t('Confirm_delete_text', count)
  )
  if (confirmed) {
    const paths = Array.from(fileStore.selectedItems)
    await operationsApi.deleteBulk(paths)
    await fileStore.refresh()
  }
}

function closeWindow() {
  if (window.parent && window.parent !== window) {
    // Embedded in iframe (TinyMCE dialog)
    try {
      // TinyMCE 4+
      if ((window.parent as any).tinymce) {
        const tinymce = (window.parent as any).tinymce
        tinymce.activeEditor?.windowManager?.close()
        return
      }
    } catch { /* cross-origin, fall through */ }
    // Try postMessage for cross-domain
    try {
      window.parent.postMessage({ action: 'rfm-close' }, '*')
    } catch { /* ignore */ }
  }
  // Fallback: close popup window
  window.close()
}

// View mode options
const viewModes: { mode: ViewMode; labelKey: string }[] = [
  { mode: 0, labelKey: 'View_grid' },
  { mode: 1, labelKey: 'View_list' },
  { mode: 2, labelKey: 'View_columns' },
]

// Type filter options
const filterOptions: { value: TypeFilter; labelKey: string }[] = [
  { value: 'all', labelKey: 'All' },
  { value: 'file', labelKey: 'Files' },
  { value: 'image', labelKey: 'Images' },
  { value: 'archive', labelKey: 'Archives' },
  { value: 'video', labelKey: 'Video' },
  { value: 'audio', labelKey: 'Music' },
]

const config = computed(() => configStore.config)
</script>

<template>
  <header
    class="sticky top-0 z-30 bg-gray-50 dark:bg-neutral-800 border-b border-gray-200 dark:border-neutral-700"
  >
    <!-- Main toolbar row -->
    <div class="flex flex-wrap items-center gap-2 px-3 py-2">
      <!-- Left section: Action buttons -->
      <div class="flex flex-wrap items-center gap-1.5">
        <!-- Upload button -->
        <button
          v-if="config?.uploadFiles"
          class="inline-flex items-center gap-1.5 rounded-full bg-rfm-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-rfm-primary-hover transition-colors"
          :title="t('Upload_file')"
          @click="openUploadPanel"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="17 8 12 3 7 8" />
            <line x1="12" y1="3" x2="12" y2="15" />
          </svg>
          <span class="hidden sm:inline">{{ t('Upload_file') }}</span>
        </button>

        <!-- New Folder button -->
        <button
          v-if="config?.createFolders"
          class="inline-flex items-center gap-1.5 rounded-full bg-gray-200 dark:bg-neutral-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-neutral-600 transition-colors"
          :title="t('New_Folder')"
          @click="createNewFolder"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
            <line x1="12" y1="11" x2="12" y2="17" />
            <line x1="9" y1="14" x2="15" y2="14" />
          </svg>
          <span class="hidden sm:inline">{{ t('New_Folder') }}</span>
        </button>

        <!-- New File button -->
        <button
          v-if="config?.createTextFiles"
          class="inline-flex items-center gap-1.5 rounded-full bg-gray-200 dark:bg-neutral-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-neutral-600 transition-colors"
          :title="t('New_File')"
          @click="createNewFile"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="12" y1="18" x2="12" y2="12" />
            <line x1="9" y1="15" x2="15" y2="15" />
          </svg>
          <span class="hidden sm:inline">{{ t('New_File') }}</span>
        </button>

        <!-- Paste button -->
        <button
          v-if="clipboard.isActive"
          class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-3 py-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-900/60 transition-colors"
          :title="t('Paste')"
          @click="pasteItems"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
            <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
          </svg>
          <span class="hidden sm:inline">{{ t('Paste') }}</span>
        </button>

        <!-- Clear Clipboard button -->
        <button
          v-if="clipboard.isActive"
          class="inline-flex items-center gap-1.5 rounded-full bg-gray-200 dark:bg-neutral-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-neutral-600 transition-colors"
          :title="t('Clear_Clipboard')"
          @click="clearClipboard"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
          <span class="hidden sm:inline">{{ t('Clear_Clipboard') }}</span>
        </button>
      </div>

      <!-- Center section: View toggles and filters -->
      <div class="flex flex-wrap items-center gap-1.5 mx-auto">
        <!-- View mode toggle buttons -->
        <div class="inline-flex rounded-lg bg-gray-200 dark:bg-neutral-700 p-0.5">
          <!-- Grid view -->
          <button
            class="rounded-md px-2 py-1 text-xs transition-colors"
            :class="ui.viewMode === 0
              ? 'bg-white dark:bg-neutral-600 text-gray-900 dark:text-white shadow-sm'
              : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
            :title="t('View_grid')"
            @click="ui.setViewMode(0)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" />
              <rect x="14" y="3" width="7" height="7" />
              <rect x="3" y="14" width="7" height="7" />
              <rect x="14" y="14" width="7" height="7" />
            </svg>
          </button>

          <!-- List view -->
          <button
            class="rounded-md px-2 py-1 text-xs transition-colors"
            :class="ui.viewMode === 1
              ? 'bg-white dark:bg-neutral-600 text-gray-900 dark:text-white shadow-sm'
              : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
            :title="t('View_list')"
            @click="ui.setViewMode(1)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="8" y1="6" x2="21" y2="6" />
              <line x1="8" y1="12" x2="21" y2="12" />
              <line x1="8" y1="18" x2="21" y2="18" />
              <line x1="3" y1="6" x2="3.01" y2="6" />
              <line x1="3" y1="12" x2="3.01" y2="12" />
              <line x1="3" y1="18" x2="3.01" y2="18" />
            </svg>
          </button>

          <!-- Columns view -->
          <button
            class="rounded-md px-2 py-1 text-xs transition-colors"
            :class="ui.viewMode === 2
              ? 'bg-white dark:bg-neutral-600 text-gray-900 dark:text-white shadow-sm'
              : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
            :title="t('View_columns')"
            @click="ui.setViewMode(2)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
              <line x1="9" y1="3" x2="9" y2="21" />
              <line x1="15" y1="3" x2="15" y2="21" />
            </svg>
          </button>
        </div>

        <!-- Type filter buttons (hidden when type is forced via URL param, e.g. TinyMCE image dialog) -->
        <div
          v-if="config?.showFilterButtons && !configStore.forceTypeFilter"
          class="inline-flex flex-wrap items-center gap-1"
        >
          <button
            v-for="filter in filterOptions"
            :key="filter.value"
            class="inline-flex items-center gap-1 rounded-full px-1.5 xl:px-2.5 py-1 text-xs font-medium transition-colors"
            :class="fileStore.typeFilter === filter.value
              ? 'bg-rfm-primary text-white'
              : 'bg-gray-200 dark:bg-neutral-700 text-gray-600 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-neutral-600'"
            :title="t(filter.labelKey)"
            @click="fileStore.changeTypeFilter(filter.value)"
          >
            <!-- All -->
            <svg v-if="filter.value === 'all'" class="w-3.5 h-3.5 xl:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><rect x="14" y="14" width="7" height="7" />
            </svg>
            <!-- Files -->
            <svg v-else-if="filter.value === 'file'" class="w-3.5 h-3.5 xl:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" />
            </svg>
            <!-- Images -->
            <svg v-else-if="filter.value === 'image'" class="w-3.5 h-3.5 xl:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><polyline points="21 15 16 10 5 21" />
            </svg>
            <!-- Archives -->
            <svg v-else-if="filter.value === 'archive'" class="w-3.5 h-3.5 xl:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" /><polyline points="3.27 6.96 12 12.01 20.73 6.96" /><line x1="12" y1="22.08" x2="12" y2="12" />
            </svg>
            <!-- Video -->
            <svg v-else-if="filter.value === 'video'" class="w-3.5 h-3.5 xl:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="23 7 16 12 23 17 23 7" /><rect x="1" y="5" width="15" height="14" rx="2" />
            </svg>
            <!-- Audio -->
            <svg v-else-if="filter.value === 'audio'" class="w-3.5 h-3.5 xl:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 18V5l12-2v13" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="16" r="3" />
            </svg>
            <span class="hidden xl:inline">{{ t(filter.labelKey) }}</span>
          </button>
        </div>
      </div>

      <!-- Right section: Search, dark mode, language -->
      <div class="flex items-center gap-1.5">
        <!-- Search input -->
        <div class="relative">
          <svg
            class="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('Search')"
            class="w-32 sm:w-44 rounded-full border border-gray-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 pl-8 pr-7 py-1.5 text-xs text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-rfm-primary/50 focus:border-rfm-primary transition-colors"
            @input="onSearchInput"
          />
          <button
            v-if="searchQuery"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            @click="clearSearch"
          >
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <!-- Dark mode toggle -->
        <button
          class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-neutral-700 transition-colors"
          :title="ui.isDark ? t('Light_mode') : t('Dark_mode')"
          @click="ui.toggleDarkMode()"
        >
          <!-- Sun icon (shown in dark mode) -->
          <svg
            v-if="ui.isDark"
            class="w-4 h-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <circle cx="12" cy="12" r="5" />
            <line x1="12" y1="1" x2="12" y2="3" />
            <line x1="12" y1="21" x2="12" y2="23" />
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
            <line x1="1" y1="12" x2="3" y2="12" />
            <line x1="21" y1="12" x2="23" y2="12" />
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
          </svg>
          <!-- Moon icon (shown in light mode) -->
          <svg
            v-else
            class="w-4 h-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
          </svg>
        </button>

        <!-- Language selector -->
        <button
          v-if="config?.showLanguageSelection"
          class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-neutral-700 transition-colors"
          :title="t('Language')"
          @click="ui.showLanguageDialog = true"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="2" y1="12" x2="22" y2="12" />
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
          </svg>
        </button>

        <!-- Close window (TinyMCE / popup mode) -->
        <button
          v-if="configStore.isEditorMode && config?.removeHeader"
          class="rounded-full p-1.5 text-gray-500 dark:text-gray-400 hover:bg-red-100 dark:hover:bg-red-900/30 hover:text-red-600 dark:hover:text-red-400 transition-colors"
          :title="t('Cancel')"
          @click="closeWindow"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Multi-select actions bar -->
    <div
      v-if="fileStore.hasSelection"
      class="flex flex-wrap items-center gap-2 px-3 py-1.5 bg-rfm-primary/10 dark:bg-rfm-primary/20 border-t border-rfm-primary/20"
    >
      <span class="text-xs font-medium text-rfm-primary dark:text-blue-300">
        {{ t('Selected') }}: {{ fileStore.selectionCount }}
      </span>

      <button
        class="rounded-full bg-rfm-primary/15 px-2.5 py-1 text-xs font-medium text-rfm-primary dark:text-blue-300 hover:bg-rfm-primary/25 transition-colors"
        @click="fileStore.selectAll()"
      >
        {{ t('Select_All') }}
      </button>

      <button
        class="rounded-full bg-gray-200 dark:bg-neutral-700 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-neutral-600 transition-colors"
        @click="fileStore.deselectAll()"
      >
        {{ t('Deselect_All') }}
      </button>

      <button
        v-if="config?.deleteFiles"
        class="rounded-full bg-rfm-danger/15 px-2.5 py-1 text-xs font-medium text-rfm-danger hover:bg-rfm-danger/25 transition-colors"
        @click="deleteSelected"
      >
        <span class="inline-flex items-center gap-1">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
          </svg>
          {{ t('Delete') }}
        </span>
      </button>
    </div>
  </header>
</template>
