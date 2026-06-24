<template>
  <DebtReportPage
    ledger="payable"
    :title="$t('reports.debt.payableTitle')"
    :subtitle="$t('reports.debt.payableSubtitle')"
    :party-label="$t('reports.debt.supplier')"
    :spent-label="$t('reports.debt.totalPurchased')"
    :invoice-section-label="$t('reports.debt.invoicesInRange')"
    :empty-description="$t('reports.debt.payableEmptyDesc')"
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
