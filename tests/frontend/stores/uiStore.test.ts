import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  setCsrfToken: vi.fn(),
  getCsrfToken: vi.fn(),
  uploadFiles: vi.fn(),
}))

vi.mock('@/api/config', () => ({
  configApi: {
    initSession: vi.fn(), changeLanguage: vi.fn(), getConfig: vi.fn(),
    getLanguages: vi.fn(), getTranslations: vi.fn(),
    changeView: vi.fn().mockResolvedValue({}),
    changeSort: vi.fn(), changeFilter: vi.fn(),
  },
}))

import { useUiStore } from '@/stores/uiStore'

describe('uiStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    // Reset document state
    document.documentElement.classList.remove('dark')
    // Clear cookies by expiring them (setting to '' doesn't work in jsdom)
    document.cookie.split(';').forEach((c) => {
      const name = c.split('=')[0].trim()
      if (name) document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/`
    })
  })

  // ---------------------------------------------------------------
  // Dark mode
  // ---------------------------------------------------------------

  it('isDark is false initially', () => {
    const store = useUiStore()
    expect(store.isDark).toBe(false)
  })

  it('initDarkMode sets dark from cookie', () => {
    document.cookie = 'rfm_dark_mode=1'
    const store = useUiStore()
    store.initDarkMode(false)
    expect(store.isDark).toBe(true)
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('initDarkMode uses config default when no cookie', () => {
    // No cookie, matchMedia returns false by default in jsdom
    const store = useUiStore()
    store.initDarkMode(false)
    expect(store.isDark).toBe(false)
  })

  it('toggleDarkMode flips isDark', () => {
    const store = useUiStore()
    store.isDark = false
    store.toggleDarkMode()
    expect(store.isDark).toBe(true)
    expect(document.documentElement.classList.contains('dark')).toBe(true)

    store.toggleDarkMode()
    expect(store.isDark).toBe(false)
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('toggleDarkMode sets cookie', () => {
    const store = useUiStore()
    store.toggleDarkMode()
    expect(document.cookie).toContain('rfm_dark_mode=1')
  })

  // ---------------------------------------------------------------
  // View mode
  // ---------------------------------------------------------------

  it('viewMode defaults to 0', () => {
    const store = useUiStore()
    expect(store.viewMode).toBe(0)
  })

  it('initViewMode reads from cookie', () => {
    document.cookie = 'rfm_view_mode=2'
    const store = useUiStore()
    store.initViewMode(0 as any)
    expect(store.viewMode).toBe(2)
  })

  it('initViewMode uses default when no cookie', () => {
    const store = useUiStore()
    store.initViewMode(1 as any)
    expect(store.viewMode).toBe(1)
  })

  it('setViewMode updates viewMode and sets cookie', async () => {
    const store = useUiStore()
    await store.setViewMode(2 as any)
    expect(store.viewMode).toBe(2)
    expect(document.cookie).toContain('rfm_view_mode=2')
  })

  // ---------------------------------------------------------------
  // Dialogs
  // ---------------------------------------------------------------

  it('confirm dialog initially not visible', () => {
    const store = useUiStore()
    expect(store.confirmDialog.visible).toBe(false)
  })

  it('confirm opens dialog and resolves true on confirm', async () => {
    const store = useUiStore()
    const promise = store.confirm('Delete?', 'Are you sure?')

    expect(store.confirmDialog.visible).toBe(true)
    expect(store.confirmDialog.title).toBe('Delete?')
    expect(store.confirmDialog.message).toBe('Are you sure?')

    // Simulate user clicking confirm
    store.confirmDialog.onConfirm!()
    const result = await promise
    expect(result).toBe(true)
  })

  it('prompt opens dialog with default value', () => {
    const store = useUiStore()
    store.prompt('Rename', 'Enter name:', 'default.txt')

    expect(store.promptDialog.visible).toBe(true)
    expect(store.promptDialog.defaultValue).toBe('default.txt')
  })

  it('prompt resolves null on cancel', async () => {
    const store = useUiStore()
    const promise = store.prompt('Rename', 'Enter name:')

    // Simulate cancel (close dialog without confirm)
    store.promptDialog.visible = false
    const result = await promise
    expect(result).toBeNull()
  })

  it('alert sets visible and message', () => {
    const store = useUiStore()
    store.alert('Error', 'Something went wrong')

    expect(store.alertDialog.visible).toBe(true)
    expect(store.alertDialog.title).toBe('Error')
    expect(store.alertDialog.message).toBe('Something went wrong')
  })

  // ---------------------------------------------------------------
  // Context menu
  // ---------------------------------------------------------------

  it('context menu initially hidden', () => {
    const store = useUiStore()
    expect(store.contextMenuVisible).toBe(false)
  })

  it('showContextMenu sets position and item', () => {
    const store = useUiStore()
    const item = { path: 'file.txt', name: 'file.txt' }
    store.showContextMenu(100, 200, item)

    expect(store.contextMenuVisible).toBe(true)
    expect(store.contextMenuX).toBe(100)
    expect(store.contextMenuY).toBe(200)
    expect(store.contextMenuItem).toEqual(item)
  })

  it('hideContextMenu hides menu', () => {
    const store = useUiStore()
    store.showContextMenu(0, 0, {})
    store.hideContextMenu()
    expect(store.contextMenuVisible).toBe(false)
  })

  // ---------------------------------------------------------------
  // Image editor
  // ---------------------------------------------------------------

  it('imageEditorState is null initially', () => {
    const store = useUiStore()
    expect(store.imageEditorState).toBeNull()
  })

  it('openImageEditor sets state', () => {
    const store = useUiStore()
    store.openImageEditor('photo.jpg', '/source/photo.jpg')
    expect(store.imageEditorState).toEqual({ path: 'photo.jpg', url: '/source/photo.jpg' })
  })

  it('closeImageEditor clears state', () => {
    const store = useUiStore()
    store.openImageEditor('photo.jpg', '/source/photo.jpg')
    store.closeImageEditor()
    expect(store.imageEditorState).toBeNull()
  })
})
