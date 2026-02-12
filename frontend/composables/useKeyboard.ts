import { onMounted, onUnmounted } from 'vue'
import { useFileStore } from '@/stores/fileStore'
import { useUiStore } from '@/stores/uiStore'
import { useClipboardStore } from '@/stores/clipboardStore'
import { useConfigStore } from '@/stores/configStore'
import { operationsApi } from '@/api/files'

export function useKeyboard() {
  const fileStore = useFileStore()
  const ui = useUiStore()
  const clipboard = useClipboardStore()
  const configStore = useConfigStore()

  function onKeyDown(e: KeyboardEvent) {
    // Ignore if inside an input/textarea/select
    const target = e.target as HTMLElement
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return

    const ctrl = e.ctrlKey || e.metaKey

    // Escape - close overlays
    if (e.key === 'Escape') {
      if (ui.contextMenuVisible) {
        ui.hideContextMenu()
      } else if (ui.previewItem) {
        ui.previewItem = null
      } else if (ui.imageEditorState) {
        ui.closeImageEditor()
      } else if (ui.showUploadPanel) {
        ui.showUploadPanel = false
      }
      return
    }

    // Ctrl+A - Select all
    if (ctrl && e.key === 'a') {
      e.preventDefault()
      fileStore.selectAll()
      return
    }

    // Ctrl+C - Copy
    if (ctrl && e.key === 'c') {
      const selected = Array.from(fileStore.selectedItems)
      if (selected.length > 0) {
        e.preventDefault()
        clipboard.copy(selected)
      }
      return
    }

    // Ctrl+X - Cut
    if (ctrl && e.key === 'x') {
      const selected = Array.from(fileStore.selectedItems)
      if (selected.length > 0) {
        e.preventDefault()
        clipboard.cut(selected)
      }
      return
    }

    // Ctrl+V - Paste
    if (ctrl && e.key === 'v') {
      if (clipboard.isActive) {
        e.preventDefault()
        clipboard.paste(fileStore.currentPath)
      }
      return
    }

    // Delete - Delete selected
    if (e.key === 'Delete') {
      const selected = Array.from(fileStore.selectedItems)
      if (selected.length > 0) {
        e.preventDefault()
        handleDelete(selected)
      }
      return
    }

    // F2 - Rename
    if (e.key === 'F2') {
      const selected = Array.from(fileStore.selectedItems)
      if (selected.length === 1) {
        e.preventDefault()
        // Rename will be handled by the file operations composable
      }
      return
    }

    // Backspace - Go up
    if (e.key === 'Backspace') {
      e.preventDefault()
      fileStore.goUp()
      return
    }

    // F5 - Refresh
    if (e.key === 'F5') {
      e.preventDefault()
      fileStore.refresh()
      return
    }
  }

  async function handleDelete(paths: string[]) {
    const confirmed = await ui.confirm(
      configStore.t('Confirm_delete'),
      configStore.t('Confirm_delete_text', paths.length)
    )
    if (confirmed) {
      try {
        await operationsApi.deleteBulk(paths)
        fileStore.deselectAll()
        await fileStore.refresh()
      } catch {
        // Error handled by API layer
      }
    }
  }

  onMounted(() => {
    document.addEventListener('keydown', onKeyDown)
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', onKeyDown)
  })
}
