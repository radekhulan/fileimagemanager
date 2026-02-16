import { useConfigStore } from '@/stores/configStore'
import type { FileItem } from '@/types/files'

export function useEditorIntegration() {
  const configStore = useConfigStore()

  function isEditorMode(): boolean {
    return configStore.isEditorMode
  }

  function getTargetOrigin(): string {
    // In cross-domain mode, use the configured baseUrl origin when available.
    // This avoids using '*' which would allow any parent to intercept messages.
    if (configStore.isCrossDomain) {
      try {
        const baseUrl = configStore.config?.baseUrl
        if (baseUrl) {
          const parsed = new URL(baseUrl)
          return parsed.origin
        }
      } catch {
        // Invalid baseUrl — fall through
      }
      // Last resort: use document.referrer origin if available
      try {
        if (document.referrer) {
          const parsed = new URL(document.referrer)
          return parsed.origin
        }
      } catch {
        // Invalid referrer
      }
      // Cannot determine target origin — use '*' as unavoidable fallback
      return '*'
    }
    return window.location.origin
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
    const origin = getTargetOrigin()
    window.parent.postMessage(
      { sender: 'fileimagemanager', url },
      origin
    )
  }

  function selectForCKEditor(url: string) {
    if (configStore.isCrossDomain) {
      const origin = getTargetOrigin()
      window.parent.postMessage(
        { sender: 'fileimagemanager', url },
        origin
      )
    } else {
      try {
        const funcNum = new URLSearchParams(window.location.search).get('CKEditorFuncNum')
        if (funcNum && /^\d+$/.test(funcNum) && (window.opener || window.parent)) {
          const target = window.opener || window.parent
          ;(target as any).CKEDITOR.tools.callFunction(funcNum, url)
          window.close()
        }
      } catch {
        // If CKEditor direct call fails, use postMessage with own origin
        window.parent.postMessage(
          { sender: 'fileimagemanager', url },
          window.location.origin
        )
      }
    }
  }

  function selectWithCallback(url: string) {
    try {
      const win = (window.opener || window.parent) as any
      const cb = configStore.callback
      // Only allow safe callback names (alphanumeric, underscore, dot)
      if (cb && /^[a-zA-Z_$][\w$.]*$/.test(cb) && typeof win[cb] === 'function') {
        win[cb](url)
        if (window.opener) window.close()
      }
    } catch {
      // If callback fails, use postMessage with own origin
      window.parent.postMessage(
        { sender: 'fileimagemanager', url },
        window.location.origin
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
      // If DOM access fails, use postMessage with own origin
      window.parent.postMessage(
        { sender: 'fileimagemanager', url },
        window.location.origin
      )
    }
  }

  function selectViaPostMessage(url: string) {
    const origin = getTargetOrigin()
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
    getTargetOrigin,
    selectFile,
    selectForPopup,
  }
}
