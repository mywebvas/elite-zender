<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>503 — Maintenance · EliteSender</title>
    <script>(function(){var t=localStorage.getItem('theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark'); document.documentElement.style.backgroundColor = '#050508';}else{document.documentElement.classList.remove('dark'); document.documentElement.style.backgroundColor = '#ffffff';}})();</script>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-white dark:bg-[#050508] flex items-center justify-center px-6">
    <div class="text-center max-w-md">
        <div class="w-20 h-20 rounded-3xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-3">Maintenance</p>
        <h1 class="text-3xl font-black text-slate-900 dark:text-white mb-3">Be right back</h1>
        <p class="text-slate-500 dark:text-slate-400 mb-2">EliteSender is undergoing scheduled maintenance.</p>
        @if(isset($exception) && $exception->getMessage()) <p class="text-sm text-slate-400 mb-8">{{ $exception->getMessage() }}</p> @endif
    </div>
</body>
</html>



