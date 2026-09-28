{{--
    Automation builder.

    The trigger and step dropdowns are rendered from the server's own
    allow-lists ($triggerTypes / $stepTypes, sourced from Automation::TRIGGERS
    and AutomationStep::TYPES). They used to be hand-written here and had
    drifted — the UI offered `link_clicked` and `page_visited`, which the
    server rejects, and `tag`, which is really `add_tag`/`remove_tag`. Picking
    any of them failed validation and the form appeared to do nothing.

    Step ids are submitted too, so saving reconciles the existing graph instead
    of deleting and recreating it (which stranded in-flight enrolments).
--}}
<x-layouts.app :header="$automation->exists ? 'Edit Automation' : 'New Automation'">
    <form method="POST"
          action="{{ $automation->exists ? route('automations.update', $automation->id) : route('automations.store') }}"
          x-data="automationBuilder()"
          @submit="prepareSubmit">
        @csrf
        @if($automation->exists)
            @method('PUT')
        @endif

        <div class="flex flex-col gap-6 xl:flex-row">

            <div class="flex-1 space-y-6">

                {{-- Trigger --}}
                <x-card>
                    <h3 class="mb-4 text-base font-semibold leading-6 text-slate-900 dark:text-slate-100">
                        Automation trigger
                    </h3>

                    @if ($errors->any())
                        <div class="mb-4 rounded-xl border border-rose-100 bg-rose-50 p-4 dark:border-rose-900/50 dark:bg-rose-900/20" role="alert">
                            <p class="text-sm font-medium text-rose-800 dark:text-rose-300">Please fix the following:</p>
                            <ul class="mt-2 list-disc pl-5 text-sm text-rose-700 dark:text-rose-400">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="automation-name" class="input-label">Automation name</label>
                            <input id="automation-name" type="text" name="name" x-model="name" required maxlength="255"
                                   class="input @error('name') ring-rose-400 dark:ring-rose-500 @enderror"
                                   placeholder="e.g. Welcome sequence">
                            @error('name')<p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="automation-trigger" class="input-label">Trigger event</label>
                            <select id="automation-trigger" name="trigger_type" x-model="triggerType" required
                                    class="input @error('trigger_type') ring-rose-400 dark:ring-rose-500 @enderror">
                                <option value="">Select a trigger…</option>
                                @foreach($triggerTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('trigger_type')<p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Trigger-specific configuration --}}
                    <div class="mt-4" x-show="triggerType === 'tag_added'" x-cloak>
                        <label class="input-label">Tag that starts this automation</label>
                        <input type="text" name="trigger_config[tag]" x-model="triggerConfig.tag"
                               class="input" placeholder="e.g. vip">
                    </div>

                    <div class="mt-4" x-show="triggerType === 'list_joined'" x-cloak>
                        <label class="input-label">List that starts this automation</label>
                        <select name="trigger_config[list_id]" x-model="triggerConfig.list_id" class="input">
                            <option value="">Any list</option>
                            @foreach($lists ?? [] as $list)
                                <option value="{{ $list->id }}">{{ $list->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-4" x-show="['campaign_opened', 'campaign_clicked'].includes(triggerType)" x-cloak>
                        <label class="input-label">Campaign to watch</label>
                        <select name="trigger_config[campaign_id]" x-model="triggerConfig.campaign_id" class="input">
                            <option value="">Any campaign</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-card>

                {{-- Workflow --}}
                <div>
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Workflow steps</h3>
                        <span class="text-xs text-slate-400" x-text="steps.length + ' step' + (steps.length === 1 ? '' : 's')"></span>
                    </div>

                    <div class="space-y-4">
                        <template x-for="(step, index) in steps" :key="step.key">
                            <div class="relative pl-8">
                                <div class="absolute left-3 top-6 bottom-[-20px] w-0.5 bg-slate-200 dark:bg-slate-800"
                                     x-show="index < steps.length - 1"></div>

                                <div class="absolute left-0 top-4 z-10 flex h-6 w-6 items-center justify-center rounded-full border-4 border-white bg-indigo-500 shadow-premium dark:border-slate-950 dark:shadow-premium-dark">
                                    <span class="text-[9px] font-bold text-white" x-text="index + 1"></span>
                                </div>

                                <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white/90 p-5 shadow-premium backdrop-blur-lg transition-all dark:border-white/10 dark:bg-[#111111]/90 dark:shadow-premium-dark">
                                    <div class="flex items-center justify-between gap-3">
                                        <select x-model="step.type" class="input w-full max-w-xs" required>
                                            <option value="">Select action…</option>
                                            @foreach($stepTypes as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <div class="flex items-center gap-1">
                                            <button type="button" @click="move(index, -1)" x-show="index > 0"
                                                    class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                                    aria-label="Move step up">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                            </button>
                                            <button type="button" @click="move(index, 1)" x-show="index < steps.length - 1"
                                                    class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                                    aria-label="Move step down">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            <button type="button" @click="removeStep(index)"
                                                    class="rounded p-1.5 text-rose-500 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-900/30"
                                                    aria-label="Remove step">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="border-t border-slate-100 pt-3 dark:border-slate-800" x-show="step.type" x-cloak>

                                        {{-- Wait --}}
                                        <div x-show="step.type === 'wait'" class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm text-slate-600 dark:text-slate-400">Wait for</span>
                                            <input type="number" x-model.number="step.config.amount" min="1" max="365" class="input w-24">
                                            <select x-model="step.config.unit" class="input w-32">
                                                <option value="minutes">minutes</option>
                                                <option value="hours">hours</option>
                                                <option value="days">days</option>
                                            </select>
                                            <span class="text-sm text-slate-600 dark:text-slate-400">before the next step</span>
                                        </div>

                                        {{-- Send email --}}
                                        <div x-show="step.type === 'send_email'" class="space-y-3">
                                            <label class="input-label">Campaign to send</label>
                                            <select x-model="step.config.campaign_id" class="input">
                                                <option value="">Choose a campaign…</option>
                                                @forelse($campaigns as $campaign)
                                                    <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                                                @empty
                                                    <option disabled>No campaigns yet — create one first</option>
                                                @endforelse
                                            </select>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                The campaign's content is sent to the enrolled contact only, through your SMTP pool.
                                            </p>
                                        </div>

                                        {{-- Add / remove tag --}}
                                        <div x-show="['add_tag', 'remove_tag'].includes(step.type)" class="space-y-2">
                                            <label class="input-label">Tag name</label>
                                            <input type="text" x-model="step.config.tag_name" class="input" placeholder="e.g. vip" maxlength="60">
                                        </div>

                                        {{-- Update field --}}
                                        <div x-show="step.type === 'update_field'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="input-label">Field</label>
                                                <select x-model="step.config.field" class="input">
                                                    <option value="first_name">First name</option>
                                                    <option value="last_name">Last name</option>
                                                    <option value="status">Status</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="input-label">New value</label>
                                                <input type="text" x-model="step.config.value" class="input" maxlength="255">
                                            </div>
                                        </div>

                                        {{-- Webhook --}}
                                        <div x-show="step.type === 'webhook'" class="space-y-2">
                                            <label class="input-label">Endpoint URL</label>
                                            <input type="url" x-model="step.config.url" class="input" placeholder="https://example.com/hooks/elitesender">
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                We POST the contact as JSON. Private and loopback addresses are rejected.
                                            </p>
                                        </div>

                                        {{-- Condition --}}
                                        <div x-show="step.type === 'condition'" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <label class="input-label">If</label>
                                                <select x-model="step.config.subject" class="input">
                                                    <option value="tag">has tag</option>
                                                    <option value="status">status is</option>
                                                </select>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="input-label">Value</label>
                                                <input type="text" x-model="step.config.value" class="input" maxlength="120">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="pl-8 pt-4">
                            <button type="button" @click="addStep()"
                                    class="inline-flex items-center rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-premium ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-100 dark:shadow-premium-dark dark:ring-slate-700 dark:hover:bg-slate-700">
                                <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                Add action
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="w-full flex-shrink-0 xl:w-80">
                <x-card class="sticky top-20">
                    <h3 class="mb-4 text-base font-semibold leading-6 text-slate-900 dark:text-slate-100">Publishing</h3>

                    <div class="mb-6 flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Active</span>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <span class="sr-only">Activate this automation</span>
                            {{-- Unchecked checkboxes submit nothing, so a hidden
                                 companion field guarantees the server always
                                 receives an explicit value. --}}
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" x-model="isActive" class="peer sr-only">
                            <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-indigo-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:border-slate-600 dark:bg-slate-700 dark:peer-focus:ring-indigo-800"></div>
                        </label>
                    </div>

                    <p class="mb-6 text-xs text-slate-500 dark:text-slate-400" x-show="!isActive" x-cloak>
                        Inactive automations are saved but never enrol anyone.
                    </p>

                    <div class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <x-button type="submit" variant="primary" class="w-full justify-center">
                            {{ $automation->exists ? 'Save changes' : 'Create automation' }}
                        </x-button>
                        <x-button type="button" href="{{ route('automations.index') }}" variant="ghost" class="w-full justify-center">
                            Cancel
                        </x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>

    @push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        window.automationBuilderData = () => ({
            name: @json(old('name', $automation->name ?? '')),
            triggerType: @json(old('trigger_type', $automation->trigger_type ?? '')),
            triggerConfig: Object.assign(
                { tag: '', list_id: '', campaign_id: '' },
                @json(old('trigger_config', $automation->trigger_config ?? [])) || {}
            ),
            isActive: @json((bool) old('is_active', $automation->is_active ?? false)),
            steps: [],

            init() {
                const existing = @json(old('steps')) ?? @json(
                    $automation->exists
                        ? $automation->steps->map(fn ($step) => ['id' => $step->id, 'type' => $step->type, 'config' => $step->config ?? []])->values()
                        : []
                );

                this.steps = (existing || []).map((step, i) => ({
                    // `key` drives Alpine's x-for identity and is never sent.
                    // `id` is the persisted step id — submitting it is what lets
                    // the server reconcile instead of recreating the graph.
                    key: `k${i}-${Math.random().toString(36).slice(2)}`,
                    id: step.id ?? null,
                    type: step.type ?? '',
                    config: this.defaults(step.config ?? {}),
                }));

                if (this.steps.length === 0) {
                    this.addStep();
                }
            },

            defaults(config) {
                return Object.assign(
                    { amount: 1, unit: 'days', campaign_id: '', tag_name: '', field: 'first_name', value: '', url: '', subject: 'tag' },
                    typeof config === 'string' ? JSON.parse(config || '{}') : (config || {})
                );
            },

            addStep() {
                this.steps.push({
                    key: `k-new-${Date.now()}-${Math.random().toString(36).slice(2)}`,
                    id: null,
                    type: '',
                    config: this.defaults({}),
                });
            },

            removeStep(index) {
                this.steps.splice(index, 1);
            },

            move(index, delta) {
                const target = index + delta;
                if (target < 0 || target >= this.steps.length) return;
                const [step] = this.steps.splice(index, 1);
                this.steps.splice(target, 0, step);
            },

            /**
             * Flatten the step graph into the array shape the Form Request
             * expects. Only the keys relevant to the chosen type are sent, so a
             * `wait` step does not carry a stale webhook URL.
             */
            prepareSubmit(event) {
                const form = event.target;
                form.querySelectorAll('.dynamic-step-input').forEach((el) => el.remove());

                const relevant = {
                    wait: ['amount', 'unit'],
                    send_email: ['campaign_id'],
                    add_tag: ['tag_name'],
                    remove_tag: ['tag_name'],
                    update_field: ['field', 'value'],
                    webhook: ['url'],
                    condition: ['subject', 'value'],
                };

                const append = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value ?? '';
                    input.className = 'dynamic-step-input';
                    form.appendChild(input);
                };

                this.steps
                    .filter((step) => step.type)
                    .forEach((step, index) => {
                        if (step.id) append(`steps[${index}][id]`, step.id);
                        append(`steps[${index}][type]`, step.type);

                        (relevant[step.type] ?? []).forEach((key) => {
                            append(`steps[${index}][config][${key}]`, step.config[key]);
                        });
                    });

                return true;
            },
        });

        document.addEventListener('alpine:init', () => {
            Alpine.data('automationBuilder', window.automationBuilderData);
        });
        if (window.Alpine) {
            window.Alpine.data('automationBuilder', window.automationBuilderData);
        }
    </script>
    @endpush
</x-layouts.app>
