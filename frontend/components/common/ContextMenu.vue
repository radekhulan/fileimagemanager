<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue'
import { useUiStore } from '@/stores/uiStore'
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import { useClipboardStore } from '@/stores/clipboardStore'
import { filesApi, operationsApi } from '@/api/files'
import { isEditableImage } from '@/utils/extensions'

const ui = useUiStore()
const fileStore = useFileStore()
const configStore = useConfigStore()
const clipboard = useClipboardStore()
const { t } = configStore

const menuRef = ref<HTMLElement>()
const menuStyle = ref({ top: '0px', left: '0px' })

const item = computed(() => ui.contextMenuItem)
const config = computed(() => configStore.config)

watch(() => ui.contextMenuVisible, async (visible) => {
  if (visible) {
    await nextTick()
    adjustPosition()
  }
})

function adjustPosition() {
  const x = ui.contextMenuX
  const y = ui.contextMenuY
  const el = menuRef.value
  if (!el) return

  const rect = el.getBoundingClientRect()
  const maxX = window.innerWidth - rect.width - 8
  const maxY = window.innerHeight - rect.height - 8

  menuStyle.value = {
    left: `${Math.min(x, maxX)}px`,
    top: `${Math.min(y, maxY)}px`,
  }
}

function close() {
  ui.hideContextMenu()
}

function onClickOutside(e: MouseEvent) {
  if (menuRef.value && !menuRef.value.contains(e.target as Node)) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', onClickOutside)
  document.addEventListener('contextmenu', onClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside)
  document.removeEventListener('contextmenu', onClickOutside)
})

// Actions
async function onSelect() {
  if (!item.value) return
  if (configStore.isEditorMode) {
    // Send file URL back to editor
    const url = configStore.getFileUrl(item.value.path)
    if (configStore.editorParams.crossdomain) {
      window.parent.postMessage({ sender: 'fileimagemanager', url }, '*')
    } else {
      window.parent.postMessage({ sender: 'fileimagemanager', url }, window.location.origin)
    }
  }
  close()
}

async function onPreview() {
  if (!item.value) return
  const cat = item.value.category
  if (cat === 'image' || cat === 'video' || cat === 'audio') {
    ui.previewItem = {
      url: configStore.getFileUrl(item.value.path),
      type: cat,
      name: item.value.name,
      path: item.value.path,
    }
  } else {
    const preview = await filesApi.preview(item.value.path)
    if (preview.type !== 'unsupported') {
      ui.previewItem = { ...preview, url: preview.url || '', name: item.value.name, path: item.value.path }
    }
  }
  close()
}

async function onDownload() {
  if (!item.value) return
  const a = document.createElement('a')
  a.href = `/api/files/download?path=${encodeURIComponent(item.value.path)}`
  a.download = item.value.name
  a.click()
  close()
}

async function onRename() {
  if (!item.value) return
  const ext = item.value.name.includes('.') ? '.' + item.value.name.split('.').pop() : ''
  const baseName = ext ? item.value.name.slice(0, -ext.length) : item.value.name
  const newName = await ui.prompt(t('Rename'), '', baseName)
  if (newName && newName !== baseName) {
    try {
      if (item.value.isDir) {
        await operationsApi.rename(item.value.path, newName)
      } else {
        await operationsApi.rename(item.value.path, newName + ext)
      }
      await fileStore.refresh()
    } catch (err: any) {
      await ui.alert(t('Error'), err?.response?.data?.error || t('Rename_Failed'))
    }
  }
  close()
}

async function onDuplicate() {
  if (!item.value) return
  try {
    await operationsApi.duplicate(item.value.path)
    await fileStore.refresh()
  } catch (err: any) {
    await ui.alert(t('Error'), err?.response?.data?.error || t('Duplicate_Failed'))
  }
  close()
}

async function onDelete() {
  if (!item.value) return
  const msg = item.value.isDir ? t('Confirm_Folder_del') : t('Confirm_del')
  const confirmed = await ui.confirm(t('Erase'), msg)
  if (confirmed) {
    try {
      await operationsApi.delete(item.value.path)
      await fileStore.refresh()
    } catch (err: any) {
      await ui.alert(t('Error'), err?.response?.data?.error || t('Delete_Failed'))
    }
  }
  close()
}

async function onCopy() {
  if (!item.value) return
  await clipboard.copy([item.value.path])
  close()
}

async function onCut() {
  if (!item.value) return
  await clipboard.cut([item.value.path])
  close()
}

function onEditImage() {
  if (!item.value) return
  ui.openImageEditor(item.value.path, configStore.getFileUrl(item.value.path))
  close()
}

