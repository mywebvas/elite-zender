<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Page Not Found · EliteSender</title>
    <script>(function(){var t=localStorage.getItem('theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark'); document.documentElement.style.backgroundColor = '#050508';}else{document.documentElement.classList.remove('dark'); document.documentElement.style.backgroundColor = '#ffffff';}})();</script>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full font-sans antialiased bg-white dark:bg-[#050508] flex items-center justify-center px-6">
    <div class="text-center max-w-md">
        <div class="w-20 h-20 rounded-3xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-3">404 Error</p>
        <h1 class="text-3xl font-black text-slate-900 dark:text-white mb-3">Page not found</h1>
        <p class="text-slate-500 dark:text-slate-400 mb-8">The page you are looking for does not exist or has been moved.</p>
        <a href="{{ url('/dashboard') }}" class="btn-gradient py-3 px-6 inline-flex">Back to Dashboard</a>
    </div>
</body>
</html>



