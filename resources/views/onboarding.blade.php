<x-layouts.app header="Onboarding">
    <div class="max-w-3xl mx-auto" x-data="onboardingWizard()">
        <!-- Progress Bar -->
        <div class="mb-8">
            <h2 class="text-base font-semibold leading-6 text-slate-900 dark:text-slate-100">Let's get you set up</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Complete these steps to send your first campaign.</p>
            
            <div class="mt-6 flex items-center justify-between" aria-hidden="true">
                <template x-for="(step, index) in steps" :key="index">
                    <div class="flex items-center flex-1">
                        <div class="flex flex-col items-center flex-1">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center border-2"
                                 :class="currentStep > index ? 'bg-indigo-600 border-indigo-600 text-white' : (currentStep === index ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-slate-300 dark:border-slate-700 text-slate-400')">
                                <svg x-show="currentStep > index" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span x-show="currentStep <= index" x-text="index + 1" class="font-medium text-sm"></span>
                            </div>
                            <span class="mt-2 text-xs font-medium" 
                                  :class="currentStep >= index ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400'"
                                  x-text="step.title"></span>
                        </div>
                        <div x-show="index < steps.length - 1" class="flex-1 h-0.5 bg-slate-200 dark:bg-slate-700 mx-2"
                             :class="currentStep > index ? 'bg-indigo-600 dark:bg-indigo-500' : ''"></div>
                    </div>
                </template>
            </div>
        </div>

        <x-card>
            <!-- Step 1: SMTP -->
            <div x-show="currentStep === 0" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <h3 class="text-lg font-medium leading-6 text-slate-900 dark:text-slate-100">Connect SMTP Provider</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add your email sending account credentials.</p>
                
                <form class="mt-6 space-y-4" @submit.prevent="testSmtp()">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Host</label>
                            <input type="text" x-model="smtp.host" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="smtp.mailgun.org">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Port</label>
                            <input type="number" x-model="smtp.port" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Username</label>
                            <input type="text" x-model="smtp.username" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                            <input type="password" x-model="smtp.password" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <x-button type="submit" variant="primary" :disabled="testingSmtp">
                            <span x-show="!testingSmtp">Connect & Continue</span>
                            <span x-show="testingSmtp">Testing...</span>
                        </x-button>
                    </div>
                </form>
            </div>

            <!-- Step 2: Contacts -->
            <div x-show="currentStep === 1" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <h3 class="text-lg font-medium leading-6 text-slate-900 dark:text-slate-100">Import Contacts</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upload a CSV file with your audience.</p>
                
                <div class="mt-6 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-lg p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <div class="mt-4 flex text-sm leading-6 text-slate-600 dark:text-slate-400 justify-center">
                        <label for="file-upload" class="relative cursor-pointer rounded-md bg-white dark:bg-slate-900 font-semibold text-indigo-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-600 focus-within:ring-offset-2 hover:text-indigo-500">
                            <span>Upload a file</span>
                            <input id="file-upload" name="file-upload" type="file" class="sr-only" @change="fileSelected">
                        </label>
                        <p class="pl-1">or drag and drop</p>
                    </div>
                    <p class="text-xs leading-5 text-slate-500">CSV up to 10MB</p>
                    
                    <div x-show="fileName" class="mt-4 text-sm font-medium text-emerald-600 dark:text-emerald-400" x-text="fileName + ' selected'"></div>
                </div>
                
                <div class="flex justify-between mt-6">
                    <x-button type="button" variant="ghost" @click="currentStep = 0">Back</x-button>
                    <x-button type="button" variant="primary" @click="importContacts" :disabled="!fileName || importing">
                        <span x-show="!importing">Import & Continue</span>
                        <span x-show="importing">Importing...</span>
                    </x-button>
                </div>
            </div>

            <!-- Step 3: Campaign -->
            <div x-show="currentStep === 2" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <h3 class="text-lg font-medium leading-6 text-slate-900 dark:text-slate-100">Send First Campaign</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Draft a quick welcome message to test your setup.</p>
                
                <form class="mt-6 space-y-4" @submit.prevent="finishOnboarding">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Campaign Name</label>
                        <input type="text" value="Welcome Campaign" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Subject</label>
                        <input type="text" value="{Hi|Hello}, welcome to our list!" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm sm:text-sm">
                        <p class="mt-1 text-xs text-slate-500">Supports spin syntax e.g., {a|b}</p>
                    </div>
                    
                    <div class="flex justify-between mt-6">
                        <x-button type="button" variant="ghost" @click="currentStep = 1">Back</x-button>
                        <x-button type="submit" variant="primary">Launch Dashboard</x-button>
                    </div>
                </form>
            </div>
        </x-card>
    </div>

    @push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('onboardingWizard', () => ({
                currentStep: 0,
                steps: [
                    { title: 'Connect SMTP' },
                    { title: 'Import Contacts' },
                    { title: 'First Campaign' }
                ],
                smtp: { host: '', port: 587, username: '', password: '' },
                testingSmtp: false,
                fileName: '',
                importing: false,
                
                testSmtp() {
                    this.testingSmtp = true;
                    // Simulate API call
                    setTimeout(() => {
                        this.testingSmtp = false;
                        window.$toast('SMTP Connected Successfully', 'success');
                        this.currentStep = 1;
                    }, 1000);
                },
                
                fileSelected(e) {
                    if (e.target.files.length > 0) {
                        this.fileName = e.target.files[0].name;
                    }
                },
                
                importContacts() {
                    this.importing = true;
                    // Simulate API call
                    setTimeout(() => {
                        this.importing = false;
                        window.$toast('Contacts imported', 'success');
                        this.currentStep = 2;
                    }, 1500);
                },
                
                finishOnboarding() {
                    window.$toast('Welcome to EliteSender!', 'success');
                    setTimeout(() => {
                        window.location.href = '/dashboard';
                    }, 500);
                }
            }))
        })
    </script>
    @endpush
</x-layouts.app>
