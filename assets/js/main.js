// main.js: integration point
import { Api_Client } from './services/api_client.js'
import { Selection_State } from './state/selection_state.js'
import { Selection_Overlay } from './ui/selection_overlay.js'

(function init_aidad() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return
  const cfg = window.AIDAD_CONFIG || {}

  const api = new Api_Client(cfg.rest_url || '', cfg.nonce || '')
  const state = new Selection_State()
  const overlay = new Selection_Overlay({ ui: cfg.ui, i18n: {
    hide: 'Скрыть блок',
    no_match: 'XPath не находит элементы на этой странице',
    save_failed: 'Ошибка сохранения правила',
    hint_title: 'Режим выбора включен',
    hint_text: 'Наведите курсор на блок, затем кликните и выберите срок скрытия. Нажмите Esc или «Выйти из режима» для выхода. Если скрыли важное — используйте «Сбросить все».',
  } }, api, state)

  overlay.init()
  // Restore selection mode state across reloads if previously active
  try {
    const keep = sessionStorage.getItem('AIDAD_SELECTION_ACTIVE') === '1'
    overlay.toggle(!!keep)
  } catch (_) {
    overlay.toggle(false)
  }

  function bind_admin_bar_icon() {
    const icon = document.getElementById('aidad-icon')?.closest('#wp-admin-bar-aidad-toggle') || document.getElementById('wp-admin-bar-aidad-toggle')
    if (!icon) return
    icon.addEventListener('click', (e) => {
      e.preventDefault()
      overlay.toggle(!state.active)
      try { sessionStorage.setItem('AIDAD_SELECTION_ACTIVE', state.active ? '1' : '0') } catch (_) {}
      icon.classList.toggle('aidad-active', state.active)
      return false
    })
    icon.title = state.active ? 'Selection: ON' : 'Selection: OFF'
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind_admin_bar_icon)
  } else {
    bind_admin_bar_icon()
  }
})()

