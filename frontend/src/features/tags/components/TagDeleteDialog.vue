<template>
  <div class="modal-overlay" @click.self="$emit('cancel')">
    <div class="dialog">
      <h3 class="dialog-title">{{ title }}</h3>

      <p class="dialog-msg">{{ message }}</p>

      <div class="impact" :class="{ warn: count > 0 }">
        <template v-if="loadingCount">Checking how many products are affected…</template>
        <template v-else-if="count === 0">No products are using this — safe to delete.</template>
        <template v-else>
          Used by <strong>{{ count }}</strong> product{{ count === 1 ? '' : 's' }}.
          Deleting will detach it from {{ count === 1 ? 'it' : 'them' }}.
          <button class="link-btn" @click="showList = !showList">
            {{ showList ? 'Hide' : 'View' }} affected product{{ count === 1 ? '' : 's' }}
          </button>
        </template>
      </div>

      <div v-if="showList && count > 0" class="affected-list">
        <div v-for="p in affected" :key="p.id" class="affected-row">
          <span class="affected-code">{{ p.code }}</span>
          <span class="affected-name">{{ p.name }}</span>
          <span v-if="!p.is_active" class="affected-inactive">inactive</span>
        </div>
      </div>

      <div class="dialog-actions">
        <button class="btn-cancel" @click="$emit('cancel')" :disabled="deleting">Cancel</button>
        <button class="btn-danger" @click="$emit('confirm')" :disabled="loadingCount || deleting">
          <span v-if="deleting" class="spinner"></span>
          {{ confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { fetchProductsByTag } from '@/features/products/services/productService'

const props = defineProps({
  title:       { type: String, required: true },
  message:     { type: String, required: true },
  confirmText: { type: String, default: 'Delete' },
  storeId:     { type: [String, Number], required: true },
  tagId:       { type: [String, Number], required: true },
  tagValueId:  { type: [String, Number], default: null },
  deleting:    { type: Boolean, default: false },
})

defineEmits(['confirm', 'cancel'])

const affected = ref([])
const count = ref(0)
const loadingCount = ref(true)
const showList = ref(false)

onMounted(async () => {
  try {
    affected.value = await fetchProductsByTag({
      storeId: props.storeId,
      tagId: props.tagId,
      tagValueId: props.tagValueId,
      includeInactive: true,
    })
    count.value = affected.value.length
  } catch (e) {
    affected.value = []
    count.value = 0
  } finally {
    loadingCount.value = false
  }
})
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1100; }
.dialog { background: #fff; border-radius: 14px; width: 100%; max-width: 480px; padding: 24px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); }

.dialog-title { font-size: 17px; font-weight: 700; color: #111; margin: 0 0 10px; }
.dialog-msg { font-size: 14px; color: #4b5563; margin: 0 0 14px; line-height: 1.5; }

.impact { font-size: 13px; color: #6b7280; background: #f9fafb; border: 1px solid #f3f4f6; border-radius: 8px; padding: 10px 12px; }
.impact.warn { color: #92400e; background: #fffbeb; border-color: #fde68a; }
.impact strong { font-weight: 700; }
.link-btn { display: inline; background: none; border: none; padding: 0; margin-left: 6px; color: #2563eb; font: inherit; font-weight: 600; cursor: pointer; text-decoration: underline; }

.affected-list { margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #f3f4f6; border-radius: 8px; }
.affected-row { display: flex; align-items: center; gap: 10px; padding: 7px 12px; border-bottom: 1px solid #f6f7f8; font-size: 13px; }
.affected-row:last-child { border-bottom: none; }
.affected-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #4338ca; }
.affected-name { color: #111; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.affected-inactive { font-size: 11px; color: #9ca3af; text-transform: uppercase; }

.dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
.btn-cancel { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: #e9eaec; }
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-danger { padding: 10px 20px; background: #dc2626; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }
.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
