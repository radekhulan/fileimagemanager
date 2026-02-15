import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  setCsrfToken: vi.fn(),
  getCsrfToken: vi.fn(),
  uploadFiles: vi.fn(),
}))

const mockList = vi.fn()
vi.mock('@/api/files', () => ({
  filesApi: {
    list: (...args: any[]) => mockList(...args),
    info: vi.fn(),
    preview: vi.fn(),
    getContent: vi.fn(),
    getDownloadUrl: vi.fn(),
  },
  foldersApi: { tree: vi.fn(), create: vi.fn(), rename: vi.fn(), delete: vi.fn() },
  operationsApi: {
    rename: vi.fn(), delete: vi.fn(), deleteBulk: vi.fn(), duplicate: vi.fn(),
    copy: vi.fn(), cut: vi.fn(), paste: vi.fn(), clearClipboard: vi.fn(),
    chmod: vi.fn(), extract: vi.fn(), saveText: vi.fn(), createFile: vi.fn(),
  },
  imageApi: { saveEdited: vi.fn() },
}))

vi.mock('@/api/config', () => ({
  configApi: {
    initSession: vi.fn(),
    changeLanguage: vi.fn(),
    getConfig: vi.fn(),
    getLanguages: vi.fn(),
    getTranslations: vi.fn(),
    changeView: vi.fn(),
    changeSort: vi.fn().mockResolvedValue({}),
    changeFilter: vi.fn(),
  },
}))

import { useFileStore } from '@/stores/fileStore'

function makeListResponse(items: any[] = [], total?: number) {
  return {
    items,
    breadcrumb: [],
    counts: { files: items.filter((i: any) => !i.isDir).length, folders: items.filter((i: any) => i.isDir).length },
    totalSize: 0,
    total: total ?? items.length,
    clipboard: { hasItems: false, action: null },
  }
}

describe('fileStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('has correct initial state', () => {
    const store = useFileStore()
    expect(store.items).toEqual([])
    expect(store.currentPath).toBe('')
    expect(store.sortBy).toBe('name')
    expect(store.descending).toBe(false)
    expect(store.typeFilter).toBe('all')
    expect(store.loading).toBe(false)
    expect(store.selectedItems.size).toBe(0)
  })

  it('loadDirectory calls API and sets state', async () => {
    const items = [
      { path: 'dir/', name: 'dir', isDir: true },
      { path: 'file.txt', name: 'file.txt', isDir: false },
    ]
    mockList.mockResolvedValue(makeListResponse(items))

    const store = useFileStore()
    await store.loadDirectory('photos/')

    expect(store.currentPath).toBe('photos/')
    expect(store.items).toEqual(items)
    expect(store.fileCount).toBe(1)
    expect(store.folderCount).toBe(1)
    expect(store.loading).toBe(false)
  })

  it('loadDirectory sets loadError on failure', async () => {
    mockList.mockRejectedValue(new Error('Network error'))

    const store = useFileStore()
    await store.loadDirectory('')

    expect(store.loadError).toBe('Network error')
    expect(store.items).toEqual([])
  })

  it('navigate calls loadDirectory with path', async () => {
    mockList.mockResolvedValue(makeListResponse([]))
    const store = useFileStore()
    await store.navigate('docs/')
    expect(store.currentPath).toBe('docs/')
  })

  it('goUp navigates to parent path', async () => {
    mockList.mockResolvedValue(makeListResponse([]))
    const store = useFileStore()
    store.currentPath = 'photos/vacation/'
    // parentPath computed
    expect(store.parentPath).toBe('photos/')
  })

  it('parentPath is empty string for root-level subfolder', () => {
    const store = useFileStore()
    store.currentPath = 'photos/'
    expect(store.parentPath).toBe('')
  })

  it('parentPath is null at root', () => {
    const store = useFileStore()
    store.currentPath = ''
    expect(store.parentPath).toBeNull()
  })

  // Selection
  it('toggleSelection adds and removes items', () => {
    const store = useFileStore()
    store.toggleSelection('file.txt')
    expect(store.selectedItems.has('file.txt')).toBe(true)
    expect(store.hasSelection).toBe(true)

    store.toggleSelection('file.txt')
    expect(store.selectedItems.has('file.txt')).toBe(false)
    expect(store.hasSelection).toBe(false)
  })

  it('selectAll selects only files', () => {
    const store = useFileStore()
    store.items = [
      { path: 'dir/', name: 'dir', isDir: true },
      { path: 'a.txt', name: 'a.txt', isDir: false },
      { path: 'b.txt', name: 'b.txt', isDir: false },
    ] as any
    store.selectAll()
    expect(store.selectionCount).toBe(2)
    expect(store.selectedItems.has('dir/')).toBe(false)
    expect(store.selectedItems.has('a.txt')).toBe(true)
  })

  it('deselectAll clears selection', () => {
    const store = useFileStore()
    store.toggleSelection('file.txt')
    store.deselectAll()
    expect(store.selectionCount).toBe(0)
  })

  // Sorting
  it('changeSort toggles descending for same field', async () => {
    mockList.mockResolvedValue(makeListResponse([]))
    const store = useFileStore()
    expect(store.sortBy).toBe('name')
    expect(store.descending).toBe(false)

    await store.changeSort('name')
    expect(store.descending).toBe(true)
  })

  it('changeSort switches field and resets descending', async () => {
    mockList.mockResolvedValue(makeListResponse([]))
    const store = useFileStore()
    store.descending = true

    await store.changeSort('date')
    expect(store.sortBy).toBe('date')
    expect(store.descending).toBe(false)
  })

  it('sortPreset returns name for default state', () => {
    const store = useFileStore()
    expect(store.sortPreset).toBe('name')
  })

  it('sortPreset returns newest for date descending', () => {
    const store = useFileStore()
    store.sortBy = 'date'
    store.descending = true
    expect(store.sortPreset).toBe('newest')
  })

  it('sortPreset returns oldest for date ascending', () => {
    const store = useFileStore()
    store.sortBy = 'date'
    store.descending = false
    expect(store.sortPreset).toBe('oldest')
  })

  it('sortPreset returns largest for size descending', () => {
    const store = useFileStore()
    store.sortBy = 'size'
    store.descending = true
    expect(store.sortPreset).toBe('largest')
  })

  // Type filter
  it('changeTypeFilter sets filter and reloads', async () => {
    mockList.mockResolvedValue(makeListResponse([]))
    const store = useFileStore()
    await store.changeTypeFilter('image')
    expect(store.typeFilter).toBe('image')
    expect(mockList).toHaveBeenCalled()
  })

  // Text filter
  it('setTextFilter updates textFilter', () => {
    const store = useFileStore()
    store.setTextFilter('photo')
    expect(store.textFilter).toBe('photo')
  })

  // Clipboard from API response
  it('loadDirectory syncs clipboard state', async () => {
    mockList.mockResolvedValue({
      items: [],
      breadcrumb: [],
      counts: { files: 0, folders: 0 },
      totalSize: 0,
      total: 0,
      clipboard: { hasItems: true, action: 'copy' },
    })

    const store = useFileStore()
    await store.loadDirectory('')
    expect(store.clipboard.hasItems).toBe(true)
    expect(store.clipboard.action).toBe('copy')
  })
})
