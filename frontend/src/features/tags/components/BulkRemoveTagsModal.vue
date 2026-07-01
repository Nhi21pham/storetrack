<template>
  <div class="modal-overlay" @click.self="onClose">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ $t('bulk.removeTagsTitle') }}</h2>
        <button class="close-btn" @click="onClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <p class="subtitle">{{ $t('bulk.removeTagsSubtitle', { count, noun }) }}</p>

        <p v-if="!availableTags.length" class="empty">{{ $t('bulk.removeTagsEmpty', { noun }) }}</p>

        <template v-else>
          <SearchBar v-model="search" :placeholder="$t('bulk.removeTagsSearch')" />

          <p v-if="!filteredTags.length" class="empty">{{ $t('bulk.removeTagsNoMatch') }}</p>

          <ul v-else class="tag-list">
            <li v-for="tag in paged" :key="keyOf(tag)" class="tag-row" @click="toggle(tag)">
              <SelectCheckbox :checked="isSelected(tag)" @change="toggle(tag)" @click.stop />
              <TagChip :tag-name="tag.tag_name" :value="tag.value" />
              <span class="count" :title="$t('bulk.removeTagsOnTitle')">{{ tag.count }}/{{ count }}</span>
            </li>
          </ul>

          <Pagination
            v-if="totalPages > 1"
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="total"
            :per-page="perPage"
            @update:current-page="currentPage = $event"
            @update:per-page="setPerPage"
          />
        </template>
      </div>

      <div class="modal-footer">
        <span v-if="selectedCount" class="selected-note">{{ $t('bulk.removeTagsSelected', { count: selectedCount }) }}</span>
        <button class="btn-cancel" @click="onClose" :disabled="busy">{{ $t('common.cancel') }}</button>
        <button class="btn-submit danger" @click="onApply" :disabled="busy || !selectedCount">
          <span v-if="busy" class="spinner"></span>
          {{ $t('bulk.removeTagsConfirm') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import TagChip from '@/components/common/TagChip.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import Pagination from '@/components/common/Pagination.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { normalizeText } from '@/utils/textNormalizer'

const props = defineProps({
  availableTags: { type: Array,   default: () => [] },
  count:         { type: Number,  default: 0 },
  noun:          { type: String,  default: '' },
  busy:          { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'apply'])

const search = ref('')
const selectedKeys = ref(new Set())

const keyOf = (tag) => `${tag.tag_id}:${tag.tag_value_id != null ? tag.tag_value_id : ''}`
const isSelected = (tag) => selectedKeys.value.has(keyOf(tag))
const selectedCount = computed(() => selectedKeys.value.size)

const filteredTags = computed(() => {
  const needle = normalizeText(search.value)
  if (!needle) return props.availableTags
  return props.availableTags.filter(tag =>
    normalizeText(tag.tag_name || '').includes(needle) ||
    normalizeText(tag.value || '').includes(needle),
  )
})

const {
  currentPage, perPage, total, totalPages, paginated: paged, setPerPage, resetPage,
} = useClientPagination(filteredTags, { defaultPerPage: 10 })

watch(search, resetPage)

const toggle = (tag) => {
  const key = keyOf(tag)
  const next = new Set(selectedKeys.value)
  next.has(key) ? next.delete(key) : next.add(key)
  selectedKeys.value = next
}

const onClose = () => {
  if (!props.busy) emit('close')
}

const onApply = () => {
  if (props.busy || !selectedKeys.value.size) return
  const pairs = props.availableTags
    .filter(isSelected)
    .map(tag => ({
      tag_id: tag.tag_id,
      tag_value_id: tag.tag_value_id != null ? tag.tag_value_id : null,
    }))
  emit('apply', pairs)
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }
.modal-body { padding: 20px 24px; }
.subtitle { font-size: 13px; color: #6b7280; margin: 0 0 14px; line-height: 1.5; }
.empty { font-size: 13px; color: #9ca3af; margin: 0; }
.tag-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; max-height: 340px; overflow-y: auto; }
.tag-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; cursor: pointer; transition: background 0.12s; }
.tag-row:hover { background: #f9fafb; }
.count { margin-left: auto; font-size: 12px; font-weight: 600; color: #9ca3af; }
.modal-footer { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }
.selected-note { margin-right: auto; font-size: 12.5px; font-weight: 600; color: #6b7280; }
.btn-cancel { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover:not(:disabled) { background: #e9eaec; }
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-submit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-submit.danger { background: #be123c; }
.btn-submit.danger:hover:not(:disabled) { background: #9f1239; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
