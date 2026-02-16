<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import type { TreeNode } from '@/types/files'
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import { useUiStore } from '@/stores/uiStore'
import { useSidebarStore } from '@/stores/sidebarStore'
import { foldersApi } from '@/api/files'

const fileStore = useFileStore()
const configStore = useConfigStore()
const ui = useUiStore()
const sidebar = useSidebarStore()
const { t } = configStore
const config = configStore.config

const menuRef = ref<HTMLElement>()
const menuStyle = ref({ top: '0px', left: '0px' })

const visible = ref(false)
const x = ref(0)
const y = ref(0)
const node = ref<TreeNode | null>(null)

function show(ev: { x: number; y: number }, target: TreeNode) {
  // Hide global context menu first
  ui.hideContextMenu()
  x.value = ev.x
  y.value = ev.y
  node.value = target
  // Defer visibility so document-level contextmenu listeners (from ContextMenu.vue)
  // fire first and don't immediately close this menu
  setTimeout(() => {
    visible.value = true
  }, 0)
}

function close() {
  visible.value = false
}

watch([visible, x, y], async ([v]) => {
  if (v) {
    await nextTick()
    adjustPosition()
  }
})

function adjustPosition() {
  const el = menuRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const maxX = window.innerWidth - rect.width - 8
  const maxY = window.innerHeight - rect.height - 8
  menuStyle.value = {
    left: `${Math.min(x.value, maxX)}px`,
    top: `${Math.min(y.value, maxY)}px`,
  }
}

function onClickOutside(e: MouseEvent) {
  if (!visible.value) return
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
function onView() {
  if (!node.value) return
  fileStore.navigate(node.value.path)
  close()
}

async function onRename() {
  if (!node.value) return
  const newName = await ui.prompt(t('Rename'), '', node.value.name)
  if (newName && newName !== node.value.name) {
    try {
      await foldersApi.rename(node.value.path, newName)
      await fileStore.refresh()
      sidebar.refresh()
    } catch (err: any) {
      ui.alert(t('Error'), err?.response?.data?.error || t('Rename_Failed'))
    }
  }
  close()
}

async function onNewFolder() {
  if (!node.value) return
  const name = await ui.prompt(t('New_Folder'), t('Insert_Folder_Name'))
  if (!name) return
  try {
    await foldersApi.create(node.value.path, name)
    const newPath = node.value.path.replace(/\/?$/, '/') + name + '/'
    await sidebar.refresh()
    await sidebar.ensurePathLoaded(newPath)
    fileStore.navigate(newPath)
  } catch (err: any) {
    ui.alert(t('Error'), err?.response?.data?.error || t('Create_Folder_Failed'))
  }
  close()
}

async function onDelete() {
  if (!node.value) return
  const confirmed = await ui.confirm(t('Erase'), t('Confirm_Folder_del'))
  if (confirmed) {
    try {
      await foldersApi.delete(node.value.path)
      // If we deleted the current directory, navigate to parent
      if (fileStore.currentPath === node.value.path || fileStore.currentPath.startsWith(node.value.path)) {
        const parentPath = node.value.path.replace(/[^/]+\/$/, '')
        fileStore.navigate(parentPath)
      }
      await fileStore.refresh()
      sidebar.refresh()
    } catch (err: any) {
      ui.alert(t('Error'), err?.response?.data?.error || t('Delete_Failed'))
    }
  }
  close()
}

defineExpose({ show })
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
        v-if="visible && node"
        ref="menuRef"
        class="fixed z-50 min-w-[180px] py-1 bg-white dark:bg-neutral-800 rounded-lg shadow-xl border border-gray-200 dark:border-neutral-700 text-sm"
        :style="menuStyle"
      >
        <!-- Zobrazit -->
        <button class="context-menu-item" @click="onView">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" />
          </svg>
          {{ t('View') }}
        </button>

        <!-- Přejmenovat -->
        <button
          v-if="config?.renameFolders"
          class="context-menu-item"
          @click="onRename"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" /></svg>
          {{ t('Rename') }}
        </button>

        <!-- Nový adresář -->
        <button
          v-if="config?.createFolders"
          class="context-menu-item"
          @click="onNewFolder"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" />
            <line x1="12" y1="11" x2="12" y2="17" />
            <line x1="9" y1="14" x2="15" y2="14" />
          </svg>
          {{ t('New_Folder') }}
        </button>

        <div class="my-1 border-t border-gray-100 dark:border-neutral-700" />

        <!-- Smazat -->
        <button
          v-if="config?.deleteFolders"
          class="context-menu-item !text-rfm-danger hover:!bg-red-50 dark:hover:!bg-red-900/20"
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
@reference "@/style.css";
.context-menu-item {
  @apply flex items-center gap-2.5 w-full px-3 py-1.5 text-left text-gray-900 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors;
}
</style>
