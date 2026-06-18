import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const pluginSource = readFileSync(resolve(process.cwd(), 'public', 'tinymce', 'plugin.js'), 'utf8')

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}

function createEditorHarness() {
  const initHandlers: Record<string, Function> = {}
  const optionValues: Record<string, unknown> = {}
  let bookmarkIndex = 0

  const editorDoc = document.implementation.createHTMLDocument('editor')
  editorDoc.body.innerHTML = '<p>Long text before and after.</p>'

  const editor = {
    options: {
      register: vi.fn((name: string, spec: { default: unknown }) => {
        optionValues[name] = spec.default
      }),
      get: vi.fn((name: string) => optionValues[name]),
      set: vi.fn((name: string, value: unknown) => {
        optionValues[name] = value
      }),
    },
    ui: {
      registry: {
        addButton: vi.fn(),
        addMenuItem: vi.fn(),
      },
    },
    windowManager: {
      openUrl: vi.fn(),
    },
    on: vi.fn((event: string, handler: Function) => {
      initHandlers[event] = handler
    }),
    getDoc: vi.fn(() => editorDoc),
    selection: {
      getBookmark: vi.fn(() => ({ id: `bookmark-${++bookmarkIndex}` })),
      moveToBookmark: vi.fn(),
      setRng: vi.fn(),
      getContent: vi.fn(() => ''),
    },
    focus: vi.fn(),
    insertContent: vi.fn(),
  }

  const pluginFactories: Record<string, Function> = {}
  vi.stubGlobal('tinymce', {
    PluginManager: {
      add: vi.fn((name: string, factory: Function) => {
        pluginFactories[name] = factory
      }),
    },
  })

  new Function(pluginSource)()
  pluginFactories.fileimagemanager(editor)

  return {
    editor,
    editorDoc,
    init: () => initHandlers.init?.(),
  }
}

describe('public TinyMCE plugin', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
    document.head.innerHTML = ''
    document.body.innerHTML = ''
  })

  it('uploads pasted clipboard images through the server flow and inserts stable HTML', async () => {
    const sameOrigin = window.location.origin
    const fetchMock = vi.fn((input: RequestInfo | URL) => {
      const url = String(input)

      if (url === '/public/api/session/init') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            csrfToken: 'csrf-token',
            config: { dragDropUpload: true },
            translations: {},
          }),
        })
      }

      if (url === '/public/api/upload/dragdrop') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            files: [
              {
                name: 'clipboard.png',
                url: `${sameOrigin}/media/source/clipboard.png`,
                thumbUrl: `${sameOrigin}/media/thumbs/clipboard.png`,
                isImage: true,
              },
            ],
          }),
        })
      }

      throw new Error(`Unexpected fetch: ${url}`)
    })

    vi.stubGlobal('fetch', fetchMock)

    const { editor, editorDoc, init } = createEditorHarness()
    init()
    await flushPromises()

    const pasteEvent = new Event('paste', { bubbles: true, cancelable: true })
    Object.defineProperty(pasteEvent, 'clipboardData', {
      value: {
        types: ['Files'],
        files: [new File(['png'], 'clipboard.png', { type: 'image/png' })],
      },
    })
    pasteEvent.preventDefault = vi.fn()
    pasteEvent.stopImmediatePropagation = vi.fn()

    editorDoc.dispatchEvent(pasteEvent)
    expect(pasteEvent.preventDefault).toHaveBeenCalledOnce()
    expect(pasteEvent.stopImmediatePropagation).toHaveBeenCalledOnce()

    await flushPromises()
    await flushPromises()

    const imageButton = document.querySelector('[data-act="image"]') as HTMLButtonElement | null
    expect(imageButton).not.toBeNull()

    imageButton?.click()

    expect(editor.focus).toHaveBeenCalled()
    expect(editor.selection.moveToBookmark).toHaveBeenCalledTimes(1)
    expect(editor.insertContent).toHaveBeenCalledWith('<img src="/media/source/clipboard.png" alt="" />')
    expect(document.querySelector('.fim-dd-overlay')).toBeNull()
  })

  it('leaves normal text paste to TinyMCE', async () => {
    const fetchMock = vi.fn(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
          csrfToken: 'csrf-token',
          config: { dragDropUpload: true },
          translations: {},
        }),
      })
    )

    vi.stubGlobal('fetch', fetchMock)

    const { editorDoc, init } = createEditorHarness()
    init()
    await flushPromises()

    const pasteEvent = new Event('paste', { bubbles: true, cancelable: true })
    Object.defineProperty(pasteEvent, 'clipboardData', {
      value: {
        types: ['text/plain'],
        files: [],
      },
    })
    pasteEvent.preventDefault = vi.fn()
    pasteEvent.stopImmediatePropagation = vi.fn()

    editorDoc.dispatchEvent(pasteEvent)

    expect(pasteEvent.preventDefault).not.toHaveBeenCalled()
    expect(pasteEvent.stopImmediatePropagation).not.toHaveBeenCalled()
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(document.querySelector('.fim-dd-overlay')).toBeNull()
  })
})