function onEditText() {
  if (!item.value) return
  ui.textEditorFile = { mode: 'edit', path: item.value.path }
  close()
}

async function onChmod() {
  if (!item.value) return
  ui.chmodTarget = {
    path: item.value.path,
    isDir: item.value.isDir,
    permissions: item.value.permissions,
  }
  close()
}

async function onExtract() {
  if (!item.value) return
  try {
    await operationsApi.extract(item.value.path)
    await fileStore.refresh()
  } catch (err: any) {
    await ui.alert(t('Error'), err?.response?.data?.error || t('Extract_Failed'))
  }
  close()
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-100"
      leave-active-class="transition-all duration-75"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="ui.contextMenuVisible && item"
        ref="menuRef"
        class="fixed z-50 min-w-[180px] py-1 bg-white dark:bg-neutral-800 rounded-lg shadow-xl border border-gray-200 dark:border-neutral-700 text-sm"
        :style="menuStyle"
      >
        <!-- Select (editor mode) -->
        <button
          v-if="configStore.isEditorMode && !item.isDir"
          class="context-menu-item"
          @click="onSelect"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
          {{ t('Select') }}
        </button>

        <!-- Preview -->
        <button
          v-if="!item.isDir"
          class="context-menu-item"
          @click="onPreview"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
          {{ t('Preview') }}
        </button>

        <!-- Download -->
        <button
          v-if="config?.downloadFiles && !item.isDir"
          class="context-menu-item"
          @click="onDownload"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" /></svg>
          {{ t('Download') }}
        </button>

        <div v-if="!item.isDir" class="my-1 border-t border-gray-100 dark:border-neutral-700" />

        <!-- Edit image -->
        <button
          v-if="config?.imageEditorActive && !item.isDir && isEditableImage(item.extension)"
          class="context-menu-item"
          @click="onEditImage"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="M21 15l-5-5L5 21" /></svg>
          {{ t('Edit_image') }}
        </button>

        <!-- Edit text -->
        <button
          v-if="config?.editTextFiles && !item.isDir && config?.editableTextFileExts?.includes(item.extension)"
          class="context-menu-item"
          @click="onEditText"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
          {{ t('Edit_File') }}
        </button>

        <!-- Rename -->
        <button
          v-if="item.isDir ? config?.renameFolders : config?.renameFiles"
          class="context-menu-item"
          @click="onRename"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
          {{ t('Rename') }}
        </button>

        <!-- Duplicate -->
        <button
          v-if="config?.duplicateFiles && !item.isDir"
          class="context-menu-item"
          @click="onDuplicate"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" /></svg>
          {{ t('Duplicate') }}
        </button>

        <div class="my-1 border-t border-gray-100 dark:border-neutral-700" />

        <!-- Copy -->
        <button
          v-if="config?.copyCutFiles"
          class="context-menu-item"
          @click="onCopy"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" /></svg>
          {{ t('Copy') }}
        </button>

        <!-- Cut -->
        <button
          v-if="config?.copyCutFiles"
          class="context-menu-item"
          @click="onCut"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3" /><circle cx="6" cy="18" r="3" /><line x1="20" y1="4" x2="8.12" y2="15.88" /><line x1="14.47" y1="14.48" x2="20" y2="20" /><line x1="8.12" y1="8.12" x2="12" y2="12" /></svg>
          {{ t('Cut') }}
        </button>

        <!-- Extract -->
        <button
          v-if="config?.extractFiles && !item.isDir && ['zip', 'gz', 'tar'].includes(item.extension)"
          class="context-menu-item"
          @click="onExtract"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" /><polyline points="14 2 14 8 20 8" /><path d="M12 18v-6M9 15l3 3 3-3" /></svg>
          {{ t('Extract') }}
        </button>

        <!-- Chmod -->
        <button
          v-if="config?.chmodFiles"
          class="context-menu-item"
          @click="onChmod"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0110 0v4" /></svg>
          {{ t('File_Permission') }}
        </button>

        <div class="my-1 border-t border-gray-100 dark:border-neutral-700" />

        <!-- Delete -->
        <button
          v-if="item.isDir ? config?.deleteFolders : config?.deleteFiles"
          class="context-menu-item text-rfm-danger hover:!bg-red-50 dark:hover:!bg-red-900/20"
          @click="onDelete"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" /></svg>
          {{ t('Erase') }}
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
@reference "tailwindcss";
.context-menu-item {
  @apply flex items-center gap-2.5 w-full px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors;
}
</style>
