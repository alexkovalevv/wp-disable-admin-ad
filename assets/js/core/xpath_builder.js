// core/xpath_builder.js
// Builds a stable XPath for a given DOM element. Avoids dynamic classes/ids when possible.
export class Xpath_Builder {
  /**
   * Build XPath string for an element by walking up the DOM.
   * @param {Element} el
   * @returns {string}
   */
  static build_from_element(el) {
    if (!(el instanceof Element)) return ''
    const segments = []
    let node = el
    while (node && node.nodeType === 1 && node !== document) {
      const node_name = node.localName
      if (!node_name) break

      // Prefer id if stable
      const id = node.getAttribute('id')
      if (id && Xpath_Builder.is_stable_id(id)) {
        segments.unshift(`//*[@id="${Xpath_Builder.escape_attr(id)}"]`)
        break
      }

      // Build predicate using classes or data-*; avoid dynamic classes
      let predicate = ''
      const data_test = node.getAttribute('data-testid') || node.getAttribute('data-test') || node.getAttribute('data-qa')
      if (data_test) {
        predicate = `[@data-testid="${Xpath_Builder.escape_attr(data_test)}" or @data-test="${Xpath_Builder.escape_attr(data_test)}" or @data-qa="${Xpath_Builder.escape_attr(data_test)}"]`
      } else {
        const cls = (node.getAttribute('class') || '')
          .split(/\s+/)
          .filter(Boolean)
          .filter(c => !Xpath_Builder.is_dynamic_class(c))
        if (cls.length) {
          const parts = cls.map(c => `contains(concat(' ', normalize-space(@class), ' '), ' ${Xpath_Builder.escape_attr(c)} ')`)
          predicate = `[${parts.join(' and ')}]`
        }
      }

      // Only add position index if we have no distinguishing predicate
      let segment = `${node_name}${predicate}`
      if (!predicate) {
        let index = 1
        let sibling = node.previousElementSibling
        while (sibling) {
          if (sibling.localName === node.localName) index++
          sibling = sibling.previousElementSibling
        }
        segment += `[${index}]`
      }
      segments.unshift(segment)
      node = node.parentElement
    }
    // Join segments; avoid leading '////' when first segment already starts with '//*'
    const path = segments.join('/')
    if (path.startsWith('//*')) {
      return path
    }
    return '//' + path
  }

  static is_stable_id(id) {
    return id && !/^\d|[A-Z]{4,}|_+|react|ember|vue|svelte|^post-\d+/.test(id)
  }

  static is_dynamic_class(cls) {
    // Treat common dynamic/generated class patterns as unstable
    if (!cls) return true
    const c = String(cls)
    if (/^(sc|css)-[a-zA-Z0-9]+$/.test(c)) return true // styled-components/css-in-js
    if (/^[a-zA-Z]{5,}$/.test(c) && /[A-Z]/.test(c) && /[a-z]/.test(c)) return true // mixed-case tokens like jPPOou
    if (/(\d{3,}|^wp-\d+|active|selected|focus|hover|tmp|nonce|token|hash)/i.test(c)) return true
    return false
  }

  static escape_attr(val) {
    return String(val).replace(/"/g, '\\"')
  }
}

