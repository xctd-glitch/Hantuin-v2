<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111827">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/icon.svg">
    <title>Statistics Dashboard — SRP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                container: { center: true, padding: "1.25rem", screens: { "2xl": "1400px" } },
                extend: {
                    colors: {
                        border: "hsl(240 5.9% 90%)",
                        input: "hsl(240 5.9% 90%)",
                        ring: "hsl(240 5.9% 10%)",
                        background: "hsl(0 0% 100%)",
                        foreground: "hsl(240 10% 3.9%)",
                        primary: { DEFAULT: "hsl(240 5.9% 10%)", foreground: "hsl(0 0% 98%)" },
                        secondary: { DEFAULT: "hsl(240 4.8% 95.9%)", foreground: "hsl(240 5.9% 10%)" },
                        destructive: { DEFAULT: "hsl(0 84.2% 60.2%)", foreground: "hsl(0 0% 98%)" },
                        muted: { DEFAULT: "hsl(240 4.8% 95.9%)", foreground: "hsl(240 3.8% 46.1%)" },
                        accent: { DEFAULT: "hsl(240 4.8% 95.9%)", foreground: "hsl(240 5.9% 10%)" },
                        card: { DEFAULT: "hsl(0 0% 100%)", foreground: "hsl(240 10% 3.9%)" }
                    },
                    borderRadius: { lg: "0.375rem", md: "calc(0.375rem - 2px)", sm: "calc(0.375rem - 4px)" }
                }
            }
        };
    </script>
    <link rel="stylesheet" type="text/css" href="/assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body class="min-h-screen bg-[color:var(--page-bg)] font-sans antialiased flex flex-col text-sm"
      x-data="statsApp()" x-init="init()">

<!-- ─── Header ──────────────────────────────────────── -->
<header class="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="flex h-12 max-w-4xl mx-auto items-center px-3 sm:px-5">
        <div class="mr-3 hidden md:flex">
            <a href="/stats.php" class="mr-4 flex items-center space-x-2">
                <img src="/assets/icons/logo.svg" alt="Logo" class="h-8 w-8" width="20" height="20">
                <div class="flex flex-col leading-tight">
                    <span class="font-semibold text-sm tracking-tight">Statistics Dashboard</span>
                    <span class="text-[11px] text-muted-foreground">Traffic analytics &amp; conversion monitoring</span>
                </div>
            </a>
        </div>

        <div class="flex md:hidden items-center space-x-2">
            <img src="/assets/icons/fox-head.png" alt="Logo" class="h-4 w-4" width="16" height="16">
            <span class="font-semibold text-xs tracking-tight">Statistics</span>
        </div>

        <div class="flex flex-1 items-center justify-end space-x-2">
            <!-- Status badge -->
            <div x-show="connected"
                 class="flex items-center space-x-2 rounded-md px-2 sm:px-2.5 py-1 bg-primary text-primary-foreground shadow-sm">
                <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                <span class="text-[11px] font-medium hidden sm:inline">Connected</span>
            </div>
            <div x-show="!connected && apiKey"
                 class="flex items-center space-x-2 rounded-md px-2 sm:px-2.5 py-1 border">
                <div class="h-1.5 w-1.5 rounded-full bg-red-400"></div>
                <span class="text-[11px] font-medium hidden sm:inline">Offline</span>
            </div>

            <button type="button" @click="showConfig = !showConfig"
                    class="btn btn-secondary btn-sm">
                <svg class="h-3.5 w-3.5 sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
                </svg>
                <span class="hidden sm:inline">API Key</span>
            </button>
        </div>
    </div>
</header>

<!-- ─── API Key Config Panel ────────────────────────── -->
<div x-show="showConfig" x-cloak x-transition
     class="border-b bg-background/95 backdrop-blur">
    <div class="max-w-4xl mx-auto px-3 sm:px-5 py-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <label class="text-[11px] font-medium text-muted-foreground mb-1 block">Base URL</label>
                <input type="url" x-model="baseUrl" placeholder="https://yourdomain.com"
                       class="input" @change="saveConfig()">
            </div>
            <div class="flex-1">
                <label class="text-[11px] font-medium text-muted-foreground mb-1 block">API Key</label>
                <input type="text" x-model="apiKey"
                       placeholder="Your SRP API key"
                       class="input font-mono" @change="saveConfig()">
            </div>
            <div class="flex items-end">
                <button type="button" @click="connectAndLoad()"
                        class="btn btn-default btn-default-size w-full sm:w-auto"
                        :disabled="!apiKey || !baseUrl || connecting">
                    <svg x-show="connecting" class="h-3.5 w-3.5 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                    </svg>
                    <span x-text="connecting ? 'Connecting...' : 'Connect'"></span>
                </button>
            </div>
        </div>
        <p x-show="errorMsg" x-text="errorMsg" class="text-[11px] text-red-500 mt-2"></p>
    </div>
</div>

<!-- ─── Prompt if no API key ────────────────────────── -->
<div x-show="!connected" x-cloak class="flex-1 flex items-center justify-center p-8">
    <div class="card p-8 text-center max-w-sm">
        <img src="/assets/icons/logo.svg" alt="Logo" class="h-12 w-12 mx-auto mb-4 opacity-40">
        <h3 class="font-semibold text-sm mb-1">Konfigurasi API</h3>
        <p class="text-[12px] text-muted-foreground mb-4">
            Masukkan Base URL dan API Key untuk menghubungkan ke SRP API.
        </p>
        <button type="button" @click="showConfig = true"
                class="btn btn-default btn-default-size">
            Set API Key
        </button>
    </div>
</div>

