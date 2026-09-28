<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — Server Error · EliteSender</title>
    <script>(function(){var t=localStorage.getItem('theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark'); document.documentElement.style.backgroundColor = '#050508';}else{document.documentElement.classList.remove('dark'); document.documentElement.style.backgroundColor = '#ffffff';}})();</script>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-white dark:bg-[#050508] flex items-center justify-center px-6">
    <div class="text-center max-w-md">
        <div class="w-20 h-20 rounded-3xl bg-rose-50 dark:bg-rose-500/10 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <p class="text-xs font-bold text-rose-600 dark:text-rose-400 uppercase tracking-widest mb-3">500 Error</p>
        <h1 class="text-3xl font-black text-slate-900 dark:text-white mb-3">Server error</h1>
        <p class="text-slate-500 dark:text-slate-400 mb-8">Something went wrong on our end. We have been notified and are working on a fix.</p>
        <a href="{{ url('/dashboard') }}" class="btn-gradient py-3 px-6 inline-flex">Back to Dashboard</a>
    </div>
</body>
</html>



