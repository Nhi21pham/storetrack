<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>Bank Account Details</h2>
        <button class="close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="detail-row">
          <span class="detail-label">Owner Type</span>
          <span class="detail-value">
            <span class="type-badge" :class="account.party?.type">{{ ownerTypeLabel }}</span>
          </span>
        </div>

        <div class="detail-row">
          <span class="detail-label">Owner Name</span>
          <span v-if="account.party?.display_name" class="detail-value name-text">{{ account.party.display_name }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">Bank</span>
          <span class="detail-value">
            <span class="bank-name">{{ account.bank?.short_name || '—' }}</span>
            <span v-if="bankFullName" class="bank-full">{{ bankFullName }}</span>
          </span>
        </div>

        <div class="detail-row">
          <span class="detail-label">Account Number</span>
          <span class="detail-value mono">{{ account.account_number }}</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">Holder Name</span>
          <span v-if="account.account_holder_name" class="detail-value">{{ account.account_holder_name }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">Branch</span>
          <span v-if="account.branch" class="detail-value">{{ account.branch }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">Province</span>
          <span v-if="account.province?.name_vi" class="detail-value">{{ account.province.name_vi }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">Created</span>
          <span v-if="account.created_at" class="detail-value">{{ formatDateTime(account.created_at) }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-close" @click="$emit('close')">Close</button>
        <button v-if="canEdit" class="btn-edit" @click="$emit('edit', account)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Edit
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatDateTime } from '@/utils/datetime'

const props = defineProps({
  account: { type: Object, required: true },
  canEdit: { type: Boolean, default: true },
})

defineEmits(['close', 'edit'])

const ownerTypeLabel = computed(() => {
  const type = props.account.party?.type
  if (type === 'business') return 'Business'
  if (type === 'customer') return 'Customer'
  if (type === 'supplier') return 'Supplier'
  return type || '—'
})

const bankFullName = computed(() => {
  const vi = props.account.bank?.full_name_vi
  const en = props.account.bank?.full_name_en
  return [vi, en].filter(Boolean).join(' • ')
})
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 540px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }

.detail-row { display: grid; grid-template-columns: 130px 1fr; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f3f4f6; align-items: baseline; }
.detail-row:last-child { border-bottom: none; }

.detail-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.detail-value { font-size: 14px; color: #111; word-break: break-word; display: flex; flex-direction: column; gap: 2px; }

.name-text { font-weight: 600; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
.empty-val { color: #d1d5db; }

.bank-name { font-weight: 600; }
.bank-full { font-size: 12px; color: #6b7280; }

.type-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; background: #f3f4f6; color: #374151; width: fit-content; }
.type-badge.business { background: #e0f2fe; color: #0369a1; }
.type-badge.customer { background: #ecfdf5; color: #047857; }
.type-badge.supplier { background: #fef3c7; color: #92400e; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }

.btn-close { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-close:hover { background: #e9eaec; }

.btn-edit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 6px; }
.btn-edit:hover { background: #333; }
</style>
