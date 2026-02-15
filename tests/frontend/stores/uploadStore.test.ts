import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  setCsrfToken: vi.fn(),
  getCsrfToken: vi.fn(),
  uploadFiles: vi.fn(),
}))

vi.mock('@/api/files', () => ({
  filesApi: {
    list: vi.fn().mockResolvedValue({ items: [], breadcrumb: [], counts: { files: 0, folders: 0 }, totalSize: 0, total: 0, clipboard: { hasItems: false, action: null } }),
    info: vi.fn(), preview: vi.fn(), getContent: vi.fn(), getDownloadUrl: vi.fn(),
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
    initSession: vi.fn(), changeLanguage: vi.fn(), getConfig: vi.fn(),
    getLanguages: vi.fn(), getTranslations: vi.fn(), changeView: vi.fn(),
    changeSort: vi.fn(), changeFilter: vi.fn(),
  },
}))

import { useUploadStore } from '@/stores/uploadStore'

function createMockFile(name: string, size: number = 1024): File {
  return new File(['x'.repeat(size)], name, { type: 'application/octet-stream' })
}

describe('uploadStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('has correct initial state', () => {
    const store = useUploadStore()
    expect(store.queue).toEqual([])
    expect(store.isUploading).toBe(false)
    expect(store.hasItems).toBe(false)
  })

  it('addFiles creates upload items', () => {
    const store = useUploadStore()
    const files = [createMockFile('a.txt'), createMockFile('b.txt')]
    store.addFiles(files)

    expect(store.queue).toHaveLength(2)
    expect(store.queue[0].name).toBe('a.txt')
    expect(store.queue[0].status).toBe('pending')
    expect(store.queue[0].progress).toBe(0)
    expect(store.hasItems).toBe(true)
  })

  it('addFiles assigns unique ids', () => {
    const store = useUploadStore()
    store.addFiles([createMockFile('a.txt'), createMockFile('b.txt')])
    expect(store.queue[0].id).not.toBe(store.queue[1].id)
  })

  it('removeItem removes item by id', () => {
    const store = useUploadStore()
    store.addFiles([createMockFile('a.txt'), createMockFile('b.txt')])
    const idToRemove = store.queue[0].id
    store.removeItem(idToRemove)
    expect(store.queue).toHaveLength(1)
    expect(store.queue[0].name).toBe('b.txt')
  })

  it('clearCompleted removes only done items', () => {
    const store = useUploadStore()
    store.addFiles([createMockFile('a.txt'), createMockFile('b.txt'), createMockFile('c.txt')])
    store.queue[0].status = 'done'
    store.queue[1].status = 'error'
    store.queue[2].status = 'pending'

    store.clearCompleted()

    expect(store.queue).toHaveLength(2)
    expect(store.queue.map(i => i.name)).toEqual(['b.txt', 'c.txt'])
  })

  it('clearAll empties queue and resets isUploading', () => {
    const store = useUploadStore()
    store.addFiles([createMockFile('a.txt')])
    store.isUploading = true
    store.clearAll()

    expect(store.queue).toEqual([])
    expect(store.isUploading).toBe(false)
  })

  it('completedCount returns count of done items', () => {
    const store = useUploadStore()
    store.addFiles([createMockFile('a.txt'), createMockFile('b.txt')])
    store.queue[0].status = 'done'

    expect(store.completedCount).toBe(1)
  })

  it('errorCount returns count of error items', () => {
    const store = useUploadStore()
    store.addFiles([createMockFile('a.txt'), createMockFile('b.txt')])
    store.queue[0].status = 'error'
    store.queue[1].status = 'error'

    expect(store.errorCount).toBe(2)
  })

  it('totalProgress averages all item progress', () => {
    const store = useUploadStore()
    store.addFiles([createMockFile('a.txt'), createMockFile('b.txt')])
    store.queue[0].progress = 100
    store.queue[1].progress = 50

    expect(store.totalProgress).toBe(75)
  })

  it('totalProgress is 0 for empty queue', () => {
    const store = useUploadStore()
    expect(store.totalProgress).toBe(0)
  })
})
