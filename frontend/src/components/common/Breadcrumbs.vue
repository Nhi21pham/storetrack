<template>
  <nav v-if="crumbs.length > 0" class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
      <li v-for="(crumb, i) in crumbs" :key="i" class="crumb">
        <router-link
          v-if="crumb.to && !isLast(i)"
          :to="crumb.to"
          class="crumb-link"
        >{{ crumb.label }}</router-link>
        <span
          v-else
          :class="['crumb-text', { current: isLast(i) }]"
        >{{ crumb.label }}</span>
        <Icon
          v-if="!isLast(i)"
          name="chevron-right"
          :size="12"
          color="currentColor"
          class="separator"
        />
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Icon from '@/components/common/Icon.vue'

const route = useRoute()

const crumbs = computed(() => route.meta?.breadcrumb || [])
const isLast = (i) => i === crumbs.value.length - 1
</script>

<style scoped>
.breadcrumbs { margin-bottom: 16px; }
.breadcrumbs ol { list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: 2px; margin: 0; padding: 0; font-size: 13px; }
.crumb { display: inline-flex; align-items: center; gap: 2px; }
.crumb-link { color: #2563eb; text-decoration: none; padding: 4px 6px; border-radius: 4px; cursor: pointer; transition: background 0.12s, color 0.12s; }
.crumb-link:hover { background: #eff6ff; color: #1d4ed8; text-decoration: underline; }
.crumb-text { color: #9ca3af; padding: 4px 6px; }
.crumb-text.current { color: #111; font-weight: 600; }
.separator { color: #d1d5db; flex-shrink: 0; }
</style>
