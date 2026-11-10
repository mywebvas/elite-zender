{{--
    Campaign Studio & Distraction-Free Composer.

    The preview is rendered server-side through EmailHtmlRenderer — the same
    pipeline that sends the message. A preview assembled in JavaScript is a
    different renderer and therefore a lie: it was showing Quill's class-based
    styling, which no email client applies.
--}}
<x-layouts.app :header="isset($campaign) ? 'Edit Campaign' : 'Create Campaign'">
    <div x-data="campaignBuilder()" class="-mx-4 -mt-6 lg:-mx-8 flex flex-col min-h-[calc(100vh-4rem)] bg-slate-50/50 dark:bg-[#07090e]">

        {{-- Top Minimal Studio Navigation Bar --}}
        <div class="px-4 sm:px-6 py-3 border-b border-slate-200/80 dark:border-white/[0.06] bg-white/95 dark:bg-[#0c0d12]/95 backdrop-blur-md flex-shrink-0 z-20">
            <div class="max-w-6xl mx-auto flex items-center justify-between gap-4">

                {{-- Left: Back to Campaigns & Title --}}
                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('campaigns.index') }}"
                       class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-white/[0.04] dark:hover:text-slate-300 transition-colors"
                       title="Back to campaigns">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-900 dark:text-white truncate">
                                {{ isset($campaign) ? ($campaign->name ?: 'Untitled Campaign') : 'New Campaign' }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400">
                                {{ isset($campaign) ? ucfirst($campaign->status) : 'Draft' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Center: Segmented Mode Switcher (Write / Preview / Text) --}}
                <div class="inline-flex p-0.5 sm:p-1 rounded-xl bg-slate-100 dark:bg-white/[0.04] border border-slate-200/80 dark:border-white/[0.06] text-xs font-medium">
                    <button type="button" @click="mode = 'design'"
                            :class="mode === 'design' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs font-semibold' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            class="px-2.5 sm:px-3 py-1 rounded-lg transition-all flex items-center gap-1 sm:gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span class="hidden sm:inline">Write</span>
                    </button>
                    <button type="button" @click="mode = 'preview'; refreshPreview()"
                            :class="mode === 'preview' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs font-semibold' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            class="px-2.5 sm:px-3 py-1 rounded-lg transition-all flex items-center gap-1 sm:gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span class="hidden sm:inline">Preview</span>
                    </button>
                    <button type="button" @click="mode = 'text'"
                            :class="mode === 'text' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs font-semibold' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            class="px-2.5 sm:px-3 py-1 rounded-lg transition-all flex items-center gap-1 sm:gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                        <span class="hidden sm:inline">Plain Text</span>
                    </button>
                </div>

                {{-- Right: Actions & Settings Drawer Toggle --}}
                <div class="flex items-center gap-2">
                    @isset($campaign)
                        <button type="button" @click="testSendModal = true"
                                class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-white/[0.04] text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/[0.08] transition-colors">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <span>Test Send</span>
                        </button>
                    @endisset

                    <button type="button" @click="settingsDrawerOpen = !settingsDrawerOpen"
                            class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl border transition-colors text-xs font-semibold"
                            :class="settingsDrawerOpen ? 'border-indigo-500/40 bg-indigo-50/50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400' : 'border-slate-200/80 dark:border-white/10 bg-white dark:bg-white/[0.04] text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/[0.08]'">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="hidden sm:inline">Parameters</span>
                    </button>

                    <button type="submit" form="campaign-form"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors"
                            :disabled="saving">
                        <svg x-show="saving" x-cloak class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="saving ? 'Saving…' : 'Save'"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Main Composer Container --}}
        <div class="relative flex-1 flex overflow-hidden">

            <form id="campaign-form" method="POST"
                  action="{{ isset($campaign) ? route('campaigns.update', $campaign->id) : route('campaigns.store') }}"
                  class="flex-1 flex overflow-y-auto"
                  @submit="if (saving) { $event.preventDefault(); return false; } syncEditor(); saving = true;">
                @csrf
                @isset($campaign)
                    @method('PUT')
                @endisset

                {{-- Hidden Fields & Parameters Drawer --}}
                <input type="hidden" name="editor_html" x-ref="editorHtml" value="{{ old('editor_html', $campaign->editor_html ?? '') }}">

                {{-- Notion-Style Writing Sheet --}}
                <div class="flex-1 overflow-y-auto px-4 py-8 flex justify-center">

                    {{-- 1. Write / Design Mode Canvas --}}
                    <div x-show="mode === 'design'" class="w-full max-w-3xl space-y-4">

                        @if ($errors->any())
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/50 dark:bg-rose-900/20" role="alert">
                                <p class="text-xs font-semibold text-rose-800 dark:text-rose-300">Please review the following:</p>
                                <ul class="mt-1 list-disc pl-5 text-xs text-rose-700 dark:text-rose-400 space-y-0.5">
                                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Distraction-Free Paper --}}
                        <div class="card overflow-hidden border border-slate-200/80 dark:border-white/[0.08] shadow-sm bg-white dark:bg-[#0c0d12]">

                            {{-- Subject & Preheader Inputs --}}
                            <div class="p-6 pb-4 border-b border-slate-100 dark:border-white/[0.06] space-y-3">
                                <div>
                                    <input id="subject" type="text" name="subject" x-model="subject"
                                           @input.debounce.400ms="debouncedPreview"
                                           required maxlength="200"
                                           placeholder="Subject line…"
                                           class="w-full text-xl sm:text-2xl font-bold text-slate-900 dark:text-white placeholder:text-slate-300 dark:placeholder:text-slate-600 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none">
                                    @error('subject')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                                </div>

                                <div class="flex items-center gap-3">
                                    <input id="preheader" type="text" name="preheader" x-model="preheader"
                                           @input.debounce.400ms="debouncedPreview"
                                           maxlength="255"
                                           placeholder="Preview text (optional snippet shown next to subject line in inbox)…"
                                           class="w-full text-xs text-slate-600 dark:text-slate-300 placeholder:text-slate-400 dark:placeholder:text-slate-600 bg-transparent border-0 p-0 focus:ring-0 focus:outline-none">
                                    <span class="text-[10px] font-mono text-slate-400 tabular-nums flex-shrink-0" x-text="(preheader ? preheader.length : 0) + '/90'"></span>
                                </div>
                            </div>

                            {{-- Personalization Tag Bar & Formatting Controls --}}
                            <div class="px-6 py-2 bg-slate-50/70 dark:bg-white/[0.02] border-b border-slate-100 dark:border-white/[0.06] flex items-center justify-between gap-4">
                                {{-- Merge-tag inserter dropdown (ensures all available tags are rendered in HTML for test assertion) --}}
                                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                    <button type="button" @click="open = !open"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200/80 dark:border-white/10 bg-white dark:bg-white/[0.04] text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition-colors">
                                        <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
                                        <span>Personalize</span>
                                    </button>

                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-30 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200/80 dark:border-white/10 bg-white shadow-xl dark:bg-[#111420] py-1">
                                        @foreach($mergeTags as $tag)
                                            <button type="button" @click="insert(@js($tag['tag'])); open = false"
                                                    class="flex w-full items-center justify-between px-3.5 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-white/[0.04] transition-colors">
                                                <span class="text-slate-700 dark:text-slate-300 font-medium">{{ $tag['label'] }}</span>
                                                <code class="text-[11px] text-indigo-600 dark:text-indigo-400 font-mono font-bold">{{ $tag['tag'] }}</code>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Quick Tag Pills --}}
                                <div class="hidden sm:flex items-center gap-1.5 overflow-x-auto text-[11px]">
                                    @foreach(array_slice($mergeTags, 0, 3) as $tag)
                                        <button type="button" @click="insert(@js($tag['tag']))"
                                                class="px-2 py-0.5 rounded-md border border-slate-200/80 dark:border-white/10 bg-white dark:bg-white/[0.04] text-slate-600 dark:text-slate-300 font-mono hover:text-indigo-600 transition-colors">
                                            {{ $tag['tag'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Quill Editor Host --}}
                            <div id="quill-toolbar" class="border-b border-slate-100 dark:border-white/[0.06]"></div>
                            <div x-ref="editor" class="min-h-[420px] p-6 text-sm text-slate-800 dark:text-slate-200 leading-relaxed"></div>
                        </div>
                    </div>

                    {{-- 2. Preview Mode Canvas --}}
                    <div x-show="mode === 'preview'" x-cloak class="w-full max-w-3xl space-y-4">
                        {{-- Device Switcher --}}
                        <div class="flex items-center justify-between px-2">
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                Real server-side render via <code class="font-mono text-indigo-500">EmailHtmlRenderer</code>
                            </span>
                            <div class="inline-flex p-1 rounded-xl bg-white dark:bg-white/[0.04] border border-slate-200/80 dark:border-white/10 text-xs">
                                <button type="button" @click="device = 'desktop'"
                                        :class="device === 'desktop' ? 'bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-500'"
                                        class="px-2.5 py-1 rounded-lg">Desktop</button>
                                <button type="button" @click="device = 'mobile'"
                                        :class="device === 'mobile' ? 'bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-500'"
                                        class="px-2.5 py-1 rounded-lg">Mobile</button>
                            </div>
                        </div>

                        {{-- Simulated Client Container --}}
                        <div class="mx-auto transition-all duration-300" :class="device === 'mobile' ? 'max-w-[380px]' : 'w-full'">
                            <div class="card overflow-hidden border border-slate-200/80 dark:border-white/10 bg-white dark:bg-[#0c0d12] shadow-lg">
                                {{-- Client Chrome Header --}}
                                <div class="px-4 py-3 bg-slate-50/80 dark:bg-white/[0.02] border-b border-slate-100 dark:border-white/[0.06] flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-white/20"></span>
                                        <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-white/20"></span>
                                        <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-white/20"></span>
                                    </div>
                                    <span class="text-[11px] text-slate-400">Recipient Preview</span>
                                </div>

                                {{-- Header Summary --}}
                                <div class="p-4 border-b border-slate-100 dark:border-white/[0.06] space-y-1.5 bg-slate-50/30 dark:bg-transparent">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-xs text-slate-400 font-medium w-14 shrink-0">Subject:</span>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white" x-text="preview.subject || subject || 'No subject set'"></span>
                                    </div>
                                    <div class="flex items-baseline gap-2" x-show="preview.preheader || preheader">
                                        <span class="text-xs text-slate-400 font-medium w-14 shrink-0">Snippet:</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400 italic" x-text="preview.preheader || preheader"></span>
                                    </div>
                                </div>

                                {{-- Server Rendered Body --}}
                                <div class="p-6 bg-white text-slate-900 min-h-[360px] overflow-x-auto text-sm"
                                     x-html="preview.html || '<div class=\'py-16 text-center text-slate-400 text-xs\'>Generating preview…</div>'">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Plain Text Mode Canvas --}}
                    <div x-show="mode === 'text'" x-cloak class="w-full max-w-3xl space-y-3">
                        <div class="card p-6 border border-slate-200/80 dark:border-white/[0.08] bg-white dark:bg-[#0c0d12]">
                            <label for="body_text" class="text-sm font-bold text-slate-900 dark:text-white block mb-1">Plain-text Alternative</label>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                                Optional fallback part for text-only email clients. Leave empty to automatically synchronize with your rich copy.
                            </p>
                            <textarea id="body_text" name="body_text" rows="18"
                                      placeholder="Leave empty for automatic compilation from your rich editor…"
                                      class="w-full rounded-xl border border-slate-200/80 dark:border-white/10 bg-slate-50 dark:bg-white/[0.02] p-4 font-mono text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-500">{{ old('body_text', $campaign->body_text ?? '') }}</textarea>
                        </div>
                    </div>

                </div>

                {{-- Mobile Backdrop for Drawer --}}
                <div x-show="settingsDrawerOpen" x-cloak
                     @click="settingsDrawerOpen = false"
                     class="sm:hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40"
                     x-transition:enter="transition-opacity ease-linear duration-150"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-linear duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0">
                </div>

                {{-- Slide-Over Parameters Drawer --}}
                <div x-show="settingsDrawerOpen" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="fixed inset-y-0 right-0 z-50 w-full sm:w-80 sm:relative border-l border-slate-200/80 dark:border-white/[0.06] bg-white dark:bg-[#0c0d12] flex-shrink-0 flex flex-col shadow-2xl sm:shadow-lg">
                    
                    <div class="p-4 border-b border-slate-100 dark:border-white/[0.06] flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">Campaign Parameters</h3>
                        <button type="button" @click="settingsDrawerOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-5 space-y-4 overflow-y-auto flex-1">
                        {{-- Campaign Internal Name --}}
                        <div>
                            <label for="name" class="input-label">Internal campaign name</label>
                            <input id="name" type="text" name="name" required maxlength="120"
                                   value="{{ old('name', $campaign->name ?? '') }}"
                                   class="input text-xs"
                                   placeholder="e.g. Product Announcement">
                            <p class="mt-1 text-[11px] text-slate-400">Recipients never see this internal title.</p>
                            @error('name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                        </div>

                        {{-- Target Audience List --}}
                        <div>
                            <label for="list_id" class="input-label">Audience list</label>
                            <select id="list_id" name="list_id" class="input text-xs">
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
                            <label for="smtp" class="input-label">Sending relay pool <span class="text-slate-400 font-normal">(optional)</span></label>
                            <select id="smtp" name="smtp_account_ids[]" multiple size="3" class="input text-xs">
                                @foreach($smtpAccounts as $smtp)
                                    <option value="{{ $smtp->id }}" @selected(isset($campaign) && $campaign->smtpAccounts->contains($smtp->id))>
                                        {{ $smtp->name }} ({{ $smtp->from_email }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-slate-400">Leave unselected to automatically rotate through all active relays.</p>
                        </div>

                        {{-- Custom Reply-To --}}
                        <div>
                            <label for="reply_to" class="input-label">Reply-to email <span class="text-slate-400 font-normal">(optional)</span></label>
                            <input id="reply_to" type="email" name="reply_to" maxlength="255"
                                   value="{{ old('reply_to', $campaign->reply_to ?? '') }}"
                                   class="input text-xs"
                                   placeholder="support@yourdomain.com">
                            @error('reply_to')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                        </div>

                        {{-- Schedule Broadcast --}}
                        <div>
                            <label for="scheduled_at" class="input-label">Schedule send <span class="text-slate-400 font-normal">(optional)</span></label>
                            <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                                   value="{{ old('scheduled_at', isset($campaign) && $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '') }}"
                                   class="input text-xs">
                            <p class="mt-1 text-[11px] text-slate-400">Timezone: {{ config('app.timezone') }}</p>
                            @error('scheduled_at')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

            </form>
        </div>

        {{-- Test Send Modal --}}
        @isset($campaign)
            <x-modal x-show="testSendModal" @close-modal="testSendModal = false" title="Send Test Email" size="sm">
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
                            <span x-show="!sendingTest">Send Test</span><span x-show="sendingTest">Sending…</span>
                        </x-button>
                    </div>
                </form>
            </x-modal>
        @endisset
    </div>

    @push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        window.campaignBuilderData = () => ({
            mode: 'design',
            device: 'desktop',
            settingsDrawerOpen: false,
            saving: false,
            loadingPreview: false,
            testSendModal: false,
            quill: null,
            subject: @json(old('subject', $campaign->subject ?? '')),
            preheader: @json(old('preheader', $campaign->preheader ?? '')),
            preview: { subject: '', preheader: '', html: '' },

            init() {
                this.$nextTick(() => this.mountEditor());
            },

            mountEditor() {
                if (!window.Quill) {
                    setTimeout(() => this.mountEditor(), 50);
                    return;
                }

                this.quill = new window.Quill(this.$refs.editor, {
                    theme: 'snow',
                    placeholder: 'Type your message or announcement…',
                    modules: {
                        toolbar: {
                            container: [
                                [{ header: [1, 2, 3, false] }],
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
                });
            },

            debouncedTimer: null,
            debouncedPreview() {
                clearTimeout(this.debouncedTimer);
                this.debouncedTimer = setTimeout(() => {
                    if (this.mode === 'preview') {
                        this.refreshPreview();
                    }
                }, 600);
            },

            handleImage() {
                const url = prompt('Image URL (public HTTPS):');
                if (url && (url.startsWith('https://') || url.startsWith('http://'))) {
                    if (this.quill) {
                        const range = this.quill.getSelection(true);
                        this.quill.insertEmbed(range.index, 'image', url);
                    }
                } else if (url) {
                    alert('Please provide a secure (https://) image URL.');
                }
            },

            syncEditor() {
                if (this.quill && this.$refs.editorHtml) {
                    const html = this.quill.root.innerHTML;
                    this.$refs.editorHtml.value = html === '<p><br></p>' ? '' : html;
                }
                return true;
            },

            insert(tag) {
                if (!this.quill) return;
                const range = this.quill.getSelection(true);
                this.quill.insertText(range.index, tag);
                this.quill.setSelection(range.index + tag.length);
                this.syncEditor();
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
                    // Preview gracefully degrades
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
        #quill-toolbar .ql-toolbar { border: 0 !important; background: transparent; padding: 0.5rem 1.25rem !important; }
        .ql-container { border: 0 !important; font-size: 15px !important; }
        .ql-editor { min-height: 380px; padding: 1.5rem; line-height: 1.7; }
        .dark .ql-editor { color: #f1f5f9; }
        .ql-editor.ql-blank::before { color: #94a3b8; font-style: normal; left: 1.5rem; }
        .ql-editor p { margin-bottom: 0.85em; }
        .ql-editor ul { list-style: disc !important; padding-left: 1.5em !important; margin-bottom: 1em; }
        .ql-editor ol { list-style: decimal !important; padding-left: 1.5em !important; margin-bottom: 1em; }
        .ql-editor h1 { font-size: 1.65rem !important; font-weight: 700 !important; margin-bottom: .5em !important; }
        .ql-editor h2 { font-size: 1.3rem !important; font-weight: 600 !important; margin-bottom: .5em !important; }
        .ql-editor h3 { font-size: 1.1rem !important; font-weight: 600 !important; margin-bottom: .5em !important; }
        .ql-editor a { color: #4f46e5; text-decoration: underline; }
        .ql-editor img { max-width: 100%; height: auto; border-radius: 8px; }
        .dark .ql-picker-options { background: #0c0d12 !important; border-color: rgba(255,255,255,.1) !important; }
        .dark .ql-picker-item, .dark .ql-picker-label { color: #cbd5e1 !important; }
        .dark .ql-stroke { stroke: #cbd5e1 !important; }
        .dark .ql-fill { fill: #cbd5e1 !important; }
    </style>
    @endpush
</x-layouts.app>
