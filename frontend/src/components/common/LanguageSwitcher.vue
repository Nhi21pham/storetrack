<template>
  <!-- Compact globe + dropdown, used floating in a corner (e.g. auth pages). -->
  <div v-if="floating" ref="root" class="lang-icon-wrap">
    <button type="button" class="lang-icon-btn" :aria-label="$t('nav.language')" @click.stop="open = !open">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <line x1="2" y1="12" x2="22" y2="12" />
        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
      </svg>
    </button>
    <div v-if="open" class="lang-menu">
      <button type="button" class="lang-menu-item" :class="{ active: locale === 'vi' }" @click="choose('vi')">{{ $t('nav.languageVietnamese') }}</button>
      <button type="button" class="lang-menu-item" :class="{ active: locale === 'en' }" @click="choose('en')">{{ $t('nav.languageEnglish') }}</button>
    </div>
  </div>

  <!-- Inline two-button toggle, used inside the navbar avatar dropdown. -->
  <div v-else class="lang-switcher">
    <button class="lang-btn" :class="{ active: locale === 'vi' }" @click.stop="setLocale('vi')">{{ $t('nav.languageVietnamese') }}</button>
    <button class="lang-btn" :class="{ active: locale === 'en' }" @click.stop="setLocale('en')">{{ $t('nav.languageEnglish') }}</button>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { setLocale } from '@/i18n'

defineProps({
  floating: {
    type: Boolean,
    default: false,
  },
})

const { locale } = useI18n({ useScope: 'global' })

const open = ref(false)
const root = ref(null)

const choose = (code) => {
  setLocale(code)
  open.value = false
}

const onClickOutside = (e) => {
  if (root.value && !root.value.contains(e.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onUnmounted(() => document.removeEventListener('click', onClickOutside))
</script>

<style scoped>
/* Floating globe + dropdown */
.lang-icon-wrap { position: fixed; top: 20px; right: 24px; z-index: 50; }
.lang-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; border: 1px solid #e5e7eb; background: #fff; color: #374151; cursor: pointer; transition: background 0.15s, color 0.15s; }
.lang-icon-btn:hover { background: #f3f4f6; color: #111; }
.lang-menu { position: absolute; top: calc(100% + 8px); right: 0; min-width: 150px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); padding: 6px; display: flex; flex-direction: column; gap: 2px; }
.lang-menu-item { text-align: left; padding: 10px 12px; border: none; background: transparent; border-radius: 8px; font-size: 14px; color: #374151; cursor: pointer; transition: background 0.15s; }
.lang-menu-item:hover { background: #f3f4f6; }
.lang-menu-item.active { background: #111; color: #fff; font-weight: 600; }

/* Inline toggle (navbar dropdown) */
.lang-switcher { display: flex; gap: 8px; }
.lang-btn { flex: 1; padding: 7px 10px; font-size: 13px; color: #374151; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
.lang-btn:hover { background: #e5e7eb; }
.lang-btn.active { background: #111; border-color: #111; color: #fff; font-weight: 600; }
</style>
