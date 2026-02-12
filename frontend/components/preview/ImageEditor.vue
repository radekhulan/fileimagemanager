<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { useUiStore } from '@/stores/uiStore'
import { useConfigStore } from '@/stores/configStore'
import { useFileStore } from '@/stores/fileStore'
import { imageApi } from '@/api/files'
import { lightPalette, darkPalette, getEditorLocale } from '@/utils/editorConfig'

// Filerobot Image Editor is React-based; it needs React on window
import React from 'react'
import ReactDOM from 'react-dom'
import FilerobotImageEditor from 'filerobot-image-editor'

// Expose React globally for filerobot-image-editor internals
;(window as any).React = React
;(window as any).ReactDOM = ReactDOM

const ui = useUiStore()
const configStore = useConfigStore()
const fileStore = useFileStore()
const { t } = configStore

const containerRef = ref<HTMLElement>()
const saving = ref(false)
let editorInstance: any = null

const imageEditorState = computed(() => ui.imageEditorState)

watch(() => ui.imageEditorState, async (state) => {
  if (state && state.url) {
    // Wait for v-if to render the container element
    await nextTick()
    initEditor(state.url)
  }
})

onMounted(() => {
  if (imageEditorState.value?.url) {
    initEditor(imageEditorState.value.url)
  }
})

onUnmounted(() => {
  destroyEditor()
})

function initEditor(imageUrl: string) {
  if (!containerRef.value) return
  destroyEditor()

  const { TABS, TOOLS } = FilerobotImageEditor

  // Locale: extract fie_* translations from lang files
  const locale = getEditorLocale(configStore.language, configStore.translations)

  const typography = { fontFamily: 'Inter, system-ui, -apple-system, sans-serif' }

  const config: Record<string, any> = {
    source: imageUrl,
    onSave: async (editedImageObject: any) => {
      await saveImage(editedImageObject)
    },
    onClose: () => {
      onClose()
    },
    annotationsCommon: {
      fill: '#ff0000',
    },
    Text: { text: 'Text...' },
    Rotate: { angle: 90, componentType: 'slider' },
    tabsIds: [TABS.ADJUST, TABS.ANNOTATE, TABS.FILTERS, TABS.FINETUNE, TABS.RESIZE],
    defaultTabId: TABS.ADJUST,
    defaultToolId: TOOLS.CROP,
    savingPixelRatio: 1,
    previewPixelRatio: window.devicePixelRatio || 1,
    // In dark mode, first render with default palette so translations
    // are established before themed styled-components mount.
    // Dark palette is applied in the next frame via render() update.
    theme: {
      palette: ui.isDark ? {} : lightPalette,
      typography,
    },
    language: locale.language,
    translations: locale.translations,
  }

  editorInstance = new FilerobotImageEditor(containerRef.value, config)
  editorInstance.render()

  if (ui.isDark) {
    // Apply dark palette AFTER translations are established in React context.
    // Also re-send translations to ensure they survive the theme re-render.
    requestAnimationFrame(() => {
      editorInstance.render({
        theme: { palette: darkPalette, typography },
        translations: locale.translations,
      })
      injectDarkOverrides()
    })
  }
}

const DARK_STYLE_ID = 'fie-dark-overrides'

function injectDarkOverrides() {
  if (document.getElementById(DARK_STYLE_ID)) return
  const style = document.createElement('style')
  style.id = DARK_STYLE_ID
  style.textContent = `
    html.dark [class*="SfxInput-root"] {
      background-color: #2a2a3e !important;
      border-color: #4a4a64 !important;
      color: #e4e4ef !important;
    }
    html.dark [class*="SfxInput-root"]:hover {
      background-color: #2a2a3e !important;
      border-color: #6a6a82 !important;
    }
    html.dark [class*="SfxInput-root"]:focus-within {
      background-color: #242438 !important;
      border-color: #5b8def !important;
    }
    html.dark [class*="SfxInput-Base"],
    html.dark input[data-testid^="FIE-"] {
      background-color: transparent !important;
      color: #e4e4ef !important;
      -webkit-text-fill-color: #e4e4ef !important;
    }
    html.dark [class*="SfxModal-Container"] {
      background-color: #242438 !important;
      color: #e4e4ef !important;
    }
  `
  document.head.appendChild(style)
}

