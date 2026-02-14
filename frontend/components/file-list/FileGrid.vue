<script setup lang="ts">
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import { useUiStore } from '@/stores/uiStore'
import { useFileOperations } from '@/composables/useFileOperations'
import { useEditorIntegration } from '@/composables/useEditorIntegration'
import { filesApi } from '@/api/files'
import type { FileItem as FileItemType } from '@/types/files'
import FileItemComponent from './FileItem.vue'
import FolderItem from './FolderItem.vue'
import BackButton from './BackButton.vue'
import SelectionCheckbox from './SelectionCheckbox.vue'
import { useRenderLimit } from '@/composables/useRenderLimit'

const fileStore = useFileStore()
const configStore = useConfigStore()
const ui = useUiStore()
const ops = useFileOperations()
const { isEditorMode, isPopupMode, selectFile, selectForPopup } = useEditorIntegration()
const { visibleFolders, visibleFiles, allRendered, sentinelRef } = useRenderLimit(() => fileStore.folders, () => fileStore.files, () => fileStore.items)

function isSelected(item: FileItemType): boolean {
  return fileStore.selectedItems.has(item.path)
}

// --- Helpers for event delegation ---

function findItemByPath(path: string): FileItemType | undefined {
  return fileStore.items.find(i => i.path === path)
}

function getItemFromEvent(e: Event): FileItemType | null {
  const li = (e.target as HTMLElement).closest<HTMLElement>('li[data-path]')
  if (!li) return null
  return findItemByPath(li.dataset.path!) ?? null
}

// --- File actions ---

let clickTimer: ReturnType<typeof setTimeout> | null = null

async function doPreview(item: FileItemType) {
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

function onPreview(item: FileItemType) {
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

function onEditImage(item: FileItemType) {
  ops.editImage(item)
}

function onDownload(item: FileItemType) {
  const url = filesApi.getDownloadUrl(item.path)
  window.open(url, '_blank')
}

function onRename(item: FileItemType) {
  ops.renameItem(item)
}

function onDuplicate(item: FileItemType) {
  ops.duplicateItem(item)
}

function onDelete(item: FileItemType) {
  ops.deleteItem(item)
}

// --- Folder actions ---

function onFolderNavigate(item: FileItemType) {
  fileStore.navigate(item.path + '/')
}

function onFolderRename(item: FileItemType) {
  ops.renameItem(item)
}

function onFolderDelete(item: FileItemType) {
  ops.deleteItem(item)
}

// --- Delegated event handlers ---

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

function onSelectionChange(item: FileItemType, checked: boolean) {
  if (checked !== isSelected(item)) {
    fileStore.toggleSelection(item.path)
  }
}

function onGoUp() {
  fileStore.goUp()
}
</script>

<template>
  <ul
    class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7 2xl:grid-cols-8 gap-2
           list-none m-0 p-0"
    @contextmenu="onGridContextMenu"
    @dblclick="onGridDblClick"
  >
    <!-- Back button -->
    <BackButton
      v-if="fileStore.currentPath !== ''"
      @click="onGoUp"
    />

    <!-- Folders first -->
    <li
      v-for="folder in visibleFolders"
      :key="'d-' + folder.path"
      :data-path="folder.path"
      class="relative group cv-auto"
      :class="{ 'ring-2 ring-rfm-primary ring-offset-1 dark:ring-offset-neutral-900 rounded-lg': isSelected(folder) }"
      draggable="true"
    >
      <SelectionCheckbox
        v-if="configStore.config?.multipleSelection"
        :checked="isSelected(folder)"
        @change="onSelectionChange(folder, $event)"
      />
      <FolderItem
        :item="folder"
        @navigate="onFolderNavigate"
        @rename="onFolderRename"
        @delete="onFolderDelete"
      />
    </li>

    <!-- Files -->
    <li
      v-for="file in visibleFiles"
      :key="'f-' + file.path"
      :data-path="file.path"
      class="relative group cv-auto"
      :class="{ 'ring-2 ring-rfm-primary ring-offset-1 dark:ring-offset-neutral-900 rounded-lg': isSelected(file) }"
      draggable="true"
    >
      <SelectionCheckbox
        v-if="configStore.config?.multipleSelection"
        :checked="isSelected(file)"
        @change="onSelectionChange(file, $event)"
      />
      <FileItemComponent
        :item="file"
        @click="onPreview"
        @preview="doPreview"
        @edit-image="onEditImage"
        @download="onDownload"
        @rename="onRename"
        @duplicate="onDuplicate"
        @delete="onDelete"
      />
    </li>

    <!-- Render-limit sentinel -->
    <li v-if="!allRendered" ref="sentinelRef" class="h-px" aria-hidden="true" />

    <!-- Error state -->
    <li
      v-if="fileStore.loadError"
      class="col-span-full flex flex-col items-center justify-center py-16 text-red-500 dark:text-red-400"
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
      class="col-span-full flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500"
    >
      <svg class="w-16 h-16 mb-3" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M6 10c0-1.1.9-2 2-2h10l4 4h18c1.1 0 2 .9 2 2v24c0 1.1-.9 2-2 2H8c-1.1 0-2-.9-2-2V10z" />
        <line x1="18" y1="26" x2="30" y2="26" />
      </svg>
      <span class="text-sm">{{ configStore.t('Empty_Folder') }}</span>
    </li>
  </ul>
</template>

<style scoped>
/* Skip rendering of off-screen grid items — browser uses placeholder size for layout */
.cv-auto {
  content-visibility: auto;
  contain-intrinsic-size: auto 150px auto 180px;
}
</style>
