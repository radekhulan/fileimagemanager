/**
 * TinyMCE 8 plugin for File Image Manager v1.0.0
 *
 * Usage in TinyMCE config:
 *   import { fileImageManager } from './plugins/tinymce'
 *   tinymce.init({
 *     plugins: ['fileimagemanager'],
 *     toolbar: 'fileimagemanager',
 *     external_plugins: {
 *       fileimagemanager: '/filemanager/plugins/tinymce.js'
 *     },
 *     filemanager_url: '/filemanager/',
 *     filemanager_access_key: 'your-key',
 *     filemanager_crossdomain: false,
 *   })
 */

declare const tinymce: any

;(function () {
  'use strict'

  function escapeHtmlAttr(str: string): string {
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  }

  function escapeHtml(str: string): string {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  }

  tinymce.PluginManager.add('fileimagemanager', function (editor: any) {
    const filemanagerUrl = editor.getParam('filemanager_url', '/filemanager/')
    const accessKey = editor.getParam('filemanager_access_key', '')
    const crossdomain = editor.getParam('filemanager_crossdomain', false)

    // Derive expected origin from the file manager URL
    let expectedOrigin: string
    try {
      expectedOrigin = new URL(filemanagerUrl, window.location.origin).origin
    } catch {
      expectedOrigin = window.location.origin
    }

    function isValidOrigin(eventOrigin: string): boolean {
      return eventOrigin === window.location.origin || eventOrigin === expectedOrigin
    }

    function buildUrl(fieldId?: string): string {
      const params = new URLSearchParams({
        editor: 'tinymce',
        popup: '1',
      })
      if (fieldId) params.set('field_id', fieldId)
      if (accessKey) params.set('akey', accessKey)
      if (crossdomain) params.set('crossdomain', '1')
      return filemanagerUrl + '?' + params.toString()
    }

    // Register file browser callback
    editor.options.register('file_picker_callback', {
      processor: 'function',
      default: (callback: Function, value: string, meta: any) => {
        const url = buildUrl()

        // If crossdomain, use postMessage
        if (crossdomain) {
          const handler = (event: MessageEvent) => {
            if (event.data?.sender === 'fileimagemanager' && isValidOrigin(event.origin)) {
              window.removeEventListener('message', handler)
              callback(event.data.url, { alt: '' })
            }
          }
          window.addEventListener('message', handler)
        }

        editor.windowManager.openUrl({
          title: 'File Image Manager',
          url,
          width: 900,
          height: 600,
        })
      }
    })

    // Register toolbar button
    editor.ui.registry.addButton('fileimagemanager', {
      icon: 'browse',
      tooltip: 'File Manager',
      onAction: () => {
        const url = buildUrl()

        if (crossdomain) {
          const handler = (event: MessageEvent) => {
            if (event.data?.sender === 'fileimagemanager' && isValidOrigin(event.origin)) {
              window.removeEventListener('message', handler)
              const fileUrl = event.data.url

              // Determine insertion type based on extension
              const ext = fileUrl.split('.').pop()?.toLowerCase() || ''
              const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico']
              const mediaExts = ['mp4', 'webm', 'ogg', 'mp3', 'wav']
              const safeUrl = escapeHtmlAttr(fileUrl)

              if (imageExts.includes(ext)) {
                editor.insertContent(`<img src="${safeUrl}" alt="" />`)
              } else if (mediaExts.includes(ext)) {
                if (['mp4', 'webm', 'ogg'].includes(ext)) {
                  editor.insertContent(`<video src="${safeUrl}" controls></video>`)
                } else {
                  editor.insertContent(`<audio src="${safeUrl}" controls></audio>`)
                }
              } else {
                const name = escapeHtml(fileUrl.split('/').pop() || 'file')
                editor.insertContent(`<a href="${safeUrl}">${name}</a>`)
              }
            }
          }
          window.addEventListener('message', handler)
        }

        editor.windowManager.openUrl({
          title: 'File Image Manager',
          url,
          width: 900,
          height: 600,
        })
      },
    })

    // Register menu item
    editor.ui.registry.addMenuItem('fileimagemanager', {
      icon: 'browse',
      text: 'File Manager',
      onAction: () => {
        editor.execCommand('mceFileImageManager')
      },
    })

    return {
      getMetadata: () => ({
        name: 'File Image Manager',
        url: 'https://github.com/user/fileimagemanager',
      }),
    }
  })
})()
