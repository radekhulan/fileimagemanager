import { useFileStore } from '@/stores/fileStore'
import { useUiStore } from '@/stores/uiStore'
import { useConfigStore } from '@/stores/configStore'
import { useClipboardStore } from '@/stores/clipboardStore'
import { operationsApi, foldersApi } from '@/api/files'
import type { FileItem } from '@/types/files'

export function useFileOperations() {
  const fileStore = useFileStore()
  const ui = useUiStore()
  const configStore = useConfigStore()
  const clipboard = useClipboardStore()
  const { t } = configStore

  async function createFolder() {
    const name = await ui.prompt(t('New_Folder'), t('Insert_Folder_Name'))
    if (!name) return
    try {
      await foldersApi.create(fileStore.currentPath, name)
      await fileStore.refresh()
    } catch (err: any) {
      ui.alert(t('Error'), err?.response?.data?.error || t('Create_Folder_Failed'))
    }
  }

  async function createFile() {
    ui.textEditorFile = {
      mode: 'create',
      currentDir: fileStore.currentPath,
    }
  }

  async function renameItem(item: FileItem) {
    const ext = !item.isDir && item.name.includes('.')
      ? '.' + item.name.split('.').pop()
      : ''
    const baseName = ext ? item.name.slice(0, -ext.length) : item.name
    const newName = await ui.prompt(t('Rename'), '', baseName)
    if (!newName || newName === baseName) return

    try {
      if (item.isDir) {
        await foldersApi.rename(item.path, newName)
      } else {
        await operationsApi.rename(item.path, newName + ext)
      }
      await fileStore.refresh()
    } catch (err: any) {
      ui.alert(t('Error'), err?.response?.data?.error || t('Rename_Failed'))
    }
  }

  async function deleteItem(item: FileItem) {
    const msg = item.isDir ? t('Confirm_Folder_del') : t('Confirm_del')
    const confirmed = await ui.confirm(t('Erase'), msg)
    if (!confirmed) return

    try {
      if (item.isDir) {
        await foldersApi.delete(item.path)
      } else {
        await operationsApi.delete(item.path)
      }
      fileStore.selectedItems.delete(item.path)
      await fileStore.refresh()
    } catch (err: any) {
      ui.alert(t('Error'), err?.response?.data?.error || t('Delete_Failed'))
    }
  }

  async function deleteSelected() {
    const selected = Array.from(fileStore.selectedItems)
    if (selected.length === 0) return

    const confirmed = await ui.confirm(
      t('Erase'),
      t('Confirm_delete_text')
    )
    if (!confirmed) return

    try {
      await operationsApi.deleteBulk(selected)
      fileStore.deselectAll()
      await fileStore.refresh()
    } catch (err: any) {
      ui.alert(t('Error'), err?.response?.data?.error || t('Delete_Failed'))
    }
  }

  async function duplicateItem(item: FileItem) {
    try {
      await operationsApi.duplicate(item.path)
      await fileStore.refresh()
    } catch (err: any) {
      ui.alert(t('Error'), err?.response?.data?.error || t('Duplicate_Failed'))
    }
  }

  async function copyItems(paths: string[]) {
    await clipboard.copy(paths)
  }

  async function cutItems(paths: string[]) {
    await clipboard.cut(paths)
  }

  async function pasteItems() {
    await clipboard.paste(fileStore.currentPath)
    await fileStore.refresh()
  }

  async function extractFile(item: FileItem) {
    try {
      await operationsApi.extract(item.path)
      await fileStore.refresh()
    } catch (err: any) {
      ui.alert(t('Error'), err?.response?.data?.error || t('Zip_No_Extract'))
    }
  }

  function editImage(item: FileItem) {
    ui.openImageEditor(item.path, configStore.getFileUrl(item.path))
  }

  function editText(item: FileItem) {
    ui.textEditorFile = { mode: 'edit', path: item.path }
  }

  function chmodItem(item: FileItem) {
    ui.chmodTarget = {
      path: item.path,
      isDir: item.isDir,
      permissions: item.permissions ?? undefined,
    }
  }

  return {
    createFolder,
    createFile,
    renameItem,
    deleteItem,
    deleteSelected,
    duplicateItem,
    copyItems,
    cutItems,
    pasteItems,
    extractFile,
    editImage,
    editText,
    chmodItem,
  }
}