<!-- ─── Tabs Navigation (sticky below header, dashboard style) ── -->
<div x-show="connected" x-cloak
     class="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 sticky top-12 z-40">
    <div class="max-w-4xl mx-auto px-3 sm:px-5">
        <nav class="flex items-center overflow-x-auto no-scrollbar" aria-label="Tabs">
            <!-- Overview -->
            <button @click="activeTab = 'overview'" type="button"
                    class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                    :class="activeTab === 'overview'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Overview</span>
            </button>

            <!-- Traffic Logs -->
            <button @click="activeTab = 'logs'" type="button"
                    class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                    :class="activeTab === 'logs'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="hidden sm:inline">Traffic Logs</span>
                <span class="sm:hidden">Logs</span>
                <span class="ml-0.5 rounded-full bg-muted px-1.5 py-0.5 text-[9px] font-medium leading-none"
                      x-text="logs.length"></span>
            </button>

            <!-- Analytics -->
            <button @click="activeTab = 'analytics'" type="button"
                    class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                    :class="activeTab === 'analytics'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 8v8m-4-5v5m-4-2v2M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Analytics</span>
            </button>

            <!-- Conversions -->
            <button @click="activeTab = 'conversions'" type="button"
                    class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                    :class="activeTab === 'conversions'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Conversions</span>
            </button>

            <!-- Right: auto-refresh + refresh button -->
            <div class="ml-auto flex items-center gap-2 pl-3 flex-shrink-0">
                <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-muted-foreground hidden sm:flex">
                    <input type="checkbox" x-model="autoRefresh" @change="toggleAutoRefresh()"
                           class="rounded border-gray-300 text-primary focus:ring-primary h-3.5 w-3.5">
                    Auto
                </label>
                <button type="button" @click="refreshAll()" class="btn btn-ghost btn-icon"
                        :disabled="refreshing" title="Refresh">
                    <svg class="h-3.5 w-3.5" :class="refreshing && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 14.652"/>
                    </svg>
                </button>
            </div>
        </nav>
    </div>
</div>

