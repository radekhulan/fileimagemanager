import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import type { ViewMode } from '@/types/files'
import { configApi } from '@/api/config'

export const useUiStore = defineStore('ui', () => {
  // View mode
  const viewMode = ref<ViewMode>(0)

  // Dark mode
  const isDark = ref(false)

  // Panels
  const showUploadPanel = ref(false)
  const showLanguageDialog = ref(false)

  // Dialogs
  const confirmDialog = ref<{
    visible: boolean
    title: string
    message: string
    onConfirm: (() => void) | null
  }>({
    visible: false,
    title: '',
    message: '',
    onConfirm: null,
  })

  const promptDialog = ref<{
    visible: boolean
    title: string
    message: string
    defaultValue: string
    onConfirm: ((value: string) => void) | null
  }>({
    visible: false,
    title: '',
    message: '',
    defaultValue: '',
    onConfirm: null,
  })

  const alertDialog = ref<{
    visible: boolean
    title: string
    message: string
  }>({
    visible: false,
    title: '',
    message: '',
  })

  // Image editor
  const imageEditorState = ref<{
    url: string
    path: string
  } | null>(null)

  // Text editor
  const textEditorFile = ref<{
    mode: 'create' | 'edit'
    path?: string
    currentDir?: string
  } | null>(null)

  // Chmod
  const chmodTarget = ref<{
    path: string
    isDir: boolean
    permissions?: string
  } | null>(null)

  // Preview
  const previewItem = ref<{
    url: string
    type: string
    name: string
    path: string
  } | null>(null)

  // Context menu
  const contextMenu = ref<{
    visible: boolean
    x: number
    y: number
    item: any
  }>({
    visible: false,
    x: 0,
    y: 0,
    item: null,
  })

  // Initialize dark mode from cookie
  function initDarkMode(configDefault: boolean) {
    const cookie = document.cookie.split('; ').find(c => c.startsWith('rfm_dark_mode='))
    if (cookie) {
      isDark.value = cookie.split('=')[1] === '1'
    } else {
      isDark.value = configDefault && window.matchMedia?.('(prefers-color-scheme: dark)').matches
    }
    applyDarkMode()
  }

  function toggleDarkMode() {
    isDark.value = !isDark.value
    document.cookie = `rfm_dark_mode=${isDark.value ? '1' : '0'};path=/;max-age=${86400 * 365};SameSite=Lax`
    applyDarkMode()
  }

  function applyDarkMode() {
    document.documentElement.classList.toggle('dark', isDark.value)
  }

  // View mode
  async function setViewMode(mode: ViewMode) {
    viewMode.value = mode
    await configApi.changeView(mode)
  }

  // Dialog helpers
  function confirm(title: string, message: string): Promise<boolean> {
    return new Promise((resolve) => {
      confirmDialog.value = {
        visible: true,
        title,
        message,
        onConfirm: () => {
          confirmDialog.value.visible = false
          resolve(true)
        },
      }
      // Handle cancel via watcher or close button
      const stop = watch(() => confirmDialog.value.visible, (v) => {
        if (!v) {
          stop()
          resolve(false)
        }
      })
    })
  }

  function prompt(title: string, message: string, defaultValue = ''): Promise<string | null> {
    return new Promise((resolve) => {
      promptDialog.value = {
        visible: true,
        title,
        message,
        defaultValue,
        onConfirm: (value: string) => {
          promptDialog.value.visible = false
          resolve(value)
        },
      }
      const stop = watch(() => promptDialog.value.visible, (v) => {
        if (!v) {
          stop()
          resolve(null)
        }
      })
    })
  }

  function alert(title: string, message: string): void {
    alertDialog.value = { visible: true, title, message }
  }

  // Context menu computed helpers
  const contextMenuVisible = computed(() => contextMenu.value.visible)
  const contextMenuX = computed(() => contextMenu.value.x)
  const contextMenuY = computed(() => contextMenu.value.y)
  const contextMenuItem = computed(() => contextMenu.value.item)

  function showContextMenu(x: number, y: number, item: any) {
    contextMenu.value = { visible: true, x, y, item }
  }

  function hideContextMenu() {
    contextMenu.value.visible = false
  }

  // Image editor actions
  function openImageEditor(path: string, url: string) {
    imageEditorState.value = { path, url }
  }

  function closeImageEditor() {
    imageEditorState.value = null
  }

  return {
    // View
    viewMode, isDark,
    // Panels
    showUploadPanel, showLanguageDialog,
    // Dialogs
    confirmDialog, promptDialog, alertDialog,
    // Image editor
    imageEditorState, textEditorFile, chmodTarget,
    // Preview
    previewItem,
    // Context menu
    contextMenu, contextMenuVisible, contextMenuX, contextMenuY, contextMenuItem,
    // Actions
    initDarkMode, toggleDarkMode, setViewMode,
    confirm, prompt, alert,
    showContextMenu, hideContextMenu,
    openImageEditor, closeImageEditor,
  }
})
