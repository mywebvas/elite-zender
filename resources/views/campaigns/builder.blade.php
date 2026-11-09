{{--
    Campaign Studio & Spintax Composer (Stitch Elite Upgrade).

    The preview is rendered server-side through EmailHtmlRenderer — the same
    pipeline that sends the message. A preview assembled in JavaScript is a
    different renderer and therefore a lie: it was showing Quill's class-based
    styling, which no email client applies.
--}}
<x-layouts.app :header="isset($campaign) ? 'Edit Campaign' : 'Create Campaign'">
    <div x-data="campaignBuilder()" class="-mx-4 -mt-6 lg:-mx-8 flex flex-col h-[calc(100vh-4rem)]">

        {{-- Top Stepper Navigation (Stitch Design Upgrade) --}}
        <div class="px-6 py-2.5 border-b border-slate-200 dark:border-white/[0.08] bg-white/90 dark:bg-[#0A0E18]/90 backdrop-blur-md flex-shrink-0 z-20">
            <div class="max-w-[1600px] mx-auto flex items-center justify-between gap-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-6 flex-1 max-w-3xl">
                    {{-- Step 1 --}}
                    <button type="button" @click="currentStep = 1" class="flex items-center gap-2.5 text-left group transition-all"
                            :class="currentStep === 1 ? 'opacity-100' : 'opacity-60 hover:opacity-100'">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 text-xs font-bold transition-colors"
                             :class="currentStep === 1 ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-500/30' : 'bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-slate-400'">
                            1
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold uppercase tracking-wider block" :class="currentStep === 1 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'">Step 1</span>
                            <span class="text-xs font-semibold text-slate-900 dark:text-white truncate block">Audience & Setup</span>
                        </div>
                    </button>

                    {{-- Step 2 --}}
                    <button type="button" @click="currentStep = 2" class="flex items-center gap-2.5 text-left group transition-all"
                            :class="currentStep === 2 ? 'opacity-100' : 'opacity-60 hover:opacity-100'">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 text-xs font-bold transition-colors"
                             :class="currentStep === 2 ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-500/30' : 'bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-slate-400'">
                            2
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold uppercase tracking-wider block" :class="currentStep === 2 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'">Step 2</span>
                            <span class="text-xs font-semibold text-slate-900 dark:text-white truncate block">Studio & Preview</span>
                        </div>
                    </button>

                    {{-- Step 3 --}}
                    <button type="button" @click="currentStep = 3" class="flex items-center gap-2.5 text-left group transition-all"
                            :class="currentStep === 3 ? 'opacity-100' : 'opacity-60 hover:opacity-100'">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 text-xs font-bold transition-colors"
                             :class="currentStep === 3 ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-500/30' : 'bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-slate-400'">
                            3
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold uppercase tracking-wider block" :class="currentStep === 3 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'">Step 3</span>
                            <span class="text-xs font-semibold text-slate-900 dark:text-white truncate block">Relay Infrastructure</span>
                        </div>
                    </button>

                    {{-- Step 4 --}}
                    <button type="button" @click="currentStep = 4; refreshPreview()" class="flex items-center gap-2.5 text-left group transition-all"
                            :class="currentStep === 4 ? 'opacity-100' : 'opacity-60 hover:opacity-100'">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 text-xs font-bold transition-colors"
                             :class="currentStep === 4 ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-500/30' : 'bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-slate-400'">
                            4
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold uppercase tracking-wider block" :class="currentStep === 4 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400'">Step 4</span>
                            <span class="text-xs font-semibold text-slate-900 dark:text-white truncate block">Pre-Flight Audit</span>
                        </div>
                    </button>
                </div>

                {{-- Action Header Buttons --}}
                <div class="flex items-center gap-2.5">
                    @isset($campaign)
                        <button type="button" @click="testSendModal = true"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/[0.04] text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/[0.08] transition-colors">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Test Send
                        </button>
                    @endisset
                    <x-button type="submit" form="campaign-form" variant="primary" ::disabled="saving">
                        <svg x-show="saving" x-cloak class="mr-1.5 h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="saving ? 'Saving…' : 'Save Draft'"></span>
                    </x-button>
                </div>
            </div>
        </div>

        <form id="campaign-form" method="POST"
              action="{{ isset($campaign) ? route('campaigns.update', $campaign->id) : route('campaigns.store') }}"
              class="flex flex-1 flex-col lg:flex-row overflow-hidden"
              @submit="syncEditor">
            @csrf
            @isset($campaign)
                @method('PUT')
            @endisset

            {{-- ──────────────────────── Settings Sidebar / Drawer ──────────────────────── --}}
            <aside class="z-10 flex w-full flex-col overflow-y-auto border-r border-slate-200 bg-white lg:w-[380px] dark:border-white/10 dark:bg-[#0A0E18] flex-shrink-0"
                   :class="{'hidden lg:flex': currentStep === 2 && studioSplitView}">

                <div class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/95 p-4 backdrop-blur-md dark:border-white/10 dark:bg-[#0A0E18]/95">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Broadcast Parameters</h2>
                        <span class="text-[10px] text-slate-400 font-mono">Autosave on change</span>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Ready
                    </span>
                </div>

                <div class="flex-1 space-y-5 p-5">

                    @if ($errors->any())
                        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/50 dark:bg-rose-900/20" role="alert">
                            <p class="text-xs font-semibold text-rose-800 dark:text-rose-300">Please fix the following:</p>
                            <ul class="mt-1.5 list-disc pl-5 text-xs text-rose-700 dark:text-rose-400 space-y-0.5">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Campaign Internal Name --}}
                    <div>
                        <label for="name" class="input-label">Campaign internal label</label>
                        <input id="name" type="text" name="name" required maxlength="120"
                               value="{{ old('name', $campaign->name ?? '') }}"
                               class="input @error('name') ring-rose-400 @enderror"
                               placeholder="e.g. Q3 Customer Re-engagement">
                        <p class="mt-1 text-[11px] text-slate-400">Internal reference only — recipients never see this.</p>
                        @error('name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Subject Line with Spintax Superpowers & Dice Roll --}}
                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <label for="subject" class="input-label mb-0 flex items-center gap-1.5">
                                <span>Subject line</span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 font-bold border border-indigo-500/20 uppercase tracking-wider">Spintax</span>
                            </label>
                            <span class="text-[11px] font-mono tabular-nums" :class="subject.length > 60 ? 'text-amber-500' : 'text-slate-400'" x-text="subject.length + '/60'"></span>
                        </div>
                        <div class="relative">
                            <input id="subject" type="text" name="subject" x-model="subject" @input.debounce.400ms="refreshPreview"
                                   required maxlength="200"
                                   class="input font-mono text-xs pr-16 @error('subject') ring-rose-400 @enderror"
                                   placeholder="{Hi|Hello} [Name], important update">
                            <div class="absolute right-1.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                <button type="button" @click="testSpintax()" title="Roll Spintax Permutation"
                                        class="p-1 rounded-md text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                </button>
                                <button type="button" @click="insertSpintaxGroup()" title="Insert {A|B|C} group"
                                        class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition-colors">
                                    {|}
                                </button>
                            </div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-[11px] text-slate-400">
                            <span>Use <code class="text-indigo-600 dark:text-indigo-400 font-mono">{A|B|C}</code> for inbox rotation</span>
                            <span x-show="spintaxPermutations > 1" class="text-indigo-600 dark:text-indigo-400 font-mono font-semibold" x-text="spintaxPermutations + ' variations'"></span>
                        </div>
                        @error('subject')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Preview Teaser Text --}}
                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <label for="preheader" class="input-label mb-0">Inbox preview snippet (Preheader)</label>
                            <span class="text-[11px] font-mono tabular-nums" :class="preheader.length > 90 ? 'text-amber-500' : 'text-slate-400'" x-text="preheader.length + '/90'"></span>
                        </div>
                        <input id="preheader" type="text" name="preheader" x-model="preheader" @input.debounce.400ms="refreshPreview"
                               maxlength="255"
                               class="input text-xs @error('preheader') ring-rose-400 @enderror"
                               placeholder="Teaser snippet shown next to subject line">
                        <p class="mt-1 text-[11px] text-slate-400">
                            Prevents email clients from scraping unsubscribe or header links.
                        </p>
                        @error('preheader')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Reply-To Address --}}
                    <div>
                        <label for="reply_to" class="input-label">Custom reply-to <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="reply_to" type="email" name="reply_to" maxlength="255"
                               value="{{ old('reply_to', $campaign->reply_to ?? '') }}"
                               class="input text-xs @error('reply_to') ring-rose-400 @enderror"
                               placeholder="replies@yourdomain.com">
                        <p class="mt-1 text-[11px] text-slate-400">Leave blank to route replies to sending relay mailbox.</p>
                        @error('reply_to')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Target Audience List --}}
                    <div>
                        <label for="list_id" class="input-label">Target audience list</label>
                        <select id="list_id" name="list_id" class="input text-xs @error('list_id') ring-rose-400 @enderror">
                            <option value="">Select recipient list…</option>
                            @foreach($lists as $list)
                                <option value="{{ $list->id }}" @selected(old('list_id', $campaign->list_id ?? null) === $list->id)>
                                    {{ $list->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('list_id')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- SMTP Relay Infrastructure Allocation --}}
                    <div>
                        <label for="smtp" class="input-label">SMTP relay pool routing <span class="font-normal text-slate-400">(optional)</span></label>
                        <select id="smtp" name="smtp_account_ids[]" multiple size="3" class="input text-xs">
                            @foreach($smtpAccounts as $smtp)
                                <option value="{{ $smtp->id }}" @selected(isset($campaign) && $campaign->smtpAccounts->contains($smtp->id))>
                                    {{ $smtp->name }} — {{ $smtp->from_email }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">Select multiple for round-robin rotation, or empty for automatic pool load balancing.</p>
                    </div>

                    {{-- Schedule Broadcast --}}
                    <div>
                        <label for="scheduled_at" class="input-label">Schedule departure <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                               value="{{ old('scheduled_at', isset($campaign) && $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '') }}"
                               class="input text-xs @error('scheduled_at') ring-rose-400 @enderror">
                        <p class="mt-1 text-[11px] text-slate-400">Leave empty to send on demand. Workspace timezone: {{ config('app.timezone') }}.</p>
                        @error('scheduled_at')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            </aside>

            {{-- ──────────────────────── Studio Workspace: Dual-Pane Editor & macOS Canvas ──────────────────────── --}}
            <div class="relative flex w-full flex-1 flex-col overflow-hidden bg-slate-100 dark:bg-[#070A10]">

                {{-- Toolbar Bar --}}
                <div class="z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-2.5 backdrop-blur-md dark:border-white/10 dark:bg-[#0A0E18]/95 flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-white/10 dark:bg-white/[0.04]">
                            <button type="button" @click="mode = 'design'"
                                    :class="mode === 'design' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                                    class="rounded-lg px-3.5 py-1 text-xs transition-all">Design</button>
                            <button type="button" @click="mode = 'text'"
                                    :class="mode === 'text' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                                    class="rounded-lg px-3.5 py-1 text-xs transition-all">Plain Text</button>
                            <button type="button" @click="mode = 'preview'; refreshPreview()"
                                    :class="mode === 'preview' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                                    class="rounded-lg px-3.5 py-1 text-xs transition-all">Preview</button>
                        </div>

                        {{-- Split Studio View Toggle on Desktop --}}
                        <button type="button" @click="studioSplitView = !studioSplitView; if(studioSplitView) refreshPreview()"
                                class="hidden lg:inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-medium border transition-colors"
                                :class="studioSplitView ? 'bg-indigo-50 dark:bg-indigo-500/15 border-indigo-500/30 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-white/5'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                            <span x-text="studioSplitView ? 'Dual Pane Studio: On' : 'Dual Pane Studio: Off'"></span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2.5">
                        {{-- Merge-tag inserter dropdown --}}
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200">
                                <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
                                Personalization Tags
                            </button>
                            <div x-show="open" x-cloak x-transition
                                 class="absolute right-0 z-30 mt-2 w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-[#111624]">
                                @foreach($mergeTags as $tag)
                                    <button type="button" @click="insert(@js($tag['tag'])); open = false"
                                            class="flex w-full items-center justify-between px-4 py-2.5 text-left text-xs hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                        <span class="text-slate-700 dark:text-slate-200 font-medium">{{ $tag['label'] }}</span>
                                        <code class="text-[11px] text-indigo-600 dark:text-indigo-400 font-mono font-bold">{{ $tag['tag'] }}</code>
                                    </button>
                                @endforeach
                                <div class="border-t border-slate-100 px-4 py-2 text-[10px] text-slate-400 dark:border-white/10">
                                    Resolved dynamically per contact on dispatch.
                                </div>
                            </div>
                        </div>

                        {{-- Device toggle for Preview / Dual Pane --}}
                        <div class="flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-white/10 dark:bg-white/[0.04]"
                             x-show="mode === 'preview' || studioSplitView" x-cloak>
                            <button type="button" @click="device = 'desktop'" :class="device === 'desktop' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500'" class="rounded-lg p-1.5" title="Desktop Preview">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </button>
                            <button type="button" @click="device = 'mobile'" :class="device === 'mobile' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500'" class="rounded-lg p-1.5" title="Mobile Preview">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Workspace Body Container --}}
                <div class="flex flex-1 overflow-hidden" :class="{'flex-col lg:flex-row': studioSplitView && mode === 'design'}">

                    {{-- Left Editor Studio --}}
                    <div class="flex flex-1 flex-col overflow-hidden bg-white dark:bg-[#0A0E18]"
                         x-show="mode === 'design'"
                         :class="{'lg:w-1/2 lg:border-r lg:border-slate-200 dark:lg:border-white/10': studioSplitView}">
                        {{-- Quick Token Chips --}}
                        <div class="flex items-center gap-1.5 px-4 py-2 bg-slate-50/80 dark:bg-white/[0.02] border-b border-slate-200 dark:border-white/[0.06] overflow-x-auto text-[11px]">
                            <span class="text-slate-400 font-medium shrink-0">Quick Tokens:</span>
                            @foreach(array_slice($mergeTags, 0, 4) as $tag)
                                <button type="button" @click="insert(@js($tag['tag']))"
                                        class="px-2 py-0.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.04] text-slate-700 dark:text-slate-300 font-mono hover:border-indigo-500/40 hover:text-indigo-600 transition-colors shrink-0">
                                    {{ $tag['tag'] }}
                                </button>
                            @endforeach
                            <button type="button" @click="insertSpintaxGroup()"
                                    class="px-2 py-0.5 rounded-lg border border-indigo-200 dark:border-indigo-500/30 bg-indigo-50/50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-mono font-semibold hover:bg-indigo-100 transition-colors shrink-0">
                                {Spintax Group}
                            </button>
                        </div>

                        {{-- Docked Quill Editor Toolbar & Content --}}
                        <div id="quill-toolbar" class="flex-shrink-0"></div>
                        <div x-ref="editor" class="flex-1 overflow-y-auto"></div>
                        <textarea name="editor_html" x-ref="editorHtml" class="hidden">{{ old('editor_html', $campaign->editor_html ?? '') }}</textarea>
                    </div>

                    {{-- Plain Text Editor --}}
                    <div class="flex-1 overflow-y-auto bg-white p-6 dark:bg-[#0A0E18]" x-show="mode === 'text'" x-cloak>
                        <label for="body_text" class="input-label">Plain-text fallback alternative</label>
                        <p class="mb-3 text-xs text-slate-400">
                            Leave empty and our server compiler automatically generates a clean plain-text counterpart. A missing text alternative increases spam heuristic scores.
                        </p>
                        <textarea id="body_text" name="body_text" rows="22"
                                  class="w-full resize-none rounded-2xl border-slate-200 bg-slate-50 p-6 font-mono text-xs text-slate-800 shadow-inner dark:border-slate-800 dark:bg-[#070A10] dark:text-slate-300">{{ old('body_text', $campaign->body_text ?? '') }}</textarea>
                    </div>

                    {{-- Simulated macOS Email Client Canvas (Shown in Preview Mode OR Side-by-Side Split Studio) --}}
                    <div class="flex flex-1 flex-col overflow-y-auto p-4 sm:p-6 bg-slate-100 dark:bg-[#070A10]"
                         x-show="mode === 'preview' || (studioSplitView && mode === 'design')"
                         :class="{'lg:w-1/2': studioSplitView && mode === 'design'}"
                         x-cloak>

                        <div class="mx-auto w-full transition-all duration-300" :class="device === 'mobile' ? 'max-w-[375px]' : 'max-w-2xl'">
                            {{-- macOS Client Window Chrome with Traffic Lights --}}
                            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-[#111624]">
                                {{-- Traffic Light Header --}}
                                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/90 px-4 py-2.5 dark:border-white/10 dark:bg-[#0D111C]">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full bg-[#ff5f56]"></div>
                                        <div class="w-3 h-3 rounded-full bg-[#ffbd2e]"></div>
                                        <div class="w-3 h-3 rounded-full bg-[#27c93f]"></div>
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            Apple Mail / Desktop
                                        </span>
                                    </div>
                                    <div class="w-12"></div>
                                </div>

                                {{-- Email Headers Simulation --}}
                                <div class="border-b border-slate-100 bg-slate-50/50 p-4 dark:border-white/5 dark:bg-[#0A0E18]/50 space-y-2">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-[11px] font-semibold text-slate-400 w-12 shrink-0">Subject:</span>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate" x-text="preview.subject || subject || 'Subject line preview'"></h4>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="text-[11px] font-semibold text-slate-400 w-12 shrink-0">From:</span>
                                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 font-medium text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                            <span>{{ auth()->user()->tenant?->name ?? 'EliteSender' }} &lt;broadcast@yourrelay.com&gt;</span>
                                        </div>
                                    </div>
                                    <div class="flex items-baseline gap-2" x-show="preview.preheader || preheader">
                                        <span class="text-[11px] font-semibold text-slate-400 w-12 shrink-0">Snippet:</span>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 italic truncate" x-text="preview.preheader || preheader"></p>
                                    </div>
                                </div>

                                {{-- Server Rendered HTML Canvas --}}
                                <div class="min-h-[420px] bg-white text-slate-900 p-6 overflow-x-auto text-sm leading-relaxed"
                                     x-html="preview.html || '<div class=\'py-16 text-center text-slate-400 text-xs\'><svg class=\'w-8 h-8 mx-auto mb-2 opacity-50\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z\'/></svg>Server preview ready. Type in the composer to render.</div>'">
                                </div>
                            </div>
                            <div class="mt-2.5 flex items-center justify-between text-[11px] text-slate-400 px-1">
                                <span x-show="loadingPreview" class="inline-flex items-center gap-1.5 text-indigo-500">
                                    <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Refreshing server preview…
                                </span>
                                <span x-show="!loadingPreview">Server-side preview via EmailHtmlRenderer</span>
                                <span class="font-mono">Viewport: <span x-text="device === 'mobile' ? '375px' : 'Desktop 100%'"></span></span>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Deliverability Telemetry Dock (Bottom Bar) --}}
                <div class="p-3 border-t border-slate-200 dark:border-white/[0.08] bg-white/95 dark:bg-[#0A0E18]/95 backdrop-blur-md flex flex-wrap items-center justify-between gap-3 text-xs flex-shrink-0">
                    <div class="flex items-center gap-4">
                        {{-- SpamAssassin Donut / Badge --}}
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-[10px] font-black text-emerald-600 dark:text-emerald-400">
                                0.4
                            </div>
                            <div>
                                <span class="font-bold text-slate-900 dark:text-white block leading-tight">SpamAssassin: 0.4 / 10</span>
                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">Primed for Primary Inbox</span>
                            </div>
                        </div>

                        {{-- Spam Trigger Keyword Watchdog --}}
                        <div class="hidden sm:flex items-center gap-1.5 pl-4 border-l border-slate-200 dark:border-white/10" x-show="spamTriggersFound.length > 0">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold" x-text="spamTriggersFound.length + ' trigger flags found'"></span>
                        </div>
                    </div>

                    {{-- Pre-Flight Checklist Chips --}}
                    <div class="flex items-center gap-3 sm:gap-4 text-slate-500 dark:text-slate-400">
                        <span class="flex items-center gap-1 text-[11px]">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            SPF/DKIM Aligned
                        </span>
                        <span class="flex items-center gap-1 text-[11px]">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Unsubscribe Tag Included
                        </span>
                        <span class="flex items-center gap-1 text-[11px]">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Plain-Text Synchronized
                        </span>
                    </div>
                </div>
            </div>
        </form>

        {{-- Test Send Modal --}}
        @isset($campaign)
            <x-modal x-show="testSendModal" @close-modal="testSendModal = false" title="Send Inbox Test" size="sm">
                <form method="POST" action="{{ route('campaigns.test-send', $campaign->id) }}" x-data="{ sendingTest: false }" @submit="sendingTest = true" class="space-y-4">
                    @csrf
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Sends a test broadcast through your configured relay to check real rendering across Gmail, Outlook, and Apple Mail.
                    </p>
                    <div>
                        <label class="input-label" for="test-email-input">Recipient test address</label>
                        <input id="test-email-input" type="email" name="test_email" required
                               value="{{ auth()->user()->email }}"
                               class="input text-xs" placeholder="you@company.com">
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/[0.06]">
                        <x-button type="button" variant="secondary" @click="testSendModal = false">Cancel</x-button>
                        <x-button type="submit" variant="primary" x-bind:disabled="sendingTest">
                            <svg x-show="sendingTest" class="w-3.5 h-3.5 animate-spin mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-show="!sendingTest">Send Test Now</span><span x-show="sendingTest">Sending…</span>
                        </x-button>
                    </div>
                </form>
            </x-modal>
        @endisset
    </div>

    @push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        window.campaignBuilderData = () => ({
            currentStep: 2,
            mode: 'design',
            device: 'desktop',
            studioSplitView: window.innerWidth >= 1280,
            saving: false,
            loadingPreview: false,
            testSendModal: false,
            quill: null,
            subject: @json(old('subject', $campaign->subject ?? '')),
            preheader: @json(old('preheader', $campaign->preheader ?? '')),
            preview: { subject: '', preheader: '', html: '' },
            spamTriggersFound: [],

            init() {
                this.$nextTick(() => this.mountEditor());
                this.checkSpamTriggers();
            },

            mountEditor() {
                if (!window.Quill) {
                    setTimeout(() => this.mountEditor(), 50);
                    return;
                }

                this.quill = new window.Quill(this.$refs.editor, {
                    theme: 'snow',
                    placeholder: 'Compose your email narrative or announce an update…',
                    modules: {
                        toolbar: {
                            container: [
                                [{ header: [1, 2, 3, false] }],
                                [{ size: ['small', false, 'large', 'huge'] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ color: [] }, { background: [] }],
                                [{ align: [] }],
                                ['link', 'image'],
                                ['clean'],
                            ],
                            handlers: {
                                image: () => this.handleImage(),
                            },
                        },
                    },
                });

                // Dock Quill's generated toolbar into #quill-toolbar above editor
                const toolbar = this.$refs.editor.previousElementSibling;
                if (toolbar && toolbar.classList.contains('ql-toolbar')) {
                    const host = document.getElementById('quill-toolbar');
                    if (host) host.appendChild(toolbar);
                }

                if (this.$refs.editorHtml.value) {
                    this.quill.root.innerHTML = this.$refs.editorHtml.value;
                }

                this.quill.on('text-change', () => {
                    this.syncEditor();
                    if (this.studioSplitView) {
                        this.debouncedPreview();
                    }
                });
            },

            debouncedTimer: null,
            debouncedPreview() {
                clearTimeout(this.debouncedTimer);
                this.debouncedTimer = setTimeout(() => this.refreshPreview(), 600);
            },

            handleImage() {
                const url = prompt('Image URL (must be publicly reachable HTTPS):');
                if (url && this.quill) {
                    const range = this.quill.getSelection(true);
                    this.quill.insertEmbed(range.index, 'image', url);
                }
            },

            syncEditor() {
                if (this.quill && this.$refs.editorHtml) {
                    const html = this.quill.root.innerHTML;
                    this.$refs.editorHtml.value = html === '<p><br></p>' ? '' : html;
                }
                this.checkSpamTriggers();
                return true;
            },

            insert(tag) {
                if (!this.quill) return;
                const range = this.quill.getSelection(true);
                this.quill.insertText(range.index, tag);
                this.quill.setSelection(range.index + tag.length);
                this.syncEditor();
            },

            insertSpintaxGroup() {
                this.insert('{Awesome|Great|Exclusive}');
            },

            get spintaxPermutations() {
                const matches = this.subject.match(/\{([^{}]+)\}/g);
                if (!matches) return 1;
                return matches.reduce((acc, m) => acc * m.replace(/[{}]/g, '').split('|').length, 1);
            },

            testSpintax() {
                let rolled = this.subject.replace(/\{([^{}]+)\}/g, (_, group) => {
                    const options = group.split('|');
                    return options[Math.floor(Math.random() * options.length)];
                });
                this.preview.subject = rolled;
            },

            checkSpamTriggers() {
                const triggers = ['FREE', 'BUY NOW', 'ACT NOW', '100% FREE', 'CLICK HERE', '$$$', 'CONGRATULATIONS', 'RISK FREE'];
                const text = (this.subject + ' ' + (this.$refs.editorHtml ? this.$refs.editorHtml.value : '')).toUpperCase();
                this.spamTriggersFound = triggers.filter(t => text.includes(t));
            },

            async refreshPreview() {
                this.syncEditor();
                this.loadingPreview = true;

                try {
                    const response = await fetch(@js(route('campaigns.preview')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            editor_html: this.$refs.editorHtml.value,
                            subject: this.subject,
                            preheader: this.preheader,
                        }),
                    });

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);

                    this.preview = (await response.json()).data;
                } catch (error) {
                    // Fail silently or notify on deliberate user click
                } finally {
                    this.loadingPreview = false;
                }
            },
        });

        document.addEventListener('alpine:init', () => Alpine.data('campaignBuilder', window.campaignBuilderData));
        if (window.Alpine) window.Alpine.data('campaignBuilder', window.campaignBuilderData);
    </script>
    @endpush

    @push('styles')
    <style nonce="{{ $cspNonce ?? '' }}">
        #quill-toolbar .ql-toolbar { border: 0 !important; border-bottom: 1px solid #e2e8f0 !important; background: #f8fafc; }
        .dark #quill-toolbar .ql-toolbar { border-bottom-color: rgba(255,255,255,.08) !important; background: #0D111C; }
        .ql-container { border: 0 !important; font-size: 14px !important; }
        .ql-editor { min-height: 100%; padding: 1.5rem; }
        .dark .ql-editor { color: #f1f5f9; }
        .ql-editor.ql-blank::before { color: #94a3b8; font-style: normal; left: 1.5rem; }
        .ql-editor p { margin-bottom: .75em; }
        .ql-editor ul { list-style: disc !important; padding-left: 1.5em !important; margin-bottom: 1em; }
        .ql-editor ol { list-style: decimal !important; padding-left: 1.5em !important; margin-bottom: 1em; }
        .ql-editor h1 { font-size: 1.75rem !important; font-weight: 700 !important; margin-bottom: .5em !important; }
        .ql-editor h2 { font-size: 1.35rem !important; font-weight: 600 !important; margin-bottom: .5em !important; }
        .ql-editor h3 { font-size: 1.15rem !important; font-weight: 600 !important; margin-bottom: .5em !important; }
        .ql-editor a { color: #6366f1; text-decoration: underline; }
        .ql-editor img { max-width: 100%; height: auto; border-radius: 8px; }
        .ql-editor blockquote { border-left: 3px solid #6366f1; padding-left: 1em; color: #64748b; font-style: italic; margin-bottom: 1em; }
        .dark .ql-picker-options { background: #0D111C !important; border-color: rgba(255,255,255,.1) !important; }
        .dark .ql-picker-item, .dark .ql-picker-label { color: #cbd5e1 !important; }
        .dark .ql-stroke { stroke: #cbd5e1 !important; }
        .dark .ql-fill { fill: #cbd5e1 !important; }
    </style>
    @endpush
</x-layouts.app>
