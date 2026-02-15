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

import { configApi } from '@/api/config'

describe('configApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('initSession calls GET /session/init without params', async () => {
    mockGet.mockResolvedValue({ data: { csrfToken: 'abc', config: {}, translations: {}, language: 'en_EN' } })
    const result = await configApi.initSession()
    expect(mockGet).toHaveBeenCalledWith('/session/init', { params: {} })
    expect(result.csrfToken).toBe('abc')
  })

  it('initSession passes akey when provided', async () => {
    mockGet.mockResolvedValue({ data: { csrfToken: 'abc', config: {}, translations: {}, language: 'en_EN' } })
    await configApi.initSession('my-key')
    expect(mockGet).toHaveBeenCalledWith('/session/init', { params: { akey: 'my-key' } })
  })

  it('getConfig calls GET /config', async () => {
    mockGet.mockResolvedValue({ data: { config: { darkMode: true } } })
    const result = await configApi.getConfig()
    expect(mockGet).toHaveBeenCalledWith('/config')
    expect(result).toEqual({ darkMode: true })
  })

  it('getLanguages calls GET /languages', async () => {
    mockGet.mockResolvedValue({ data: { languages: [{ code: 'en_EN', name: 'English' }] } })
    const result = await configApi.getLanguages()
    expect(mockGet).toHaveBeenCalledWith('/languages')
    expect(result).toHaveLength(1)
  })

  it('getTranslations calls GET /translations without lang', async () => {
    mockGet.mockResolvedValue({ data: { translations: { hello: 'Hello' } } })
    const result = await configApi.getTranslations()
    expect(mockGet).toHaveBeenCalledWith('/translations', { params: {} })
    expect(result.hello).toBe('Hello')
  })

  it('getTranslations passes lang when provided', async () => {
    mockGet.mockResolvedValue({ data: { translations: { hello: 'Ahoj' } } })
    await configApi.getTranslations('cs')
    expect(mockGet).toHaveBeenCalledWith('/translations', { params: { lang: 'cs' } })
  })

  it('changeLanguage calls POST /config/language', async () => {
    mockPost.mockResolvedValue({ data: { success: true, translations: {} } })
    const result = await configApi.changeLanguage('cs')
    expect(mockPost).toHaveBeenCalledWith('/config/language', { lang: 'cs' })
    expect(result.success).toBe(true)
  })

  it('changeView calls POST /config/view', async () => {
    mockPost.mockResolvedValue({ data: { success: true, viewType: 1 } })
    await configApi.changeView(1)
    expect(mockPost).toHaveBeenCalledWith('/config/view', { type: 1 })
  })

  it('changeSort calls POST /config/sort', async () => {
    mockPost.mockResolvedValue({ data: { success: true } })
    await configApi.changeSort('date', true)
    expect(mockPost).toHaveBeenCalledWith('/config/sort', { sort_by: 'date', descending: true })
  })
})
