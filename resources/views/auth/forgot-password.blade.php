<x-layouts.guest :title="'Forgot Password'">
    <div class="min-h-screen flex items-center justify-center p-4" x-data="forgotPassword()">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="text-4xl mb-2">🔑</div>
                <h1 class="text-2xl font-bold text-slate-900">Reset your password</h1>
                <p class="text-slate-500 text-sm mt-1">Answer your security questions to reset</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl shadow-slate-200 p-8 space-y-4">
                <template x-if="message"><div class="rounded-lg px-4 py-3 text-sm" :class="success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'" x-text="message"></div></template>

                <template x-if="step === 1">
                    <div class="space-y-4">
                        <div>
                            <label for="recovery-username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                            <input id="recovery-username" type="text" x-model="username" autocomplete="username" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button @click="fetchQuestions" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-lg transition">Continue</button>
                    </div>
                </template>

                <template x-if="step === 2">
                    <div class="space-y-4">
                        <template x-for="(q, i) in questions" :key="i">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1" x-text="q"></label>
                                <input type="text" x-model="answers[i]" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                        </template>
                        <div>
                            <label for="new-password" class="block text-sm font-medium text-slate-700 mb-1">New password</label>
                            <input id="new-password" type="password" x-model="password" autocomplete="new-password" minlength="6" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="confirm-password" class="block text-sm font-medium text-slate-700 mb-1">Confirm new password</label>
                            <input id="confirm-password" type="password" x-model="passwordConfirmation" autocomplete="new-password" minlength="6" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button @click="resetPassword" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-lg transition">Reset Password</button>
                    </div>
                </template>

                <p class="text-center text-sm text-slate-500">
                    <a href="{{ route('login') }}" class="text-brand-600 hover:underline">Back to login</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function forgotPassword() {
            return {
                step: 1, username: '', questions: [], answers: ['', '', ''], password: '', passwordConfirmation: '', message: '', success: false,
                async postJson(url, body) {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify(body)
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        const firstError = Object.values(data.errors ?? {}).flat()[0];
                        throw new Error(firstError ?? data.message ?? 'Unable to process your request. Please try again.');
                    }
                    return data;
                },
                async fetchQuestions() {
                    this.message = '';
                    try {
                        const data = await this.postJson('{{ route('password.questions') }}', { username: this.username });
                        this.message = data.message ?? '';
                        this.success = data.success;
                        if (data.success) { this.questions = data.questions; this.step = 2; }
                    } catch (error) {
                        this.message = error.message;
                        this.success = false;
                    }
                },
                async resetPassword() {
                    this.message = '';
                    try {
                        const data = await this.postJson('{{ route('password.reset') }}', {
                            username: this.username,
                            answer1: this.answers[0],
                            answer2: this.answers[1],
                            answer3: this.answers[2],
                            password: this.password,
                            password_confirmation: this.passwordConfirmation
                        });
                        this.message = data.message ?? '';
                        this.success = data.success;
                        if (data.success) {
                            this.step = 1;
                            this.questions = [];
                            this.answers = ['', '', ''];
                            this.password = '';
                            this.passwordConfirmation = '';
                        }
                    } catch (error) {
                        this.message = error.message;
                        this.success = false;
                    }
                }
            }
        }
    </script>
</x-layouts.guest>
