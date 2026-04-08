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
import { updateTranslations } from 'react-filerobot-image-editor/lib/utils/translator'

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

  // Pre-select correct format/name based on the original file
  const originalName = imageEditorState.value?.path?.split('/').pop() || 'image.png'
  const originalExt = originalName.split('.').pop()?.toLowerCase() || 'png'
  const extToType: Record<string, 'png' | 'jpeg' | 'webp'> = {
    png: 'png', jpg: 'jpeg', jpeg: 'jpeg', webp: 'webp',
  }
  const defaultType = extToType[originalExt] || 'png'
  const defaultName = originalName.replace(/\.[^.]+$/, '')

  const config: Record<string, any> = {
    source: imageUrl,
    onSave: async (editedImageObject: any, designState: any) => {
      await saveImage(editedImageObject, designState)
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
    defaultSavedImageName: defaultName,
    defaultSavedImageType: defaultType,
    defaultSavedImageQuality: 0.92,
    forceToPngInEllipticalCrop: false,
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
    useBackendTranslations: false,
  }

  // Pre-populate the FIE translation singleton BEFORE React renders.
  // FIE's useEffect calls updateTranslations AFTER render, but toolbar
  // components call translate() DURING render, reading stale English defaults.
  updateTranslations(locale.translations, locale.language)

  editorInstance = new FilerobotImageEditor(containerRef.value, config)
  editorInstance.render()

  if (ui.isDark) {
    // Apply dark palette AFTER React has mounted (styled-components need
    // the initial render to establish the theme context).
    requestAnimationFrame(() => {
      if (!editorInstance) return
      editorInstance.render({
        theme: { palette: darkPalette, typography },
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
    html.dark [class*="ColorPicker-root"] {
      background-color: #2b2b3e !important;
    }
    html.dark [class*="SfxModal-Wrapper"],
    html.dark [class*="SfxModal-Overlay"],
    html.dark [class*="SfxModal-root"] {
      background-color: rgba(0, 0, 0, 0.35) !important;
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

/**
 * Check if the user made edits that REQUIRE the canvas pipeline.
 * Pure geometric ops (flip, cardinal rotation) can be done losslessly server-side via GD,
 * so we only return true for edits that need the Filerobot canvas (filters, finetunes, etc.).
 */
function hasCanvasEdits(ds: any): boolean {
  if (!ds) return true // assume edited if no state available
  // Non-cardinal rotation (not 0, 90, 180, 270) needs canvas
  const rot = ds.adjustments?.rotation || 0
  if (rot !== 0 && rot % 90 !== 0) return true
  // Crop (if ratio or custom crop is set — noEffect means full image)
  const crop = ds.adjustments?.crop
  if (crop && !crop.noEffect && (crop.x || crop.y)) return true
  // Filter
  if (ds.filter) return true
  // Finetunes
  if (ds.finetunes && ds.finetunes.length > 0) return true
  // Annotations (text, shapes, etc.)
  if (ds.annotations && Object.keys(ds.annotations).length > 0) return true
  // Resize (custom size set by user)
  if (ds.resize && (ds.resize.width || ds.resize.height)) return true
  return false
}

/** Check if there are any geometric-only edits (flip, cardinal rotation) */
function getGeometricEdits(ds: any): { rotation: number; flipX: boolean; flipY: boolean } | null {
  if (!ds) return null
  const rotation = ds.adjustments?.rotation || 0
  const flipX = !!ds.adjustments?.isFlippedX
  const flipY = !!ds.adjustments?.isFlippedY
  if (rotation === 0 && !flipX && !flipY) return null
  return { rotation, flipX, flipY }
}

async function saveImage(editedImageObject: any, designState?: any) {
  if (!imageEditorState.value?.path) return
  saving.value = true
  try {
    const newName = editedImageObject.fullName
      || `${editedImageObject.name || 'image'}.${editedImageObject.extension || 'png'}`
    const quality = typeof editedImageObject.quality === 'number'
      ? Math.round(editedImageObject.quality * 100)
      : 92

    if (!hasCanvasEdits(designState)) {
      // No canvas-requiring edits — handle server-side (lossless).
      // This covers: no edits at all, or pure geometric (flip + cardinal rotation).
      const geo = getGeometricEdits(designState)
      await imageApi.saveEdited(imageEditorState.value.path, '', newName, quality, geo)
    } else {
      // Complex edits — send canvas data as lossless PNG for server re-encoding
      const canvas = editedImageObject.imageCanvas || editedImageObject.canvas
      let base64Data: string
      if (canvas) {
        base64Data = canvas.toDataURL('image/png')
      } else if (editedImageObject.imageBase64) {
        base64Data = editedImageObject.imageBase64
      } else {
        throw new Error('No image data available')
      }
      await imageApi.saveEdited(imageEditorState.value.path, base64Data, newName, quality)
    }

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
/* Only style the outer wrapper — internals (saturation panel,
   gradients, hue bar, color swatches) must keep their own
   styled-component backgrounds untouched. */
html.dark [class*="ColorPicker-root"] {
  background-color: #2b2b3e !important;
  color: #e4e4ef !important;
}

html.dark [class*="StyledPickerWrapper"],
html.dark [class*="SfxColorInput"] {
  background-color: #2b2b3e !important;
  color: #e4e4ef !important;
}

/* Modal backdrop: semi-transparent so the image behind stays visible
   (matches light-mode behaviour) */
html.dark [class*="SfxModal-Wrapper"],
html.dark [class*="SfxModal-Overlay"],
html.dark [class*="SfxModal-root"] {
  background-color: rgba(0, 0, 0, 0.35) !important;
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

/* ── Global catch-all: any FIE element text ── */
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
