<x-layouts.app header="{{ isset($campaign) ? 'Edit Campaign' : 'Create Campaign' }}">
    <div x-data="campaignBuilder()" class="-mt-6 -mx-4 lg:-mx-8">
        <form action="{{ isset($campaign) ? route('campaigns.update', $campaign->id) : route('campaigns.store') }}" method="POST" class="flex flex-col lg:flex-row h-[calc(100vh-4rem)]">
            @csrf
            @if(isset($campaign))
                @method('PUT')
            @endif

            <!-- Left Panel: Configuration -->
            <div class="w-full lg:w-1/3 flex flex-col bg-white dark:bg-[#0A0A0A] border-r border-slate-200 dark:border-white/10 z-10 overflow-y-auto">
                <div class="p-6 border-b border-slate-200 dark:border-white/10 flex justify-between items-center sticky top-0 bg-white/90 dark:bg-[#0A0A0A]/90 backdrop-blur-md z-20">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Settings</h2>
                    <x-button type="submit" variant="primary" class="shadow-premium dark:shadow-premium-dark" x-bind:disabled="$store.loading" @click="$store.loading = true">
                        <svg x-show="$store.loading" class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-show="!$store.loading">Save Draft</span><span x-show="$store.loading">Saving...</span>
                    </x-button>
                </div>

                <div class="p-6 space-y-6 flex-1">
                    @if ($errors->any())
                        <div class="rounded-xl bg-rose-50 dark:bg-rose-900/20 p-4 border border-rose-100 dark:border-rose-900/50">
                            <h3 class="text-sm font-medium text-rose-800 dark:text-rose-300">Validation errors:</h3>
                            <ul class="list-disc pl-5 text-sm text-rose-700 dark:text-rose-400 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Campaign Name -->
                    <div class="space-y-1">
                        <label class="input-label">Campaign Name</label>
                        <input type="text" name="name" value="{{ old('name', isset($campaign) ? $campaign->name : '') }}" required class="input @error('name') ring-rose-400 dark:ring-rose-500 @enderror" placeholder="Q3 Newsletter">
                        @error('name')<p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <!-- Subject -->
                    <div class="space-y-1">
                        <label class="input-label">Subject Line</label>
                        <div class="relative">
                            <input type="text" name="subject" x-model="subject" @input="generatePreviews" required class="input pr-10 @error('subject') ring-rose-400 dark:ring-rose-500 @enderror" placeholder="{Hi|Hello|Hey}">
                        @error('subject')<p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>@enderror
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Supports nested spin syntax"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        </div>
                    </div>

                    <!-- Audience -->
                    <div class="space-y-1">
                        <label class="input-label">Audience List</label>
                        <select name="list_id" class="input @error('list_id') ring-rose-400 dark:ring-rose-500 @enderror">
                        @error('list_id')<p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>@enderror
                            <option value="">Select a list</option>
                            @foreach($lists as $list)
                                <option value="{{ $list->id }}" {{ (isset($campaign) && $campaign->list_id === $list->id) ? 'selected' : '' }}>
                                    {{ $list->name }} ({{ number_format($list->contacts()->count()) }} contacts)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- SMTP -->
                    <div class="space-y-1">
                        <label class="input-label">Routing (Optional)</label>
                        <select name="smtp_account_ids[]" multiple class="input" style="height: 80px;">
                            @foreach($smtpAccounts as $smtp)
                                <option value="{{ $smtp->id }}" {{ (isset($campaign) && $campaign->smtpAccounts->contains($smtp->id)) ? 'selected' : '' }}>
                                    {{ $smtp->name }} ({{ $smtp->from_email }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Leave empty to use all active accounts.</p>
                    </div>
                </div>

                <div class="p-6 bg-slate-50 dark:bg-white/[0.02] border-t border-slate-200 dark:border-white/10">
                    <h3 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Spin Syntax Previews</h3>
                    <div class="space-y-3">
                        <template x-for="(preview, idx) in previews" :key="idx">
                            <div class="p-3 bg-white dark:bg-[#111111] rounded-lg border border-slate-200 dark:border-slate-800 text-xs shadow-sm">
                                <div class="font-medium text-slate-900 dark:text-white truncate" x-text="'Subj: ' + preview.subject"></div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="generatePreviews" class="mt-3 text-xs text-indigo-600 dark:text-indigo-400 font-medium hover:text-indigo-500 flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Regenerate Variations
                    </button>
                </div>
            </div>

            <!-- Right Panel: Editor & Preview -->
            <div class="w-full lg:w-2/3 flex flex-col bg-slate-100 dark:bg-slate-950/50 relative overflow-hidden">
                <!-- Editor Toolbar -->
                <div class="p-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between bg-white dark:bg-[#0A0A0A] z-10">
                    <div class="flex rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-1 bg-slate-50 dark:bg-[#111111]">
                        <button type="button" @click="mode = 'html'" :class="mode === 'html' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'" class="px-4 py-1.5 text-sm font-medium rounded-md transition-all">Designer</button>
                        <button type="button" @click="mode = 'text'" :class="mode === 'text' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'" class="px-4 py-1.5 text-sm font-medium rounded-md transition-all">Plain Text</button>
                    </div>

                    <div class="flex rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-1 bg-slate-50 dark:bg-[#111111]" x-show="mode === 'html'">
                        <button type="button" @click="device = 'desktop'" :class="device === 'desktop' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'" class="px-3 py-1.5 text-sm font-medium rounded-md transition-all" aria-label="Desktop Preview">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </button>
                        <button type="button" @click="device = 'mobile'" :class="device === 'mobile' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'" class="px-3 py-1.5 text-sm font-medium rounded-md transition-all" aria-label="Mobile Preview">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Editor Canvas -->
                <div class="flex-1 overflow-hidden relative flex" x-show="mode === 'html'">
                    <!-- Left: Real Editor -->
                    <div class="flex-1 border-r border-slate-200 dark:border-white/10 flex flex-col bg-white dark:bg-[#111111]">
                        <textarea name="body_html" x-model="bodyHtml" class="hidden"></textarea>
                        <div x-ref="quillEditor" class="flex-1 overflow-y-auto"></div>
                    </div>
                    
                    <!-- Right: Live Device Preview -->
                    <div class="flex-1 bg-slate-100 dark:bg-slate-900/50 flex justify-center items-start overflow-y-auto p-8 relative">
                        <div class="absolute inset-0 bg-grid-slate-200 dark:bg-grid-slate-800 [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))] dark:[mask-image:linear-gradient(0deg,rgba(0,0,0,0.8),rgba(0,0,0,0))]"></div>
                        
                        <!-- Device Frame -->
                        <div class="transition-all duration-500 ease-in-out relative z-10" :class="device === 'mobile' ? 'w-[375px]' : 'w-full max-w-2xl'">
                            <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl shadow-premium dark:shadow-premium-dark border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col h-[700px]">
                                <!-- Browser/App Header -->
                                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-[#0A0A0A] flex items-center gap-3">
                                    <div class="flex gap-1.5" x-show="device === 'desktop'">
                                        <div class="w-3 h-3 rounded-full bg-rose-400"></div>
                                        <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 truncate flex-1 font-medium text-center" x-text="spin(subject) || 'Subject line preview'"></div>
                                </div>
                                <!-- Content Area -->
                                <div class="flex-1 bg-white dark:bg-black p-6 overflow-y-auto prose prose-sm max-w-none dark:prose-invert" x-html="spin(bodyHtml) || '<p class=\'text-slate-400\'>Your content will appear here...</p>'"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plain Text Editor -->
                <div class="flex-1 bg-white dark:bg-[#0A0A0A] p-6 overflow-y-auto" x-show="mode === 'text'" x-cloak>
                    <textarea name="body_text" class="w-full h-full min-h-[500px] bg-slate-50 dark:bg-[#111111] border-slate-200 dark:border-slate-800 rounded-xl font-mono text-sm p-6 text-slate-800 dark:text-slate-300 focus:ring-indigo-500 focus:border-indigo-500 resize-none shadow-inner">{{ old('body_text', isset($campaign) ? $campaign->body_text : '') }}</textarea>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        window.campaignBuilderData = () => ({
            mode: 'html',
            device: 'desktop',
            subject: {!! json_encode(old('subject', isset($campaign) ? $campaign->subject : '{Hi|Hello|Hey} {there|friend}, check out our {new|latest} update!')) !!},
            bodyHtml: {!! json_encode(old('body_html', isset($campaign) ? $campaign->body_html : '<h1>Your awesome email</h1><p>Start typing...</p>')) !!},
            previews: [],
            
                          init() {
                  this.generatePreviews();
                  
                  this.$nextTick(() => {
                      if (this.$refs.quillEditor) {
                          const initQuill = () => {
                              if (!window.Quill) {
                                  setTimeout(initQuill, 50);
                                  return;
                              }
                              const quill = new window.Quill(this.$refs.quillEditor, {
                                  theme: 'snow',
                                  modules: {
                                      toolbar: [
                                          [{ 'header': [1, 2, 3, false] }],
                                          ['bold', 'italic', 'underline', 'strike'],
                                          ['link', 'blockquote', 'code-block'],
                                          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                          ['clean']
                                      ]
                                  }
                              });
                              
                              quill.root.innerHTML = this.bodyHtml;
                              
                              quill.on('text-change', () => {
                                  this.bodyHtml = quill.root.innerHTML;
                                  this.generatePreviews();
                              });
                          };
                          initQuill();
                      }
                  });
              },
              
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
        });

        document.addEventListener('alpine:init', () => {
            Alpine.data('campaignBuilder', window.campaignBuilderData);
        });
        if (window.Alpine) {
            window.Alpine.data('campaignBuilder', window.campaignBuilderData);
        }
    </script>
    @endpush
    <style>
        .ql-toolbar { border: none !important; border-bottom: 1px solid #e2e8f0 !important; background: #f8fafc; }
        .dark .ql-toolbar { border-bottom-color: rgba(255,255,255,0.1) !important; background: #1a1a1a; }
        .ql-container { border: none !important; font-size: 15px !important; }
        .dark .ql-editor { color: #f1f5f9; }
        /* Fix Quill rendering under Tailwind Preflight */
        .ql-editor p { margin-bottom: 0.75em; }
        .ql-editor ul { list-style-type: disc !important; padding-left: 1.5em !important; margin-bottom: 1em; }
        .ql-editor ol { list-style-type: decimal !important; padding-left: 1.5em !important; margin-bottom: 1em; }
        .ql-editor h1 { font-size: 1.875rem !important; font-weight: 700 !important; margin-bottom: 0.5em !important; line-height: 1.2; }
        .ql-editor h2 { font-size: 1.5rem !important; font-weight: 600 !important; margin-bottom: 0.5em !important; line-height: 1.3; }
        .ql-editor h3 { font-size: 1.25rem !important; font-weight: 600 !important; margin-bottom: 0.5em !important; line-height: 1.4; }
        .ql-editor a { color: #4f46e5; text-decoration: underline; }
        .dark .ql-editor a { color: #818cf8; }
        .ql-editor blockquote { border-left: 4px solid #e2e8f0; padding-left: 1em; color: #64748b; font-style: italic; margin-bottom: 1em; }
        .dark .ql-editor blockquote { border-left-color: #334155; color: #94a3b8; }
        .dark .ql-picker-options { background: #1a1a1a !important; border-color: #333 !important; }
        .dark .ql-picker-item, .dark .ql-picker-label { color: #cbd5e1 !important; }
        .dark .ql-stroke { stroke: #cbd5e1 !important; }
        .dark .ql-fill { fill: #cbd5e1 !important; }
        .bg-grid-slate-200 { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32' fill='none' stroke='%23e2e8f0'%3e%3cpath d='M0 .5H31.5V32'/%3e%3c/svg%3e"); }
        .dark .bg-grid-slate-800 { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32' fill='none' stroke='%231e293b'%3e%3cpath d='M0 .5H31.5V32'/%3e%3c/svg%3e"); }
    </style>
</x-layouts.app>








