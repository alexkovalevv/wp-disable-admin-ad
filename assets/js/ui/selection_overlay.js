// ui/selection_overlay.js
// Handles overlay rendering and interactions without mutating page DOM structure.
import {Xpath_Builder} from '../core/xpath_builder.js'
import {Xpath_Tester} from '../core/xpath_tester.js'
import {__} from '@wordpress/i18n'

export class Selection_Overlay {
    constructor(config, api, state) {
        this.config = config
        this.api = api
        this.state = state

        this.dim_el = null
        this.hover_mask = null
        this.selected_mask = null
        this.hide_button = null
        this.hint_box = null
        this.badge = null

        this.bound_mousemove = this.on_mouse_move.bind(this)
        this.bound_click = this.on_click.bind(this)
        this.bound_keydown = this.on_key_down.bind(this)
    }

    init() {
        if (this.hover_mask) return
        this.hover_mask = document.createElement('div')
        this.hover_mask.className = 'aidad-overlay aidad-hover-mask'
        this.selected_mask = document.createElement('div')
        this.selected_mask.className = 'aidad-overlay aidad-selected-mask'
        this.hide_button = document.createElement('button')
        this.hide_button.type = 'button'
        this.hide_button.className = 'aidad-hide-button'
        this.hide_button.textContent = this.config.i18n?.hide || __('Hide block', 'ads-destroyer')
        this.hide_button.addEventListener('click', (e) => this.toggle_context_menu(e))

        this.context_menu = document.createElement('div')
        this.context_menu.className = 'aidad-context-menu'
        this.context_menu.style.display = 'none'
        this.menu_items = [
            {label: __('Hide forever', 'ads-destroyer'), seconds: 0},
            {label: __('For a day', 'ads-destroyer'), seconds: 86400},
            {label: __('For a week', 'ads-destroyer'), seconds: 604800},
            {label: __('For a month', 'ads-destroyer'), seconds: 2592000},
        ]
        this.context_menu.innerHTML = this.menu_items.map((it, idx) => `<button type="button" class="aidad-context-menu__item" data-seconds="${it.seconds}">${it.label}</button>`).join('')

        document.body.appendChild(this.hover_mask)
        document.body.appendChild(this.selected_mask)
        document.body.appendChild(this.hide_button)
        document.body.appendChild(this.context_menu)

        // hover badge
        this.badge = document.createElement('div')
        this.badge.className = 'aidad-badge'
        this.badge.style.display = 'none'
        document.body.appendChild(this.badge)

        this.create_hint_box()

        this.attach_listeners()
        this.update_visibility()
    }

    destroy() {
        this.detach_listeners()
        for (const el of [this.hover_mask, this.selected_mask, this.hide_button, this.context_menu, this.hint_box, this.badge]) {
            if (el && el.parentNode) el.parentNode.removeChild(el)
        }
        this.hover_mask = this.selected_mask = this.hide_button = this.context_menu = this.hint_box = this.badge = null
    }

    attach_listeners() {
        document.addEventListener('mousemove', this.bound_mousemove, true)
        document.addEventListener('click', this.bound_click, true)
        document.addEventListener('keydown', this.bound_keydown, true)
    }

    detach_listeners() {
        document.removeEventListener('mousemove', this.bound_mousemove, true)
        document.removeEventListener('click', this.bound_click, true)
        document.removeEventListener('keydown', this.bound_keydown, true)
    }

    toggle(active) {
        this.state.set_active(active)
        // persist selection mode flag across reloads
        try {
            sessionStorage.setItem('AIDAD_SELECTION_ACTIVE', active ? '1' : '0')
        } catch (_) {
        }
        if (!active) {
            this.state.set_hover_target(null)
            this.state.set_selected_target(null)
            this.close_context_menu()
            // hide overlays and badge explicitly
            if (this.hover_mask) this.hover_mask.style.display = 'none'
            if (this.selected_mask) this.selected_mask.style.display = 'none'
            if (this.hide_button) this.hide_button.style.display = 'none'
            if (this.badge) this.badge.style.display = 'none'
        }
        this.update_visibility()
    }

