// A reliable replacement for the native `title` tooltip: a single teleported
// bubble that appears instantly on hover and, by default, only when the target's
// text is actually clipped — so it never fires on text that already fits.
// Usage: v-tooltip="fullText" (falls back to the element's own text), or
// v-tooltip.always="fullText" to show regardless of truncation.

import type { Directive, DirectiveBinding } from 'vue'

type TooltipEl = HTMLElement & {
  __tt?: DirectiveBinding
  __ttShow?: () => void
  __ttHide?: () => void
}

let bubble: HTMLElement | null = null

const ensureBubble = (): HTMLElement => {
  if (bubble) return bubble
  bubble = document.createElement('div')
  bubble.className = 'app-tooltip'
  bubble.setAttribute('role', 'tooltip')
  document.body.appendChild(bubble)
  return bubble
}

const isTruncated = (el: HTMLElement): boolean =>
  el.scrollWidth > el.clientWidth + 1 || el.scrollHeight > el.clientHeight + 1

const textFor = (el: TooltipEl): string => {
  const explicit = el.__tt?.value
  if (explicit != null && explicit !== '') return String(explicit)
  return (el.textContent || '').trim()
}

const place = (el: HTMLElement): void => {
  if (!bubble) return
  const rect = el.getBoundingClientRect()
  const box = bubble.getBoundingClientRect()
  const gap = 8
  let top = rect.top - box.height - gap
  let below = false
  if (top < 4) {
    top = rect.bottom + gap
    below = true
  }
  let left = rect.left + rect.width / 2 - box.width / 2
  left = Math.max(8, Math.min(left, window.innerWidth - box.width - 8))
  bubble.style.top = `${Math.round(top)}px`
  bubble.style.left = `${Math.round(left)}px`
  bubble.classList.toggle('app-tooltip--below', below)
}

const show = (el: TooltipEl): void => {
  const binding = el.__tt
  if (!binding) return
  if (!binding.modifiers.always && !isTruncated(el)) return
  const text = textFor(el)
  if (!text) return
  const box = ensureBubble()
  box.textContent = text
  box.classList.add('app-tooltip--visible')
  place(el)
}

const hide = (): void => {
  if (bubble) bubble.classList.remove('app-tooltip--visible')
}

const tooltip: Directive = {
  mounted(el: TooltipEl, binding) {
    el.__tt = binding
    el.__ttShow = () => show(el)
    el.__ttHide = hide
    el.addEventListener('mouseenter', el.__ttShow)
    el.addEventListener('mouseleave', el.__ttHide)
    el.addEventListener('mousedown', el.__ttHide)
  },
  updated(el: TooltipEl, binding) {
    el.__tt = binding
  },
  beforeUnmount(el: TooltipEl) {
    if (el.__ttShow) el.removeEventListener('mouseenter', el.__ttShow)
    if (el.__ttHide) {
      el.removeEventListener('mouseleave', el.__ttHide)
      el.removeEventListener('mousedown', el.__ttHide)
    }
    hide()
  },
}

export default tooltip
