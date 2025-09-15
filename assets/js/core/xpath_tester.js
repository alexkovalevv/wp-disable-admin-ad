// core/xpath_tester.js
// Lightweight tester around document.evaluate
export class Xpath_Tester {
  static test(xpath) {
    if (!xpath || typeof xpath !== 'string') {
      return { ok: false, error: 'Invalid XPath: empty or not a string' }
    }

    // Clean XPath before testing
    const cleanedXpath = xpath.replace(/^\/\/+/, '//')
    
    try {
      const res = document.evaluate(cleanedXpath, document, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null)
      return { ok: true, count: res.snapshotLength, xpath: cleanedXpath }
    } catch (e) {
      console.warn('XPath evaluation failed:', cleanedXpath, e.message)
      return { 
        ok: false, 
        error: e.message || String(e),
        xpath: cleanedXpath
      }
    }
  }
}

