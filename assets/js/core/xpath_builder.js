// core/xpath_builder.js
// Builds a stable XPath for a given DOM element using Chrome DevTools algorithm
export class Xpath_Builder {
  /**
   * Build XPath string for an element using Chrome DevTools algorithm
   * @param {Element} el
   * @returns {string}
   */
  static build_from_element(el) {
    if (!(el instanceof Element)) return ''
    
    // Try multiple strategies and pick the best one
    const strategies = [
      () => this.build_chrome_style(el),
      () => this.build_by_id(el),
      () => this.build_by_unique_text(el),
      () => this.build_by_data_attributes(el)
    ]

    let bestXPath = ''
    let bestScore = 0

    for (const strategy of strategies) {
      try {
        const xpath = strategy()
        if (xpath) {
          // Clean and validate XPath
          const cleanedXpath = this.clean_xpath(xpath)
          if (this.is_valid_xpath(cleanedXpath)) {
            const score = this.calculate_specificity_score(cleanedXpath, el)
            if (score > bestScore) {
              bestXPath = cleanedXpath
              bestScore = score
            }
          }
        }
      } catch (error) {
        console.warn('XPath strategy failed:', error)
      }
    }

    // Fallback to Chrome style if no valid XPath found
    if (!bestXPath) {
      const fallbackXpath = this.build_chrome_style(el)
      bestXPath = this.clean_xpath(fallbackXpath)
    }

    return bestXPath
  }

  /**
   * Build XPath using Chrome DevTools algorithm (position-based)
   * @param {Element} el
   * @returns {string}
   */
  static build_chrome_style(el) {
    const segments = []
    let node = el

    while (node && node.nodeType === 1 && node !== document) {
      const tagName = node.tagName.toLowerCase()
      
      // Check if this element has a unique ID
      const id = node.getAttribute('id')
      if (id && this.is_stable_id(id)) {
        // If we found an ID, return the full path from root
        const pathFromId = segments.length > 0 ? '/' + segments.join('/') : ''
        return `//*[@id="${this.escape_attr(id)}"]${pathFromId}`
      }

      // Calculate position among siblings of the same tag
      const siblings = Array.from(node.parentElement?.children || [])
        .filter(sibling => sibling.tagName.toLowerCase() === tagName)
      
      let position = 1
      if (siblings.length > 1) {
        position = siblings.indexOf(node) + 1
      }

      // Build segment with position
      let segment = tagName
      if (siblings.length > 1) {
        segment += `[${position}]`
      }

      segments.unshift(segment)
      node = node.parentElement
    }

    return '//' + segments.join('/')
  }

  /**
   * Build XPath by ID (most specific)
   * @param {Element} el
   * @returns {string}
   */
  static build_by_id(el) {
    const id = el.getAttribute('id')
    if (id && this.is_stable_id(id)) {
      return `//*[@id="${this.escape_attr(id)}"]`
    }
    return null
  }

  /**
   * Build XPath by unique text content
   * @param {Element} el
   * @returns {string}
   */
  static build_by_unique_text(el) {
    const text = el.textContent?.trim()
    if (!text || text.length < 3 || text.length > 100) return null

    // Check if text is unique
    const elementsWithSameText = document.evaluate(
      `//${el.tagName.toLowerCase()}[normalize-space(text())="${this.escape_attr(text)}"]`,
      document,
      null,
      XPathResult.ORDERED_NODE_SNAPSHOT_TYPE,
      null
    )

    if (elementsWithSameText.snapshotLength === 1) {
      return `//${el.tagName.toLowerCase()}[normalize-space(text())="${this.escape_attr(text)}"]`
    }
    return null
  }

  /**
   * Build XPath by data attributes
   * @param {Element} el
   * @returns {string}
   */
  static build_by_data_attributes(el) {
    const dataAttrs = ['data-testid', 'data-test', 'data-qa', 'data-id', 'data-name']
    
    for (const attr of dataAttrs) {
      const value = el.getAttribute(attr)
      if (value) {
        return `//${el.tagName.toLowerCase()}[@${attr}="${this.escape_attr(value)}"]`
      }
    }
    return null
  }

  /**
   * Calculate specificity score for XPath
   * @param {string} xpath
   * @param {Element} element
   * @returns {number}
   */
  static calculate_specificity_score(xpath, element) {
    let score = 0

    // Prefer shorter XPath
    score += Math.max(0, 200 - xpath.length)

    // Prefer ID selectors
    if (xpath.includes('@id=')) {
      score += 1000
    }

    // Prefer data attributes
    if (xpath.includes('@data-')) {
      score += 500
    }

    // Prefer text-based selectors
    if (xpath.includes('normalize-space(text())')) {
      score += 300
    }

    // Prefer Chrome-style position selectors
    if (xpath.match(/\/\w+\[\d+\]/)) {
      score += 100
    }

    // Test if XPath actually matches the element
    try {
      const result = document.evaluate(xpath, document, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null)
      if (result.snapshotLength === 1 && result.snapshotItem(0) === element) {
        score += 500 // Perfect match
      } else if (result.snapshotLength > 1) {
        score -= 200 // Too many matches
      }
    } catch (error) {
      score -= 1000 // Invalid XPath
    }

    return score
  }

  static is_stable_id(id) {
    if (!id) return false
    
    // Allow common stable ID patterns
    const stablePatterns = [
      /^wpforms-/,  // WordPress forms
      /^wp-/,       // WordPress core
      /^canvas/,    // Canvas elements
      /^chart/,     // Chart elements
      /^widget/,    // Widget elements
      /^dash-/,     // Dashboard elements
      /^admin-/,    // Admin elements
    ]
    
    // Check if it matches any stable pattern
    if (stablePatterns.some(pattern => pattern.test(id))) {
      return true
    }
    
    // Reject obviously dynamic IDs
    const dynamicPatterns = [
      /^\d+$/,                    // Pure numbers
      /[A-Z]{4,}/,                // Multiple uppercase letters
      /_+/,                       // Multiple underscores
      /^(react|ember|vue|svelte)/, // Framework prefixes
      /^post-\d+/,                // WordPress post IDs
      /^[a-f0-9]{8,}$/,           // Hex IDs
      /^[a-zA-Z0-9]{20,}$/,       // Very long alphanumeric
    ]
    
    return !dynamicPatterns.some(pattern => pattern.test(id))
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

  /**
   * Validate XPath expression
   * @param {string} xpath
   * @returns {boolean}
   */
  static is_valid_xpath(xpath) {
    try {
      document.evaluate(xpath, document, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null)
      return true
    } catch (error) {
      console.warn('Invalid XPath:', xpath, error.message)
      return false
    }
  }

  /**
   * Clean XPath expression (remove double slashes, etc.)
   * @param {string} xpath
   * @returns {string}
   */
  static clean_xpath(xpath) {
    if (!xpath) return ''
    
    // Remove double slashes at the beginning
    xpath = xpath.replace(/^\/\/+/, '//')
    
    // Ensure it starts with //
    if (!xpath.startsWith('//')) {
      xpath = '//' + xpath
    }
    
    return xpath
  }
}

