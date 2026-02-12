<script setup lang="ts">
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import { useUiStore } from '@/stores/uiStore'
import type { FileItem } from '@/types/files'
import { formatFileSize, formatDate } from '@/utils/filesize'
import { getIconColor, getIconType, isEditableImage } from '@/utils/extensions'
import { useEditorIntegration } from '@/composables/useEditorIntegration'
import { filesApi, operationsApi, foldersApi } from '@/api/files'
import SelectionCheckbox from './SelectionCheckbox.vue'
import Thumbnail from './Thumbnail.vue'

const fileStore = useFileStore()
const configStore = useConfigStore()
const ui = useUiStore()
const { isEditorMode, isPopupMode, selectFile, selectForPopup } = useEditorIntegration()

function isSelected(item: FileItem): boolean {
  return fileStore.selectedItems.has(item.path)
}

function onRowClick(item: FileItem) {
  if (item.isDir) {
    fileStore.navigate(item.path + '/')
  } else {
    onPreview(item)
  }
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

let clickTimer: ReturnType<typeof setTimeout> | null = null

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

function onDownload(item: FileItem) {
  const url = filesApi.getDownloadUrl(item.path)
  window.open(url, '_blank')
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

function onEditImage(item: FileItem) {
  ui.openImageEditor(item.path, configStore.getFileUrl(item.path))
}

async function onRename(item: FileItem) {
  const ext = item.isDir ? '' : '.' + item.extension
  const baseName = item.isDir ? item.name : item.name.replace(/\.[^.]+$/, '')
  const newName = await ui.prompt(
    configStore.t('Rename'),
    configStore.t('New_file_name'),
    baseName
  )
  if (newName && newName !== baseName) {
    if (item.isDir) {
      await foldersApi.rename(item.path, newName)
    } else {
      await operationsApi.rename(item.path, newName + ext)
    }
    await fileStore.refresh()
  }
}

async function onDelete(item: FileItem) {
  const confirmed = await ui.confirm(
    configStore.t('Confirm_del'),
    configStore.t('Confirm_del_msg', item.name)
  )
  if (confirmed) {
    if (item.isDir) {
      await foldersApi.delete(item.path)
    } else {
      await operationsApi.delete(item.path)
    }
    await fileStore.refresh()
  }
}

function getDimension(item: FileItem): string {
  if (item.width && item.height) {
    return `${item.width}x${item.height}`
  }
  return ''
}
</script>

<template>
  <div class="w-full">
    <!-- Header row -->
    <div
      class="grid grid-cols-[1fr_auto_auto_auto_auto_auto] gap-x-4 px-3 py-2
             text-xs font-semibold text-gray-500 dark:text-gray-400
             border-b border-gray-200 dark:border-gray-700 uppercase tracking-wide
             hidden sm:grid"
    >
      <span>{{ configStore.t('Name') }}</span>
      <span class="w-24 text-right">{{ configStore.t('Date') }}</span>
      <span class="w-20 text-right">{{ configStore.t('Size') }}</span>
      <span class="w-20 text-right">{{ configStore.t('Dimension') }}</span>
      <span class="w-14 text-center">{{ configStore.t('Type') }}</span>
      <span class="w-28 text-center">{{ configStore.t('Actions') }}</span>
    </div>

    <ul class="list-none m-0 p-0">
      <!-- Back button row -->
      <li
        v-if="fileStore.currentPath !== ''"
        class="grid grid-cols-[1fr_auto_auto_auto_auto_auto] gap-x-4 px-3 py-2
               hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer
               border-b border-gray-100 dark:border-gray-800 transition-colors items-center"
        @click="onGoUp"
      >
        <div class="flex items-center gap-2 min-w-0">
          <svg class="w-9 h-9 flex-shrink-0 text-amber-500 dark:text-amber-400" viewBox="0 0 48 48" fill="currentColor">
            <path d="M6 10c0-1.1.9-2 2-2h10l4 4h18c1.1 0 2 .9 2 2v24c0 1.1-.9 2-2 2H8c-1.1 0-2-.9-2-2V10z" opacity="0.85" />
            <path d="M26 22H16m0 0l5 5m-5-5l5-5" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span class="text-sm font-medium text-gray-600 dark:text-gray-400">..</span>
        </div>
        <span class="w-24 hidden sm:block"></span>
        <span class="w-20 hidden sm:block"></span>
        <span class="w-20 hidden sm:block"></span>
        <span class="w-14 hidden sm:block"></span>
        <span class="w-28 hidden sm:block"></span>
      </li>

      <!-- Folder rows -->
      <li
        v-for="folder in fileStore.folders"
        :key="'d-' + folder.path"
        class="relative group grid grid-cols-[1fr] sm:grid-cols-[1fr_auto_auto_auto_auto_auto] gap-x-4 px-3 py-2
               hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer
               border-b border-gray-100 dark:border-gray-800 transition-colors items-center"
        :class="{ 'bg-rfm-primary/5 ring-2 ring-inset ring-rfm-primary': isSelected(folder) }"
        draggable="true"
        @click="onRowClick(folder)"
        @contextmenu="onContextMenu($event, folder)"
      >
        <!-- Selection checkbox -->
        <SelectionCheckbox
          v-if="configStore.config?.multipleSelection"
          :checked="isSelected(folder)"
          @change="onSelectionChange(folder, $event)"
        />

        <!-- Name -->
        <div class="flex items-center gap-2 min-w-0">
          <svg class="w-9 h-9 flex-shrink-0 text-amber-500 dark:text-amber-400" viewBox="0 0 48 48" fill="currentColor">
            <path d="M4 12c0-1.1.9-2 2-2h10l4 4h20c1.1 0 2 .9 2 2v2H4v-6z" opacity="0.7" />
            <path d="M4 18h40v20c0 1.1-.9 2-2 2H6c-1.1 0-2-.9-2-2V18z" opacity="0.9" />
          </svg>
          <span class="text-sm truncate text-gray-800 dark:text-gray-200 font-medium" :title="folder.name">
            {{ folder.name }}
          </span>
        </div>

        <!-- Date -->
        <span class="w-24 text-right text-xs text-gray-500 dark:text-gray-400 hidden sm:block">
          {{ formatDate(folder.modifiedAt) }}
        </span>

        <!-- Size -->
        <span class="w-20 text-right text-xs text-gray-500 dark:text-gray-400 hidden sm:block">
          &mdash;
        </span>

        <!-- Dimension -->
        <span class="w-20 text-right text-xs text-gray-500 dark:text-gray-400 hidden sm:block"></span>

        <!-- Extension -->
        <span class="w-14 text-center text-xs text-gray-400 dark:text-gray-500 hidden sm:block">
          {{ configStore.t('Folder') }}
        </span>

        <!-- Actions -->
        <div class="w-28 hidden sm:flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <!-- Rename -->
          <button
            v-if="configStore.config?.renameFolders"
            class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400"
            :title="configStore.t('Rename')"
            @click.stop="onRename(folder)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
              <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
            </svg>
          </button>

          <!-- Delete -->
          <button
            v-if="configStore.config?.deleteFolders"
            class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300"
            :title="configStore.t('Delete')"
            @click.stop="onDelete(folder)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6" />
              <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
            </svg>
          </button>
        </div>
      </li>

      <!-- File rows -->
      <li
        v-for="file in fileStore.files"
        :key="'f-' + file.path"
        class="relative group grid grid-cols-[1fr] sm:grid-cols-[1fr_auto_auto_auto_auto_auto] gap-x-4 px-3 py-2
               hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer
               border-b border-gray-100 dark:border-gray-800 transition-colors items-center"
        :class="{ 'bg-rfm-primary/5 ring-2 ring-inset ring-rfm-primary': isSelected(file) }"
        draggable="true"
        @click="onRowClick(file)"
        @dblclick="onDblClick(file)"
        @contextmenu="onContextMenu($event, file)"
      >
        <!-- Selection checkbox -->
        <SelectionCheckbox
          v-if="configStore.config?.multipleSelection"
          :checked="isSelected(file)"
          @change="onSelectionChange(file, $event)"
        />

        <!-- Name with icon/thumb -->
        <div class="flex items-center gap-2 min-w-0">
          <!-- Small thumbnail for images -->
          <div
            v-if="file.thumbnailUrl"
            class="w-7 h-7 flex-shrink-0 rounded overflow-hidden bg-gray-100 dark:bg-gray-800"
          >
            <Thumbnail :src="file.thumbnailUrl" :alt="file.name" />
          </div>

          <!-- File type icon for non-images -->
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

          <span class="text-sm truncate text-gray-800 dark:text-gray-200" :title="file.name">
            {{ file.name }}
          </span>
        </div>

        <!-- Date -->
        <span class="w-24 text-right text-xs text-gray-500 dark:text-gray-400 hidden sm:block">
          {{ formatDate(file.modifiedAt) }}
        </span>

        <!-- Size -->
        <span class="w-20 text-right text-xs text-gray-500 dark:text-gray-400 hidden sm:block">
          {{ formatFileSize(file.size) }}
        </span>

        <!-- Dimension -->
        <span class="w-20 text-right text-xs text-gray-500 dark:text-gray-400 hidden sm:block">
          {{ getDimension(file) }}
        </span>

        <!-- Extension -->
        <span class="w-14 text-center text-xs text-gray-400 dark:text-gray-500 uppercase hidden sm:block">
          {{ file.extension }}
        </span>

        <!-- Action buttons -->
        <div class="w-28 hidden sm:flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <!-- Download -->
          <button
            v-if="configStore.config?.downloadFiles"
            class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400"
            :title="configStore.t('Download')"
            @click.stop="onDownload(file)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
          </button>

          <!-- Preview -->
          <button
            v-if="file.category === 'image'"
            class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400"
            :title="configStore.t('Preview')"
            @click.stop="doPreview(file)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </button>

          <!-- Edit image -->
          <button
            v-if="configStore.config?.imageEditorActive && isEditableImage(file.extension)"
            class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400"
            :title="configStore.t('Edit_image')"
            @click.stop="onEditImage(file)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" />
              <circle cx="8.5" cy="8.5" r="1.5" />
              <path d="M21 15l-5-5L5 21" />
            </svg>
          </button>

          <!-- Rename -->
          <button
            v-if="configStore.config?.renameFiles"
            class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400"
            :title="configStore.t('Rename')"
            @click.stop="onRename(file)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
              <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
            </svg>
          </button>

          <!-- Delete -->
          <button
            v-if="configStore.config?.deleteFiles"
            class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300"
            :title="configStore.t('Delete')"
            @click.stop="onDelete(file)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6" />
              <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
            </svg>
          </button>
        </div>
      </li>

      <!-- Error state -->
      <li
        v-if="fileStore.loadError"
        class="flex flex-col items-center justify-center py-16 text-red-500 dark:text-red-400"
      >
        <svg class="w-16 h-16 mb-3" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="24" cy="24" r="20" />
          <line x1="24" y1="14" x2="24" y2="28" stroke-width="2" />
          <circle cx="24" cy="34" r="1.5" fill="currentColor" />
        </svg>
        <span class="text-sm">{{ fileStore.loadError }}</span>
      </li>

      <!-- Empty state -->
      <li
        v-else-if="fileStore.folders.length === 0 && fileStore.files.length === 0 && fileStore.currentPath === ''"
        class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500"
      >
        <svg class="w-16 h-16 mb-3" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M6 10c0-1.1.9-2 2-2h10l4 4h18c1.1 0 2 .9 2 2v24c0 1.1-.9 2-2 2H8c-1.1 0-2-.9-2-2V10z" />
          <line x1="18" y1="26" x2="30" y2="26" />
        </svg>
        <span class="text-sm">{{ configStore.t('Empty_Folder') }}</span>
      </li>
    </ul>
  </div>
</template>
