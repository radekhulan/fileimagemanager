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
import { useRenderLimit } from '@/composables/useRenderLimit'

const ICON_MAP: Record<string, string> = {
  pdf: '#rfm-i24-pdf', word: '#rfm-i24-word', excel: '#rfm-i24-excel',
  video: '#rfm-i24-video', audio: '#rfm-i24-audio', archive: '#rfm-i24-archive',
  image: '#rfm-i24-image',
}

const fileStore = useFileStore()
const configStore = useConfigStore()
const ui = useUiStore()
const { isEditorMode, isPopupMode, selectFile, selectForPopup } = useEditorIntegration()
const { visibleFolders, visibleFiles, allRendered, sentinelRef } = useRenderLimit(() => fileStore.folders, () => fileStore.files, () => fileStore.items)

function isSelected(item: FileItem): boolean {
  return fileStore.selectedItems.has(item.path)
}

// --- Helpers for event delegation ---

function findItemByPath(path: string): FileItem | undefined {
  return fileStore.items.find(i => i.path === path)
}

function getItemFromEvent(e: Event): FileItem | null {
  const li = (e.target as HTMLElement).closest<HTMLElement>('li[data-path]')
  if (!li) return null
  return findItemByPath(li.dataset.path!) ?? null
}

function onListClick(event: MouseEvent) {
  // Ignore clicks on action buttons
  if ((event.target as HTMLElement).closest('button')) return
  const item = getItemFromEvent(event)
  if (!item) return
  if (item.isDir) {
    fileStore.navigate(item.path + '/')
  } else {
    onPreview(item)
  }
}

function onListContextMenu(event: MouseEvent) {
  const item = getItemFromEvent(event)
  if (!item) return
  event.preventDefault()
  ui.showContextMenu(event.clientX, event.clientY, item)
}

function onSelectionChange(item: FileItem, checked: boolean) {
  if (checked !== isSelected(item)) {
    fileStore.toggleSelection(item.path)
  }
}

let clickTimer: ReturnType<typeof setTimeout> | null = null

function onListDblClick(event: MouseEvent) {
  const item = getItemFromEvent(event)
  if (!item || item.isDir) return
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

    <ul class="list-none m-0 p-0" @click="onListClick" @contextmenu="onListContextMenu" @dblclick="onListDblClick">
      <!-- Back button row -->
      <li
        v-if="fileStore.currentPath !== ''"
        class="grid grid-cols-[1fr_auto_auto_auto_auto_auto] gap-x-4 px-3 py-2
               hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer
               border-b border-gray-100 dark:border-gray-800 transition-[background-color] items-center"
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
        v-for="folder in visibleFolders"
        :key="'d-' + folder.path"
        :data-path="folder.path"
        class="cv-auto relative group grid grid-cols-[1fr] sm:grid-cols-[1fr_auto_auto_auto_auto_auto] gap-x-4 px-3 py-2
               hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer
               border-b border-gray-100 dark:border-gray-800 transition-[background-color] items-center"
        :class="{ 'bg-rfm-primary/5 ring-2 ring-inset ring-rfm-primary': isSelected(folder) }"
        draggable="true"
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
        v-for="file in visibleFiles"
        :key="'f-' + file.path"
        :data-path="file.path"
        class="cv-auto relative group grid grid-cols-[1fr] sm:grid-cols-[1fr_auto_auto_auto_auto_auto] gap-x-4 px-3 py-2
               hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer
               border-b border-gray-100 dark:border-gray-800 transition-[background-color] items-center"
        :class="{ 'bg-rfm-primary/5 ring-2 ring-inset ring-rfm-primary': isSelected(file) }"
        draggable="true"
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

          <!-- File type icon for non-images — references shared SVG sprite -->
          <svg
            v-else
            class="w-6 h-6 flex-shrink-0"
            :class="getIconColor(file.extension, file.category)"
            viewBox="0 0 24 24"
          >
            <use :href="ICON_MAP[getIconType(file.extension, file.category)] || '#rfm-i24-generic'" />
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

      <!-- Render-limit sentinel — triggers loading more VNodes on scroll -->
      <li v-if="!allRendered" ref="sentinelRef" class="h-px" aria-hidden="true" />

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

<style scoped>
.cv-auto {
  content-visibility: auto;
  contain-intrinsic-size: auto 100% auto 52px;
}
</style>
