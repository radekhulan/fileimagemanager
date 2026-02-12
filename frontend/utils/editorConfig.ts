/**
 * Filerobot Image Editor configuration: dark theme palette and translations.
 *
 * Translations are loaded from the lang/*.json files (keys prefixed with `fie_`).
 * The `extractEditorTranslations()` function strips the prefix and returns
 * a flat object suitable for filerobot-image-editor's `translations` config.
 */

/** Full dark-mode palette for filerobot-image-editor */
export const darkPalette: Record<string, string> = {
  // Backgrounds (slightly lighter for better readability)
  'bg-secondary': '#242438',
  'bg-primary': '#2b2b3e',
  'bg-primary-light': '#2b2b3e',
  'bg-primary-active': '#404054',
  'bg-primary-hover': '#333346',
  'bg-primary-0-5-opacity': 'rgba(36, 36, 56, 0.5)',
  'bg-primary-stateless': '#404054',
  'bg-hover': '#303044',
  'bg-active': '#383850',
  'bg-stateless': '#2b2b3e',
  'bg-base-light': '#303046',
  'bg-base-medium': '#383850',
  'bg-grey': '#404054',
  'bg-green': '#1a2e25',
  'bg-green-medium': '#1a3828',
  'bg-blue': '#1a2536',
  'bg-red': '#2e1a1a',
  'bg-red-light': '#331a1a',
  'background-red-medium': '#3d2020',
  'bg-orange': '#2e2518',
  'bg-tooltip': '#444460',

  // Text
  'txt-primary': '#e4e4ef',
  'txt-secondary': '#a0a0b8',
  'txt-secondary-invert': '#70708a',
  'txt-placeholder': '#6a6a82',
  'txt-warning': '#f0a030',
  'txt-error': '#f06060',
  'txt-info': '#60a0e0',

  // Accent
  'accent-primary': '#5b8def',
  'accent-primary-hover': '#4a7de0',
  'accent-primary-active': '#3a6dd0',
  'accent-primary-disabled': '#3b3b50',
  'accent-stateless': '#5b8def',

  // Icons
  'icon-primary': '#b0b0c8',
  'icons-primary': '#b0b0c8',
  'icons-primary-opacity-0-6': 'rgba(176, 176, 200, 0.6)',
  'icons-secondary': '#8080a0',
  'icons-placeholder': '#4a4a64',
  'icons-invert': '#ffffff',
  'icons-muted': '#6a6a82',
  'icons-primary-hover': '#d0d0e0',
  'icons-secondary-hover': '#a0a0b8',

  // Buttons
  'btn-primary-text': '#ffffff',
  'btn-primary-text-0-6': 'rgba(255, 255, 255, 0.6)',
  'btn-primary-text-0-4': 'rgba(255, 255, 255, 0.4)',
  'btn-disabled-text': '#5a5a72',
  'btn-secondary-text': '#e4e4ef',

  // Borders
  'borders-primary': '#4a4a64',
  'borders-primary-hover': '#6a6a82',
  'borders-secondary': '#3a3a52',
  'borders-strong': '#5a5a72',
  'borders-invert': '#6a6a82',
  'border-hover-bottom': 'rgba(91, 141, 239, 0.25)',
  'border-active-bottom': '#5b8def',
  'border-primary-stateless': '#4a4a64',
  'borders-disabled': 'rgba(91, 141, 239, 0.3)',
  'borders-button': '#5a5a72',
  'borders-item': '#3a3a52',

  // Links
  'link-primary': '#a0a0b8',
  'link-stateless': '#a0a0b8',
  'link-hover': '#c0c0d0',
  'link-active': '#e4e4ef',
  'link-pressed': '#5b8def',
  'link-muted': '#6a6a82',

  // States
  'error': '#f06060',
  'error-hover': '#e04545',
  'error-active': '#d03030',
  'success': '#40c880',
  'success-hover': '#30b870',
  'warning': '#f0a030',
  'warning-hover': '#e09020',
  'info': '#60a0e0',

  // Shadows
  'light-shadow': 'rgba(0, 0, 0, 0.35)',
  'medium-shadow': 'rgba(0, 0, 0, 0.45)',
  'large-shadow': 'rgba(0, 0, 0, 0.55)',
  'x-large-shadow': 'rgba(0, 0, 0, 0.7)',

  // Overlay
  'extra-0-3-overlay': 'rgba(26, 26, 40, 0.3)',
  'extra-0-5-overlay': 'rgba(26, 26, 40, 0.5)',
  'extra-0-7-overlay': 'rgba(26, 26, 40, 0.7)',
  'extra-0-9-overlay': 'rgba(26, 26, 40, 0.9)',
  'white-0-7-8-overlay': 'rgba(36, 36, 56, 0.78)',

  // Active
  'active-secondary': '#2b2b3e',
  'active-secondary-hover': '#333346',

  // Accent opacity variants
  'accent-stateless_0_4_opacity': 'rgba(91, 141, 239, 0.4)',
  'accent_0_5_5_opacity': 'rgba(91, 141, 239, 0.55)',
  'accent_0_7_opacity': 'rgba(91, 141, 239, 0.7)',
  'accent_0_5_opacity': 'rgba(91, 141, 239, 0.05)',
  'accent_1_2_opacity': 'rgba(91, 141, 239, 0.12)',
  'accent_1_8_opacity': 'rgba(91, 141, 239, 0.18)',
  'accent_2_8_opacity': 'rgba(91, 141, 239, 0.28)',
  'accent_4_0_opacity': 'rgba(91, 141, 239, 0.4)',
  'accent-secondary-disabled': '#2a2a3e',

  // Error opacity
  'error-0-28-opacity': 'rgba(240, 96, 96, 0.28)',
  'error-0-12-opacity': 'rgba(240, 96, 96, 0.12)',

  // Gradients (dark variants)
  'gradient-right': 'linear-gradient(270deg, #242438 1.56%, rgba(36,36,56,0.89) 52.4%, rgba(36,36,56,0.53) 76.04%, rgba(36,36,56,0) 100%)',
  'gradient-right-active': 'linear-gradient(270deg, #2b2b3e 0%, rgba(43,43,62,0) 100%)',
  'gradient-right-hover': 'linear-gradient(270deg, #303044 0%, rgba(48,48,68,0) 100%)',

  // Tag
  'tag': '#6a6a82',
}

/** Light-mode palette (minimal overrides, editor defaults are fine) */
export const lightPalette: Record<string, string> = {
  'accent-primary': '#3b82f6',
  'accent-primary-hover': '#2563eb',
  'accent-primary-active': '#1d4ed8',
}

/**
 * Extract filerobot editor translations from the RFM translations object.
 * Keys prefixed with `fie_` are stripped of the prefix and returned.
 */
export function extractEditorTranslations(
  translations: Record<string, string>,
): Record<string, string> {
  const result: Record<string, string> = {}
  for (const [key, value] of Object.entries(translations)) {
    if (key.startsWith('fie_')) {
      result[key.slice(4)] = value
    }
  }
  return result
}

/**
 * Get filerobot editor locale configuration.
 * Extracts fie_* translations from the RFM translations object.
 * Returns { language, translations } for the editor config.
 */
export function getEditorLocale(
  rfmLang: string,
  translations: Record<string, string>,
): { language: string; translations: Record<string, string> } {
  const editorTranslations = extractEditorTranslations(translations)
  return {
    language: rfmLang,
    translations: editorTranslations,
  }
}
