/**
 * CKEditor 5 integration for File Image Manager v1.0.0
 *
 * Usage:
 *   import { createRfmBrowseAdapter } from './plugins/ckeditor'
 *
 *   ClassicEditor.create(document.querySelector('#editor'), {
 *     ckfinder: {
 *       // Override the default upload URL
 *       uploadUrl: '/api/upload',
 *     },
 *     toolbar: ['rfmBrowse', ...],
 *   })
 *
 * Or use the browse helper directly:
 *   const adapter = createRfmBrowseAdapter({
 *     filemanagerUrl: '/filemanager/',
 *     accessKey: 'your-key',
 *   })
 *   adapter.open((url) => { console.log('Selected:', url) })
 */

export interface RfmBrowseOptions {
  filemanagerUrl?: string
  accessKey?: string
  crossdomain?: boolean
  width?: number
  height?: number
}

export function createRfmBrowseAdapter(options: RfmBrowseOptions = {}) {
  const {
    filemanagerUrl = '/filemanager/',
    accessKey = '',
    crossdomain = false,
    width = 900,
    height = 600,
  } = options

  function buildUrl(): string {
    const params = new URLSearchParams({
      editor: 'ckeditor',
      popup: '1',
    })
    if (accessKey) params.set('akey', accessKey)
    if (crossdomain) params.set('crossdomain', '1')
    return filemanagerUrl + '?' + params.toString()
  }

  function open(callback: (url: string) => void) {
    const url = buildUrl()

    const left = (screen.width - width) / 2
    const top = (screen.height - height) / 2

    const popup = window.open(
      url,
      'rfm_ckeditor',
      `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
    )

    // Listen for postMessage from the file manager
    const handler = (event: MessageEvent) => {
      if (event.data?.sender === 'fileimagemanager') {
        window.removeEventListener('message', handler)
        callback(event.data.url)
        popup?.close()
      }
    }
    window.addEventListener('message', handler)

    // Fallback: check if popup was closed without selection
    const checkClosed = setInterval(() => {
      if (popup?.closed) {
        clearInterval(checkClosed)
        window.removeEventListener('message', handler)
      }
    }, 500)
  }

  return { open, buildUrl }
}

/**
 * CKEditor 4 backward compatibility
 * For CKEditor 4, use the traditional browse function approach:
 *
 * CKEDITOR.config.filebrowserBrowseUrl = '/filemanager/?editor=ckeditor&popup=1'
 * CKEDITOR.config.filebrowserImageBrowseUrl = '/filemanager/?editor=ckeditor&popup=1'
 *
 * The file manager will automatically call CKEDITOR.tools.callFunction() with the
 * CKEditorFuncNum parameter when a file is selected.
 */
