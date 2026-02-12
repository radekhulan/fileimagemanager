<script setup lang="ts">
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import { useUiStore } from '@/stores/uiStore'
import { useFileOperations } from '@/composables/useFileOperations'
import { useEditorIntegration } from '@/composables/useEditorIntegration'
import { filesApi } from '@/api/files'
import type { FileItem } from '@/types/files'
import { formatFileSize } from '@/utils/filesize'
import { getIconColor, getIconType, isEditableImage } from '@/utils/extensions'
import SelectionCheckbox from './SelectionCheckbox.vue'

const fileStore = useFileStore()
const configStore = useConfigStore()
const ui = useUiStore()
const ops = useFileOperations()
const { isEditorMode, isPopupMode, selectFile, selectForPopup } = useEditorIntegration()

function isSelected(item: FileItem): boolean {
  return fileStore.selectedItems.has(item.path)
}

let clickTimer: ReturnType<typeof setTimeout> | null = null

function onItemClick(item: FileItem) {
  if (item.isDir) {
    fileStore.navigate(item.path + '/')
  } else {
    onPreview(item)
  }
}

async function doPreview(item: FileItem) {
  if (item.category === 'image') {
    ui.previewItem = {
      url: configStore.getFileUrl(item.path),
      type: 'image',
      name: item.name,
      path: item.path,
    }
  } else if (item.category === 'video' || item.category === 'audio') {
    ui.previewItem = {
      url: configStore.getFileUrl(item.path),
      type: item.category,
      name: item.name,
      path: item.path,
    }
  } else {
    const preview = await filesApi.preview(item.path)
    if (preview.type !== 'unsupported') {
      ui.previewItem = { ...preview, url: preview.url || '', name: item.name, path: item.path }
    }
  }
}

function onPreview(item: FileItem) {
  if (isEditorMode()) {
    selectFile(item)
    return
  }
  if (isPopupMode()) {
    if (clickTimer) clearTimeout(clickTimer)
    clickTimer = setTimeout(() => { clickTimer = null; doPreview(item) }, 300)
    return
  }
  doPreview(item)
}

function onDownload(item: FileItem) {
  const url = filesApi.getDownloadUrl(item.path)
  window.open(url, '_blank')
}

function onContextMenu(event: MouseEvent, item: FileItem) {
  event.preventDefault()
  ui.showContextMenu(event.clientX, event.clientY, item)
}

function onSelectionChange(item: FileItem, checked: boolean) {
  if (checked !== isSelected(item)) {
    fileStore.toggleSelection(item.path)
  }
}

function onDblClick(item: FileItem) {
  if (item.isDir) return
  if (isPopupMode()) {
    if (clickTimer) { clearTimeout(clickTimer); clickTimer = null }
    selectForPopup(item)
  }
}

