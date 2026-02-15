<script setup lang="ts">
import { computed } from 'vue'
import type { TreeNode } from '@/types/files'
import { useFileStore } from '@/stores/fileStore'
import { useConfigStore } from '@/stores/configStore'
import SidebarTreeItem from './SidebarTreeItem.vue'

const fileStore = useFileStore()
const configStore = useConfigStore()
const { t } = configStore

// Set of paths that should be expanded (all ancestors of current path)
const expandedPaths = computed(() => {
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

function isActive(path: string): boolean {
  return fileStore.currentPath === path
}

function isExpanded(path: string): boolean {
  return expandedPaths.value.has(path)
}

function navigate(path: string) {
  fileStore.navigate(path)
}

defineProps<{
  tree: TreeNode[]
}>()
</script>

<template>
  <aside
    class="hidden lg:flex flex-col w-56 shrink-0 border-r border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-800 overflow-y-auto overflow-x-hidden"
  >
    <!-- Home root item -->
    <button
      class="flex items-center gap-2 w-full px-3 py-1.5 text-xs font-medium transition-colors text-left"
      :class="fileStore.currentPath === ''
        ? 'text-rfm-primary bg-rfm-primary/10'
        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-neutral-700'"
      @click="navigate('')"
    >
      <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
    />
  </aside>
</template>
