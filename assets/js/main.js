// main.js: integration point
import { Api_Client } from './services/api_client.js'
import { Selection_State } from './state/selection_state.js'
import { Selection_Overlay } from './ui/selection_overlay.js'
import { __ } from '@wordpress/i18n'

(function init_aidad() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return
  const cfg = window.AIDAD_CONFIG || {}

  const api = new Api_Client(cfg.rest_url || '', cfg.nonce || '')
  const state = new Selection_State()
  const overlay = new Selection_Overlay({ ui: cfg.ui, i18n: {
    hide: __('Hide block', 'ads-destroyer'),
    no_match: __('XPath does not match elements on this page', 'ads-destroyer'),
    save_failed: __('Failed to save the rule', 'ads-destroyer'),
    hint_title: __('Selection mode is ON', 'ads-destroyer'),
    hint_text: __('Hover a block, click it, then choose hide duration. Press Esc or “Exit mode” to leave. If you hid something important — use “Reset all”.', 'ads-destroyer'),
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
    icon.title = state.active ? __('Selection: ON', 'ads-destroyer') : __('Selection: OFF', 'ads-destroyer')
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind_admin_bar_icon)
  } else {
    bind_admin_bar_icon()
  }
})()

