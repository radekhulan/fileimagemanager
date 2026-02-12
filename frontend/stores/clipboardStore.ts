import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { operationsApi } from '@/api/files'
import { useFileStore } from './fileStore'

export const useClipboardStore = defineStore('clipboard', () => {
  const hasItems = ref(false)
  const action = ref<'copy' | 'cut' | null>(null)
  const count = ref(0)

  const isActive = computed(() => hasItems.value && action.value !== null)

  function syncFromResponse(clipboard: { hasItems: boolean; action: string | null }) {
    hasItems.value = clipboard.hasItems
    action.value = clipboard.action as 'copy' | 'cut' | null
  }

  async function copy(paths: string[]) {
    const result = await operationsApi.copy({ paths })
    if (result.clipboard) {
      syncFromResponse(result.clipboard)
      count.value = paths.length
    }
  }

  async function cut(paths: string[]) {
    const result = await operationsApi.cut({ paths })
    if (result.clipboard) {
      syncFromResponse(result.clipboard)
      count.value = paths.length
    }
  }

  async function paste(targetPath: string) {
    await operationsApi.paste(targetPath)
    hasItems.value = false
    action.value = null
    count.value = 0

    // Refresh file list
    const fileStore = useFileStore()
    await fileStore.refresh()
  }

  async function clear() {
    await operationsApi.clearClipboard()
    hasItems.value = false
    action.value = null
    count.value = 0
  }

  return { hasItems, action, count, isActive, syncFromResponse, copy, cut, paste, clear }
})
