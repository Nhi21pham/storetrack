<template>
  <!-- Couldn't read the file at all (upload step): offer the AI scan. -->
  <div v-if="needsAi" class="src-bar needs">
    <p class="src-msg">We couldn't read this file automatically. Scan it with AI for a better read?</p>
    <button class="btn-ai" :disabled="busy" @click="$emit('scan-ai')">
      <span v-if="busy" class="spinner"></span>
      {{ busy ? 'Scanning with AI…' : 'Scan with AI' }}
    </button>
  </div>

  <!-- Read for free, but some fields came back thin: suggest AI for a better result. -->
  <div v-else-if="suggestAi" class="src-bar warn">
    <span class="src-msg">Some fields couldn't be read automatically — scan with AI for a better result?</span>
    <button class="btn-ai" :disabled="busy" @click="$emit('scan-ai')">
      <span v-if="busy" class="spinner"></span>
      {{ busy ? 'Scanning…' : 'Scan with AI' }}
    </button>
  </div>

  <!-- Clean read: show how it was read so the user knows whether AI was spent. -->
  <div v-else-if="source === 'template'" class="src-bar ok">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
    <span>Read for free — no AI used.</span>
  </div>
  <div v-else-if="source === 'gemini'" class="src-bar ai">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/></svg>
    <span>Read with AI.</span>
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

.spinner { width: 13px; height: 13px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
