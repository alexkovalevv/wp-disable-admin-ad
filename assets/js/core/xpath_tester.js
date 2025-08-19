// core/xpath_tester.js
// Lightweight tester around document.evaluate
export class Xpath_Tester {
  static test(xpath) {
    try {
      const res = document.evaluate(xpath, document, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null)
      return { ok: true, count: res.snapshotLength }
    } catch (e) {
      return { ok: false, error: String(e) }
    }
  }
}

