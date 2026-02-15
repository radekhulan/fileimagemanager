import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  setCsrfToken: vi.fn(),
  getCsrfToken: vi.fn(),
  uploadFiles: vi.fn(),
}))

const mockCopy = vi.fn()
const mockCut = vi.fn()
const mockPaste = vi.fn()
const mockClearClipboard = vi.fn()

vi.mock('@/api/files', () => ({
  filesApi: {
    list: vi.fn().mockResolvedValue({ items: [], breadcrumb: [], counts: { files: 0, folders: 0 }, totalSize: 0, total: 0, clipboard: { hasItems: false, action: null } }),
    info: vi.fn(), preview: vi.fn(), getContent: vi.fn(), getDownloadUrl: vi.fn(),
  },
  foldersApi: { tree: vi.fn(), create: vi.fn(), rename: vi.fn(), delete: vi.fn() },
  operationsApi: {
    copy: (...args: any[]) => mockCopy(...args),
    cut: (...args: any[]) => mockCut(...args),
    paste: (...args: any[]) => mockPaste(...args),
    clearClipboard: (...args: any[]) => mockClearClipboard(...args),
    rename: vi.fn(), delete: vi.fn(), deleteBulk: vi.fn(), duplicate: vi.fn(),
    chmod: vi.fn(), extract: vi.fn(), saveText: vi.fn(), createFile: vi.fn(),
  },
  imageApi: { saveEdited: vi.fn() },
}))

vi.mock('@/api/config', () => ({
  configApi: {
    initSession: vi.fn(), changeLanguage: vi.fn(), getConfig: vi.fn(),
    getLanguages: vi.fn(), getTranslations: vi.fn(), changeView: vi.fn(),
    changeSort: vi.fn(), changeFilter: vi.fn(),
  },
}))

import { useClipboardStore } from '@/stores/clipboardStore'

describe('clipboardStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('has correct initial state', () => {
    const store = useClipboardStore()
    expect(store.hasItems).toBe(false)
    expect(store.action).toBeNull()
    expect(store.count).toBe(0)
  })

  it('isActive is false when empty', () => {
    const store = useClipboardStore()
    expect(store.isActive).toBe(false)
  })

  it('isActive is true when hasItems and action set', () => {
    const store = useClipboardStore()
    store.hasItems = true
    store.action = 'copy'
    expect(store.isActive).toBe(true)
  })

  it('syncFromResponse updates state', () => {
    const store = useClipboardStore()
    store.syncFromResponse({ hasItems: true, action: 'cut' })
    expect(store.hasItems).toBe(true)
    expect(store.action).toBe('cut')
  })

  it('copy calls API and updates state', async () => {
    mockCopy.mockResolvedValue({ clipboard: { hasItems: true, action: 'copy' } })
    const store = useClipboardStore()
    await store.copy(['file.txt', 'file2.txt'])

    expect(mockCopy).toHaveBeenCalledWith({ paths: ['file.txt', 'file2.txt'] })
    expect(store.hasItems).toBe(true)
    expect(store.action).toBe('copy')
    expect(store.count).toBe(2)
  })

  it('cut calls API and updates state', async () => {
    mockCut.mockResolvedValue({ clipboard: { hasItems: true, action: 'cut' } })
    const store = useClipboardStore()
    await store.cut(['file.txt'])

    expect(mockCut).toHaveBeenCalledWith({ paths: ['file.txt'] })
    expect(store.hasItems).toBe(true)
    expect(store.action).toBe('cut')
    expect(store.count).toBe(1)
  })

  it('paste calls API and resets state', async () => {
    mockPaste.mockResolvedValue({ success: true })
    const store = useClipboardStore()
    store.hasItems = true
    store.action = 'copy'
    store.count = 3

    await store.paste('target/')

    expect(mockPaste).toHaveBeenCalledWith('target/')
    expect(store.hasItems).toBe(false)
    expect(store.action).toBeNull()
    expect(store.count).toBe(0)
  })

  it('clear calls API and resets state', async () => {
    mockClearClipboard.mockResolvedValue({ success: true })
    const store = useClipboardStore()
    store.hasItems = true
    store.action = 'cut'
    store.count = 2

    await store.clear()

    expect(mockClearClipboard).toHaveBeenCalled()
    expect(store.hasItems).toBe(false)
    expect(store.action).toBeNull()
    expect(store.count).toBe(0)
  })

  it('copy does not update count when no clipboard in response', async () => {
    mockCopy.mockResolvedValue({})
    const store = useClipboardStore()
    await store.copy(['file.txt'])
    expect(store.count).toBe(0) // Not updated because no clipboard in response
  })

  it('isActive stays false when hasItems but no action', () => {
    const store = useClipboardStore()
    store.hasItems = true
    store.action = null
    expect(store.isActive).toBe(false)
  })

  it('isActive stays false when action but no items', () => {
    const store = useClipboardStore()
    store.hasItems = false
    store.action = 'copy'
    expect(store.isActive).toBe(false)
  })
})
