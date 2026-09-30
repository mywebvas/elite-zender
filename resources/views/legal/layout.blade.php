{{-- Shared shell for the legal pages: readable, printable, no chrome to distract. --}}
<x-layouts.bare :title="$title">
    <div class="mx-auto max-w-3xl px-6 py-16">
        <a href="{{ route('welcome') }}" class="text-sm font-semibold text-brand-600 underline-offset-2 hover:underline dark:text-brand-400">
            &larr; {{ config('platform.name') }}
        </a>

        <h1 class="mt-6 text-3xl font-black tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Last updated {{ $updated }}</p>

        <div class="prose prose-slate mt-8 max-w-none dark:prose-invert prose-headings:font-bold prose-h2:text-xl prose-h2:mt-10 prose-a:text-brand-600">
            {{ $slot }}
        </div>

        <p class="mt-12 border-t border-slate-200 pt-6 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
            Questions about this page? Write to
            <a href="mailto:{{ config('platform.support_email') }}" class="font-semibold underline underline-offset-2">{{ config('platform.support_email') }}</a>.
        </p>
    </div>
</x-layouts.bare>