    update_visibility() {
        const on = this.state.active
        // Overlays depend only on active state
        for (const el of [this.hover_mask, this.selected_mask]) {
            if (el) el.style.display = on ? 'block' : 'none'
        }
        if (this.hint_box) {
            this.hint_box.style.display = on ? 'block' : 'none'
        }
        if (!on && this.badge) {
            this.badge.style.display = 'none'
        }
        // Hide button visible only when a target is selected
        if (this.hide_button) {
            const show_btn = on && !!this.state.selected_target
            this.hide_button.style.display = show_btn ? 'block' : 'none'
            if (show_btn) {
                this.position_button_selected(this.state.selected_target)
            }
        }
        if (this.context_menu && (!on || !this.state.selected_target)) {
            this.close_context_menu()
        }
    }

    on_mouse_move(e) {
        if (!this.state.active) return
        // If a block is already selected, do not allow inner hover/selection
        if (this.state.selected_target) return
        // Ignore hover when interacting with our UI
        if (this.is_ui_event(e)) return
        const target = this.pick_target(e)
        this.state.set_hover_target(target)
        this.position_outline(this.hover_mask, target)
        this.update_badge(target)
        // Do NOT move the button with the cursor to avoid chasing behavior
    }

    on_click(e) {
        if (!this.state.active) return
        // If clicking our own UI (button or context menu), do not treat as selection
        if (this.is_ui_event(e)) return
        const target = this.pick_target(e)
        if (!target) return
        e.preventDefault()
        e.stopPropagation()
        this.state.set_selected_target(target)
        this.position_outline(this.selected_mask, target)
        this.position_button_selected(target)
        this.update_visibility()
        this.update_badge(target)
        // Отладка: что выбрано и какой базовый XPath сформирован
        try {
            const primaryXpath = Xpath_Builder.build_from_element(target)
            const t = Xpath_Tester.test(primaryXpath)
            // eslint-disable-next-line no-console
            console.groupCollapsed('[Aidad] Block selection')
            // eslint-disable-next-line no-console
            console.log('Element:', target)
            // eslint-disable-next-line no-console
            console.log('Initial XPath:', primaryXpath, 'Test:', t)
            // eslint-disable-next-line no-console
            console.groupEnd()
        } catch (err) {
            // eslint-disable-next-line no-console
            console.warn('[Aidad] Error building initial XPath in on_click:', err)
        }
    }

    on_key_down(e) {
        const key = e.key.toLowerCase()
        // ESC exits selection mode
        if (this.state.active && key === 'escape') {
            e.preventDefault()
            this.toggle(false)
            return
        }

        const hotkey = (this.config.ui?.hotkey || 'Ctrl+Shift+X').toLowerCase()
        const is_mac = navigator.platform.toLowerCase().includes('mac')
        const pressed = `${is_mac ? (e.metaKey ? 'cmd' : '') : (e.ctrlKey ? 'ctrl' : '')}${e.shiftKey ? '+shift' : ''}+${key}`
        if (hotkey.toLowerCase().replace('cmd', 'meta') === pressed.replace('cmd', 'meta')) {
            e.preventDefault()
            this.toggle(!this.state.active)
        }
    }

    pick_target(e) {
        const el = e.target
        if (!(el instanceof Element)) return null
        // Avoid overlay picking its own elements
        if (el.classList.contains('aidad-overlay') || el.classList.contains('aidad-hide-button') || el.classList.contains('aidad-dim') || el.closest('.aidad-context-menu') || el.closest('.aidad-hint')) {
            return null
        }
        // If a block is already selected, do not allow selecting inner elements until user exits selection or reselects
        if (this.state.selected_target) {
            return null
        }
        // Prefer selecting notice container in WP admin notices
        const notice = el.closest('.notice, .update-nag')
        if (notice) return notice
        // Exclude Gutenberg editable canvas if needed
        return el.closest('.edit-post-layout, .block-editor, body') === document.body ? el : document.body
    }

