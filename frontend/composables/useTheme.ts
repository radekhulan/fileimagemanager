import { computed } from 'vue'
import { useUiStore } from '@/stores/uiStore'

export function useTheme() {
  const ui = useUiStore()

  const isDark = computed(() => ui.isDark)

  function toggle() {
    ui.toggleDarkMode()
  }

  function init(configDefault: boolean) {
    ui.initDarkMode(configDefault)
  }

  return {
    isDark,
    toggle,
    init,
  }
}
