<template>
  <!-- Couldn't read the file at all (upload step): offer the AI scan. -->
  <div v-if="needsAi" class="src-bar needs">
    <p class="src-msg">{{ $t('invoices.cannotReadFile') }}</p>
    <button class="btn-ai" :disabled="busy" @click="$emit('scan-ai')">
      <span v-if="busy" class="spinner"></span>
      {{ busy ? $t('invoices.scanningAI') : $t('invoices.scanWithAI') }}
    </button>
  </div>

  <!-- Read for free, but some fields came back thin: suggest AI for a better result. -->
  <div v-else-if="suggestAi" class="src-bar warn">
    <span class="src-msg">{{ $t('invoices.someFieldsThin') }}</span>
    <button class="btn-ai" :disabled="busy" @click="$emit('scan-ai')">
      <span v-if="busy" class="spinner"></span>
      {{ busy ? $t('invoices.scanning') : $t('invoices.scanWithAI') }}
    </button>
  </div>

  <!-- Clean read: show how it was read, and still offer an AI re-read in case the
       free parser got the layout wrong and the user wants a second opinion. -->
  <div v-else-if="source === 'template'" class="src-bar ok">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
    <span class="src-msg">{{ $t('invoices.readFree') }}</span>
    <button v-if="allowAi" class="btn-ai-glow" :disabled="busy" @click="$emit('scan-ai')">
      <span v-if="busy" class="spinner"></span>
      <svg v-else class="sparkle" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/></svg>
      {{ busy ? $t('invoices.scanning') : $t('invoices.rescanAI') }}
    </button>
  </div>
  <div v-else-if="source === 'gemini'" class="src-bar ai">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/></svg>
    <span>{{ $t('invoices.readWithAI') }}</span>
  </div>
</template>

<script setup>
// Template-vs-AI extraction status for the invoice scan/import review. Shared by
// the purchase scan page and (later) the sale scan page — each owns its own
// extraction calls and just feeds the state and handles `scan-ai`.
defineProps({
  // 'template' (free, deterministic) | 'gemini' (AI) | '' (not read yet)
  source: { type: String, default: '' },
  // Read for free but the result is thin — nudge toward an AI re-read.
  suggestAi: { type: Boolean, default: false },
  // Offer an AI re-read even on a clean free read (the parsed data may be wrong).
  allowAi: { type: Boolean, default: false },
  // The free reader couldn't handle this file at all.
  needsAi: { type: Boolean, default: false },
  // An extraction call is in flight (disables the button, shows a spinner).
  busy: { type: Boolean, default: false },
})

defineEmits(['scan-ai'])
</script>

<style scoped>
.src-bar { display: flex; align-items: center; gap: 10px; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px; }
.src-bar svg { flex-shrink: 0; }
.src-msg { margin: 0; flex: 1; }

.src-bar.needs { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; }
.src-bar.warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.src-bar.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }
.src-bar.ai { background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca; }

.btn-ai { display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; padding: 7px 14px; background: #111; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-ai:hover:not(:disabled) { background: #333; }
.btn-ai:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-ai-glow { display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; padding: 7px 15px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #a855f7 100%); color: #fff; border: none; border-radius: 9px; font-size: 12.5px; font-weight: 700; letter-spacing: 0.01em; cursor: pointer; box-shadow: 0 2px 10px rgba(124,58,237,0.4); transition: transform 0.15s, box-shadow 0.15s, filter 0.15s; animation: ai-pulse 2.4s ease-in-out infinite; }
.btn-ai-glow:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(124,58,237,0.5); filter: brightness(1.06); animation: none; }
.btn-ai-glow:active:not(:disabled) { transform: translateY(0); }
.btn-ai-glow:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; animation: none; }
.btn-ai-glow .sparkle { flex-shrink: 0; animation: sparkle-twinkle 2.4s ease-in-out infinite; }

@keyframes ai-pulse { 0%, 100% { box-shadow: 0 2px 10px rgba(124,58,237,0.4); } 50% { box-shadow: 0 2px 18px rgba(124,58,237,0.65); } }
@keyframes sparkle-twinkle { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(1.18) rotate(8deg); } }

.spinner { width: 13px; height: 13px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