    position_outline(outline, target) {
        if (!outline) return
        if (!target || !(target instanceof Element)) {
            outline.style.display = 'none'
            return
        }
        const rect = target.getBoundingClientRect()
        outline.style.display = 'block'
        const left = rect.left + window.scrollX
        const top = rect.top + window.scrollY
        outline.style.left = `${left}px`
        outline.style.top = `${top}px`
        outline.style.width = `${rect.width}px`
        outline.style.height = `${rect.height}px`
    }

    update_badge(target) {
        if (!this.badge) return
        if (!this.state.active || !target || !(target instanceof Element)) {
            this.badge.style.display = 'none'
            return
        }
        const id = target.id ? '#' + target.id : ''
        let classes = ''
        try {
            classes = (target.className && typeof target.className === 'string') ? '.' + target.className.trim().split(/\s+/).join('.') : ''
        } catch (_) {
        }
        const label = (id || classes) ? `${id}${classes}` : target.tagName.toLowerCase()
        this.badge.textContent = label
        const rect = target.getBoundingClientRect()
        const left = rect.left + window.scrollX + 4
        const top = rect.top + window.scrollY + 4
        this.badge.style.left = left + 'px'
        this.badge.style.top = top + 'px'
        this.badge.style.display = 'block'
    }

    position_button_selected(target) {
        if (!this.hide_button || !target || !(target instanceof Element)) return
        const rect = target.getBoundingClientRect()
        const left = rect.left + window.scrollX + rect.width / 2 - this.hide_button.offsetWidth / 2
        const top = rect.top + window.scrollY + rect.height / 2 - this.hide_button.offsetHeight / 2
        this.hide_button.style.left = `${Math.max(0, left)}px`
        this.hide_button.style.top = `${Math.max(0, top)}px`
    }

    toggle_context_menu(e) {
        e.preventDefault()
        e.stopPropagation()
        if (!this.context_menu || !this.hide_button || !this.state.selected_target) return
        const is_open = this.hide_button.classList.contains('is-open')
        if (is_open) {
            this.close_context_menu()
            return
        }
        // Open
        this.hide_button.classList.add('is-open')
        this.context_menu.style.display = 'block'
        // Position menu next to the button (right side)
        const btn = this.hide_button.getBoundingClientRect()
        const left = btn.right + window.scrollX + 8
        const top = btn.top + window.scrollY
        this.context_menu.style.left = `${left}px`
        this.context_menu.style.top = `${top}px`
        // Bind item clicks
        this.context_menu.querySelectorAll('.aidad-context-menu__item').forEach((el) => {
            el.addEventListener('click', (evt) => {
                evt.preventDefault()
                evt.stopPropagation()
                const seconds = parseInt(el.getAttribute('data-seconds') || '0', 10)
                this.save_with_expiry(seconds)
            }, true)
        })
        // Click outside to close
        const on_doc_click = (evt) => {
            if (!this.context_menu.contains(evt.target) && evt.target !== this.hide_button) {
                this.close_context_menu()
                document.removeEventListener('click', on_doc_click, true)
            }
        }
        setTimeout(() => document.addEventListener('click', on_doc_click, true), 0)
    }

    close_context_menu() {
        if (!this.context_menu || !this.hide_button) return
        this.hide_button.classList.remove('is-open')
        this.context_menu.style.display = 'none'
    }

    is_ui_event(e) {
        const t = e.target
        if (!(t instanceof Element)) return false
        return (
            t.classList.contains('aidad-hide-button') ||
            t.closest('.aidad-context-menu') !== null ||
            t.classList.contains('aidad-overlay')
        )
    }

