import { ref } from 'vue'
import { useFileStore } from '@/stores/fileStore'
import { useUploadStore } from '@/stores/uploadStore'
import { operationsApi } from '@/api/files'

export function useDragDrop() {
  const fileStore = useFileStore()
  const uploadStore = useUploadStore()

  const isDragging = ref(false)
  const dragTarget = ref<string | null>(null)
  let draggedPaths: string[] = []

  function onDragStart(e: DragEvent, paths: string[]) {
    draggedPaths = paths
    if (e.dataTransfer) {
      e.dataTransfer.effectAllowed = 'move'
      e.dataTransfer.setData('text/plain', JSON.stringify(paths))
    }
  }

  function onDragOver(e: DragEvent, targetPath?: string) {
    e.preventDefault()
    if (e.dataTransfer) {
      // If files from OS, show copy cursor
      if (e.dataTransfer.types.includes('Files')) {
        e.dataTransfer.dropEffect = 'copy'
      } else {
        e.dataTransfer.dropEffect = 'move'
      }
    }
    isDragging.value = true
    dragTarget.value = targetPath || null
  }

  function onDragLeave() {
    isDragging.value = false
    dragTarget.value = null
  }

  async function onDrop(e: DragEvent, targetPath?: string) {
    e.preventDefault()
    isDragging.value = false
    dragTarget.value = null

    // Handle file drops from OS
    if (e.dataTransfer?.files.length) {
      uploadStore.addFiles(e.dataTransfer.files)
      return
    }

    // Handle internal file moves
    const data = e.dataTransfer?.getData('text/plain')
    if (!data) return

    try {
      const paths = JSON.parse(data) as string[]
      const destination = targetPath || fileStore.currentPath

      await operationsApi.cut({ paths })
      await operationsApi.paste(destination)
      await fileStore.refresh()
    } catch {
      // Ignore parse errors from non-RFM drag sources
    }
  }

  return {
    isDragging,
    dragTarget,
    onDragStart,
    onDragOver,
    onDragLeave,
    onDrop,
  }
}
