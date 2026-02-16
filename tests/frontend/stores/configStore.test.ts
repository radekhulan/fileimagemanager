import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  setCsrfToken: vi.fn(),
  getCsrfToken: vi.fn(),
  uploadFiles: vi.fn(),
}))

const mockInitSession = vi.fn()
const mockChangeLanguage = vi.fn()
vi.mock('@/api/config', () => ({
  configApi: {
    initSession: (...args: any[]) => mockInitSession(...args),
    changeLanguage: (...args: any[]) => mockChangeLanguage(...args),
    getConfig: vi.fn(),
    getLanguages: vi.fn(),
    getTranslations: vi.fn(),
    changeView: vi.fn(),
    changeSort: vi.fn(),
    changeFilter: vi.fn(),
  },
}))

import { useConfigStore } from '@/stores/configStore'
import { setCsrfToken } from '@/api/client'

describe('configStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('has correct initial state', () => {
    const store = useConfigStore()
    expect(store.config).toBeNull()
    expect(store.translations).toEqual({})
    expect(store.language).toBe('en_EN')
    expect(store.initialized).toBe(false)
    expect(store.isPopup).toBe(false)
  })

  it('isReady is false before initialization', () => {
    const store = useConfigStore()
    expect(store.isReady).toBe(false)
  })

  it('initialize() sets all fields from API response', async () => {
    const mockResponse = {
      csrfToken: 'token123',
      config: { darkMode: true, uploadDir: '/source/' },
      translations: { hello: 'Hello' },
      language: 'en_EN',
    }
    mockInitSession.mockResolvedValue(mockResponse)

    const store = useConfigStore()
    await store.initialize()

    expect(store.config).toEqual(mockResponse.config)
    expect(store.translations).toEqual(mockResponse.translations)
    expect(store.language).toBe('en_EN')
    expect(store.initialized).toBe(true)
    // CSRF token is now stored privately in api/client module, not in the store
    expect(setCsrfToken).toHaveBeenCalledWith('token123')
  })

  it('isReady is true after initialization', async () => {
    mockInitSession.mockResolvedValue({
      csrfToken: 'x', config: {}, translations: {}, language: 'en_EN',
    })
    const store = useConfigStore()
    await store.initialize()
    expect(store.isReady).toBe(true)
  })

  it('t() returns translation for key', () => {
    const store = useConfigStore()
    store.translations = { greeting: 'Hello World' }
    expect(store.t('greeting')).toBe('Hello World')
  })

  it('t() returns key when translation missing', () => {
    const store = useConfigStore()
    expect(store.t('missing_key')).toBe('missing_key')
  })

  it('t() replaces %1$s placeholders', () => {
    const store = useConfigStore()
    store.translations = { msg: 'Hello %1$s, you are %2$d' }
    expect(store.t('msg', 'Alice', 30)).toBe('Hello Alice, you are 30')
  })

  it('t() replaces %s and %d positionally', () => {
    const store = useConfigStore()
    store.translations = { msg: 'File %s deleted' }
    expect(store.t('msg', 'test.txt')).toBe('File test.txt deleted')
  })

  it('changeLanguage updates state', async () => {
    mockChangeLanguage.mockResolvedValue({ translations: { hello: 'Ahoj' } })
    const store = useConfigStore()
    await store.changeLanguage('cs')
    expect(store.translations).toEqual({ hello: 'Ahoj' })
    expect(store.language).toBe('cs')
  })

  it('getFileUrl builds URL from config uploadDir', () => {
    const store = useConfigStore()
    store.config = { uploadDir: '/source/' } as any
    expect(store.getFileUrl('photos/pic.jpg')).toBe('/source/photos/pic.jpg')
  })

  it('getFileUrl falls back to /source/ when config is null', () => {
    const store = useConfigStore()
    expect(store.getFileUrl('pic.jpg')).toBe('/source/pic.jpg')
  })

  it('isEditorMode is true when editorType is set', async () => {
    mockInitSession.mockResolvedValue({
      csrfToken: 'x', config: {}, translations: {}, language: 'en_EN',
    })
    const store = useConfigStore()
    // Simulate URL params manually
    store.editorType = 'tinymce'
    expect(store.isEditorMode).toBe(true)
  })

  it('isEditorMode is false when editorType is null', () => {
    const store = useConfigStore()
    expect(store.isEditorMode).toBe(false)
  })

  it('isPopupMode is true when popup is set but no editor', () => {
    const store = useConfigStore()
    store.isPopup = true
    store.editorType = null
    expect(store.isPopupMode).toBe(true)
  })

  it('editorParams returns current editor parameters', () => {
    const store = useConfigStore()
    store.isPopup = true
    store.callback = 'myCallback'
    store.fieldId = 'field1'
    store.isCrossDomain = true
    store.editorType = 'ckeditor'

    expect(store.editorParams).toEqual({
      popup: true,
      callback: 'myCallback',
      fieldId: 'field1',
      crossdomain: true,
      editor: 'ckeditor',
    })
  })
})
