<script setup lang="ts">
const props = defineProps<{
    report: any;
}>();

const liableLabel = (party: string) => {
    const map: Record<string, string> = {
        student: 'Student (Borrower)',
        faculty: 'Faculty (Instructor In-Charge)',
        shared:  'Shared — Student and Faculty',
    };
    return map[party] ?? 'To be determined';
};

const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';
const formatDateTime = (d: string) => d ? new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const print = () => window.print();
const goBack = () => window.history.back();
</script>

<template>
    <!-- Print button — hidden when printing -->
    <div class="no-print fixed top-4 right-4 z-50 flex gap-2">
        <button @click="print"
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg hover:bg-green-700 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" />
            </svg>
            Print / Save as PDF
        </button>
        <button @click="goBack"
            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 shadow hover:bg-gray-50 transition-colors">
            ← Back
        </button>
    </div>

    <!-- Report page -->
    <div class="report-page mx-auto max-w-3xl bg-white px-12 py-10 text-gray-900">

        <!-- Header -->
        <div class="text-center border-b-2 border-gray-800 pb-6 mb-6">
            <p class="text-xs font-semibold tracking-widest uppercase text-gray-500 mb-1">PHINMA College of Cagayan de Oro</p>
            <h1 class="text-2xl font-bold tracking-tight">Laboratory Equipment</h1>
            <h1 class="text-2xl font-bold tracking-tight">Incident Report</h1>
            <div class="mt-3 flex justify-center gap-8 text-xs text-gray-500">
                <span>Report No: <strong class="text-gray-800">INC-{{ String(report.id).padStart(5, '0') }}</strong></span>
                <span>Date Filed: <strong class="text-gray-800">{{ formatDateTime(report.created_at) }}</strong></span>
                <span>Status: <strong class="text-gray-800 capitalize">{{ report.status }}</strong></span>
            </div>
        </div>

        <!-- Section: Involved Parties -->
        <section class="mb-6">
            <h2 class="section-title">Involved Parties</h2>
            <div class="info-grid">
                <div>
                    <p class="label">Borrower (Student)</p>
                    <p class="value">{{ report.borrowing?.user?.name ?? '—' }}</p>
                </div>
                <div>
                    <p class="label">Student ID</p>
                    <p class="value font-mono">{{ report.borrowing?.user?.student_id ?? '—' }}</p>
                </div>
                <div>
                    <p class="label">Student Email</p>
                    <p class="value">{{ report.borrowing?.user?.email ?? '—' }}</p>
                </div>
                <div>
                    <p class="label">Faculty In-Charge</p>
                    <p class="value">{{ report.borrowing?.faculty?.name ?? '—' }}</p>
                </div>
                <div>
                    <p class="label">Faculty Email</p>
                    <p class="value">{{ report.borrowing?.faculty?.email ?? '—' }}</p>
                </div>
                <div>
                    <p class="label">Reported By (Lab Staff)</p>
                    <p class="value">{{ report.reporter?.name ?? '—' }}</p>
                </div>
            </div>
        </section>

        <!-- Section: Borrowing Details -->
        <section class="mb-6">
            <h2 class="section-title">Borrowing Details</h2>
            <div class="info-grid">
                <div>
                    <p class="label">Borrowing Reference</p>
                    <p class="value font-mono">CHEM-REQ-{{ new Date(report.borrowing?.created_at).getFullYear() }}-{{ String(report.borrowing?.id).padStart(5, '0') }}</p>
                </div>
                <div>
                    <p class="label">Claim Code</p>
                    <p class="value font-mono">{{ report.borrowing?.claim_code ?? '—' }}</p>
                </div>
                <div>
                    <p class="label">Borrow Date</p>
                    <p class="value">{{ formatDate(report.borrowing?.borrow_date) }}</p>
                </div>
                <div>
                    <p class="label">Return Date</p>
                    <p class="value">{{ formatDate(report.borrowing?.return_date) }}</p>
                </div>
                <div>
                    <p class="label">Activity / Subject</p>
                    <p class="value">{{ report.borrowing?.subject ?? '—' }}</p>
                </div>
            </div>
        </section>

        <!-- Section: Damaged Equipment -->
        <section class="mb-6">
            <h2 class="section-title">Damaged Equipment</h2>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Category</th>
                        <th>Campus / Location</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in report.borrowing?.borrowing_equipment" :key="item.id">
                        <td class="font-medium">{{ item.equipment?.name }}</td>
                        <td>{{ item.equipment?.category }}</td>
                        <td>{{ item.inventory?.campus?.campus_name }} — {{ item.inventory?.location?.location_name }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Section: Incident Details -->
        <section class="mb-6">
            <h2 class="section-title">Incident Details</h2>
            <div class="space-y-3">
                <div>
                    <p class="label">Description of Damage</p>
                    <p class="value whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 p-3">{{ report.description }}</p>
                </div>
                <div class="info-grid">
                    <div>
                        <p class="label">Liable Party</p>
                        <p class="value font-semibold">{{ liableLabel(report.liable_party) }}</p>
                    </div>
                </div>

                <!-- Replacement Policy Notice -->
                <div class="rounded border border-gray-300 bg-gray-50 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">School Policy — Equipment Replacement</p>
                    <p class="text-sm text-gray-800">
                        The liable party is required to <strong>physically replace the damaged equipment</strong> with the same item or an equivalent unit approved by the laboratory staff.
                        <strong>Cash payment or monetary settlement is not accepted</strong> as a form of resolution. The replacement item must be surrendered to the laboratory before the case is marked resolved.
                    </p>
                </div>

                <div v-if="report.liable_reason">
                    <p class="label">Reason / Justification</p>
                    <p class="value whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 p-3">{{ report.liable_reason }}</p>
                </div>
                <div v-if="report.borrowing?.remarks">
                    <p class="label">Return Remarks (Lab Staff Notes)</p>
                    <p class="value whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 p-3">{{ report.borrowing.remarks }}</p>
                </div>
            </div>
        </section>

        <!-- Section: Resolution -->
        <section class="mb-10" v-if="report.resolved_date">
            <h2 class="section-title">Resolution</h2>
            <div class="info-grid">
                <div>
                    <p class="label">Resolved On</p>
                    <p class="value">{{ formatDateTime(report.resolved_date) }}</p>
                </div>
            </div>
        </section>

        <!-- Signature Lines -->
        <section class="mt-10 border-t border-gray-300 pt-8">
            <h2 class="section-title">Acknowledgment & Signatures</h2>
            <p class="mb-6 text-xs text-gray-500">By signing below, all parties acknowledge the accuracy of this incident report.</p>
            <div class="grid grid-cols-3 gap-8 text-center text-sm">
                <div>
                    <div class="mb-8 border-b border-gray-400"></div>
                    <p class="font-medium">{{ report.borrowing?.user?.name ?? 'Student / Borrower' }}</p>
                    <p class="text-xs text-gray-500">Student / Borrower</p>
                    <p class="text-xs text-gray-400 mt-1">Date: _______________</p>
                </div>
                <div>
                    <div class="mb-8 border-b border-gray-400"></div>
                    <p class="font-medium">{{ report.borrowing?.faculty?.name ?? 'Faculty In-Charge' }}</p>
                    <p class="text-xs text-gray-500">Faculty In-Charge</p>
                    <p class="text-xs text-gray-400 mt-1">Date: _______________</p>
                </div>
                <div>
                    <div class="mb-8 border-b border-gray-400"></div>
                    <p class="font-medium">{{ report.reporter?.name ?? 'Lab Staff' }}</p>
                    <p class="text-xs text-gray-500">Lab Staff</p>
                    <p class="text-xs text-gray-400 mt-1">Date: _______________</p>
                </div>
            </div>
        </section>

        <p class="mt-8 text-center text-xs text-gray-400">
            Generated by ChemLab — PHINMA COC Science Laboratory Borrowing System • {{ new Date().toLocaleString('en-PH') }}
        </p>
    </div>
</template>

<style scoped>
.section-title {
    @apply text-xs font-bold uppercase tracking-widest text-gray-500 border-b border-gray-200 pb-1 mb-3;
}
.info-grid {
    @apply grid grid-cols-2 gap-x-6 gap-y-3;
}
.label {
    @apply text-xs font-medium text-gray-500;
}
.value {
    @apply text-sm text-gray-900 mt-0.5;
}
.report-table {
    @apply w-full text-sm border-collapse;
}
.report-table th {
    @apply bg-gray-100 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 px-3 py-2 border border-gray-200;
}
.report-table td {
    @apply px-3 py-2 border border-gray-200 text-gray-800;
}

@media print {
    .no-print { display: none !important; }
    .report-page { max-width: 100%; padding: 0; margin: 0; }
    body { background: white; }
}
</style>
