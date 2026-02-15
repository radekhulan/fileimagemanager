<script setup lang="ts">
import type { TreeNode } from '@/types/files'

const props = defineProps<{
  node: TreeNode
  depth: number
  isActive: (path: string) => boolean
  isExpanded: (path: string) => boolean
}>()

const emit = defineEmits<{
  navigate: [path: string]
}>()

function onClick() {
  emit('navigate', props.node.path)
}
</script>

<template>
  <div>
    <button
      class="flex items-center gap-1.5 w-full py-1 pr-2 text-xs transition-colors text-left truncate"
      :class="isActive(node.path)
        ? 'text-rfm-primary bg-rfm-primary/10 font-semibold'
        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-neutral-700'"
      :style="{ paddingLeft: (depth * 12 + 12) + 'px' }"
      :title="node.name"
      @click="onClick"
    >
      <svg v-if="node.children.length > 0" class="w-3 h-3 shrink-0 transition-transform" :class="{ 'rotate-90': isExpanded(node.path) }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="9 18 15 12 9 6" />
      </svg>
      <span v-else class="w-3 shrink-0" />
      <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="currentColor">
        <path d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8l-2-2z" />
      </svg>
      <span class="truncate">{{ node.name }}</span>
    </button>
    <template v-if="isExpanded(node.path) && node.children.length > 0">
      <SidebarTreeItem
        v-for="child in node.children"
        :key="child.path"
        :node="child"
        :depth="depth + 1"
        :is-active="isActive"
        :is-expanded="isExpanded"
        @navigate="$emit('navigate', $event)"
      />
    </template>
  </div>
</template>
