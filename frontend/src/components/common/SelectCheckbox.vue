<template>
  <input
    type="checkbox"
    class="row-check"
    :checked="checked"
    :indeterminate.prop="indeterminate"
    :title="title"
    @change="$emit('change')"
  />
</template>

<script setup>
defineProps({
  checked:       { type: Boolean, default: false },
  indeterminate: { type: Boolean, default: false },
  title:         { type: String,  default: '' },
})

defineEmits(['change'])
</script>

<style scoped>
.row-check { width: 15px; height: 15px; cursor: pointer; accent-color: #111; }
</style>
