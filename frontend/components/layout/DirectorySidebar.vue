<script setup lang="ts">
import { computed, watch, ref, onMounted, nextTick } from 'vue'
import type { TreeNode } from '@/types/files'
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import { useSidebarStore } from '@/stores/sidebarStore'
import SidebarTreeItem from './SidebarTreeItem.vue'
import SidebarContextMenu from './SidebarContextMenu.vue'

const fileStore = useFileStore()
const configStore = useConfigStore()
const sidebar = useSidebarStore()
const { t } = configStore

// Paths manually expanded/collapsed by clicking
const userExpandedPaths = ref(new Set<string>())
const userCollapsedPaths = ref(new Set<string>())
let navigatingFromSidebar = false

const sidebarRef = ref<HTMLElement | null>(null)

// Paths auto-expanded from current navigation
const autoExpandedPaths = computed(() => {
  const paths = new Set<string>()
  const current = fileStore.currentPath
  if (!current) return paths
  const parts = current.replace(/\/$/, '').split('/')
  let accumulated = ''
  for (const part of parts) {
    accumulated += part + '/'
    paths.add(accumulated)
  }
  return paths
})

async function scrollActiveIntoView() {
  const path = fileStore.currentPath
  const container = sidebarRef.value
  if (!path || !container) return
  await nextTick()
  const escaped = (window.CSS?.escape ?? ((s: string) => s.replace(/(["\\])/g, '\\$1')))(path)
  const el = container.querySelector<HTMLElement>(`[data-path="${escaped}"]`)
  el?.scrollIntoView({ block: 'nearest' })
}

// Auto-load ancestors when navigating to a deep path
watch(() => fileStore.currentPath, async (path) => {
  if (path && sidebar.loaded) {
    await sidebar.ensurePathLoaded(path)
  }
  if (!navigatingFromSidebar) {
    // External navigation (breadcrumb, address bar): clear manual collapse state
    userCollapsedPaths.value.clear()
  }
  navigatingFromSidebar = false
  await scrollActiveIntoView()
})

onMounted(async () => {
  if (fileStore.currentPath && sidebar.loaded) {
    await sidebar.ensurePathLoaded(fileStore.currentPath)
  }
  await scrollActiveIntoView()
})

function isActive(path: string): boolean {
  return fileStore.currentPath === path
}

function isExpanded(path: string): boolean {
  if (userCollapsedPaths.value.has(path)) return false
  return autoExpandedPaths.value.has(path) || userExpandedPaths.value.has(path)
}

function navigate(path: string) {
  if (fileStore.currentPath === path && isExpanded(path) && sidebar.isLoaded(path)) {
    // Collapse only when re-clicking the already-current folder
    userExpandedPaths.value.delete(path)
    userCollapsedPaths.value.add(path)
  } else {
    // Navigating to a different folder: ensure it stays expanded
    userCollapsedPaths.value.delete(path)
    userExpandedPaths.value.add(path)
  }
  navigatingFromSidebar = true
  fileStore.navigate(path)
}

const sidebarMenuRef = ref<InstanceType<typeof SidebarContextMenu>>()

function onTreeContextMenu(e: MouseEvent, node: TreeNode) {
  sidebarMenuRef.value?.show({ x: e.clientX, y: e.clientY }, node)
}

defineProps<{
  tree: TreeNode[]
}>()
</script>

<template>
  <aside
    ref="sidebarRef"
    class="hidden lg:flex flex-col w-56 shrink-0 border-r border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-800 overflow-y-auto overflow-x-hidden"
  >
    <!-- Home root item -->
    <button
      class="flex items-center gap-2 w-full px-3 py-1.5 text-sm font-medium transition-colors text-left"
      :class="fileStore.currentPath === ''
        ? 'text-rfm-primary bg-rfm-primary/10'
        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-neutral-700'"
      @click="navigate('')"
    >
      <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
        <polyline points="9 22 9 12 15 12 15 22" />
      </svg>
      <span class="truncate">{{ t('Home') }}</span>
    </button>

    <!-- Recursive tree -->
    <SidebarTreeItem
      v-for="node in tree"
      :key="node.path"
      :node="node"
      :depth="0"
      :is-active="isActive"
      :is-expanded="isExpanded"
      @navigate="navigate"
      @contextmenu="onTreeContextMenu"
    />

    <SidebarContextMenu ref="sidebarMenuRef" />
  </aside>
</template>
