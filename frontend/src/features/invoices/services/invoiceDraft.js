// One-shot hand-off of a prefilled purchase-invoice draft from the Scan / review
// page to the existing create page. Kept at module scope and read once, so the
// create page hydrates from it on mount and it never leaks into a later visit.
let draft = null

export const setInvoiceDraft = (value) => {
  draft = value
}

export const takeInvoiceDraft = () => {
  const value = draft
  draft = null
  return value
}
