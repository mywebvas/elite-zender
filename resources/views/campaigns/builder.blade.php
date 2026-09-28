{{--
    Campaign composer.

    The preview is rendered server-side through EmailHtmlRenderer — the same
    pipeline that sends the message. A preview assembled in JavaScript is a
    different renderer and therefore a lie: it was showing Quill's class-based
    styling, which no email client applies.
--}}
<x-layouts.app :header="isset($campaign) ? 'Edit Campaign' : 'Create Campaign'">
    <div x-data="campaignBuilder()" class="-mx-4 -mt-6 lg:-mx-8">

        <form method="POST"
              action="{{ isset($campaign) ? route('campaigns.update', $campaign->id) : route('campaigns.store') }}"
              class="flex h-[calc(100vh-4rem)] flex-col lg:flex-row"
              @submit="syncEditor">
            @csrf
            @isset($campaign)
                @method('PUT')
            @endisset

            {{-- ───────────────────────── Settings ───────────────────────── --}}
            <aside class="z-10 flex w-full flex-col overflow-y-auto border-r border-slate-200 bg-white lg:w-[380px] dark:border-white/10 dark:bg-[#0A0A0A]">

                <div class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-slate-200 bg-white/90 p-5 backdrop-blur-md dark:border-white/10 dark:bg-[#0A0A0A]/90">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Campaign settings</h2>
                    <x-button type="submit" variant="primary" ::disabled="saving" @click="saving = true">
                        <svg x-show="saving" x-cloak class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="saving ? 'Saving…' : 'Save draft'"></span>
                    </x-button>
                </div>

                <div class="flex-1 space-y-6 p-5">

                    @if ($errors->any())
                        <div class="rounded-xl border border-rose-100 bg-rose-50 p-4 dark:border-rose-900/50 dark:bg-rose-900/20" role="alert">
                            <p class="text-sm font-medium text-rose-800 dark:text-rose-300">Please fix the following:</p>
                            <ul class="mt-2 list-disc pl-5 text-sm text-rose-700 dark:text-rose-400">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <label for="name" class="input-label">Campaign name</label>
                        <input id="name" type="text" name="name" required maxlength="120"
                               value="{{ old('name', $campaign->name ?? '') }}"
                               class="input @error('name') ring-rose-400 @enderror"
                               placeholder="Q3 newsletter">
                        <p class="mt-1 text-xs text-slate-400">Internal only — recipients never see this.</p>
                        @error('name')<p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="mb-2 flex items-baseline justify-between">
                            <label for="subject" class="input-label mb-0">Subject line</label>
                            <span class="text-xs tabular-nums" :class="subject.length > 60 ? 'text-amber-500' : 'text-slate-400'" x-text="subject.length + '/60'"></span>
                        </div>
                        <input id="subject" type="text" name="subject" x-model="subject" @input.debounce.400ms="refreshPreview"
                               required maxlength="200"
                               class="input @error('subject') ring-rose-400 @enderror"
                               placeholder="{Hi|Hello} [Name], your July update">
                        <p class="mt-1 text-xs text-slate-400">Most inboxes truncate past ~60 characters. Supports spin syntax and merge tags.</p>
                        @error('subject')<p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="mb-2 flex items-baseline justify-between">
                            <label for="preheader" class="input-label mb-0">Preview text</label>
                            <span class="text-xs tabular-nums" :class="preheader.length > 90 ? 'text-amber-500' : 'text-slate-400'" x-text="preheader.length + '/90'"></span>
                        </div>
                        <input id="preheader" type="text" name="preheader" x-model="preheader" @input.debounce.400ms="refreshPreview"
                               maxlength="255"
                               class="input @error('preheader') ring-rose-400 @enderror"
                               placeholder="The one line that shows next to your subject">
                        <p class="mt-1 text-xs text-slate-400">
                            Leave this empty and Gmail scrapes the first words of your email instead — usually a merge tag or "view in browser".
                        </p>
                        @error('preheader')<p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="reply_to" class="input-label">Reply-to address <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="reply_to" type="email" name="reply_to" maxlength="255"
                               value="{{ old('reply_to', $campaign->reply_to ?? '') }}"
                               class="input @error('reply_to') ring-rose-400 @enderror"
                               placeholder="support@yourdomain.com">
                        <p class="mt-1 text-xs text-slate-400">Defaults to the sending relay's address.</p>
                        @error('reply_to')<p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="list_id" class="input-label">Audience</label>
                        <select id="list_id" name="list_id" class="input @error('list_id') ring-rose-400 @enderror">
                            <option value="">Select a list…</option>
                            @foreach($lists as $list)
                                <option value="{{ $list->id }}" @selected(old('list_id', $campaign->list_id ?? null) === $list->id)>
                                    {{ $list->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('list_id')<p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="smtp" class="input-label">Sending relays <span class="font-normal text-slate-400">(optional)</span></label>
                        <select id="smtp" name="smtp_account_ids[]" multiple size="4" class="input">
                            @foreach($smtpAccounts as $smtp)
                                <option value="{{ $smtp->id }}" @selected(isset($campaign) && $campaign->smtpAccounts->contains($smtp->id))>
                                    {{ $smtp->name }} — {{ $smtp->from_email }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Leave empty to rotate across every healthy relay.</p>
                    </div>

                    <div>
                        <label for="scheduled_at" class="input-label">Schedule <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                               value="{{ old('scheduled_at', isset($campaign) && $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '') }}"
                               class="input @error('scheduled_at') ring-rose-400 @enderror">
                        <p class="mt-1 text-xs text-slate-400">Leave empty to send manually. Times are in {{ config('app.timezone') }}.</p>
                        @error('scheduled_at')<p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Test send lives outside the main form: nested forms are
                     invalid HTML, so it posts on its own. --}}
                @isset($campaign)
                    <div class="border-t border-slate-200 bg-slate-50 p-5 dark:border-white/10 dark:bg-white/[0.02]">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Send a test</h3>
                        <div class="flex gap-2">
                            <input type="email" form="test-send-form" name="test_email" required
                                   value="{{ auth()->user()->email }}"
                                   class="input flex-1 text-sm" placeholder="you@company.com">
                            <x-button type="submit" form="test-send-form" variant="secondary">Send</x-button>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">Saves nothing — sends the last saved version to one address.</p>
                    </div>
                @endisset
            </aside>

            {{-- ───────────────────────── Editor ───────────────────────── --}}
            <div class="relative flex w-full flex-1 flex-col overflow-hidden bg-slate-100 dark:bg-slate-950/50">

                <div class="z-10 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-[#0A0A0A]">
                    <div class="flex rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-[#111111]">
                        @foreach (['design' => 'Design', 'text' => 'Plain text', 'preview' => 'Preview'] as $value => $label)
                            <button type="button" @click="mode = '{{ $value }}'; if (mode === 'preview') refreshPreview()"
                                    :class="mode === '{{ $value }}' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                                    class="rounded-md px-4 py-1.5 text-sm font-medium transition-all">{{ $label }}</button>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-3">
                        {{-- Merge-tag inserter: the list comes from
                             App\Support\MergeTags, the same source the renderer
                             resolves against. --}}
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" @click="open = !open"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-[#111111] dark:text-slate-200">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
                                Merge tags
                            </button>
                            <div x-show="open" x-cloak x-transition
                                 class="absolute right-0 z-30 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-premium dark:border-white/10 dark:bg-[#111111] dark:shadow-premium-dark">
                                @foreach($mergeTags as $tag)
                                    <button type="button" @click="insert(@js($tag['tag'])); open = false"
                                            class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-indigo-50 dark:hover:bg-indigo-500/10">
                                        <span class="text-slate-700 dark:text-slate-200">{{ $tag['label'] }}</span>
                                        <code class="text-xs text-indigo-600 dark:text-indigo-400">{{ $tag['tag'] }}</code>
                                    </button>
                                @endforeach
                                <div class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400 dark:border-white/10">
                                    Unresolved tags are removed before sending.
                                </div>
                            </div>
                        </div>

                        <div class="flex rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-[#111111]" x-show="mode === 'preview'" x-cloak>
                            <button type="button" @click="device = 'desktop'" :class="device === 'desktop' ? 'bg-white dark:bg-slate-800 text-indigo-600' : 'text-slate-500'" class="rounded-md px-3 py-1.5" aria-label="Desktop preview">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </button>
                            <button type="button" @click="device = 'mobile'" :class="device === 'mobile' ? 'bg-white dark:bg-slate-800 text-indigo-600' : 'text-slate-500'" class="rounded-md px-3 py-1.5" aria-label="Mobile preview">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Design --}}
                <div class="flex flex-1 flex-col overflow-hidden bg-white dark:bg-[#111111]" x-show="mode === 'design'">
                    <div id="quill-toolbar" class="flex-shrink-0"></div>
                    <div x-ref="editor" class="flex-1 overflow-y-auto"></div>
                    <textarea name="editor_html" x-ref="editorHtml" class="hidden">{{ old('editor_html', $campaign->editor_html ?? '') }}</textarea>
                </div>

                {{-- Plain text --}}
                <div class="flex-1 overflow-y-auto bg-white p-6 dark:bg-[#0A0A0A]" x-show="mode === 'text'" x-cloak>
                    <label for="body_text" class="input-label">Plain-text alternative</label>
                    <p class="mb-3 text-xs text-slate-400">
                        Leave empty and we generate it from your design automatically. Every message needs one — a missing text part is a spam signal.
                    </p>
                    <textarea id="body_text" name="body_text" rows="24"
                              class="w-full resize-none rounded-xl border-slate-200 bg-slate-50 p-6 font-mono text-sm text-slate-800 shadow-inner dark:border-slate-800 dark:bg-[#111111] dark:text-slate-300">{{ old('body_text', $campaign->body_text ?? '') }}</textarea>
                </div>

                {{-- Preview --}}
                <div class="relative flex flex-1 items-start justify-center overflow-y-auto p-8" x-show="mode === 'preview'" x-cloak>
                    <div class="relative z-10 transition-all duration-300" :class="device === 'mobile' ? 'w-[375px]' : 'w-full max-w-3xl'">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-premium dark:border-slate-800 dark:bg-[#1a1a1a] dark:shadow-premium-dark">
                            <div class="border-b border-slate-100 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-[#0A0A0A]">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white" x-text="preview.subject || 'Subject line'"></p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400" x-text="preview.preheader || 'Preview text appears here'"></p>
                            </div>
                            <div class="min-h-[400px] bg-white" x-html="preview.html || '<p style=\'padding:2rem;color:#94a3b8\'>Nothing to preview yet.</p>'"></div>
                        </div>
                        <p class="mt-3 text-center text-xs text-slate-400" x-show="loadingPreview" x-cloak>Rendering…</p>
                    </div>
                </div>
            </div>
        </form>

        @isset($campaign)
            <form id="test-send-form" method="POST" action="{{ route('campaigns.test-send', $campaign->id) }}" class="hidden">@csrf</form>
        @endisset
    </div>

    @push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        window.campaignBuilderData = () => ({
            mode: 'design',
            device: 'desktop',
            saving: false,
            loadingPreview: false,
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
                    placeholder: 'Write your email…',
                    modules: {
                        toolbar: {
                            container: [
                                [{ header: [1, 2, 3, false] }],
                                [{ size: ['small', false, 'large', 'huge'] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ color: [] }, { background: [] }],
                                [{ align: [] }],
                                [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
                                ['link', 'image', 'blockquote', 'code-block'],
                                ['clean'],
                            ],
                            handlers: {
                                // Quill's default image handler base64-inlines
                                // the file, which balloons the message past the
                                // ~102 KB Gmail clipping threshold. Ask for a
                                // URL instead.
                                image: () => {
                                    const url = window.prompt('Image URL (must be publicly reachable):');
                                    if (!url) return;
                                    const range = this.quill.getSelection(true);
                                    this.quill.insertEmbed(range.index, 'image', url, 'user');
                                },
                            },
                        },
                    },
                });

                // Move Quill's generated toolbar into our slot so it sits above
                // the scroll area rather than inside it.
                const toolbar = this.$refs.editor.previousElementSibling;
                if (toolbar && toolbar.classList.contains('ql-toolbar')) {
                    document.getElementById('quill-toolbar').appendChild(toolbar);
                }

                this.quill.root.innerHTML = this.$refs.editorHtml.value || '';
                this.quill.on('text-change', () => this.syncEditor());
            },

            syncEditor() {
                if (this.quill) {
                    const html = this.quill.root.innerHTML;
                    this.$refs.editorHtml.value = html === '<p><br></p>' ? '' : html;
                }
                return true;
            },

            insert(tag) {
                if (this.mode !== 'design' || !this.quill) {
                    this.subject += tag;
                    return;
                }
                const range = this.quill.getSelection(true);
                this.quill.insertText(range ? range.index : 0, tag, 'user');
            },

            /**
             * Ask the server to render the preview. Doing it here rather than
             * in JS guarantees what you see is what the send pipeline produces.
             */
            async refreshPreview() {
                if (this.mode !== 'preview') return;

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
                    window.$toast?.('Could not render the preview.', 'error');
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
        .dark #quill-toolbar .ql-toolbar { border-bottom-color: rgba(255,255,255,.1) !important; background: #1a1a1a; }
        .ql-container { border: 0 !important; font-size: 15px !important; }
        .ql-editor { min-height: 100%; padding: 2rem; }
        .dark .ql-editor { color: #f1f5f9; }
        .ql-editor.ql-blank::before { color: #94a3b8; font-style: normal; left: 2rem; }
        /* Tailwind Preflight resets list and heading styling; Quill needs it back. */
        .ql-editor p { margin-bottom: .75em; }
        .ql-editor ul { list-style: disc !important; padding-left: 1.5em !important; margin-bottom: 1em; }
        .ql-editor ol { list-style: decimal !important; padding-left: 1.5em !important; margin-bottom: 1em; }
        .ql-editor h1 { font-size: 1.875rem !important; font-weight: 700 !important; margin-bottom: .5em !important; }
        .ql-editor h2 { font-size: 1.5rem !important; font-weight: 600 !important; margin-bottom: .5em !important; }
        .ql-editor h3 { font-size: 1.25rem !important; font-weight: 600 !important; margin-bottom: .5em !important; }
        .ql-editor a { color: #4f46e5; text-decoration: underline; }
        .ql-editor img { max-width: 100%; height: auto; }
        .ql-editor blockquote { border-left: 4px solid #e2e8f0; padding-left: 1em; color: #64748b; font-style: italic; margin-bottom: 1em; }
        .dark .ql-picker-options { background: #1a1a1a !important; border-color: #333 !important; }
        .dark .ql-picker-item, .dark .ql-picker-label { color: #cbd5e1 !important; }
        .dark .ql-stroke { stroke: #cbd5e1 !important; }
        .dark .ql-fill { fill: #cbd5e1 !important; }
    </style>
    @endpush
</x-layouts.app>
