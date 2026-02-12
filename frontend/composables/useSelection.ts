import { computed } from 'vue'
import { useFileStore } from '@/stores/fileStore'
import type { FileItem } from '@/types/files'

export function useSelection() {
  const fileStore = useFileStore()

  const selectedCount = computed(() => fileStore.selectedItems.size)
  const hasSelection = computed(() => fileStore.selectedItems.size > 0)

  function isSelected(item: FileItem): boolean {
    return fileStore.selectedItems.has(item.path)
  }

  function toggle(item: FileItem, event?: MouseEvent) {
    if (event?.shiftKey) {
      // Range selection handled by store
      fileStore.toggleSelection(item.path)
    } else if (event?.ctrlKey || event?.metaKey) {
      // Add/remove from selection
      fileStore.toggleSelection(item.path)
    } else {
      // Single selection
      fileStore.deselectAll()
      fileStore.toggleSelection(item.path)
    }
  }

  function selectAll() {
    fileStore.selectAll()
  }

  function deselectAll() {
    fileStore.deselectAll()
  }

  return {
    selectedCount,
    hasSelection,
    isSelected,
    toggle,
    selectAll,
    deselectAll,
  }
}
