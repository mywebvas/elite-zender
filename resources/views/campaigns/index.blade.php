<x-layouts.app header="Campaigns">
    <div class="max-w-7xl mx-auto">
        <div class="sm:flex sm:items-center sm:justify-between mb-8">
            <div>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">Manage your email broadcasts and view their performance.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <a href="{{ route('campaigns.create') }}" class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    New Campaign
                </a>
            </div>
        </div>

        <x-card class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-300 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-slate-900 dark:text-slate-100 sm:pl-6">Name</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900 dark:text-slate-100">Status</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900 dark:text-slate-100">Sent</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900 dark:text-slate-100">Opens</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-slate-900 dark:text-slate-100">Clicks</th>
                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-900">
                        <!-- Stubbed data for UI -->
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-slate-900 dark:text-slate-100 sm:pl-6">
                                Welcome Q3 Launch
                                <div class="font-normal text-slate-500 text-xs mt-1">Sent on Sep 15, 2026</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                <x-badge variant="success">Completed</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">4,200</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">68% <span class="text-xs text-slate-400">(2,856)</span></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">12% <span class="text-xs text-slate-400">(504)</span></td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <a href="#" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">View<span class="sr-only">, Welcome Q3 Launch</span></a>
                            </td>
                        </tr>
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-slate-900 dark:text-slate-100 sm:pl-6">
                                Product Update v2.0
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                <x-badge variant="slate">Draft</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">—</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">—</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 dark:text-slate-400">—</td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <a href="#" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Edit<span class="sr-only">, Product Update v2.0</span></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-layouts.app>
