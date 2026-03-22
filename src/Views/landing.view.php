<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
          content="Smart Redirect Platform with device-aware routing, country filters, and real-time traffic insights.">
    <meta name="theme-color" content="#111827">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/icon.svg">
    <link rel="apple-touch-icon" href="/assets/icons/icon-maskable.svg">
    <title>Smart Redirect Platform</title>

    <script nonce="<?= htmlspecialchars($cspNonce ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            src="https://cdn.tailwindcss.com"></script>
    <script nonce="<?= htmlspecialchars($cspNonce ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        tailwind.config = {
            theme: {
                container: {
                    center: true,
                    padding: "1.25rem",
                    screens: { "2xl": "1400px" }
                },
                extend: {
                    colors: {
                        border: "hsl(240 5.9% 90%)",
                        input: "hsl(240 5.9% 90%)",
                        ring: "hsl(240 5.9% 10%)",
                        background: "hsl(0 0% 100%)",
                        foreground: "hsl(240 10% 3.9%)",
                        primary: {
                            DEFAULT: "hsl(240 5.9% 10%)",
                            foreground: "hsl(0 0% 98%)"
                        },
                        secondary: {
                            DEFAULT: "hsl(240 4.8% 95.9%)",
                            foreground: "hsl(240 5.9% 10%)"
                        },
                        destructive: {
                            DEFAULT: "hsl(0 84.2% 60.2%)",
                            foreground: "hsl(0 0% 98%)"
                        },
                        muted: {
                            DEFAULT: "hsl(240 4.8% 95.9%)",
                            foreground: "hsl(240 3.8% 46.1%)"
                        },
                        accent: {
                            DEFAULT: "hsl(240 4.8% 95.9%)",
                            foreground: "hsl(240 5.9% 10%)"
                        },
                        popover: {
                            DEFAULT: "hsl(0 0% 100%)",
                            foreground: "hsl(240 10% 3.9%)"
                        },
                        card: {
                            DEFAULT: "hsl(0 0% 100%)",
                            foreground: "hsl(240 10% 3.9%)"
                        }
                    },
                    borderRadius: {
                        lg: "0.375rem",
                        md: "calc(0.375rem - 2px)",
                        sm: "calc(0.375rem - 4px)"
                    }
                }
            }
        };
    </script>

    <link rel="stylesheet" type="text/css" href="/assets/style.css" id="preload-stylesheet"/>
    <script src="/pwa/register-sw.js" defer></script>

</head>
<body class="min-h-screen bg-background text-foreground antialiased flex flex-col">
<header class="border-b bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="container mx-auto flex h-14 items-center justify-between">
        <div class="flex items-center gap-2">
            <img src="/assets/icons/logo.svg"
                 alt="Ghost logo"
                 class="h-8 w-8"
                 width="24"
                 height="24">
            <div class="flex flex-col leading-tight">
                <span class="text-sm font-semibold tracking-tight">Hantuin-v2 Decision Logic</span>
                <span class="text-[11px] text-muted-foreground">Traffic control, made not stupid.</span>
            </div>
        </div>
        <nav class="hidden md:flex items-center gap-4 text-xs text-muted-foreground">
            <a href="#features" class="hover:text-foreground transition-colors">Features</a>
            <a href="#flow" class="hover:text-foreground transition-colors">Routing Flow</a>
            <a href="#status" class="hover:text-foreground transition-colors">Status</a>
            <a href="/login.php"
               class="inline-flex items-center rounded-md border px-3 py-1.5 text-xs font-medium
                      border-border hover:bg-secondary/60 transition-colors">
                Admin Login
            </a>
        </nav>
    </div>
</header>

