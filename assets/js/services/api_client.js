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
    const res = await fetch(`${this.base_url}${path}`, { ...options, headers })
    if (!res.ok) {
      const text = await res.text()
      throw new Error(`request_failed: ${res.status} ${text}`)
    }
    const ct = res.headers.get('content-type') || ''
    if (ct.includes('application/json')) {
      return await res.json()
    }
    return await res.text()
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
}

