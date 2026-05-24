import { createBankAccount } from '@/features/banking/services/bankAccountService'

/**
 * After a parent (supplier/customer/business) is created, persist any draft
 * bank accounts collected in the form. Returns the list of error messages for
 * accounts that failed to save — parent stays created either way.
 */
export const submitDraftBankAccounts = async ({ partyId, drafts }) => {
  if (!partyId || !Array.isArray(drafts) || drafts.length === 0) return []

  const errors = []
  for (const draft of drafts) {
    const input = {
      bank_id: draft.bank_id,
      account_number: draft.account_number,
    }
    if (draft.province_id) input.province_id = draft.province_id
    if (draft.account_holder_name) input.account_holder_name = draft.account_holder_name
    if (draft.branch) input.branch = draft.branch

    try {
      await createBankAccount({ partyId, input })
    } catch (err) {
      const label = `${draft.bank?.short_name || 'bank'} ${draft.account_number}`
      errors.push(`${label}: ${err.message}`)
    }
  }
  return errors
}