<main class="flex-1">
    <!-- Hero -->
    <section class="border-b bg-gradient-to-b from-slate-950 via-slate-950 to-background text-slate-100">
        <div class="container mx-auto grid gap-8 py-10 md:py-14 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] items-center">
            <div class="space-y-5">
                <span class="inline-flex items-center rounded-full border border-slate-700/60 bg-slate-900/70 px-2.5 py-1 text-[10px] font-medium uppercase tracking-[0.16em] text-slate-300">
                    Multi-role · Device-aware · Cloudflare-friendly
                </span>
                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                    Route every click to the right destination,<br class="hidden md:block">
                    <span class="text-emerald-400">without babysitting traffic 24/7.</span>
                </h1>
                <p class="text-xs md:text-sm text-slate-300/90 max-w-xl">
                    Hantuin-v2 memetakan trafik berdasarkan negara, device, dan profil risiko.
                    Superadmin, pre-admin, dan user punya panel masing-masing, tapi log dan
                    keputusan tetap konsisten di satu core routing engine.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="/login.php"
                       class="inline-flex items-center rounded-md border border-emerald-400/80 bg-emerald-500 px-4 py-2 text-xs font-medium text-slate-950 shadow-sm hover:bg-emerald-400 hover:border-emerald-300 transition-colors">
                        Open Dashboard
                    </a>
                    <a href="#features"
                       class="inline-flex items-center rounded-md border border-slate-600 px-4 py-2 text-xs font-medium text-slate-100 hover:bg-slate-800/70 transition-colors">
                        View Features
                    </a>
                    <p class="w-full text-[11px] text-slate-400 md:w-auto">
                        Dashboard dioptimalkan untuk mobile. Non-mobile bisa tetap pakai API & reporting.
                    </p>
                </div>
            </div>

            <!-- Fake stats card to mirror dashboard vibes -->
            <div class="rounded-lg border border-slate-800 bg-slate-950/80 p-4 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <p class="text-[11px] text-slate-400">Live routing snapshot</p>
                        <p class="text-xs font-semibold text-slate-100">Last 15 minutes</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-300">
                        Auto-balanced
                    </span>
                </div>
                <dl class="grid grid-cols-3 gap-3 text-xs">
                    <div class="rounded-md border border-slate-800/80 bg-slate-900/80 p-2">
                        <dt class="text-[10px] text-slate-400">Total hits</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-50">12,384</dd>
                    </div>
                    <div class="rounded-md border border-slate-800/80 bg-slate-900/80 p-2">
                        <dt class="text-[10px] text-slate-400">Safe passed</dt>
                        <dd class="mt-1 text-sm font-semibold text-emerald-400">98.4%</dd>
                    </div>
                    <div class="rounded-md border border-slate-800/80 bg-slate-900/80 p-2">
                        <dt class="text-[10px] text-slate-400">Muted routes</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-100">3</dd>
                    </div>
                </dl>
                <p class="mt-3 text-[11px] text-slate-400">
                    Device split, VPN flag, dan negara di-handle oleh engine, bukan oleh manusia yang kurang tidur.
                </p>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="border-b bg-background">
        <div class="container mx-auto py-8 md:py-10 space-y-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm md:text-base font-semibold tracking-tight">Three dashboards, one brain.</h2>
                    <p class="text-xs text-muted-foreground max-w-xl">
                        Superadmin, pre-admin, dan user jalan di panel berbeda, tapi semua keputusan
                        redirect tetap konsisten di satu sistem.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <article class="rounded-lg border bg-card text-card-foreground p-4 shadow-sm">
                    <h3 class="text-xs font-semibold tracking-tight mb-1.5">Superadmin control</h3>
                    <p class="text-[11px] text-muted-foreground mb-2">
                        CRUD admin, konfigurasi global, sinkronisasi domain & campaign, plus log sistem secara penuh.
                    </p>
                    <ul class="text-[11px] text-muted-foreground space-y-1">
                        <li>• Global on/off routing switch</li>
                        <li>• Tag-based isolation antar admin</li>
                        <li>• System configs & emergency mute</li>
                    </ul>
                </article>

                <article class="rounded-lg border bg-card text-card-foreground p-4 shadow-sm">
                    <h3 class="text-xs font-semibold tracking-tight mb-1.5">Pre-admin workspace</h3>
                    <p class="text-[11px] text-muted-foreground mb-2">
                        Kelola user, parked domain, dan mapping negara/device tanpa menyentuh core engine.
                    </p>
                    <ul class="text-[11px] text-muted-foreground space-y-1">
                        <li>• Device-aware targets (WAP / WEB / ALL)</li>
                        <li>• ISO country lists with validation</li>
                        <li>• Per-user reporting & history</li>
                    </ul>
                </article>

                <article class="rounded-lg border bg-card text-card-foreground p-4 shadow-sm">
                    <h3 class="text-xs font-semibold tracking-tight mb-1.5">User-safe interface</h3>
                    <p class="text-[11px] text-muted-foreground mb-2">
                        User cukup atur domain & meta. Redirect, beban, dan filtering di-handle oleh belakang layar.
                    </p>
                    <ul class="text-[11px] text-muted-foreground space-y-1">
                        <li>• Safe redirect & friendly URL</li>
                        <li>• Domain management up to per-tag</li>
                        <li>• Optimized for mobile dashboards</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <!-- Routing Flow -->
    <section id="flow" class="bg-muted/60 border-b">
        <div class="container mx-auto py-8 md:py-10 space-y-4">
            <h2 class="text-sm md:text-base font-semibold tracking-tight">Routing flow, simplified.</h2>
            <p class="text-xs text-muted-foreground max-w-xl">
                Tiap klik melewati chain yang sama: domain → resolver → rules (negara, device, VPN) →
                decision API (opsional) → target akhir. Kalau sesuatu error, fallback tetap aman.
            </p>

            <div class="grid gap-3 md:grid-cols-4 text-[11px] text-muted-foreground">
                <div class="rounded-md border bg-card p-3">
                    <p class="font-semibold text-xs mb-1">1. Entry</p>
                    <p>Wildcard domain + shortlink → identifikasi campaign & route set.</p>
                </div>
                <div class="rounded-md border bg-card p-3">
                    <p class="font-semibold text-xs mb-1">2. Detection</p>
                    <p>IP, negara, device, VPN/WAP flag ditandai tanpa bocorin engine.</p>
                </div>
                <div class="rounded-md border bg-card p-3">
                    <p class="font-semibold text-xs mb-1">3. Decision</p>
                    <p>Rules internal + optional external decision API menentukan target final.</p>
                </div>
                <div class="rounded-md border bg-card p-3">
                    <p class="font-semibold text-xs mb-1">4. Persist</p>
                    <p>Click dicatat, reporting aman, dan route bisa di-mute/unmute kapan saja.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Status / Info -->
    <section id="status" class="bg-background">
        <div class="container mx-auto py-6 md:py-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold tracking-tight">Environment-friendly redirect.</h2>
                <p class="text-xs text-muted-foreground max-w-md">
                    Dirancang untuk main bareng Cloudflare: DNS, WAF, dan caching bisa dikombinasikan dengan Hantuin-v2
                    tanpa saling injak kaki.
                </p>
            </div>
            <div class="flex flex-col items-start md:items-end gap-1 text-[11px] text-muted-foreground">
                <span class="inline-flex items-center rounded-full border border-emerald-400/40 bg-emerald-500/5 px-2 py-0.5 font-medium text-emerald-500">
                    Status: Core engine active
                </span>
                <span>Dashboard: <span class="font-semibold">best on mobile</span></span>
                <span>API & reporting: <span class="font-semibold">multi-device</span></span>
            </div>
        </div>
    </section>
</main>

<footer class="border-t bg-background/80">
    <div class="container mx-auto flex flex-col md:flex-row items-center justify-between gap-2 py-4">
        <p class="text-[11px] text-muted-foreground">
            © <?= date('Y'); ?> Smart Redirect Platform. No "smart" buzzword without actual routing logic.
        </p>
        <div class="flex items-center gap-3 text-[11px] text-muted-foreground">
            <a href="/privacy" class="hover:text-foreground transition-colors">Privacy</a>
            <a href="/terms" class="hover:text-foreground transition-colors">Terms</a>
        </div>
    </div>
</footer>
</body>
</html>
