<template>
  <DebtReportPage
    ledger="payable"
    title="Supplier Debt Report"
    subtitle="What we were billed by each supplier, paid, and still owe — within the selected date range."
    party-label="Supplier"
    spent-label="Total Purchased"
    invoice-section-label="Invoices in range"
    empty-description="Record a purchase invoice to start tracking what we owe suppliers."
    export-filename-prefix="supplier-debt-report"
    :fetchers="fetchers"
    :exporters="exporters"
  />
</template>

<script setup>
import DebtReportPage from '@/features/reports/components/DebtReportPage.vue'
import {
  fetchPayablesReport, fetchPayablesReportByBusiness,
  startPayablesReportExport, startPayablesReportBusinessExport,
} from '@/features/reports/services/reportService'

const fetchers = { store: fetchPayablesReport, business: fetchPayablesReportByBusiness }
const exporters = { store: startPayablesReportExport, business: startPayablesReportBusinessExport }
</script>
