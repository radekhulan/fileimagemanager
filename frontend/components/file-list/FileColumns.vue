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

const ICON_MAP: Record<string, string> = {
  pdf: '#rfm-i24-pdf', word: '#rfm-i24-word', excel: '#rfm-i24-excel',
  video: '#rfm-i24-video', audio: '#rfm-i24-audio', archive: '#rfm-i24-archive',
  image: '#rfm-i24-image',
}

const fileStore = useFileStore()
const configStore = useConfigStore()
const ui = useUiStore()
const ops = useFileOperations()
const { isEditorMode, isPopupMode, selectFile, selectForPopup } = useEditorIntegration()

function isSelected(item: FileItem): boolean {
  return fileStore.selectedItems.has(item.path)
}

let clickTimer: ReturnType<typeof setTimeout> | null = null

// --- Helpers for event delegation ---

function findItemByPath(path: string): FileItem | undefined {
  return fileStore.items.find(i => i.path === path)
}

function getItemFromEvent(e: Event): FileItem | null {
  const el = (e.target as HTMLElement).closest<HTMLElement>('[data-path]')
  if (!el) return null
  return findItemByPath(el.dataset.path!) ?? null
}

function onGridClick(event: MouseEvent) {
  if ((event.target as HTMLElement).closest('button')) return
  const item = getItemFromEvent(event)
  if (!item) return
  if (item.isDir) {
    fileStore.navigate(item.path + '/')
  } else {
    onPreview(item)
  }
}

function onGridContextMenu(event: MouseEvent) {
  const item = getItemFromEvent(event)
  if (!item) return
  event.preventDefault()
  ui.showContextMenu(event.clientX, event.clientY, item)
}

function onGridDblClick(event: MouseEvent) {
  const item = getItemFromEvent(event)
  if (!item || item.isDir) return
  if (isPopupMode()) {
    if (clickTimer) { clearTimeout(clickTimer); clickTimer = null }
    selectForPopup(item)
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

function onSelectionChange(item: FileItem, checked: boolean) {
  if (checked !== isSelected(item)) {
    fileStore.toggleSelection(item.path)
  }
}

function onGoUp() {
  fileStore.goUp()
}
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-px bg-gray-200 dark:bg-neutral-700 rounded-lg overflow-hidden"
       @click="onGridClick" @contextmenu="onGridContextMenu" @dblclick="onGridDblClick">
    <!-- Back button -->
    <div
      v-if="fileStore.currentPath !== ''"
      class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-neutral-900
             hover:bg-gray-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-[background-color]"
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
      :data-path="folder.path"
      class="cv-auto relative group flex items-center gap-2 px-3 py-2 bg-white dark:bg-neutral-900
             hover:bg-gray-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-[background-color]"
      :class="{ 'ring-2 ring-inset ring-rfm-primary bg-rfm-primary/5': isSelected(folder) }"
      draggable="true"
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
      :data-path="file.path"
      class="cv-auto relative group flex items-center gap-2 px-3 py-2 bg-white dark:bg-neutral-900
             hover:bg-gray-50 dark:hover:bg-neutral-800/50 cursor-pointer transition-[background-color]"
      :class="{ 'ring-2 ring-inset ring-rfm-primary bg-rfm-primary/5': isSelected(file) }"
      draggable="true"
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
        />
      </div>
      <svg
        v-else
        class="w-6 h-6 flex-shrink-0"
        :class="getIconColor(file.extension, file.category)"
        viewBox="0 0 24 24"
      >
        <use :href="ICON_MAP[getIconType(file.extension, file.category)] || '#rfm-i24-generic'" />
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

<style scoped>
.cv-auto {
  content-visibility: auto;
  contain-intrinsic-size: auto 100% auto 52px;
}
</style>
