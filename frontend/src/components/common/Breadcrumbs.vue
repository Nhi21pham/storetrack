<template>
  <nav v-if="crumbs.length > 0" class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
      <li v-for="(crumb, i) in crumbs" :key="i" class="crumb">
        <router-link
          v-if="crumb.to && !isLast(i)"
          :to="crumb.to"
          class="crumb-pill crumb-link"
        >
          <Icon v-if="crumb.icon" :name="crumb.icon" :size="13" color="currentColor" />
          <span>{{ crumb.labelKey ? $t(crumb.labelKey) : crumb.label }}</span>
        </router-link>
        <span
          v-else
          :class="['crumb-pill', 'crumb-text', { current: isLast(i) }]"
        >
          <Icon v-if="crumb.icon" :name="crumb.icon" :size="13" color="currentColor" />
          <span>{{ crumb.labelKey ? $t(crumb.labelKey) : crumb.label }}</span>
        </span>
        <Icon
          v-if="!isLast(i)"
          name="chevron-right"
          :size="13"
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
.breadcrumbs { margin-bottom: 20px; }
.breadcrumbs ol { list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: 2px; margin: 0; padding: 0; font-size: 13px; }
.crumb { display: inline-flex; align-items: center; gap: 2px; }

.crumb-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; line-height: 1; font-weight: 500; transition: all 0.15s ease; }

.crumb-link { color: #4f46e5; text-decoration: none; background: #eef2ff; border: 1px solid transparent; cursor: pointer; }
.crumb-link:hover { background: #e0e7ff; color: #3730a3; transform: translateY(-1px); box-shadow: 0 2px 6px rgba(79,70,229,0.15); }
.crumb-link:active { transform: translateY(0); box-shadow: none; }

.crumb-text { color: #6b7280; background: #f3f4f6; }
.crumb-text.current { color: #3730a3; background: #e0e7ff; font-weight: 600; }

.separator { color: #cbd5e1; flex-shrink: 0; margin: 0 2px; }
</style>