    async save_with_expiry(seconds) {
        const el = this.state.selected_target || this.state.hover_target
        if (!el) return
        const candidates = this.get_xpath_candidates(el)
        const best = this.pick_best_xpath(candidates)
        if (!best) {
            alert(this.config.i18n?.no_match || __('XPath does not match elements on this page', 'ads-destroyer'))
            return
        }
        const expires_at = seconds === 0 ? 0 : Math.floor(Date.now() / 1000) + seconds
        try {
            const res = await this.api.create_rule({xpath: best.xpath, label: '', active: true, expires_at})
            try {
                best.element?.remove()
            } catch (_) {
            }
            this.close_context_menu()
            // keep selection mode active across reload to see the effect
            try {
                sessionStorage.setItem('AIDAD_SELECTION_ACTIVE', '1')
            } catch (_) {
            }
            window.location.reload()
            return res
        } catch (err) {
            console.error(err)
            alert(this.config.i18n?.save_failed || __('Failed to save the rule', 'ads-destroyer'))
        }
    }

    // --- Hint box ---

    create_hint_box() {
        if (this.hint_box) return
        const box = document.createElement('div')
        box.className = 'aidad-hint'
        box.setAttribute('data-aidad-ui', '1')
        const title = document.createElement('div')
        title.className = 'aidad-hint__title'
        title.textContent = this.config.i18n?.hint_title || __('Selection mode is ON', 'ads-destroyer')
        const text = document.createElement('div')
        text.className = 'aidad-hint__text'
        text.textContent = this.config.i18n?.hint_text || __('Hover a block to preview, then click it and press “Hide block”. Press Esc to exit. If you hid something important — press “Reset all” below.', 'ads-destroyer')
        const actions = document.createElement('div')
        actions.className = 'aidad-hint__actions'
        const exitBtn = document.createElement('button')
        exitBtn.type = 'button'
        exitBtn.className = 'aidad-hint__btn'
        exitBtn.textContent = __('Exit mode', 'ads-destroyer')
        exitBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle(false)
        }, true)
        const resetBtn = document.createElement('button')
        resetBtn.type = 'button'
        resetBtn.className = 'aidad-hint__btn'
        resetBtn.textContent = __('Reset all', 'ads-destroyer')
        resetBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (!window.confirm(__('Reset all rules?', 'ads-destroyer'))) return
            try {
                await this.api.request('/rules/reset', {method: 'POST', headers: {'Content-Type': 'application/json'}})
                window.location.reload()
            } catch (err) {
                console.error(err)
                alert(__('Failed to reset rules', 'ads-destroyer'))
            }
        }, true)
        actions.appendChild(exitBtn)
        actions.appendChild(resetBtn)
        box.appendChild(title)
        box.appendChild(text)
        box.appendChild(actions)
        document.body.appendChild(box)
        this.hint_box = box
        // Draggable by title
        let drag = {active: false, dx: 0, dy: 0}
        title.addEventListener('mousedown', (e) => {
            drag.active = true
            const r = box.getBoundingClientRect()
            drag.dx = e.clientX - r.left
            drag.dy = e.clientY - r.top
            e.preventDefault()
        })
        const move = (e) => {
            if (!drag.active) return
            const x = e.clientX - drag.dx + window.scrollX
            const y = e.clientY - drag.dy + window.scrollY
            box.style.left = x + 'px'
            box.style.top = y + 'px'
            box.style.bottom = 'auto'
            box.style.right = 'auto'
        }
        const up = () => {
            drag.active = false
        }
        document.addEventListener('mousemove', move)
        document.addEventListener('mouseup', up)
    }

    // --- Helpers for XPath candidates and debug ---

    get_xpath_candidates(el) {
        const candidates = []
        // Option 1: the element itself
        try {
            const own = Xpath_Builder.build_from_element(el)
            if (own) candidates.push({label: 'element', xpath: own, element: el})
        } catch (_) {
        }
        // Option 2..N: parent elements up to body/html
        let node = el?.parentElement
        while (node && node instanceof Element && node !== document.documentElement && node !== document.body) {
            try {
                const xp = Xpath_Builder.build_from_element(node)
                if (xp) candidates.push({label: 'ancestor', xpath: xp, element: node})
            } catch (_) {
            }
            node = node.parentElement
        }
        // Option at body level (for large containers)
        if (document.body) {
            try {
                const bodyXp = Xpath_Builder.build_from_element(document.body)
                if (bodyXp) candidates.push({label: 'body', xpath: bodyXp, element: document.body})
            } catch (_) {
            }
        }
        return candidates
    }

    pick_best_xpath(candidates) {
        // Test and pick the most specific XPath with non-zero matches
        // eslint-disable-next-line no-console
        console.groupCollapsed('[Aidad] Testing XPath candidates')
        let best = null
        for (const c of candidates) {
            const t = Xpath_Tester.test(c.xpath)
            // eslint-disable-next-line no-console
            console.log(`${c.label}:`, c.xpath, '=>', t)
            if (t.ok && t.count > 0) {
                if (!best || t.count < best.count) {
                    best = {...c, count: t.count}
                }
            }
        }
        // eslint-disable-next-line no-console
        console.groupEnd()
        return best
    }

    async prompt_and_save() {
        // Prompt user for duration: 0 (forever), 1h, 24h, 7d
        const choices = [
            {label: __('Forever', 'ads-destroyer'), seconds: 0},
            {label: __('1 hour', 'ads-destroyer'), seconds: 3600},
            {label: __('24 hours', 'ads-destroyer'), seconds: 86400},
            {label: __('7 days', 'ads-destroyer'), seconds: 604800},
        ]
        let sel = 0
        try {
            const str = window.prompt(__('Hide duration: 0=Forever, 1=1h, 24=24h, 168=7d. Enter number of hours:', 'ads-destroyer'), '0')
            if (str === null) return
            const hours = parseInt(str, 10)
            if (!isNaN(hours) && hours > 0) {
                sel = hours * 3600
            } else {
                sel = 0
            }
        } catch (_) {
            sel = 0
        }

        const el = this.state.selected_target || this.state.hover_target
        if (!el) return
        const candidates = this.get_xpath_candidates(el)
        const best = this.pick_best_xpath(candidates)
        if (!best) {
            alert(this.config.i18n?.no_match || __('XPath does not find elements on this page', 'ads-destroyer'))
            return
        }
        const expires_at = sel === 0 ? 0 : Math.floor(Date.now() / 1000) + sel

        try {
            const res = await this.api.create_rule({xpath: best.xpath, label: '', active: true, expires_at})
            // Remove element immediately from the DOM for instant feedback
            try {
                best.element?.remove()
            } catch (_) {
            }
            this.toggle(false)
            return res
        } catch (err) {
            console.error(err)
            alert(this.config.i18n?.save_failed || __('Error saving rule', 'ads-destroyer'))
        }
    }

    async save_current_rule() {
        const el = this.state.selected_target || this.state.hover_target
        if (!el) return
        // Collect candidates and pick the best
        const candidates = this.get_xpath_candidates(el)
        const best = this.pick_best_xpath(candidates)

        if (!best) {
            alert(this.config.i18n?.no_match || __('XPath does not match elements on this page', 'ads-destroyer'))
            return
        }

        // Final debug output
        // eslint-disable-next-line no-console
        console.group('[Aidad] Final XPath selection')
        // eslint-disable-next-line no-console
        console.log('Selected element:', best.element)
        // eslint-disable-next-line no-console
        console.log('Selected XPath:', best.xpath, 'Matches:', best.count)
        // eslint-disable-next-line no-console
        console.log('All candidates (for reference):', candidates.map(c => c.xpath))
        // eslint-disable-next-line no-console
        console.groupEnd()

        try {
            const res = await this.api.create_rule({xpath: best.xpath, label: '', active: true})
            // Visual feedback: exit selection mode after save
            this.toggle(false)
            return res
        } catch (err) {
            console.error(err)
            alert(this.config.i18n?.save_failed || __('Failed to save the rule', 'ads-destroyer'))
        }
    }
}