function onGoUp() {
  fileStore.goUp()
}
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-px bg-gray-200 dark:bg-neutral-700 rounded-lg overflow-hidden">
    <!-- Back button -->
    <div
      v-if="fileStore.currentPath !== ''"
      class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-neutral-900
             hover:bg-gray-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors"
      @click="onGoUp"
    >
      <svg class="w-9 h-9 flex-shrink-0 text-amber-500 dark:text-amber-400" viewBox="0 0 48 48" fill="currentColor">
        <path d="M6 10c0-1.1.9-2 2-2h10l4 4h18c1.1 0 2 .9 2 2v24c0 1.1-.9 2-2 2H8c-1.1 0-2-.9-2-2V10z" opacity="0.85" />
        <path d="M26 22H16m0 0l5 5m-5-5l5-5" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <span class="text-sm text-gray-600 dark:text-gray-400 font-medium">..</span>
    </div>

    <!-- Folders -->
    <div
      v-for="folder in fileStore.folders"
      :key="'d-' + folder.path"
      class="relative group flex items-center gap-2 px-3 py-2 bg-white dark:bg-neutral-900
             hover:bg-gray-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors"
      :class="{ 'ring-2 ring-inset ring-rfm-primary bg-rfm-primary/5': isSelected(folder) }"
      draggable="true"
      @click="onItemClick(folder)"
      @contextmenu="onContextMenu($event, folder)"
    >
      <!-- Selection checkbox -->
      <SelectionCheckbox
        v-if="configStore.config?.multipleSelection"
        :checked="isSelected(folder)"
        @change="onSelectionChange(folder, $event)"
      />

      <!-- Folder icon -->
      <svg class="w-9 h-9 flex-shrink-0 text-amber-500 dark:text-amber-400" viewBox="0 0 48 48" fill="currentColor">
        <path d="M4 12c0-1.1.9-2 2-2h10l4 4h20c1.1 0 2 .9 2 2v2H4v-6z" opacity="0.7" />
        <path d="M4 18h40v20c0 1.1-.9 2-2 2H6c-1.1 0-2-.9-2-2V18z" opacity="0.9" />
      </svg>

      <!-- Name -->
      <span class="text-sm truncate text-gray-800 dark:text-gray-200 font-medium flex-1 min-w-0" :title="folder.name">
        {{ folder.name }}
      </span>

      <!-- Action buttons -->
      <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" @click.stop>
        <!-- Rename -->
        <button
          v-if="configStore.config?.renameFolders"
          class="p-1 rounded hover:bg-gray-200 dark:hover:bg-neutral-700 text-gray-500 dark:text-gray-400"
          :title="configStore.t('Rename')"
          @click="ops.renameItem(folder)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
          </svg>
        </button>

        <!-- Delete -->
        <button
          v-if="configStore.config?.deleteFolders"
          class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-red-500 dark:text-red-400"
          :title="configStore.t('Delete')"
          @click="ops.deleteItem(folder)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Files -->
    <div
      v-for="file in fileStore.files"
      :key="'f-' + file.path"
      class="relative group flex items-center gap-2 px-3 py-2 bg-white dark:bg-neutral-900
             hover:bg-gray-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-colors"
      :class="{ 'ring-2 ring-inset ring-rfm-primary bg-rfm-primary/5': isSelected(file) }"
      draggable="true"
      @click="onItemClick(file)"
      @dblclick="onDblClick(file)"
      @contextmenu="onContextMenu($event, file)"
    >
      <!-- Selection checkbox -->
      <SelectionCheckbox
        v-if="configStore.config?.multipleSelection"
        :checked="isSelected(file)"
        @change="onSelectionChange(file, $event)"
      />

      <!-- Small thumbnail or file icon -->
      <div
        v-if="file.thumbnailUrl"
        class="w-7 h-7 flex-shrink-0 rounded overflow-hidden bg-gray-100 dark:bg-neutral-800"
      >
        <img
          :src="file.thumbnailUrl"
          :alt="file.name"
          class="object-cover w-full h-full"
          loading="lazy"
        />
      </div>
      <svg
        v-else
        class="w-6 h-6 flex-shrink-0"
        :class="getIconColor(file.extension, file.category)"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <!-- PDF -->
        <template v-if="getIconType(file.extension, file.category) === 'pdf'">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <rect x="6" y="13" width="12" height="7" rx="1" fill="currentColor" opacity="0.15" />
          <path d="M9 17.5v-3h1.5a1.25 1.25 0 010 2.5H9" stroke-width="1.2" />
        </template>
        <!-- Word -->
        <template v-else-if="getIconType(file.extension, file.category) === 'word'">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <line x1="8" y1="13" x2="16" y2="13" />
          <line x1="8" y1="16" x2="14" y2="16" />
          <line x1="8" y1="19" x2="12" y2="19" />
        </template>
        <!-- Excel -->
        <template v-else-if="getIconType(file.extension, file.category) === 'excel'">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <rect x="7" y="12" width="10" height="8" rx="0.5" />
          <line x1="7" y1="16" x2="17" y2="16" />
          <line x1="12" y1="12" x2="12" y2="20" />
        </template>
        <!-- Video - clapperboard -->
        <template v-else-if="file.category === 'video'">
          <rect x="2" y="6" width="20" height="15" rx="2" />
          <path d="M2 10h20" />
          <path d="M6 6l-2.5 4M11 6l-2.5 4M16 6l-2.5 4M21 6l-2.5 4" stroke-width="1.2" />
          <path d="M10 14v5l4.5-2.5L10 14z" fill="currentColor" opacity="0.3" />
        </template>
        <!-- Audio - speaker with waves -->
        <template v-else-if="file.category === 'audio'">
          <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor" opacity="0.15" />
          <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
          <path d="M15.54 8.46a5 5 0 010 7.07" />
          <path d="M19.07 4.93a10 10 0 010 14.14" />
        </template>
        <!-- Archive - package box -->
        <template v-else-if="file.category === 'archive'">
          <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
          <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
          <line x1="12" y1="22.08" x2="12" y2="12" />
        </template>
        <!-- Image -->
        <template v-else-if="file.category === 'image'">
          <rect x="3" y="3" width="18" height="18" rx="2" />
          <circle cx="8.5" cy="8.5" r="1.5" />
          <path d="M21 15l-5-5L5 21" />
        </template>
        <!-- Generic document / Misc -->
        <template v-else>
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <line x1="8" y1="13" x2="16" y2="13" />
          <line x1="8" y1="17" x2="16" y2="17" />
        </template>
      </svg>

      <!-- Name -->
      <span class="text-sm truncate text-gray-800 dark:text-gray-200 flex-1 min-w-0" :title="file.name">
        {{ file.name }}
      </span>

      <!-- Size -->
      <span class="text-xs text-gray-400 dark:text-gray-500 flex-shrink-0 w-14 text-right">
        {{ formatFileSize(file.size) }}
      </span>

      <!-- Action buttons -->
      <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" @click.stop>
        <!-- Preview -->
        <button
          v-if="file.category === 'image'"
          class="p-1 rounded hover:bg-gray-200 dark:hover:bg-neutral-700 text-gray-500 dark:text-gray-400"
          :title="configStore.t('Preview')"
          @click="doPreview(file)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" />
          </svg>
        </button>

        <!-- Edit Image -->
        <button
          v-if="configStore.config?.imageEditorActive && isEditableImage(file.extension)"
          class="p-1 rounded hover:bg-gray-200 dark:hover:bg-neutral-700 text-gray-500 dark:text-gray-400"
          :title="configStore.t('Edit_image')"
          @click="ops.editImage(file)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
          </svg>
        </button>

        <!-- Download -->
        <button
          v-if="configStore.config?.downloadFiles"
          class="p-1 rounded hover:bg-gray-200 dark:hover:bg-neutral-700 text-gray-500 dark:text-gray-400"
          :title="configStore.t('Download')"
          @click="onDownload(file)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />
          </svg>
        </button>

        <!-- Rename -->
        <button
          v-if="configStore.config?.renameFiles"
          class="p-1 rounded hover:bg-gray-200 dark:hover:bg-neutral-700 text-gray-500 dark:text-gray-400"
          :title="configStore.t('Rename')"
          @click="ops.renameItem(file)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
          </svg>
        </button>

        <!-- Duplicate -->
        <button
          v-if="configStore.config?.duplicateFiles"
          class="p-1 rounded hover:bg-gray-200 dark:hover:bg-neutral-700 text-gray-500 dark:text-gray-400"
          :title="configStore.t('Duplicate')"
          @click="ops.duplicateItem(file)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
          </svg>
        </button>

        <!-- Delete -->
        <button
          v-if="configStore.config?.deleteFiles"
          class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-red-500 dark:text-red-400"
          :title="configStore.t('Delete')"
          @click="ops.deleteItem(file)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Error state -->
    <div
      v-if="fileStore.loadError"
      class="col-span-full flex flex-col items-center justify-center py-16 bg-white dark:bg-neutral-900 text-red-500 dark:text-red-400"
    >
      <svg class="w-16 h-16 mb-3" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="24" cy="24" r="20" />
        <line x1="24" y1="14" x2="24" y2="28" stroke-width="2" />
        <circle cx="24" cy="34" r="1.5" fill="currentColor" />
      </svg>
      <span class="text-sm">{{ fileStore.loadError }}</span>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="fileStore.folders.length === 0 && fileStore.files.length === 0 && fileStore.currentPath === ''"
      class="col-span-full flex flex-col items-center justify-center py-16 bg-white dark:bg-neutral-900 text-gray-400 dark:text-gray-500"
    >
      <svg class="w-16 h-16 mb-3" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M6 10c0-1.1.9-2 2-2h10l4 4h18c1.1 0 2 .9 2 2v24c0 1.1-.9 2-2 2H8c-1.1 0-2-.9-2-2V10z" />
        <line x1="18" y1="26" x2="30" y2="26" />
      </svg>
      <span class="text-sm">{{ configStore.t('Empty_Folder') }}</span>
    </div>
  </div>
</template>
