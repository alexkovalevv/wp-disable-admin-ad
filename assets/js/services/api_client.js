// ES6 modules for selection overlay, no camelCase identifiers.

// services/api_client.js
export class Api_Client {
  /**
   * @param {string} base_url REST base url
   * @param {string} nonce WP nonce for REST
   */
  constructor(base_url, nonce) {
    this.base_url = base_url
    this.nonce = nonce
  }

  /**
   * @param {string} path
   * @param {object} options
   */
  async request(path, options = {}) {
    const headers = options.headers || {}
    headers['X-WP-Nonce'] = this.nonce
    
    // Debug logging
    console.log('API Request:', {
      url: `${this.base_url}${path}`,
      method: options.method || 'GET',
      headers: headers,
      nonce: this.nonce ? 'present' : 'missing'
    })
    
    const res = await fetch(`${this.base_url}${path}`, { ...options, headers })
    if (!res.ok) {
      const ct = res.headers.get('content-type') || ''
      let errorData
      if (ct.includes('application/json')) {
        errorData = await res.json()
      } else {
        const text = await res.text()
        errorData = { message: text }
      }
      
      // Special handling for nonce errors
      if (res.status === 403 && errorData.code === 'invalid_nonce') {
        console.error('Nonce validation failed. Current nonce:', this.nonce)
        // Try to refresh nonce from the page
        const newNonce = this.getNonceFromPage()
        if (newNonce && newNonce !== this.nonce) {
          console.log('Retrying with refreshed nonce')
          this.nonce = newNonce
          headers['X-WP-Nonce'] = this.nonce
          const retryRes = await fetch(`${this.base_url}${path}`, { ...options, headers })
          if (retryRes.ok) {
            const retryCt = retryRes.headers.get('content-type') || ''
            if (retryCt.includes('application/json')) {
              return await retryRes.json()
            }
            return await retryRes.text()
          }
        }
      }
      
      const error = new Error(`request_failed: ${res.status} ${JSON.stringify(errorData)}`)
      error.status = res.status
      error.data = errorData
      throw error
    }
    const ct = res.headers.get('content-type') || ''
    if (ct.includes('application/json')) {
      return await res.json()
    }
    return await res.text()
  }

  /**
   * Try to get nonce from the page
   * @returns {string|null}
   */
  getNonceFromPage() {
    // Try to get nonce from meta tag
    const nonceMeta = document.querySelector('meta[name="wp_rest_nonce"]')
    if (nonceMeta) {
      return nonceMeta.getAttribute('content')
    }
    
    // Try to get nonce from window object
    if (window.ADSD_CONFIG && window.ADSD_CONFIG.nonce) {
      return window.ADSD_CONFIG.nonce
    }
    
    // Try to get nonce from wpApiSettings
    if (window.wpApiSettings && window.wpApiSettings.nonce) {
      return window.wpApiSettings.nonce
    }
    
    return null
  }

  /**
   * Update nonce
   * @param {string} newNonce
   */
  updateNonce(newNonce) {
    this.nonce = newNonce
  }

  /**
   * Get current nonce
   * @returns {string}
   */
  getNonce() {
    return this.nonce
  }

  async list_rules() {
    return await this.request('/rules', { method: 'GET' })
  }

  async create_rule(rule) {
    return await this.request('/rules', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(rule),
    })
  }

  async update_rule(id, rule) {
    return await this.request(`/rules/${id}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(rule),
    })
  }

  async delete_rule(id) {
    return await this.request(`/rules/${id}`, { method: 'DELETE' })
  }

  async test_xpath(xpath) {
    return await this.request('/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ xpath }),
    })
  }

  async activate_existing_rule(rule_id) {
    return await this.request('/rules/activate-existing', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ rule_id }),
    })
  }

  /**
   * Get fresh nonce from server
   * @returns {Promise<string>}
   */
  async refreshNonce() {
    try {
      // Try to get nonce from WordPress REST API
      const response = await fetch(`${this.base_url}/nonce`, {
        method: 'GET',
        headers: {
          'X-WP-Nonce': this.nonce
        }
      })
      
      if (response.ok) {
        const data = await response.json()
        if (data.nonce) {
          this.updateNonce(data.nonce)
          return data.nonce
        }
      }
    } catch (error) {
      console.warn('Failed to refresh nonce from server:', error)
    }
    
    // Fallback to page nonce
    const pageNonce = this.getNonceFromPage()
    if (pageNonce) {
      this.updateNonce(pageNonce)
      return pageNonce
    }
    
    throw new Error('Unable to refresh nonce')
  }
}

