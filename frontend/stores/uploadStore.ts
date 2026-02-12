import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { uploadFiles } from '@/api/client'
import apiClient from '@/api/client'
import { useFileStore } from './fileStore'
import { useConfigStore } from './configStore'

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  // Fallback for non-secure contexts (HTTP) or older browsers
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

export interface UploadItem {
  id: string
  file: File
  name: string
  size: number
  progress: number
  status: 'pending' | 'uploading' | 'done' | 'error'
  error?: string
}

export const useUploadStore = defineStore('upload', () => {
  const queue = ref<UploadItem[]>([])
  const isUploading = ref(false)

  const hasItems = computed(() => queue.value.length > 0)
  const completedCount = computed(() => queue.value.filter(i => i.status === 'done').length)
  const errorCount = computed(() => queue.value.filter(i => i.status === 'error').length)
  const totalProgress = computed(() => {
    if (queue.value.length === 0) return 0
    const total = queue.value.reduce((sum, item) => sum + item.progress, 0)
    return Math.round(total / queue.value.length)
  })

  function addFiles(files: FileList | File[]) {
    const newItems: UploadItem[] = Array.from(files).map((file) => ({
      id: generateId(),
      file,
      name: file.name,
      size: file.size,
      progress: 0,
      status: 'pending' as const,
    }))
    queue.value.push(...newItems)

    const configStore = useConfigStore()
    if (configStore.config?.autoUpload) {
      const fileStore = useFileStore()
      startUpload(fileStore.currentPath)
    }
  }

  async function startUpload(targetPath: string) {
    if (isUploading.value) return

    isUploading.value = true
    const fileStore = useFileStore()
    const pendingItems = queue.value.filter(i => i.status === 'pending')

    for (const item of pendingItems) {
      item.status = 'uploading'
      try {
        await uploadFiles(
          [item.file],
          targetPath,
          (percent) => { item.progress = percent },
        )
        item.status = 'done'
        item.progress = 100
      } catch (err: any) {
        item.status = 'error'
        item.error = err?.response?.data?.error || err?.message || 'Upload failed'
      }
    }

    isUploading.value = false

    // Refresh file list after uploads
    await fileStore.refresh()
  }

  async function uploadFromUrl(url: string, targetPath: string) {
    const fileStore = useFileStore()
    await apiClient.post('/upload/url', { url, path: targetPath })
    await fileStore.refresh()
  }

  function removeItem(id: string) {
    queue.value = queue.value.filter(i => i.id !== id)
  }

  function clearCompleted() {
    queue.value = queue.value.filter(i => i.status !== 'done')
  }

  function clearAll() {
    queue.value = []
    isUploading.value = false
  }

  return {
    queue, isUploading, hasItems, completedCount, errorCount, totalProgress,
    addFiles, startUpload, uploadFromUrl, removeItem, clearCompleted, clearAll,
  }
})
