<x-layouts.app :title="'Borrow / Reserve'">
    <div class="max-w-4xl" x-data="borrowForm()" x-init="loadItems()" @keydown.escape.window="closeIssueConfirmation()">
        <div class="mb-5">
            <h2 class="text-2xl font-bold text-slate-900">New Reservation</h2>
            <p class="text-slate-500 text-sm">Reservations must be made at least 3 days in advance.</p>
        </div>

        <form method="POST" action="{{ route('borrow.store') }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-6" @submit.prevent="prepareSubmission($event)">
            @csrf
            <input type="hidden" name="refnum" value="{{ $refnum }}">
            <input type="hidden" name="acknowledged_return_issues" x-ref="issueAcknowledgement" value="0">

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reference No.</label>
                    <input type="text" value="{{ $refnum }}" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500">
                </div>
                <div></div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">First Name</label>
                    <input type="text" name="fname" value="{{ old('fname') }}" x-model="firstName" @input="invalidateReturnIssues()" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Last Name</label>
                    <input type="text" name="lname" value="{{ old('lname') }}" x-model="lastName" @input="invalidateReturnIssues()" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <p x-show="issueLookupError" x-cloak role="alert" class="sm:col-span-2 text-sm text-rose-700">Unable to check this borrower’s return history. Please try again before submitting.</p>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                    <input type="text" name="addr" value="{{ old('addr') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contact Number</label>
                    <input type="text" name="contNum" value="{{ old('contNum') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Borrow Date &amp; Time</label>
                    <input type="datetime-local" name="dateBorrowed" x-model="borrowDate" @change="loadItems()" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <p class="text-xs text-slate-400 mt-1">Must be at least 3 days from now.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Return Date &amp; Time</label>
                    <input type="datetime-local" name="returnDate" x-model="returnDateVal" @change="loadItems()" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-slate-700">Items</label>
                    <button type="button" @click="addRow()" class="text-sm text-brand-600 hover:underline">+ Add item</button>
                </div>

                <div class="space-y-2">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="flex gap-2 items-center">
                            <select :name="'borrowItems[]'" x-model="row.equipment_id" required class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Select equipment...</option>
                                <template x-for="item in items" :key="item.id">
                                    <option :value="item.id" x-text="`${item.name} (${item.futureAvailable ?? item.availableQuantity} available)`"></option>
                                </template>
                            </select>
                            <input type="number" :name="'borrowQuantities[]'" x-model="row.quantity" min="1" :max="availableQuantityForRow(row, index)" required class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button type="button" @click="rows.splice(index, 1)" class="text-rose-500 px-2" x-show="rows.length > 1">✕</button>
                        </div>
                    </template>
                </div>
            </div>

            <button type="submit" :disabled="issueLookupLoading" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-lg transition disabled:cursor-wait disabled:opacity-60">
                Submit Reservation
            </button>
        </form>

        <div
            x-show="issueConfirmationOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
            role="presentation"
            @click.self="closeIssueConfirmation()"
        >
            <section
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="return-issues-title"
                aria-describedby="return-issues-description"
            >
                <header class="flex items-start justify-between gap-4 border-b border-amber-200 bg-amber-50 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="return-issues-title" class="text-lg font-semibold text-slate-900">Previous return issues found</h2>
                        <p id="return-issues-description" class="mt-1 text-sm text-slate-600">
                            This borrower has reported damaged or maintenance-needed items. Review the history and confirm whether you want to continue.
                        </p>
                    </div>
                    <button type="button" class="text-2xl leading-none text-slate-500 hover:text-slate-900" aria-label="Close warning" @click="closeIssueConfirmation()">&times;</button>
                </header>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5 sm:p-6">
                    <p x-show="issueLookupLoading" class="text-sm text-slate-500" role="status">Checking previous return issues...</p>
                    <template x-for="(issue, index) in returnIssues" :key="`${issue.reference}-${index}`">
                        <article class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                <span class="font-semibold text-slate-800" x-text="issue.equipment"></span>
                                <span class="text-xs text-slate-500" x-text="`${issue.reportedAt} · ${issue.reference}`"></span>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">
                                <span x-show="issue.brokenQuantity > 0" x-text="`${issue.brokenQuantity} broken`"></span>
                                <span x-show="issue.brokenQuantity > 0 && issue.maintenanceQuantity > 0"> · </span>
                                <span x-show="issue.maintenanceQuantity > 0" x-text="`${issue.maintenanceQuantity} sent for maintenance`"></span>
                            </p>
                        </article>
                    </template>
                    <div x-show="issuePagination.lastPage > 1" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
                        <p class="text-xs text-slate-500" x-text="`Showing ${issuePagination.from}-${issuePagination.to} of ${issuePagination.total}`"></p>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="loadReturnIssues(issuePagination.currentPage - 1)" :disabled="issueLookupLoading || issuePagination.currentPage <= 1" class="rounded border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 disabled:opacity-40">Previous</button>
                            <span class="text-xs text-slate-600" x-text="`Page ${issuePagination.currentPage} of ${issuePagination.lastPage}`"></span>
                            <button type="button" @click="loadReturnIssues(issuePagination.currentPage + 1)" :disabled="issueLookupLoading || issuePagination.currentPage >= issuePagination.lastPage" class="rounded border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 disabled:opacity-40">Next</button>
                        </div>
                    </div>
                    <button x-show="issueLookupError" type="button" @click="loadReturnIssues(issuePagination.currentPage)" class="text-sm font-medium text-brand-600 hover:underline">Retry loading return history</button>
                    <p x-show="issueLookupError" x-cloak role="alert" class="text-sm text-rose-700">Unable to load the full return history. Please retry before continuing.</p>
                </div>

                <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" @click="closeIssueConfirmation()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Go back</button>
                    <button type="button" @click="confirmIssueHistory()" :disabled="issueLookupLoading || issueLookupError" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50">Continue with reservation</button>
                </footer>
            </section>
        </div>
    </div>

    <script>
        function borrowForm() {
            return {
                items: [], rows: [{ equipment_id: '', quantity: 1 }],
                borrowDate: '', returnDateVal: '',
                firstName: @js(old('fname', '')), lastName: @js(old('lname', '')),
                formElement: null, issueConfirmationOpen: false, issueLookupName: '',
                returnIssues: [], issueLookupLoading: false, issueLookupComplete: false, issueLookupError: false,
                issuePagination: { currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 },
                issueLookupSequence: 0,
                addRow() { this.rows.push({ equipment_id: '', quantity: 1 }); },
                availableQuantityForRow(row, index) {
                    const item = this.items.find(item => String(item.id) === String(row.equipment_id));
                    if (!item) return 0;

                    const alreadySelected = this.rows.reduce((quantity, selectedRow, selectedIndex) => {
                        if (selectedIndex === index || String(selectedRow.equipment_id) !== String(row.equipment_id)) {
                            return quantity;
                        }

                        return quantity + (Number(selectedRow.quantity) || 0);
                    }, 0);

                    return Math.max(0, Number(item.futureAvailable ?? item.availableQuantity) - alreadySelected);
                },
                invalidateReturnIssues() {
                    this.issueLookupSequence++;
                    this.returnIssues = [];
                    this.issueLookupLoading = false;
                    this.issueLookupComplete = false;
                    this.issueLookupError = false;
                    this.issueConfirmationOpen = false;
                    this.$refs.issueAcknowledgement.value = '0';
                },
                async prepareSubmission(event) {
                    this.formElement = event.target;
                    this.$refs.issueAcknowledgement.value = '0';

                    if (!this.firstName.trim() || !this.lastName.trim()) {
                        this.formElement.submit();
                        return;
                    }

                    const hasLoadedHistoryForCurrentName = this.issueLookupComplete
                        && this.issueLookupName === this.currentBorrowerName();

                    if (hasLoadedHistoryForCurrentName) {
                        this.handleReturnIssueLookupResult();
                        return;
                    }

                    await this.loadReturnIssues(1);
                    if (this.issueLookupComplete) {
                        this.handleReturnIssueLookupResult();
                    }
                },
                handleReturnIssueLookupResult() {
                    if (this.issuePagination.total > 0) {
                        this.issueConfirmationOpen = true;
                        return;
                    }

                    this.formElement.submit();
                },
                currentBorrowerName() {
                    return `${this.firstName.trim().toLocaleLowerCase()}|${this.lastName.trim().toLocaleLowerCase()}`;
                },
                confirmIssueHistory() {
                    if (!this.formElement || !this.issueLookupComplete || this.issueLookupError) {
                        return;
                    }

                    this.$refs.issueAcknowledgement.value = '1';
                    this.issueConfirmationOpen = false;
                    this.formElement.submit();
                },
                closeIssueConfirmation() {
                    this.issueConfirmationOpen = false;
                    this.$refs.issueAcknowledgement.value = '0';
                },
                async loadReturnIssues(page = 1) {
                    const firstName = this.firstName.trim();
                    const lastName = this.lastName.trim();
                    const sequence = ++this.issueLookupSequence;
                    this.returnIssues = [];
                    this.issueLookupComplete = false;
                    this.issueLookupError = false;
                    this.issueLookupName = this.currentBorrowerName();

                    if (!firstName || !lastName) {
                        this.issueLookupLoading = false;
                        this.issueLookupError = true;
                        return false;
                    }

                    this.issueLookupLoading = true;

                    try {
                        const params = new URLSearchParams({ fname: firstName, lname: lastName, issue_page: page });
                        const response = await fetch(`{{ route('borrow.return-issues') }}?${params}`);
                        if (!response.ok) {
                            throw new Error('Return issue lookup failed');
                        }

                        const data = await response.json();
                        if (sequence === this.issueLookupSequence) {
                            this.returnIssues = data.issues ?? [];
                            this.issuePagination = data.pagination ?? this.issuePagination;
                            this.issueLookupComplete = true;
                            return true;
                        }
                    } catch (error) {
                        if (sequence === this.issueLookupSequence) {
                            this.issueLookupError = true;
                        }
                    } finally {
                        if (sequence === this.issueLookupSequence) {
                            this.issueLookupLoading = false;
                        }
                    }
                    return false;
                },
                async loadItems() {
                    const params = new URLSearchParams();
                    if (this.borrowDate) params.set('borrowDate', this.borrowDate);
                    if (this.returnDateVal) params.set('returnDate', this.returnDateVal);
                    params.set('all', '1');
                    const res = await fetch(`{{ route('inventory.data') }}?${params}`);
                    const data = await res.json();
                    this.items = (data.items ?? []).filter(i => (i.futureAvailable ?? i.availableQuantity) > 0);
                },
            }
        }
    </script>
</x-layouts.app>
