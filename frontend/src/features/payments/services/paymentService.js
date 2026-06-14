import { graphql } from '@/api'

const PAYMENT_FIELDS = `
  id store_id party_id amount paid_at method note created_at
  allocations {
    id invoice_id amount
    invoice { id code invoice_date type grand_total }
  }
`

const OPEN_INVOICE_FIELDS = `
  id code type invoice_date grand_total paid_amount balance payment_status
`

const PAYMENTS_QUERY = `
  query Payments($store_id: ID!, $party_id: ID!) {
    payments(store_id: $store_id, party_id: $party_id) { ${PAYMENT_FIELDS} }
  }
`

const PARTY_OPEN_INVOICES_QUERY = `
  query PartyOpenInvoices($store_id: ID!, $party_id: ID!) {
    partyOpenInvoices(store_id: $store_id, party_id: $party_id) { ${OPEN_INVOICE_FIELDS} }
  }
`

const RECORD_PAYMENT_MUTATION = `
  mutation RecordPayment($store_id: ID!, $input: RecordPaymentInput!) {
    recordPayment(store_id: $store_id, input: $input) { ${PAYMENT_FIELDS} }
  }
`

const DELETE_PAYMENT_MUTATION = `
  mutation DeletePayment($id: ID!) {
    deletePayment(id: $id)
  }
`

export const fetchPayments = async ({ storeId, partyId }) => {
  const data = await graphql(PAYMENTS_QUERY, { store_id: storeId, party_id: partyId })
  return data.payments
}

export const fetchPartyOpenInvoices = async ({ storeId, partyId }) => {
  const data = await graphql(PARTY_OPEN_INVOICES_QUERY, { store_id: storeId, party_id: partyId })
  return data.partyOpenInvoices
}

const PAYMENTS_BY_BUSINESS_QUERY = `
  query PaymentsByBusiness($business_id: ID!, $party_id: ID!) {
    paymentsByBusiness(business_id: $business_id, party_id: $party_id) { ${PAYMENT_FIELDS} }
  }
`

const PARTY_OPEN_INVOICES_BY_BUSINESS_QUERY = `
  query PartyOpenInvoicesByBusiness($business_id: ID!, $party_id: ID!) {
    partyOpenInvoicesByBusiness(business_id: $business_id, party_id: $party_id) { ${OPEN_INVOICE_FIELDS} }
  }
`

export const fetchPaymentsByBusiness = async ({ businessId, partyId }) => {
  const data = await graphql(PAYMENTS_BY_BUSINESS_QUERY, { business_id: businessId, party_id: partyId })
  return data.paymentsByBusiness
}

export const fetchPartyOpenInvoicesByBusiness = async ({ businessId, partyId }) => {
  const data = await graphql(PARTY_OPEN_INVOICES_BY_BUSINESS_QUERY, { business_id: businessId, party_id: partyId })
  return data.partyOpenInvoicesByBusiness
}

export const recordPayment = async ({ storeId, input }) => {
  const data = await graphql(RECORD_PAYMENT_MUTATION, { store_id: storeId, input })
  return data.recordPayment
}

export const deletePayment = async ({ id }) => {
  await graphql(DELETE_PAYMENT_MUTATION, { id })
}
