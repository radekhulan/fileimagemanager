<script setup lang="ts">
import { ref, onMounted, computed, defineAsyncComponent } from 'vue'
import { useConfigStore } from '@/stores/configStore'
import { useFileStore } from '@/stores/fileStore'
import { useUiStore } from '@/stores/uiStore'
import { useUploadStore } from '@/stores/uploadStore'
import { useKeyboard } from '@/composables/useKeyboard'
import AppHeader from '@/components/layout/AppHeader.vue'
import Breadcrumb from '@/components/layout/Breadcrumb.vue'
import SortBar from '@/components/layout/SortBar.vue'
import StatusBar from '@/components/layout/StatusBar.vue'
import FileGrid from '@/components/file-list/FileGrid.vue'
import FileList from '@/components/file-list/FileList.vue'
import FileColumns from '@/components/file-list/FileColumns.vue'
import UploadPanel from '@/components/upload/UploadPanel.vue'
const ImageEditor = defineAsyncComponent(() => import('@/components/preview/ImageEditor.vue'))
import PreviewOverlay from '@/components/preview/PreviewOverlay.vue'
import ContextMenu from '@/components/common/ContextMenu.vue'
import LoadingOverlay from '@/components/common/LoadingOverlay.vue'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import PromptDialog from '@/components/dialogs/PromptDialog.vue'
import AlertDialog from '@/components/dialogs/AlertDialog.vue'
import TextEditorDialog from '@/components/dialogs/TextEditorDialog.vue'
import ChmodDialog from '@/components/dialogs/ChmodDialog.vue'
import LanguageDialog from '@/components/dialogs/LanguageDialog.vue'

const configStore = useConfigStore()
const fileStore = useFileStore()
const ui = useUiStore()
const uploadStore = useUploadStore()

// Register keyboard shortcuts
useKeyboard()

const viewComponent = computed(() => {
  switch (ui.viewMode) {
    case 1: return FileList
    case 2: return FileColumns
    default: return FileGrid
  }
})

onMounted(async () => {
  await configStore.initialize()
  if (configStore.config) {
    ui.initDarkMode(configStore.config.darkMode)
    ui.viewMode = configStore.config.defaultView as 0 | 1 | 2
  }
  const lastPath = fileStore.getLastPath()
  await fileStore.loadDirectory(lastPath)
})

function onMainClick() {
  ui.hideContextMenu()
}

// ── Global drag & drop ──────────────────────────────────────────────
const isDraggingOver = ref(false)
let dragCounter = 0

function onGlobalDragEnter(e: DragEvent) {
  e.preventDefault()
  // Only react to external file drops, not internal drags
  if (!e.dataTransfer?.types.includes('Files')) return
  dragCounter++
  isDraggingOver.value = true
}

function onGlobalDragOver(e: DragEvent) {
  e.preventDefault()
  if (e.dataTransfer) {
    e.dataTransfer.dropEffect = 'copy'
  }
}

function onGlobalDragLeave(e: DragEvent) {
  e.preventDefault()
  dragCounter--
  if (dragCounter <= 0) {
    dragCounter = 0
    isDraggingOver.value = false
  }
}

function onGlobalDrop(e: DragEvent) {
  e.preventDefault()
  dragCounter = 0
  isDraggingOver.value = false
  if (e.dataTransfer?.files.length) {
    uploadStore.addFiles(e.dataTransfer.files)
    ui.showUploadPanel = true
  }
}
</script>

<template>
  <div
    v-if="configStore.isReady"
    class="h-screen flex flex-col bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100 overflow-hidden"
    @click="onMainClick"
    @dragenter="onGlobalDragEnter"
    @dragover="onGlobalDragOver"
    @dragleave="onGlobalDragLeave"
    @drop="onGlobalDrop"
  >
    <!-- Header toolbar -->
    <AppHeader />

    <!-- Breadcrumb navigation -->
    <Breadcrumb />

    <!-- Sort bar (list/columns view) -->
    <SortBar v-if="configStore.config?.showSortingBar && ui.viewMode > 0" />

    <!-- Main file area -->
    <main class="relative flex-1 overflow-y-auto overflow-x-hidden p-2 sm:p-3">
      <LoadingOverlay :show="fileStore.loading" />
      <component
        v-if="!fileStore.loading"
        :is="viewComponent"
      />

      <!-- Drop overlay -->
      <Transition
        enter-active-class="transition-opacity duration-150"
        leave-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="isDraggingOver"
          class="absolute inset-0 z-40 flex flex-col items-center justify-center
                 bg-rfm-primary/10 dark:bg-rfm-primary/20
                 border-2 border-dashed border-rfm-primary rounded-lg
                 pointer-events-none"
        >
          <svg class="w-16 h-16 text-rfm-primary mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
          </svg>
          <p class="text-lg font-semibold text-rfm-primary">{{ configStore.t('Upload_drop_here') }}</p>
        </div>
      </Transition>
    </main>

    <!-- Status bar -->
    <StatusBar />

    <!-- Overlays -->
    <UploadPanel
      v-if="ui.showUploadPanel"
      @close="ui.showUploadPanel = false"
    />
    <ImageEditor />
    <PreviewOverlay />

    <!-- Context Menu -->
    <ContextMenu />

    <!-- Dialogs -->
    <ConfirmDialog v-if="ui.confirmDialog.visible" />
    <PromptDialog v-if="ui.promptDialog.visible" />
    <AlertDialog v-if="ui.alertDialog.visible" />
    <TextEditorDialog
      v-if="ui.textEditorFile"
      :mode="ui.textEditorFile.mode"
      :path="ui.textEditorFile.path"
      :current-dir="ui.textEditorFile.currentDir"
      @close="ui.textEditorFile = null"
    />
    <ChmodDialog
      v-if="ui.chmodTarget"
      :path="ui.chmodTarget.path"
      :is-dir="ui.chmodTarget.isDir"
      :current-permissions="ui.chmodTarget.permissions"
      @close="ui.chmodTarget = null"
    />
    <LanguageDialog v-if="ui.showLanguageDialog" />
  </div>

  <!-- Loading state before init -->
  <div v-else class="h-screen flex items-center justify-center bg-white dark:bg-neutral-900">
    <div class="flex flex-col items-center gap-3">
      <svg class="w-8 h-8 rfm-spinner text-rfm-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83M2 12h4m12 0h4m-3.93 7.07l-2.83-2.83M7.76 7.76L4.93 4.93" />
      </svg>
      <span class="text-sm text-gray-500 dark:text-gray-400">{{ configStore.t('Loading') }}</span>
    </div>
  </div>
</template>
