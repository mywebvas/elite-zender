<x-layouts.app header="{{ $automation->exists ? 'Edit Automation' : 'New Automation' }}">
    <form action="{{ $automation->exists ? route('automations.update', $automation->id) : route('automations.store') }}" method="POST" x-data="automationBuilder()" @submit="prepareSubmit">
        @csrf
        @if($automation->exists)
            @method('PUT')
        @endif

        <div class="flex flex-col md:flex-row gap-6">
            
            {{-- Main Builder Area --}}
            <div class="flex-1 space-y-6">
                
                {{-- Setup --}}
                <x-card>
                    <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-slate-100 mb-4">Automation Trigger</h3>

                    @if ($errors->any())
                        <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-900/20 p-4 border border-rose-100 dark:border-rose-900/50">
                            <h3 class="text-sm font-medium text-rose-800 dark:text-rose-300">Please fix the following errors:</h3>
                            <ul class="list-disc pl-5 text-sm text-rose-700 dark:text-rose-400 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="input-label">Automation Name</label>
                            <input type="text" name="name" x-model="name" required
                                   class="input @error('name') ring-rose-400 dark:ring-rose-500 @enderror"
                                   placeholder="e.g. Welcome Sequence">
                            @error('name')
                                <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="input-label">Trigger Event</label>
                            <select name="trigger_type" x-model="triggerType" required
                                    class="input @error('trigger_type') ring-rose-400 dark:ring-rose-500 @enderror">
                                <option value="">Select a trigger...</option>
                                <option value="subscribed">When a contact subscribes</option>
                                <option value="tag_added">When a tag is added</option>
                                <option value="link_clicked">When a link is clicked</option>
                                <option value="page_visited">When a page is visited</option>
                            </select>
                            @error('trigger_type')
                                <p class="mt-1.5 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-card>

                {{-- Visual Workflow Pipeline --}}
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4">Workflow Steps</h3>
                    
                    <div class="space-y-4">
                        <template x-for="(step, index) in steps" :key="step.id">
                            <div class="relative pl-8">
                                {{-- Vertical connector line --}}
                                <div class="absolute left-3 top-6 bottom-[-20px] w-0.5 bg-slate-200 dark:bg-slate-800" x-show="index < steps.length - 1"></div>
                                
                                {{-- Step Node Marker --}}
                                <div class="absolute left-0 top-4 w-6 h-6 rounded-full border-4 border-white dark:border-slate-950 bg-indigo-500 shadow-premium dark:shadow-premium-dark z-10 flex items-center justify-center">
                                    <span class="text-[9px] font-bold text-white" x-text="index + 1"></span>
                                </div>
                                
                                <div class="bg-white/90 dark:bg-[#111111]/90 backdrop-blur-lg border border-slate-200 dark:border-white/10 rounded-xl shadow-premium dark:shadow-premium-dark p-5 flex flex-col gap-4 transition-all">
                                    <div class="flex justify-between items-center">
                                        <select x-model="step.type" class="block w-48 input">
                                            <option value="">Select action...</option>
                                            <option value="send_email">Send Email</option>
                                            <option value="wait">Wait</option>
                                            <option value="tag">Add/Remove Tag</option>
                                            <option value="update_field">Update Contact Field</option>
                                            <option value="webhook">Send Webhook</option>
                                        </select>
                                        
                                        <button type="button" @click="removeStep(index)" class="text-rose-500 hover:text-rose-700 p-1 rounded hover:bg-rose-50 dark:hover:bg-rose-900/30">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                    
                                    {{-- Configuration Area (Dynamic based on type) --}}
                                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800" x-show="step.type">
                                        
                                        {{-- Wait Config --}}
                                        <div x-show="step.type === 'wait'" class="flex items-center gap-2">
                                            <span class="text-sm text-slate-600 dark:text-slate-400">Wait for</span>
                                            <input type="number" x-model="step.config.amount" min="1" class="w-20 input">
                                            <select x-model="step.config.unit" class="input">
                                                <option value="minutes">Minutes</option>
                                                <option value="hours">Hours</option>
                                                <option value="days">Days</option>
                                            </select>
                                        </div>
                                        
                                        {{-- Send Email Config --}}
                                        <div x-show="step.type === 'send_email'" class="space-y-3">
                                            <div>
                                                <label class="input-label">Select Campaign/Template</label>
                                                <select x-model="step.config.campaign_id" class="input">
                                                    <option value="">Choose a campaign...</option>
                                                    @forelse($campaigns ?? [] as $c)
                                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                                    @empty
                                                        <option disabled>No campaigns yet — create one first</option>
                                                    @endforelse
                                                </select>
                                            </div>
                                        </div>
                                        
                                        {{-- Tag Config --}}
                                        <div x-show="step.type === 'tag'" class="space-y-3">
                                            <div class="flex items-center gap-3">
                                                <select x-model="step.config.action" class="input">
                                                    <option value="add">Add Tag</option>
                                                    <option value="remove">Remove Tag</option>
                                                </select>
                                                <input type="text" x-model="step.config.tag_name" placeholder="Tag name (e.g. VIP)" class="flex-1 input">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <div class="pl-8 pt-4">
                            <button type="button" @click="addStep()" class="inline-flex items-center rounded-full bg-white dark:bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-900 dark:text-slate-100 shadow-premium dark:shadow-premium-dark ring-1 ring-inset ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                Add Action
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar Controls --}}
            <div class="w-full md:w-80 flex-shrink-0">
                <x-card class="sticky top-20">
                    <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-slate-100 mb-4">Publishing</h3>
                    
                    <div class="flex items-center justify-between mb-6">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Active Status</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" x-model="isActive" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
                        <x-button type="submit" variant="primary" class="w-full justify-center">Save Automation</x-button>
                        <x-button type="button" href="{{ route('automations.index') }}" variant="ghost" class="w-full justify-center">Cancel</x-button>
                    </div>
                </x-card>
            </div>
            
        </div>
        
        <input type="hidden" name="steps_json" id="steps_json">
    </form>

    @push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        window.automationBuilderData = () => ({
            name: {!! json_encode(old('name', $automation->name ?? '')) !!},
            triggerType: {!! json_encode(old('trigger_type', $automation->trigger_type ?? '')) !!},
            isActive: {{ old('is_active', $automation->is_active ?? false) ? 'true' : 'false' }},
            
            steps: {!! json_encode(old('steps', $automation->steps ?? [])) !!},
            
            init() {
                if (this.steps.length === 0) {
                    this.addStep();
                }
                
                // Normalize JSON config fields if loaded from DB
                this.steps = this.steps.map(step => {
                    return {
                        id: step.id || Date.now() + Math.random(),
                        type: step.type || '',
                        config: (typeof step.config === 'string' ? JSON.parse(step.config) : step.config) || { amount: 1, unit: 'days' }
                    };
                });
            },
            
            addStep() {
                this.steps.push({
                    id: Date.now(),
                    type: '',
                    config: { amount: 1, unit: 'days', action: 'add', tag_name: '' }
                });
            },
            
            removeStep(index) {
                this.steps.splice(index, 1);
            },
            
            prepareSubmit(e) {
                // Create hidden inputs for the array structure so Laravel validation passes
                const form = e.target;
                
                // Remove existing dynamic inputs to avoid duplicates
                form.querySelectorAll('.dynamic-step-input').forEach(el => el.remove());
                
                this.steps.forEach((step, index) => {
                    const typeInput = document.createElement('input');
                    typeInput.type = 'hidden';
                    typeInput.name = `steps[${index}][type]`;
                    typeInput.value = step.type;
                    typeInput.className = 'dynamic-step-input';
                    form.appendChild(typeInput);
                    
                    // We must serialize the config object or pass it as array. Passing as array for Laravel:
                    if (step.config) {
                        for (const [key, value] of Object.entries(step.config)) {
                            const configInput = document.createElement('input');
                            configInput.type = 'hidden';
                            configInput.name = `steps[${index}][config][${key}]`;
                            configInput.value = value;
                            configInput.className = 'dynamic-step-input';
                            form.appendChild(configInput);
                        }
                    }
                });
                
                return true;
            }
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

