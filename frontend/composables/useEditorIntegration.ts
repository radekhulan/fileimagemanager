import { useConfigStore } from '@/stores/configStore'
import type { FileItem } from '@/types/files'

export function useEditorIntegration() {
  const configStore = useConfigStore()

  function isEditorMode(): boolean {
    return configStore.isEditorMode
  }

  function selectFile(item: FileItem) {
    if (!configStore.isEditorMode) return

    const fileUrl = configStore.getFileUrl(item.path)

    if (configStore.editorType === 'tinymce') {
      selectForTinyMCE(fileUrl)
    } else if (configStore.editorType === 'ckeditor') {
      selectForCKEditor(fileUrl)
    } else if (configStore.callback) {
      selectWithCallback(fileUrl)
    } else if (configStore.fieldId) {
      selectForFieldId(fileUrl)
    } else {
      selectViaPostMessage(fileUrl)
    }
  }

  function selectForTinyMCE(url: string) {
    // Always use postMessage — the TinyMCE plugin listens for it,
    // handles content insertion, and closes the dialog.
    const origin = configStore.isCrossDomain ? '*' : window.location.origin
    window.parent.postMessage(
      { sender: 'fileimagemanager', url },
      origin
    )
  }

  function selectForCKEditor(url: string) {
    if (configStore.isCrossDomain) {
      window.parent.postMessage(
        { sender: 'fileimagemanager', url },
        '*'
      )
    } else {
      try {
        const funcNum = new URLSearchParams(window.location.search).get('CKEditorFuncNum')
        if (funcNum && (window.opener || window.parent)) {
          const target = window.opener || window.parent
          ;(target as any).CKEDITOR.tools.callFunction(funcNum, url)
          window.close()
        }
      } catch {
        window.parent.postMessage(
          { sender: 'fileimagemanager', url },
          '*'
        )
      }
    }
  }

  function selectWithCallback(url: string) {
    try {
      const win = (window.opener || window.parent) as any
      if (configStore.callback && typeof win[configStore.callback] === 'function') {
        win[configStore.callback](url)
        if (window.opener) window.close()
      }
    } catch {
      window.parent.postMessage(
        { sender: 'fileimagemanager', url },
        '*'
      )
    }
  }

  function selectForFieldId(url: string) {
    try {
      const win = (window.opener || window.parent) as any
      if (configStore.fieldId) {
        const field = win.document.getElementById(configStore.fieldId)
        if (field) {
          field.value = url
          if (typeof field.onchange === 'function') field.onchange()
        }
        if (window.opener) window.close()
      }
    } catch {
      window.parent.postMessage(
        { sender: 'fileimagemanager', url },
        '*'
      )
    }
  }

  function selectViaPostMessage(url: string) {
    const origin = configStore.isCrossDomain ? '*' : window.location.origin
    const target = window.opener || window.parent
    target.postMessage(
      { sender: 'fileimagemanager', url },
      origin
    )
  }

  function isPopupMode(): boolean {
    return configStore.isPopupMode
  }

  function selectForPopup(item: FileItem) {
    if (!configStore.isPopup) return
    const fileUrl = configStore.getFileUrl(item.path)
    selectViaPostMessage(fileUrl)
  }

  return {
    isEditorMode,
    isPopupMode,
    selectFile,
    selectForPopup,
  }
}
