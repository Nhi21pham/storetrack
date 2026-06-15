<template>
  <DebtReportPage
    ledger="receivable"
    title="Customer Debt Report"
    subtitle="What each customer was billed, paid, and still owes — within the selected date range."
    party-label="Customer"
    spent-label="Total Spent"
    invoice-section-label="Invoices in range"
    empty-description="Record a sale invoice to start tracking what customers owe."
    export-filename-prefix="customer-debt-report"
    :fetchers="fetchers"
    :exporters="exporters"
  />
</template>

<script setup>
import DebtReportPage from '@/features/reports/components/DebtReportPage.vue'
import {
  fetchReceivablesReport, fetchReceivablesReportByBusiness,
  startReceivablesReportExport, startReceivablesReportBusinessExport,
} from '@/features/reports/services/reportService'

const fetchers = { store: fetchReceivablesReport, business: fetchReceivablesReportByBusiness }
const exporters = { store: startReceivablesReportExport, business: startReceivablesReportBusinessExport }
</script>
