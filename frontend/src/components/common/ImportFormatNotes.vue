<template>
  <details v-if="instructions.length" class="format-notes">
    <summary>
      <svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      {{ $t('shared.formattingNotes', { count: instructions.length }) }}
    </summary>
    <ul>
      <li v-for="(note, i) in instructions" :key="i">
        <span>{{ noteText(note) }}</span>
        <code v-if="noteExample(note)" class="note-example">{{ noteExample(note) }}</code>
      </li>
    </ul>
  </details>
</template>

<script setup>
defineProps({
  instructions: { type: Array, default: () => [] },
})

// An instruction is either a plain string or { text, example } — the optional
// example renders as a monospace snippet under the note (e.g. "input → result").
const noteText = (note) => (typeof note === 'string' ? note : note.text)
const noteExample = (note) => (typeof note === 'string' ? '' : note.example || '')
</script>

<style scoped>
.format-notes { margin: 10px 0 0; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; }
.format-notes > summary { display: flex; align-items: center; gap: 6px; padding: 9px 12px; font-size: 12.5px; font-weight: 600; color: #374151; cursor: pointer; list-style: none; user-select: none; }
.format-notes > summary::-webkit-details-marker { display: none; }
.format-notes > summary:hover { color: #111; }
.format-notes > summary .chev { color: #9ca3af; transition: transform 0.15s; flex-shrink: 0; }
.format-notes[open] > summary .chev { transform: rotate(90deg); }
.format-notes[open] > summary { border-bottom: 1px solid #e5e7eb; }
.format-notes ul { margin: 0; padding: 10px 12px; list-style: none; display: flex; flex-direction: column; gap: 5px; }
.format-notes li { font-size: 12.5px; color: #4b5563; line-height: 1.45; padding-left: 14px; position: relative; }
.format-notes li::before { content: '•'; position: absolute; left: 2px; color: #9ca3af; }
.format-notes .note-example { display: inline-block; margin-top: 5px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11.5px; font-weight: 600; color: #3730a3; background: #eef2ff; border: 1px solid #c7d2fe; border-left: 3px solid #6366f1; border-radius: 5px; padding: 4px 9px; white-space: pre-wrap; }
.format-notes .note-example::before { content: 'e.g.'; margin-right: 6px; font-weight: 700; font-style: normal; color: #6366f1; opacity: 0.8; }
</style>
