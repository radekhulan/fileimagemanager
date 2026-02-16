import { describe, it, expect, vi, beforeEach } from 'vitest'

const mockGet = vi.fn()
const mockPost = vi.fn()

vi.mock('@/api/client', () => ({
  default: {
    get: (...args: any[]) => mockGet(...args),
    post: (...args: any[]) => mockPost(...args),
  },
  setCsrfToken: vi.fn(),
  getCsrfToken: vi.fn(),
  uploadFiles: vi.fn(),
}))

import { filesApi, foldersApi, operationsApi, imageApi } from '@/api/files'

describe('filesApi', () => {
  beforeEach(() => vi.clearAllMocks())

  it('list calls GET /files with params', async () => {
    mockGet.mockResolvedValue({ data: { items: [], total: 0 } })
    await filesApi.list({ path: 'photos/', sort_by: 'name', limit: 100, offset: 0 })
    expect(mockGet).toHaveBeenCalledWith('/files', { params: { path: 'photos/', sort_by: 'name', limit: 100, offset: 0 } })
  })

  it('info calls GET /files/info', async () => {
    mockGet.mockResolvedValue({ data: { name: 'test.jpg' } })
    await filesApi.info('test.jpg')
    expect(mockGet).toHaveBeenCalledWith('/files/info', { params: { path: 'test.jpg' } })
  })

  it('preview calls GET /files/preview', async () => {
    mockGet.mockResolvedValue({ data: { type: 'image', url: '/source/test.jpg' } })
    const result = await filesApi.preview('test.jpg')
    expect(mockGet).toHaveBeenCalledWith('/files/preview', { params: { path: 'test.jpg' } })
    expect(result.type).toBe('image')
  })

  it('getContent calls GET /files/content', async () => {
    mockGet.mockResolvedValue({ data: { content: 'hello', name: 'f.txt', extension: 'txt' } })
    const result = await filesApi.getContent('f.txt')
    expect(result.content).toBe('hello')
  })

  it('getDownloadUrl returns correct URL', () => {
    // jsdom has window.location.pathname = '/' by default
    const url = filesApi.getDownloadUrl('photos/pic.jpg')
    expect(url).toContain('api/files/download')
    expect(url).toContain('path=')
    expect(url).toContain('photos%2Fpic.jpg')
  })
})

describe('foldersApi', () => {
  beforeEach(() => vi.clearAllMocks())

  it('tree calls GET /folders/tree without params when no path', async () => {
    mockGet.mockResolvedValue({ data: { tree: [] } })
    await foldersApi.tree()
    expect(mockGet).toHaveBeenCalledWith('/folders/tree', { params: undefined })
  })

  it('tree calls GET /folders/tree with path', async () => {
    mockGet.mockResolvedValue({ data: { tree: [] } })
    await foldersApi.tree('photos/')
    expect(mockGet).toHaveBeenCalledWith('/folders/tree', { params: { path: 'photos/' } })
  })

  it('create calls POST /folders/create', async () => {
    mockPost.mockResolvedValue({ data: { success: true, path: 'photos/new/' } })
    await foldersApi.create('photos/', 'new')
    expect(mockPost).toHaveBeenCalledWith('/folders/create', { path: 'photos/', name: 'new' })
  })

  it('rename calls POST /folders/rename', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await foldersApi.rename('old/', 'new')
    expect(mockPost).toHaveBeenCalledWith('/folders/rename', { path: 'old/', name: 'new' })
  })

  it('delete calls POST /folders/delete', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await foldersApi.delete('folder/')
    expect(mockPost).toHaveBeenCalledWith('/folders/delete', { path: 'folder/' })
  })
})

describe('operationsApi', () => {
  beforeEach(() => vi.clearAllMocks())

  it('rename calls POST /operations/rename', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.rename('f.txt', 'g.txt')
    expect(mockPost).toHaveBeenCalledWith('/operations/rename', { path: 'f.txt', name: 'g.txt' })
  })

  it('delete calls POST /operations/delete', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.delete('f.txt')
    expect(mockPost).toHaveBeenCalledWith('/operations/delete', { path: 'f.txt' })
  })

  it('deleteBulk calls POST /operations/delete-bulk', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.deleteBulk(['a.txt', 'b.txt'])
    expect(mockPost).toHaveBeenCalledWith('/operations/delete-bulk', { paths: ['a.txt', 'b.txt'] })
  })

  it('duplicate calls POST /operations/duplicate', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.duplicate('f.txt', 'f_copy.txt')
    expect(mockPost).toHaveBeenCalledWith('/operations/duplicate', { path: 'f.txt', name: 'f_copy.txt' })
  })

  it('copy calls POST /operations/copy', async () => {
    mockPost.mockResolvedValue({ data: { success: true, clipboard: {} } })
    await operationsApi.copy({ paths: ['a.txt'] })
    expect(mockPost).toHaveBeenCalledWith('/operations/copy', { paths: ['a.txt'] })
  })

  it('cut calls POST /operations/cut', async () => {
    mockPost.mockResolvedValue({ data: { success: true, clipboard: {} } })
    await operationsApi.cut({ paths: ['a.txt'] })
    expect(mockPost).toHaveBeenCalledWith('/operations/cut', { paths: ['a.txt'] })
  })

  it('paste calls POST /operations/paste', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.paste('target/')
    expect(mockPost).toHaveBeenCalledWith('/operations/paste', { path: 'target/' })
  })

  it('clearClipboard calls POST /operations/clear-clipboard', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.clearClipboard()
    expect(mockPost).toHaveBeenCalledWith('/operations/clear-clipboard')
  })

  it('chmod calls POST /operations/chmod', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.chmod('f.txt', '755', 'files')
    expect(mockPost).toHaveBeenCalledWith('/operations/chmod', { path: 'f.txt', mode: '755', recursive: 'files' })
  })

  it('extract calls POST /operations/extract', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.extract('archive.zip')
    expect(mockPost).toHaveBeenCalledWith('/operations/extract', { path: 'archive.zip' })
  })

  it('saveText calls POST /operations/save-text', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.saveText('f.txt', 'hello')
    expect(mockPost).toHaveBeenCalledWith('/operations/save-text', { path: 'f.txt', content: 'hello' })
  })

  it('createFile calls POST /operations/create-file', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await operationsApi.createFile('dir/', 'new.txt', 'content')
    expect(mockPost).toHaveBeenCalledWith('/operations/create-file', { path: 'dir/', name: 'new.txt', content: 'content' })
  })
})

describe('imageApi', () => {
  beforeEach(() => vi.clearAllMocks())

  it('saveEdited calls POST /image/save', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await imageApi.saveEdited('photo.jpg', 'base64data', 'photo.jpg')
    expect(mockPost).toHaveBeenCalledWith('/image/save', { path: 'photo.jpg', image_data: 'base64data', new_name: 'photo.jpg', quality: 92 })
  })
})
