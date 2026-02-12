import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { AppConfig } from '@/types/config'
import { configApi } from '@/api/config'
import { setCsrfToken } from '@/api/client'

export const useConfigStore = defineStore('config', () => {
  const config = ref<AppConfig | null>(null)
  const translations = ref<Record<string, string>>({})
  const language = ref('en_EN')
  const csrfToken = ref('')
  const initialized = ref(false)
  const isPopup = ref(false)
  const callback = ref<string | null>(null)
  const fieldId = ref<string | null>(null)
  const isCrossDomain = ref(false)
  const editorType = ref<string | null>(null) // 'tinymce', 'ckeditor', null

  const isReady = computed(() => initialized.value && config.value !== null)
  const isEditorMode = computed(() => !!editorType.value)
  const isPopupMode = computed(() => isPopup.value && !editorType.value)
  const isDark = computed(() => document.documentElement.classList.contains('dark'))

  const editorParams = computed(() => ({
    popup: isPopup.value,
    callback: callback.value,
    fieldId: fieldId.value,
    crossdomain: isCrossDomain.value,
    editor: editorType.value,
  }))

  function getFileUrl(relativePath: string): string {
    const base = config.value?.uploadDir || '/source/'
    return base + relativePath
  }

  async function initialize() {
    // Parse URL params for editor integration
    const params = new URLSearchParams(window.location.search)
    isPopup.value = params.get('popup') === '1'
    callback.value = params.get('callback')
    fieldId.value = params.get('field_id')
    isCrossDomain.value = params.get('crossdomain') === '1'
    editorType.value = params.get('editor')

    const akey = params.get('akey') || undefined
    const response = await configApi.initSession(akey)

    config.value = response.config
    translations.value = response.translations
    language.value = response.language
    csrfToken.value = response.csrfToken

    setCsrfToken(response.csrfToken)
    initialized.value = true
  }

  function t(key: string, ...args: (string | number)[]): string {
    let text = translations.value[key] ?? key
    // Replace %1$s, %2$s etc. with arguments
    args.forEach((arg, i) => {
      text = text.replace(new RegExp(`%${i + 1}\\$[sd]`, 'g'), String(arg))
      text = text.replace('%s', String(arg))
      text = text.replace('%d', String(arg))
    })
    return text
  }

  async function changeLanguage(lang: string) {
    const response = await configApi.changeLanguage(lang)
    if (response.translations) {
      translations.value = response.translations
      language.value = lang
    }
  }

  return {
    config, translations, language, csrfToken, initialized, isReady,
    isPopup, callback, fieldId, isCrossDomain, editorType,
    isEditorMode, isPopupMode, isDark, editorParams,
    initialize, t, changeLanguage, getFileUrl,
  }
})
