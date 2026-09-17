<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <title>You're offline — EliteSender</title>
    {{-- Inline styles: this page may be served without network, so no external CSS --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh;
            background: #020617; /* slate-950 */
            font-family: ui-sans-serif, system-ui, sans-serif;
            color: #f1f5f9; /* slate-100 */
            padding: 1.5rem;
            text-align: center;
        }
        .card {
            max-width: 24rem;
            width: 100%;
            background: #0f172a; /* slate-900 */
            border: 1px solid #1e293b; /* slate-800 */
            border-radius: 1rem;
            padding: 2.5rem 2rem;
        }
        .icon {
            width: 4rem; height: 4rem;
            background: #1e293b;
            border-radius: 1rem;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }
        h1 {
            font-size: 1.125rem; font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
        }
        p {
            font-size: 0.875rem;
            color: #94a3b8; /* slate-400 */
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        button {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            background: #4f46e5; color: #fff;
            border: none; border-radius: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem; font-weight: 500;
            cursor: pointer; min-height: 44px;
            transition: background 0.15s;
        }
        button:hover { background: #6366f1; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#94a3b8" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/>
            </svg>
        </div>
        <h1>You're offline</h1>
        <p>It looks like you've lost your internet connection. Check your connection and try again.</p>
        <button onclick="window.location.reload()">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Try again
        </button>
    </div>
</body>
</html>
