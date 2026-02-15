import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Polyfill matchMedia for jsdom
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
})

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  setCsrfToken: vi.fn(),
  getCsrfToken: vi.fn(),
  uploadFiles: vi.fn(),
}))

const mockTree = vi.fn()
vi.mock('@/api/files', () => ({
  filesApi: {
    list: vi.fn().mockResolvedValue({ items: [], breadcrumb: [], counts: { files: 0, folders: 0 }, totalSize: 0, total: 0, clipboard: { hasItems: false, action: null } }),
    info: vi.fn(), preview: vi.fn(), getContent: vi.fn(), getDownloadUrl: vi.fn(),
  },
  foldersApi: {
    tree: (...args: any[]) => mockTree(...args),
    create: vi.fn(), rename: vi.fn(), delete: vi.fn(),
  },
  operationsApi: {
    rename: vi.fn(), delete: vi.fn(), deleteBulk: vi.fn(), duplicate: vi.fn(),
    copy: vi.fn(), cut: vi.fn(), paste: vi.fn(), clearClipboard: vi.fn(),
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

import { useSidebarStore } from '@/stores/sidebarStore'

describe('sidebarStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    document.cookie = ''
  })

  it('has correct initial state', () => {
    const store = useSidebarStore()
    expect(store.tree).toEqual([])
    expect(store.loaded).toBe(false)
  })

  it('loadTree calls API and sets tree', async () => {
    const mockNodes = [
      { name: 'photos', path: 'photos/', hasChildren: true },
      { name: 'docs', path: 'docs/', hasChildren: false },
    ]
    mockTree.mockResolvedValue({ tree: mockNodes })

    const store = useSidebarStore()
    await store.loadTree()

    expect(mockTree).toHaveBeenCalled()
    expect(store.tree).toHaveLength(2)
    expect(store.tree[0].name).toBe('photos')
    expect(store.tree[0].children).toEqual([]) // initNodes adds empty children
    expect(store.loaded).toBe(true)
  })

  it('toggle flips collapsed state', () => {
    const store = useSidebarStore()
    const initial = store.collapsed
    store.toggle()
    expect(store.collapsed).toBe(!initial)
    store.toggle()
    expect(store.collapsed).toBe(initial)
  })

  it('toggleHideFolders flips hideFoldersInGrid', () => {
    const store = useSidebarStore()
    expect(store.hideFoldersInGrid).toBe(false)
    store.toggleHideFolders()
    expect(store.hideFoldersInGrid).toBe(true)
    store.toggleHideFolders()
    expect(store.hideFoldersInGrid).toBe(false)
  })

  it('rootFolderCount reflects tree length', async () => {
    mockTree.mockResolvedValue({ tree: [
      { name: 'a', path: 'a/', hasChildren: false },
      { name: 'b', path: 'b/', hasChildren: false },
      { name: 'c', path: 'c/', hasChildren: false },
    ] })
    const store = useSidebarStore()
    await store.loadTree()
    expect(store.rootFolderCount).toBe(3)
  })
})
