<template>
  <DebtReportPage
    ledger="receivable"
    :title="$t('reports.debt.receivableTitle')"
    :subtitle="$t('reports.debt.receivableSubtitle')"
    :party-label="$t('reports.debt.customer')"
    :spent-label="$t('reports.debt.totalSpent')"
    :invoice-section-label="$t('reports.debt.invoicesInRange')"
    :empty-description="$t('reports.debt.receivableEmptyDesc')"
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