<!-- ─── Main Content ────────────────────────────────── -->
<main x-show="connected" x-cloak class="flex-1 max-w-4xl w-full mx-auto px-3 sm:px-5 py-5 space-y-5">

        <!-- ══════════════════════════════════════════ -->
        <!-- TAB: Overview (Charts + Top Country)       -->
        <!-- ══════════════════════════════════════════ -->
        <div x-show="activeTab === 'overview'">
            <div class="space-y-4">

                <!-- Loading -->
                <div x-show="loading.stats || loading.analytics" class="flex items-center justify-center py-12 text-muted-foreground text-xs gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    Memuat data...
                </div>

                <div x-show="!loading.stats && !loading.analytics" x-cloak>
                    <div class="space-y-4">

                        <!-- Row 1: Traffic Line Chart + Decision Doughnut -->
                        <div class="grid gap-4 grid-cols-1 lg:grid-cols-3">

                            <!-- Traffic Trend (Line Chart) -->
                            <div class="card p-4 lg:col-span-2">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold">Traffic Trend</h3>
                                    <div class="flex items-center gap-3 text-[10px] text-muted-foreground">
                                        <span class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span> Redirect A
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span> Fallback B
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <span class="inline-block w-2 h-2 rounded-full bg-violet-500"></span> Leads
                                        </span>
                                    </div>
                                </div>
                                <div class="relative" style="height: 260px;">
                                    <canvas id="trafficLineChart"></canvas>
                                </div>
                            </div>

                            <!-- Decision Split (Doughnut) -->
                            <div class="card p-4">
                                <h3 class="text-sm font-semibold mb-3">Decision Split</h3>
                                <div class="relative mx-auto" style="height: 200px; max-width: 200px;">
                                    <canvas id="decisionDoughnut"></canvas>
                                </div>
                                <div class="mt-3 space-y-1.5 text-xs" x-show="stats && stats.traffic && stats.conversions">
                                    <div class="flex items-center justify-between">
                                        <span class="flex items-center gap-1.5">
                                            <span class="inline-block w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>
                                            <span class="text-muted-foreground">Redirect A</span>
                                        </span>
                                        <span class="font-medium tabular-nums" x-text="(stats.traffic.a_count || 0).toLocaleString()"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="flex items-center gap-1.5">
                                            <span class="inline-block w-2.5 h-2.5 rounded-sm bg-amber-400"></span>
                                            <span class="text-muted-foreground">Fallback B</span>
                                        </span>
                                        <span class="font-medium tabular-nums" x-text="(stats.traffic.b_count || 0).toLocaleString()"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="flex items-center gap-1.5">
                                            <span class="inline-block w-2.5 h-2.5 rounded-sm bg-violet-500"></span>
                                            <span class="text-muted-foreground">Leads</span>
                                        </span>
                                        <span class="font-medium tabular-nums" x-text="(stats.conversions.total || 0).toLocaleString()"></span>
                                    </div>
                                    <div class="separator my-2"></div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground">Rate</span>
                                        <span class="font-semibold text-emerald-600"
                                              x-text="(stats.traffic.total || 0) > 0
                                                  ? (stats.traffic.a_count / stats.traffic.total * 100).toFixed(1) + '%'
                                                  : '—'"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground">Payout</span>
                                        <span class="font-semibold text-violet-600"
                                              x-text="'$' + (stats.conversions.total_payout || 0).toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Conversions Bar Chart + Top Countries -->
                        <div class="grid gap-4 grid-cols-1 lg:grid-cols-3">

                            <!-- Conversions Bar Chart -->
                            <div class="card p-4 lg:col-span-2">
                                <h3 class="text-sm font-semibold mb-3">Daily Conversions &amp; Payout</h3>
                                <div class="relative" style="height: 220px;">
                                    <canvas id="convBarChart"></canvas>
                                </div>
                            </div>

                            <!-- Top Countries -->
                            <div class="card p-4">
                                <h3 class="text-sm font-semibold mb-3">Top Countries</h3>
                                <div x-show="topCountries.length === 0" class="text-xs text-muted-foreground text-center py-6">
                                    Tidak ada data country.
                                </div>
                                <div class="space-y-2" x-show="topCountries.length > 0">
                                    <template x-for="(c, i) in topCountries" :key="c.code">
                                        <div>
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-mono font-semibold text-[11px] w-6"
                                                          x-text="c.code"></span>
                                                    <span class="text-muted-foreground" x-text="c.count.toLocaleString() + ' hits'"></span>
                                                </div>
                                                <span class="font-medium tabular-nums text-[11px]"
                                                      x-text="c.pct.toFixed(1) + '%'"></span>
                                            </div>
                                            <div class="h-1.5 bg-muted rounded-full overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-500"
                                                     :class="i === 0 ? 'bg-primary' : (i === 1 ? 'bg-primary/70' : (i === 2 ? 'bg-primary/50' : 'bg-primary/30'))"
                                                     :style="'width:' + c.pct + '%'"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ -->
        <!-- TAB: Traffic Logs                          -->
        <!-- ══════════════════════════════════════════ -->
        <div x-show="activeTab === 'logs'" x-cloak>
            <div class="card">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border-b">
                    <div class="space-y-0.5">
                        <h3 class="font-semibold tracking-tight text-sm">Traffic Logs</h3>
                        <p class="text-[12px] text-muted-foreground">
                            Halaman <span x-text="logsPage"></span>
                            <span x-show="logsMeta.count !== undefined"
                                  x-text="' · ' + logsMeta.count + ' record'"></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model.number="logsLimit" @change="fetchLogs(1)"
                                class="input h-[30px] w-20 text-[11px]">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                    </div>
                </div>

                <!-- Loading -->
                <div x-show="loading.logs" class="flex items-center justify-center py-12 text-muted-foreground text-xs gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    Memuat logs...
                </div>

                <!-- Table -->
                <div x-show="!loading.logs" class="relative overflow-x-auto overflow-y-auto max-h-[calc(100vh-280px)] scroll-logs">
                    <table class="w-full text-[12px]">
                        <thead class="border-b bg-white sticky top-0 z-10">
                            <tr>
                                <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground">Time</th>
                                <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground">Click ID</th>
                                <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground hidden lg:table-cell">IP Address</th>
                                <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground">Country</th>
                                <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground">Decision</th>
                                <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground hidden md:table-cell">User Agent</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0">
                            <template x-if="logs.length === 0">
                                <tr class="border-b">
                                    <td colspan="6" class="p-3 text-center text-[11px] text-muted-foreground">
                                        Tidak ada traffic log.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="r in logs" :key="r.id">
                                <tr class="border-b transition-colors hover:bg-muted/50"
                                    :class="r.decision === 'A' ? 'bg-emerald-50/40' : 'bg-slate-50'">
                                    <td class="p-2 align-middle text-[11px] text-muted-foreground tabular-nums whitespace-nowrap"
                                        x-text="formatTs(r.ts)"></td>
                                    <td class="p-2 align-middle">
                                        <code class="relative rounded bg-muted px-1 py-[0.1rem] font-mono text-[11px] font-semibold"
                                              x-text="r.click_id || '—'"></code>
                                    </td>
                                    <td class="p-2 align-middle hidden lg:table-cell">
                                        <code class="relative rounded bg-muted px-1 py-[0.1rem] font-mono text-[11px] text-muted-foreground"
                                              x-text="r.ip || '—'"></code>
                                    </td>
                                    <td class="p-2 align-middle">
                                        <span class="badge badge-outline text-[11px]" x-text="r.country_code || 'XX'"></span>
                                    </td>
                                    <td class="p-2 align-middle">
                                        <span class="badge text-[11px]"
                                              :class="r.decision === 'A' ? 'badge-default' : 'badge-secondary'"
                                              x-text="r.decision === 'A' ? 'Redirect' : 'Fallback'"></span>
                                    </td>
                                    <td class="p-2 align-middle hidden md:table-cell">
                                        <span class="text-[11px] text-muted-foreground max-w-[200px] truncate block up-text"
                                              x-text="r.ua" :title="r.ua"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div x-show="!loading.logs" class="flex items-center justify-between px-4 py-2.5 border-t text-xs text-muted-foreground">
                    <span x-text="'Page ' + logsPage + ' · ' + (logsMeta.count || 0) + ' records'"></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="fetchLogs(logsPage - 1)"
                                :disabled="logsPage <= 1"
                                class="px-2 py-1 rounded border text-[11px] hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            &lsaquo; Prev
                        </button>
                        <span class="px-2 py-1 text-[11px] tabular-nums" x-text="logsPage"></span>
                        <button type="button" @click="fetchLogs(logsPage + 1)"
                                :disabled="(logsMeta.count || 0) < logsLimit"
                                class="px-2 py-1 rounded border text-[11px] hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Next &rsaquo;
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ -->
        <!-- TAB: Analytics                             -->
        <!-- ══════════════════════════════════════════ -->
        <div x-show="activeTab === 'analytics'" x-cloak>
            <div class="space-y-4">

                <!-- Header + Day Range -->
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold tracking-tight text-sm">Daily Analytics</h3>
                        <p class="text-[11px] text-muted-foreground">Breakdown traffic per hari</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <template x-for="d in [7, 14, 30, 60, 90]" :key="d">
                            <button type="button" @click="analyticsDays = d; analyticsWeekPage = 1; fetchAnalytics()"
                                    class="px-2.5 py-1 text-[11px] font-medium rounded-md transition-colors"
                                    :class="analyticsDays === d
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-muted'">
                                <span x-text="d + 'd'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Loading -->
                <div x-show="loading.analytics" class="flex items-center justify-center py-12 text-muted-foreground text-xs gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    Memuat analytics...
                </div>

                <!-- Empty -->
                <div x-show="!loading.analytics && analytics.length === 0"
                     class="card p-8 text-center text-muted-foreground text-xs">
                    <svg class="h-8 w-8 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Tidak ada data untuk periode ini.
                </div>

                <!-- Summary Cards -->
                <template x-if="!loading.analytics && analytics.length > 0">
                    <div>
                        <div class="grid gap-3 grid-cols-2 md:grid-cols-4 mb-4">
                            <div class="card p-3 text-center">
                                <div class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide mb-1">Total Hits</div>
                                <div class="text-xl font-semibold leading-tight"
                                     x-text="analytics.reduce(function(s,r){ return s + r.total; }, 0).toLocaleString()"></div>
                                <p class="text-[10px] text-muted-foreground mt-0.5" x-text="'Last ' + analyticsDays + ' days'"></p>
                            </div>
                            <div class="card p-3 text-center">
                                <div class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide mb-1">Redirect A</div>
                                <div class="text-xl font-semibold leading-tight text-emerald-600"
                                     x-text="analytics.reduce(function(s,r){ return s + r.a_count; }, 0).toLocaleString()"></div>
                                <p class="text-[10px] text-muted-foreground mt-0.5">Decision A</p>
                            </div>
                            <div class="card p-3 text-center">
                                <div class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide mb-1">Fallback B</div>
                                <div class="text-xl font-semibold leading-tight text-amber-600"
                                     x-text="analytics.reduce(function(s,r){ return s + r.b_count; }, 0).toLocaleString()"></div>
                                <p class="text-[10px] text-muted-foreground mt-0.5">Decision B</p>
                            </div>
                            <div class="card p-3 text-center">
                                <div class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide mb-1">Leads</div>
                                <div class="text-xl font-semibold leading-tight text-violet-600"
                                     x-text="analytics.reduce(function(s,r){ return s + (r.conv_count || 0); }, 0).toLocaleString()"></div>
                                <p class="text-[10px] text-muted-foreground mt-0.5"
                                   x-text="'$' + analytics.reduce(function(s,r){ return s + (r.conv_payout || 0); }, 0).toFixed(2) + ' payout'"></p>
                            </div>
                        </div>

                        <!-- Daily Breakdown Table (paginated per 7 hari) -->
                        <div class="card overflow-hidden">
                            <div class="px-4 py-3 border-b flex items-center justify-between">
                                <h4 class="text-xs font-semibold">Daily Breakdown</h4>
                                <div class="flex items-center gap-3 text-[10px] text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <span class="inline-block w-2 h-2 rounded-sm bg-emerald-500"></span> A
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="inline-block w-2 h-2 rounded-sm bg-amber-400"></span> B
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="inline-block w-2 h-2 rounded-sm bg-violet-500"></span> Leads
                                    </span>
                                </div>
                            </div>

                            <div class="divide-y">
                                <template x-for="row in analyticsPagedRows()" :key="row.day">
                                    <div class="px-4 py-2.5 hover:bg-muted/30 transition-colors">
                                        <div class="flex items-center gap-3">
                                            <span class="text-[11px] font-mono text-muted-foreground w-20 flex-shrink-0"
                                                  x-text="row.day"></span>
                                            <div class="flex-1 flex items-center gap-1 min-w-0">
                                                <div class="flex-1 h-4 bg-muted rounded-sm overflow-hidden flex">
                                                    <div class="h-full bg-emerald-500 transition-all duration-300"
                                                         :style="'width:' + barPct(row, 'a') + '%'"
                                                         :title="'A: ' + row.a_count"></div>
                                                    <div class="h-full bg-amber-400 transition-all duration-300"
                                                         :style="'width:' + barPct(row, 'b') + '%'"
                                                         :title="'B: ' + row.b_count"></div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0 text-[11px] text-right">
                                                <span class="w-10 sm:w-12 font-medium tabular-nums" x-text="row.total.toLocaleString()"></span>
                                                <span class="hidden sm:block w-10 text-emerald-600 font-medium tabular-nums"
                                                      x-text="row.a_count > 0 ? row.a_count.toLocaleString() : '—'"></span>
                                                <span class="hidden sm:block w-10 text-amber-600 font-medium tabular-nums"
                                                      x-text="row.b_count > 0 ? row.b_count.toLocaleString() : '—'"></span>
                                                <span class="hidden sm:block w-10 text-violet-600 font-medium tabular-nums"
                                                      x-text="(row.conv_count || 0) > 0 ? row.conv_count.toLocaleString() : '—'"></span>
                                                <span class="w-10 sm:w-12 text-muted-foreground tabular-nums"
                                                      x-text="row.total > 0
                                                          ? (row.a_count / row.total * 100).toFixed(0) + '%'
                                                          : '—'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Footer: column legend + week pagination -->
                            <div class="px-4 py-2 bg-muted/40 border-t flex items-center justify-between">
                                <div class="flex items-center gap-3 text-[10px] text-muted-foreground font-medium uppercase tracking-wide">
                                    <span class="w-20 flex-shrink-0">Date</span>
                                    <span class="flex-1"></span>
                                    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0 text-right">
                                        <span class="w-10 sm:w-12">Total</span>
                                        <span class="hidden sm:block w-10 text-emerald-600">A</span>
                                        <span class="hidden sm:block w-10 text-amber-600">B</span>
                                        <span class="hidden sm:block w-10 text-violet-600">Leads</span>
                                        <span class="w-10 sm:w-12">Rate</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1" x-show="analyticsTotalWeekPages() > 1">
                                    <button type="button" @click="analyticsWeekPage = Math.max(1, analyticsWeekPage - 1)"
                                            :disabled="analyticsWeekPage <= 1"
                                            class="px-2 py-1 rounded border text-[11px] hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                        &lsaquo;
                                    </button>
                                    <span class="px-2 py-0.5 text-[10px] text-muted-foreground tabular-nums"
                                          x-text="'Week ' + analyticsWeekPage + '/' + analyticsTotalWeekPages()"></span>
                                    <button type="button" @click="analyticsWeekPage = Math.min(analyticsTotalWeekPages(), analyticsWeekPage + 1)"
                                            :disabled="analyticsWeekPage >= analyticsTotalWeekPages()"
                                            class="px-2 py-1 rounded border text-[11px] hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                        &rsaquo;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ -->
        <!-- TAB: Conversions                           -->
        <!-- ══════════════════════════════════════════ -->
        <div x-show="activeTab === 'conversions'" x-cloak>
            <div class="card">
                <div class="p-4 border-b space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="space-y-0.5">
                            <h3 class="font-semibold tracking-tight text-sm">Conversions</h3>
                            <p class="text-[12px] text-muted-foreground">
                                Halaman <span x-text="convPage"></span>
                                <span x-show="convMeta.count !== undefined"
                                      x-text="' · ' + convMeta.count + ' record'"></span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-xs text-violet-600"
                                  x-text="'$' + convTotalPayout.toFixed(2) + ' total'"></span>
                            <select x-model.number="convLimit" @change="filterConversions()"
                                    class="input h-[30px] w-20 text-[11px]">
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                            </select>
                        </div>
                    </div>
                    <!-- Date Range Filter -->
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">Range</label>
                        <input type="date" x-model="convDateFrom"
                               class="input h-[30px] w-[130px] text-[11px] tabular-nums"
                               @change="filterConversions()">
                        <span class="text-[10px] text-muted-foreground">—</span>
                        <input type="date" x-model="convDateTo"
                               class="input h-[30px] w-[130px] text-[11px] tabular-nums"
                               @change="filterConversions()">
                        <button type="button" @click="convDateFrom = ''; convDateTo = ''; filterConversions()"
                                class="btn btn-ghost btn-sm text-[11px] text-muted-foreground"
                                x-show="convDateFrom || convDateTo">
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div x-show="loading.conversions" class="flex items-center justify-center py-12 text-muted-foreground text-xs gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    Memuat conversions...
                </div>

                <!-- Table -->
                <div x-show="!loading.conversions" class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Time</th>
                                <th class="px-4 py-2 font-medium">Click ID</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 font-medium text-right">Payout</th>
                                <th class="px-4 py-2 font-medium hidden md:table-cell">Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="convFiltered.length === 0">
                                <tr class="border-b">
                                    <td colspan="5" class="p-3 text-center text-[11px] text-muted-foreground">
                                        Tidak ada conversion<span x-show="convDateFrom || convDateTo"> untuk rentang tanggal ini</span>.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="c in convFiltered" :key="c.id">
                                <tr class="border-b hover:bg-muted/50 transition-colors">
                                    <td class="px-4 py-2 tabular-nums text-muted-foreground whitespace-nowrap"
                                        x-text="formatTs(c.ts)"></td>
                                    <td class="px-4 py-2 font-mono truncate max-w-[140px]" x-text="c.click_id"></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                              :class="c.status === 'approved' ? 'bg-emerald-100 text-emerald-700'
                                                  : (c.status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')"
                                              x-text="c.status"></span>
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium tabular-nums"
                                        x-text="'$' + parseFloat(c.payout).toFixed(2)"></td>
                                    <td class="px-4 py-2 hidden md:table-cell">
                                        <span x-show="c.country"
                                              class="inline-flex items-center rounded px-1 py-0.5 text-[10px] font-mono font-medium bg-muted text-muted-foreground"
                                              x-text="c.country"></span>
                                        <span x-show="!c.country" class="text-muted-foreground/40 text-[11px]">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot x-show="convFiltered.length > 0">
                            <tr class="border-t bg-muted/30 font-medium text-xs">
                                <td class="px-4 py-2" colspan="3">
                                    <span x-text="convFiltered.length + ' conversion' + (convFiltered.length !== 1 ? 's' : '')"></span>
                                    <span x-show="convDateFrom || convDateTo" class="text-muted-foreground font-normal"
                                          x-text="' (filtered)'"></span>
                                </td>
                                <td class="px-4 py-2 text-right text-violet-600 tabular-nums"
                                    x-text="'$' + convFilteredPayout.toFixed(2)"></td>
                                <td class="hidden md:table-cell"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Pagination (only for API mode without date filter) -->
                <div x-show="!loading.conversions && !convDateFrom && !convDateTo"
                     class="flex items-center justify-between px-4 py-2.5 border-t text-xs text-muted-foreground">
                    <span x-text="'Page ' + convPage + ' · ' + (convMeta.count || 0) + ' records'"></span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="fetchConversions(convPage - 1)"
                                :disabled="convPage <= 1"
                                class="px-2 py-1 rounded border text-[11px] hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            &lsaquo; Prev
                        </button>
                        <span class="px-2 py-1 text-[11px] tabular-nums" x-text="convPage"></span>
                        <button type="button" @click="fetchConversions(convPage + 1)"
                                :disabled="(convMeta.count || 0) < convLimit"
                                class="px-2 py-1 rounded border text-[11px] hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Next &rsaquo;
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </main>

<!-- ─── Footer ──────────────────────────────────────── -->
<footer class="border-t py-4 md:py-5 mt-auto">
    <div class="max-w-5xl mx-auto px-5">
        <p class="text-center text-[11px] text-muted-foreground">
            &copy; <?= date('Y'); ?> SRP Statistics Dashboard
        </p>
    </div>
</footer>

<!-- ─── Alpine.js App ───────────────────────────────── -->
<script>
function statsApp() {
    return {
        /* ── Config ─────────────────── */
        baseUrl: '',
        apiKey: '',
        showConfig: false,
        connected: false,
        connecting: false,
        errorMsg: '',

        /* ── UI State ───────────────── */
        activeTab: 'overview',
        autoRefresh: false,
        refreshing: false,
        refreshTimer: null,

        /* ── Data ───────────────────── */
        stats: {
            traffic: { total: 0, a_count: 0, b_count: 0 },
            conversions: { total: 0, total_payout: 0 }
        },
        logs: [],
        analytics: [],
        conversions: [],

        /* ── Loading ────────────────── */
        loading: { stats: false, logs: false, analytics: false, conversions: false },

        /* ── Pagination ─────────────── */
        logsPage: 1,
        logsLimit: 50,
        logsMeta: {},
        convPage: 1,
        convLimit: 50,
        convMeta: {},
        convTotalPayout: 0,

        /* ── Analytics ──────────────── */
        analyticsDays: 7,
        analyticsWeekPage: 1,

        /* ── Conversions date filter ── */
        convDateFrom: '',
        convDateTo: '',
        convFiltered: [],
        convFilteredPayout: 0,

        /* ── Charts ─────────────────── */
        charts: {},
        topCountries: [],

        /* ── Init ───────────────────── */
        init: function() {
            var saved = localStorage.getItem('srp_stats_config');
            if (saved) {
                try {
                    var cfg = JSON.parse(saved);
                    this.baseUrl = cfg.baseUrl || '';
                    this.apiKey = cfg.apiKey || '';
                    if (this.baseUrl && this.apiKey) {
                        this.connectAndLoad();
                    }
                } catch (e) {
                    // ignore
                }
            }
            /* Re-render charts saat switch ke tab overview */
            var self = this;
            this.$watch('activeTab', function(tab) {
                if (tab === 'overview' && self._chartsReady) {
                    self.$nextTick(function() {
                        setTimeout(function() { self.renderCharts(); }, 50);
                    });
                }
            });
        },

        /* ── Config ─────────────────── */
        saveConfig: function() {
            localStorage.setItem('srp_stats_config', JSON.stringify({
                baseUrl: this.baseUrl,
                apiKey: this.apiKey
            }));
        },

        /* ── Connect ────────────────── */
        connectAndLoad: function() {
            var self = this;
            self.connecting = true;
            self.errorMsg = '';
            self.saveConfig();

            self.apiFetch('/api/v1/stats')
                .then(function(res) {
                    self.connected = true;
                    self.connecting = false;
                    self.showConfig = false;
                    self.stats = res.data;
                    self.refreshAll();
                })
                .catch(function(err) {
                    self.connected = false;
                    self.connecting = false;
                    self.errorMsg = 'Koneksi gagal: ' + (err.message || 'Unknown error');
                });
        },

        /* ── API Fetch Helper ───────── */
        /* Prefix API: /api/v1 (Apache rewrite) atau /api.php/v1 (direct) */
        apiPrefix: '/api/v1',

        apiFetch: function(endpoint, params) {
            var self = this;
            var base = self.baseUrl.replace(/\/+$/, '');
            var url  = base + self.apiPrefix + endpoint.replace(/^\/api\/v1/, '');
            if (params) {
                var qs = Object.keys(params).map(function(k) {
                    return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                }).join('&');
                url += '?' + qs;
            }

            var headers = { 'X-API-Key': self.apiKey, 'Accept': 'application/json' };

            return fetch(url, { method: 'GET', headers: headers })
            .then(function(response) {
                var ct = response.headers.get('content-type') || '';
                /* Jika dapat HTML, coba fallback ke /api.php/v1 path */
                if (ct.indexOf('text/html') !== -1 && self.apiPrefix === '/api/v1') {
                    self.apiPrefix = '/api.php/v1';
                    return self.apiFetch(endpoint, params);
                }
                if (!response.ok) {
                    /* 404: coba fallback ke /api.php/v1 (mod_rewrite tidak aktif) */
                    if (response.status === 404 && self.apiPrefix === '/api/v1') {
                        self.apiPrefix = '/api.php/v1';
                        return self.apiFetch(endpoint, params);
                    }
                    if (response.status === 401) {
                        throw new Error('API Key tidak valid (401)');
                    }
                    if (response.status === 429) {
                        throw new Error('Rate limit terlampaui (429)');
                    }
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            }).then(function(json) {
                if (!json.ok) {
                    throw new Error(json.error || 'API error');
                }
                return json;
            });
        },

        /* ── Refresh All ────────────── */
        refreshAll: function() {
            this.refreshing = true;
            this.fetchStats();
            this.fetchLogsForCountries();
            this.fetchLogs(this.logsPage);
            this.fetchAnalytics();
            this.fetchConversions(this.convPage);
            var self = this;
            setTimeout(function() { self.refreshing = false; }, 1000);
        },

        /* ── Auto Refresh ───────────── */
        toggleAutoRefresh: function() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
            if (this.autoRefresh) {
                var self = this;
                this.refreshTimer = setInterval(function() { self.refreshAll(); }, 15000);
            }
        },

        /* ── Fetch Stats ────────────── */
        fetchStats: function() {
            var self = this;
            self.loading.stats = true;
            self.apiFetch('/api/v1/stats')
                .then(function(res) {
                    self.stats = res.data;
                    self.renderCharts();
                })
                .catch(function(err) {
                    console.error('Stats error:', err);
                })
                .finally(function() {
                    self.loading.stats = false;
                });
        },

        /* ── Fetch Logs ─────────────── */
        fetchLogs: function(page) {
            if (page < 1) return;
            var self = this;
            self.logsPage = page;
            self.loading.logs = true;
            self.apiFetch('/api/v1/logs', { limit: self.logsLimit, page: page })
                .then(function(res) {
                    self.logs = res.data || [];
                    self.logsMeta = res.meta || {};
                })
                .catch(function(err) {
                    console.error('Logs error:', err);
                })
                .finally(function() {
                    self.loading.logs = false;
                });
        },

        /* ── Fetch Logs for Top Countries ── */
        fetchLogsForCountries: function() {
            var self = this;
            self.apiFetch('/api/v1/logs', { limit: 200, page: 1 })
                .then(function(res) {
                    var allLogs = res.data || [];
                    var map = {};
                    var total = 0;
                    allLogs.forEach(function(r) {
                        var cc = r.country_code || 'XX';
                        map[cc] = (map[cc] || 0) + 1;
                        total++;
                    });
                    var arr = Object.keys(map).map(function(code) {
                        return { code: code, count: map[code], pct: total > 0 ? (map[code] / total * 100) : 0 };
                    });
                    arr.sort(function(a, b) { return b.count - a.count; });
                    self.topCountries = arr.slice(0, 10);
                })
                .catch(function(err) {
                    console.error('Country logs error:', err);
                });
        },

        /* ── Fetch Analytics ────────── */
        fetchAnalytics: function() {
            var self = this;
            self.loading.analytics = true;
            self.apiFetch('/api/v1/analytics', { days: self.analyticsDays })
                .then(function(res) {
                    self.analytics = res.data || [];
                    self.renderCharts();
                })
                .catch(function(err) {
                    console.error('Analytics error:', err);
                })
                .finally(function() {
                    self.loading.analytics = false;
                });
        },

        /* ── Fetch Conversions ──────── */
        fetchConversions: function(page) {
            if (page < 1) return;
            var self = this;
            self.convPage = page;
            self.loading.conversions = true;
            self.apiFetch('/api/v1/conversions', { limit: self.convLimit, page: page })
                .then(function(res) {
                    self.conversions = res.data || [];
                    self.convMeta = res.meta || {};
                    self.convTotalPayout = self.conversions.reduce(function(s, c) {
                        return s + (parseFloat(c.payout) || 0);
                    }, 0);
                    self.filterConversions();
                })
                .catch(function(err) {
                    console.error('Conversions error:', err);
                })
                .finally(function() {
                    self.loading.conversions = false;
                });
        },

        /* ── Analytics weekly pagination ── */
        analyticsPagedRows: function() {
            var perPage = 7;
            var start = (this.analyticsWeekPage - 1) * perPage;
            return this.analytics.slice(start, start + perPage);
        },

        analyticsTotalWeekPages: function() {
            return Math.max(1, Math.ceil(this.analytics.length / 7));
        },

        /* ── Conversions date filter ── */
        filterConversions: function() {
            var self = this;
            var fromTs = self.convDateFrom ? (new Date(self.convDateFrom + 'T00:00:00').getTime() / 1000) : 0;
            var toTs = self.convDateTo ? (new Date(self.convDateTo + 'T23:59:59').getTime() / 1000) : Infinity;

            if (!self.convDateFrom && !self.convDateTo) {
                self.convFiltered = self.conversions;
            } else {
                self.convFiltered = self.conversions.filter(function(c) {
                    return c.ts >= fromTs && c.ts <= toTs;
                });
            }
            self.convFilteredPayout = self.convFiltered.reduce(function(s, c) {
                return s + (parseFloat(c.payout) || 0);
            }, 0);
        },

        /* ── Helpers ────────────────── */
        formatTs: function(ts) {
            if (!ts) return '—';
            var d = new Date(ts * 1000);
            return d.toLocaleString(undefined, {
                month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        },

        barPct: function(row, type) {
            var max = this.analytics.reduce(function(m, r) {
                return Math.max(m, r.total);
            }, 1);
            if (type === 'a') return (row.a_count / max * 100).toFixed(1);
            return (row.b_count / max * 100).toFixed(1);
        },

        /* ── Charts ────────────────── */
        _chartRetry: 0,
        _chartsReady: false,
        _isCanvasReady: function(id) {
            var canvas = document.getElementById(id);
            if (!canvas || !canvas.getContext) return false;
            /* Canvas harus visible: punya dimensi dan tidak di-hide */
            var rect = canvas.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0) return false;
            /* Pastikan 2d context bisa didapat (null jika canvas belum siap) */
            var ctx2d = canvas.getContext('2d');
            return ctx2d !== null;
        },
        renderCharts: function() {
            var self = this;
            self._chartsReady = true;
            /* Hanya render jika tab overview aktif */
            if (self.activeTab !== 'overview') return;

            /* Gunakan requestAnimationFrame agar yakin browser sudah layout */
            requestAnimationFrame(function() {
                if (!self._isCanvasReady('trafficLineChart')) {
                    /* Canvas belum visible, retry */
                    if (self._chartRetry < 20) {
                        self._chartRetry++;
                        setTimeout(function() { self.renderCharts(); }, 200);
                    }
                    return;
                }
                self._chartRetry = 0;
                self.renderTrafficLine();
                self.renderDecisionDoughnut();
                self.renderConvBar();
            });
        },

        renderTrafficLine: function() {
            var ctx = document.getElementById('trafficLineChart');
            if (!ctx || !ctx.getContext || !this._isCanvasReady('trafficLineChart')) return;
            var ctx2d = ctx.getContext('2d');
            if (!ctx2d) return;
            if (this.charts.line) this.charts.line.destroy();

            var data = (this.analytics || []).slice().reverse();
            var labels = data.map(function(r) {
                var parts = r.day.split('-');
                return parts[2] + '/' + parts[1];
            });

            this.charts.line = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Redirect A',
                            data: data.map(function(r) { return r.a_count; }),
                            borderColor: 'rgb(16,185,129)',
                            backgroundColor: 'rgba(16,185,129,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'Fallback B',
                            data: data.map(function(r) { return r.b_count; }),
                            borderColor: 'rgb(251,191,36)',
                            backgroundColor: 'rgba(251,191,36,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'Leads',
                            data: data.map(function(r) { return r.conv_count || 0; }),
                            borderColor: 'rgb(139,92,246)',
                            backgroundColor: 'rgba(139,92,246,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'hsl(240,5.9%,10%)',
                            titleFont: { size: 11 },
                            bodyFont: { size: 11 },
                            padding: 8,
                            cornerRadius: 6
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10 }, color: 'hsl(240,4%,46%)' }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'hsl(240,6%,93%)' },
                            ticks: {
                                font: { size: 10 },
                                color: 'hsl(240,4%,46%)',
                                precision: 0
                            }
                        }
                    }
                }
            });
        },

        renderDecisionDoughnut: function() {
            var ctx = document.getElementById('decisionDoughnut');
            if (!ctx || !ctx.getContext || !this.stats || !this.stats.traffic || !this.stats.conversions || !this._isCanvasReady('decisionDoughnut')) return;
            var ctx2d = ctx.getContext('2d');
            if (!ctx2d) return;
            if (this.charts.doughnut) this.charts.doughnut.destroy();

            var a = this.stats.traffic.a_count || 0;
            var b = this.stats.traffic.b_count || 0;
            var leads = this.stats.conversions.total || 0;
            var hasData = (a + b + leads) > 0;

            this.charts.doughnut = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Redirect A', 'Fallback B', 'Leads'],
                    datasets: [{
                        data: hasData ? [a, b, leads] : [1],
                        backgroundColor: hasData
                            ? ['rgb(16,185,129)', 'rgb(251,191,36)', 'rgb(139,92,246)']
                            : ['hsl(240,5%,90%)'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: hasData,
                            backgroundColor: 'hsl(240,5.9%,10%)',
                            bodyFont: { size: 11 },
                            padding: 8,
                            cornerRadius: 6
                        }
                    }
                }
            });
        },

        renderConvBar: function() {
            var ctx = document.getElementById('convBarChart');
            if (!ctx || !ctx.getContext || !this._isCanvasReady('convBarChart')) return;
            var ctx2d = ctx.getContext('2d');
            if (!ctx2d) return;
            if (this.charts.convBar) this.charts.convBar.destroy();

            var data = (this.analytics || []).slice().reverse();
            var labels = data.map(function(r) {
                var parts = r.day.split('-');
                return parts[2] + '/' + parts[1];
            });

            this.charts.convBar = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Conversions',
                            data: data.map(function(r) { return r.conv_count || 0; }),
                            backgroundColor: 'rgba(139,92,246,0.7)',
                            borderRadius: 3,
                            yAxisID: 'y',
                            order: 2
                        },
                        {
                            label: 'Payout ($)',
                            data: data.map(function(r) { return r.conv_payout || 0; }),
                            type: 'line',
                            borderColor: 'rgb(16,185,129)',
                            backgroundColor: 'rgba(16,185,129,0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            borderWidth: 2,
                            yAxisID: 'y1',
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: { font: { size: 10 }, boxWidth: 8, boxHeight: 8, padding: 12 }
                        },
                        tooltip: {
                            backgroundColor: 'hsl(240,5.9%,10%)',
                            titleFont: { size: 11 },
                            bodyFont: { size: 11 },
                            padding: 8,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.dataset.yAxisID === 'y1') {
                                        return ctx.dataset.label + ': $' + (ctx.raw || 0).toFixed(2);
                                    }
                                    return ctx.dataset.label + ': ' + ctx.raw;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10 }, color: 'hsl(240,4%,46%)' }
                        },
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            grid: { color: 'hsl(240,6%,93%)' },
                            ticks: { font: { size: 10 }, color: 'hsl(240,4%,46%)', precision: 0 },
                            title: { display: true, text: 'Count', font: { size: 10 }, color: 'hsl(240,4%,46%)' }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                font: { size: 10 },
                                color: 'rgb(16,185,129)',
                                callback: function(v) { return '$' + v; }
                            },
                            title: { display: true, text: 'Payout', font: { size: 10 }, color: 'rgb(16,185,129)' }
                        }
                    }
                }
            });
        }
    };
}
</script>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
