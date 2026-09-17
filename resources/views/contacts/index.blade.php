<x-layouts.app header="Contacts">
    <div class="max-w-7xl mx-auto">
        <div class="sm:flex sm:items-center sm:justify-between mb-8">
            <div>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">Manage your audience, lists, and suppressions.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 flex gap-3">
                <x-button variant="secondary">Create List</x-button>
                <x-button variant="primary">Import CSV</x-button>
            </div>
        </div>

        <x-card class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">No contacts yet</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by importing your first CSV list.</p>
            <div class="mt-6">
                <x-button variant="primary">Import Contacts</x-button>
            </div>
        </x-card>
    </div>
</x-layouts.app>
