<x-layouts.app header="Create Campaign">
    <div class="max-w-5xl mx-auto" x-data="campaignBuilder()">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Builder Form -->
            <div class="lg:col-span-2 space-y-6">
                <x-card class="p-6">
                    <h2 class="text-base font-semibold leading-6 text-slate-900 dark:text-slate-100">Campaign Details</h2>
                    <div class="mt-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Campaign Name</label>
                            <input type="text" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Subject</label>
                            <input type="text" x-model="subject" @input="generatePreviews" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm sm:text-sm font-mono" placeholder="{Hi|Hello|Hey}">
                            <p class="mt-1 text-xs text-slate-500">Supports nested spin syntax: {Hello|Hi {there|friend}}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Contact List</label>
                            <select class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm sm:text-sm">
                                <option>Main Audience (5,240 contacts)</option>
                                <option>Test List (3 contacts)</option>
                            </select>
                        </div>
                    </div>
                </x-card>
                
                <x-card class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold leading-6 text-slate-900 dark:text-slate-100">Content</h2>
                        <div class="flex rounded-md shadow-sm">
                            <button type="button" @click="mode = 'html'" :class="mode === 'html' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'bg-white text-slate-700 dark:bg-slate-800 dark:text-slate-300'" class="relative inline-flex items-center rounded-l-md border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700">HTML</button>
                            <button type="button" @click="mode = 'text'" :class="mode === 'text' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'bg-white text-slate-700 dark:bg-slate-800 dark:text-slate-300'" class="relative -ml-px inline-flex items-center rounded-r-md border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700">Plain Text</button>
                        </div>
                    </div>
                    
                    <div x-show="mode === 'html'">
                        <textarea rows="10" x-model="bodyHtml" @input="generatePreviews" class="block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm sm:text-sm font-mono" placeholder="<h1>{Welcome|Hello}</h1>"></textarea>
                    </div>
                    
                    <div x-show="mode === 'text'" x-cloak>
                        <textarea rows="10" class="block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 shadow-sm sm:text-sm font-mono"></textarea>
                        <p class="mt-2 text-sm text-slate-500">Plain text version for accessibility and spam score optimization.</p>
                    </div>
                </x-card>
            </div>
            
            <!-- Sidebar: Spin Preview & Actions -->
            <div class="space-y-6">
                <x-card class="p-6">
                    <h3 class="text-sm font-medium text-slate-900 dark:text-slate-100 flex items-center justify-between">
                        Spin Preview
                        <button @click="generatePreviews" class="text-indigo-600 hover:text-indigo-500 text-xs">Refresh</button>
                    </h3>
                    
                    <div class="mt-4 space-y-4">
                        <template x-for="(preview, idx) in previews" :key="idx">
                            <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-lg border border-slate-100 dark:border-slate-700 text-sm">
                                <div class="font-medium text-slate-900 dark:text-slate-100 truncate" x-text="'Subj: ' + preview.subject"></div>
                                <div class="mt-1 text-slate-500 dark:text-slate-400 line-clamp-3 text-xs" x-html="preview.body"></div>
                            </div>
                        </template>
                        <div x-show="previews.length === 0" class="text-sm text-slate-500 text-center py-4">
                            Type above to generate variations
                        </div>
                    </div>
                </x-card>
                
                <x-card class="p-6">
                    <h3 class="text-sm font-medium text-slate-900 dark:text-slate-100 mb-4">Actions</h3>
                    <div class="space-y-3">
                        <x-button variant="ghost" class="w-full justify-center">Send Test Email</x-button>
                        <x-button variant="secondary" class="w-full justify-center">Save Draft</x-button>
                        <x-button variant="primary" class="w-full justify-center">Queue & Send</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('campaignBuilder', () => ({
                mode: 'html',
                subject: '{Hi|Hello|Hey} {there|friend}, check out our {new|latest} {product|feature}!',
                bodyHtml: '<p>{We are excited|We are thrilled} to announce...</p>',
                previews: [],
                
                init() {
                    this.generatePreviews();
                },
                
                // Simple recursive spin syntax parser for preview purposes
                spin(text) {
                    if (!text) return '';
                    let regex = /\{([^{}]*)\}/g;
                    let matches = text.match(regex);
                    if (!matches) return text;
                    
                    let result = text;
                    matches.forEach(match => {
                        let options = match.substring(1, match.length - 1).split('|');
                        let choice = options[Math.floor(Math.random() * options.length)];
                        result = result.replace(match, choice);
                    });
                    
                    // Recurse for nested (simple approximation)
                    if (result.includes('{')) return this.spin(result);
                    return result;
                },
                
                generatePreviews() {
                    this.previews = [];
                    for(let i=0; i<3; i++) {
                        this.previews.push({
                            subject: this.spin(this.subject) || 'No subject',
                            body: this.spin(this.bodyHtml) || 'No body'
                        });
                    }
                }
            }))
        })
    </script>
    @endpush
</x-layouts.app>