function removeDarkOverrides() {
  document.getElementById(DARK_STYLE_ID)?.remove()
}

function destroyEditor() {
  if (editorInstance) {
    editorInstance.terminate()
    editorInstance = null
  }
  removeDarkOverrides()
}

async function saveImage(editedImageObject: any) {
  if (!imageEditorState.value?.path) return
  saving.value = true
  try {
    // Convert to base64
    const canvas = editedImageObject.imageCanvas || editedImageObject.canvas
    let base64Data: string

    if (canvas) {
      base64Data = canvas.toDataURL('image/png')
    } else if (editedImageObject.imageBase64) {
      base64Data = editedImageObject.imageBase64
    } else {
      throw new Error('No image data available')
    }

    const name = imageEditorState.value.path.split('/').pop() || 'image.png'
    await imageApi.saveEdited(imageEditorState.value.path, base64Data, name)
    await fileStore.refresh()
    onClose()
  } catch (err: any) {
    ui.alert(t('Error'), err?.response?.data?.error || err?.message || t('Image_Editor_No_Save'))
  } finally {
    saving.value = false
  }
}

function onClose() {
  destroyEditor()
  ui.closeImageEditor()
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="imageEditorState"
        class="fixed inset-0 z-50 flex flex-col bg-black dark:bg-[#171717]"
      >
        <!-- Saving overlay -->
        <div
          v-if="saving"
          class="absolute inset-0 z-60 flex items-center justify-center bg-black/50"
        >
          <div class="flex flex-col items-center gap-3 text-white">
            <svg class="w-10 h-10 rfm-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" d="M12 2v4m0 12v4m-7.07-3.93l2.83-2.83m8.49-8.49l2.83-2.83" />
            </svg>
            <span>{{ t('Saving') }}</span>
          </div>
        </div>

        <!-- Editor container -->
        <div
          ref="containerRef"
          class="flex-1 w-full h-full"
          :class="{ 'fie-dark': ui.isDark }"
        />
      </div>
    </Transition>
  </Teleport>
</template>

<style>
/*
 * Dark mode overrides for Filerobot Image Editor (FIE).
 *
 * FIE uses styled-components which generate hashed class names (sc-xxx)
 * alongside display names (SfxInput-root, SfxInput-Base, etc.).
 * Some elements like modals and popovers render via React portals
 * directly into <body>, outside our container. So we use html.dark
 * as the root selector.
 *
 * Key issue: @scaleflex/ui's input.utils.js uses hardcoded lightPalette
 * for getInputBackgroundColor/getInputTextColor/getInputBorderColor,
 * bypassing the theme. The white bg is on the wrapper (SfxInput-root),
 * not on the input itself (SfxInput-Base which is transparent).
 */

/* ── Input wrapper (root) - the element with the hardcoded white bg ── */
html.dark [class*="SfxInput-root"] {
  background-color: #2a2a3e !important;
  border-color: #4a4a64 !important;
  color: #e4e4ef !important;
}

html.dark [class*="SfxInput-root"]:hover {
  background-color: #2a2a3e !important;
  border-color: #6a6a82 !important;
}

html.dark [class*="SfxInput-root"]:focus-within {
  background-color: #242438 !important;
  border-color: #5b8def !important;
}

/* Input element itself */
html.dark [class*="SfxInput-Base"],
html.dark input[data-testid^="FIE-"] {
  background-color: transparent !important;
  color: #e4e4ef !important;
  -webkit-text-fill-color: #e4e4ef !important;
}

html.dark [class*="SfxInput-Base"]::placeholder,
html.dark input[data-testid^="FIE-"]::placeholder {
  color: #6a6a82 !important;
  -webkit-text-fill-color: #6a6a82 !important;
}

/* Field wrapper & container */
html.dark [class*="SfxInput-fieldWrapper"],
html.dark [class*="SfxInput-Container"],
html.dark [class*="SfxInput-inputContent"] {
  background-color: transparent !important;
}

/* Number input spin buttons */
html.dark input[type="number"][data-testid^="FIE-"]::-webkit-inner-spin-button {
  filter: invert(1);
}

/* ── Labels & text ──────────────────────────────────────── */
html.dark [class*="SfxLabel"],
html.dark label[class*="SfxLabel"] {
  color: #a0a0b8 !important;
}

html.dark [class*="SfxTabs"] [class*="SfxTab-Label"],
html.dark [class*="SfxTab-Label"] {
  color: #a0a0b8 !important;
}

/* ── Select / Dropdown ─────────────────────────────────── */
html.dark [class*="SfxSelect-root"],
html.dark [class*="SfxSelect-Base"],
html.dark [class*="SfxSelect"] > div {
  background-color: #2a2a3e !important;
  color: #e4e4ef !important;
  border-color: #4a4a64 !important;
}

/* ── Menu / Popover ────────────────────────────────────── */
html.dark [class*="SfxMenu-root"],
html.dark [class*="SfxMenu"],
html.dark [class*="SfxMenuItem"],
html.dark [class*="SfxPopover"],
html.dark [class*="SfxPopper-root"],
html.dark [class*="SfxAutocomplete"] {
  background-color: #2b2b3e !important;
  color: #e4e4ef !important;
  border-color: #4a4a64 !important;
}

/* Popper wrapper/backdrop must stay transparent */
html.dark [class*="SfxPopper-wrapper"] {
  background-color: transparent !important;
}

html.dark [class*="SfxMenuItem"]:hover {
  background-color: #383850 !important;
}

/* ── Buttons ────────────────────────────────────────────── */
html.dark [class*="SfxButton-secondary"],
html.dark [class*="SfxButton-Secondary"],
html.dark button[class*="SfxButton"][class*="secondary"] {
  background-color: #383850 !important;
  color: #e4e4ef !important;
  border-color: #4a4a64 !important;
}

/* ── Slider ─────────────────────────────────────────────── */
html.dark [class*="SfxSlider"] [class*="rail"],
html.dark [class*="SfxSlider-rail"] {
  background-color: #3b3b50 !important;
}

/* ── Modal / Dialog ─────────────────────────────────────── */
html.dark [class*="SfxModal-Container"],
html.dark [class*="SfxModal-root"],
html.dark [class*="SfxModal"],
html.dark [class*="SfxModalContent"],
html.dark [class*="SfxModal-content"],
html.dark [class*="SfxDrawer"],
html.dark [class*="SfxDialog"] {
  background-color: #242438 !important;
  color: #e4e4ef !important;
}

/* Save-as dialog & its content */
html.dark [data-testid="FIE-save-as-modal"],
html.dark [data-testid="FIE-save-as-modal"] div,
html.dark [data-testid="FIE-save-as-modal"] [class*="Styled"],
html.dark [class*="StyledSaveAs"],
html.dark [class*="StyledSave"],
html.dark [class*="StyledSaveModal"],
html.dark [class*="StyledSaveResize"] {
  background-color: #242438 !important;
  color: #e4e4ef !important;
}

/* ── Crop preset items ──────────────────────────────────── */
html.dark [class*="StyledCropPresetItem"],
html.dark [class*="StyledOptionPopup"],
html.dark [class*="StyledOptionWrapper"] {
  background-color: #2b2b3e !important;
  color: #e4e4ef !important;
}

/* ── Filters ────────────────────────────────────────────── */
html.dark [class*="StyledFilterItem"] span,
html.dark [class*="FilterItem"] span,
html.dark [class*="FilterLabel"] {
  color: #a0a0b8 !important;
}

/* ── Spinner / loading overlay ──────────────────────────── */
html.dark [class*="SfxSpinner-wrapper"],
html.dark [class*="SfxSpinner"] > div {
  background-color: rgba(36, 36, 56, 0.85) !important;
}

/* ── Carousel arrows gradients ──────────────────────────── */
html.dark [class*="SfxCarousel"] [class*="arrow"]::before,
html.dark [class*="SfxCarousel"] [class*="arrow"]::after {
  background: transparent !important;
}

/* ── Annotation options, node controls, toolbars ────────── */
html.dark [class*="StyledAnnotationOptions"],
html.dark [class*="StyledNodeControls"],
html.dark [class*="StyledControlsBar"],
html.dark [class*="StyledToolOptions"],
html.dark [class*="StyledOptions"] {
  background-color: #242438 !important;
  border-color: #3a3a52 !important;
  color: #e4e4ef !important;
}

/* ── Color picker ───────────────────────────────────────── */
html.dark [class*="SfxColorPicker"],
html.dark [class*="StyledPickerWrapper"],
html.dark [class*="SfxColorInput"] {
  background-color: #2b2b3e !important;
  color: #e4e4ef !important;
}

/* ── Tooltip ────────────────────────────────────────────── */
html.dark [class*="SfxTooltip"] {
  background-color: #444460 !important;
  color: #e4e4ef !important;
}

/* ── Tabs bar & items ───────────────────────────────────── */
html.dark [class*="StyledTabItem"],
html.dark [class*="SfxTab-item"],
html.dark [class*="SfxTabs-root"] {
  background-color: transparent !important;
  color: #a0a0b8 !important;
}

/* ── Watermark ──────────────────────────────────────────── */
html.dark [class*="StyledWatermark"] [class*="Styled"] {
  background-color: #2b2b3e !important;
  color: #e4e4ef !important;
}

/* ── InputGroup / SelectGroup wrappers ──────────────────── */
html.dark [class*="SfxInputGroup"],
html.dark [class*="SfxSelectGroup"] {
  color: #e4e4ef !important;
}

/* ── Global catch-all: any FIE element text ────────────── */
html.dark [data-testid^="FIE-"] {
  color: #e4e4ef;
}

/* Styled-components wrappers borders */
html.dark div[class*="Sfx"][class*="wrapper"],
html.dark div[class*="Sfx"][class*="Wrapper"] {
  border-color: #4a4a64;
}

/* FIE text color - backgrounds are handled by darkPalette theme */
html.dark [class*="FIE_"]:not(button):not([class*="SfxButton"]) {
  color: #e4e4ef;
}

/* Scrollbar inside editor */
html.dark [class*="FIE_"] ::-webkit-scrollbar-thumb,
html.dark .fie-dark ::-webkit-scrollbar-thumb {
  background: #4a4a64 !important;
}

html.dark [class*="FIE_"] ::-webkit-scrollbar-track,
html.dark .fie-dark ::-webkit-scrollbar-track {
  background: #242438 !important;
}

/* ── Top bar: 10% bigger ──────────────────────────────── */
[class*="FIE_topbar-buttons-wrapper"] {
  padding: 10px 14px !important;
}

[class*="FIE_topbar-buttons-wrapper"] button {
  font-size: 15px !important;
  padding: 7px 16px !important;
}

[class*="FIE_topbar-buttons-wrapper"] button svg {
  width: 18px !important;
  height: 18px !important;
}

[class*="FIE_tabs"] {
  padding: 5px 0 !important;
}

[class*="FIE_tab"] {
  font-size: 13px !important;
  padding: 10px 16px !important;
}

[class*="FIE_tab"] svg,
[class*="FIE_tab"] [class*="Icon"] {
  width: 19px !important;
  height: 19px !important;
}

/* ── Save button icon (floppy disk) ────────────────────── */
.FIE_buttons-save-btn-button [class*="SfxButton-Label"]::before {
  content: '';
  display: inline-block;
  width: 14px;
  height: 14px;
  margin-right: 5px;
  vertical-align: -2px;
  flex-shrink: 0;
  background-color: currentColor;
  -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z'/%3E%3Cpolyline points='17 21 17 13 7 13 7 21'/%3E%3Cpolyline points='7 3 7 8 15 8'/%3E%3C/svg%3E");
  -webkit-mask-size: contain;
  -webkit-mask-repeat: no-repeat;
  mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z'/%3E%3Cpolyline points='17 21 17 13 7 13 7 21'/%3E%3Cpolyline points='7 3 7 8 15 8'/%3E%3C/svg%3E");
  mask-size: contain;
  mask-repeat: no-repeat;
}
</style>
