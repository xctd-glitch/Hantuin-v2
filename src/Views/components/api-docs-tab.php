<!-- API Docs Tab -->
<div x-show="activeTab === 'api-docs'" x-cloak>
    <div class="space-y-4">

        <!-- Header -->
        <div>
            <h2 class="text-sm font-semibold">API Integration Guide</h2>
            <p class="text-[10px] text-muted-foreground mt-0.5">
                Public REST API v1 — integrate Hantuin-v2 routing into your own platform
            </p>
        </div>

        <!-- Credentials & Base URL -->
        <div class="card p-3 space-y-2.5">
            <h3 class="text-xs font-semibold flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                Credentials
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <!-- Base URL -->
                <div>
                    <p class="text-[10px] text-muted-foreground mb-1">Base URL</p>
                    <div class="flex items-center gap-1">
                        <code class="text-[11px] bg-muted px-2 py-1 rounded font-mono flex-1 min-w-0 truncate"
                              x-text="getApiBase()"></code>
                        <button @click="copyCode('baseurl', getApiBase())"
                                class="btn btn-ghost btn-sm text-[10px] flex-shrink-0 px-2">
                            <span x-text="copiedBlock==='baseurl' ? 'Copied!' : 'Copy'"></span>
                        </button>
                    </div>
                </div>
                <!-- API Key -->
                <div>
                    <p class="text-[10px] text-muted-foreground mb-1">API Key <span class="text-[9px]">(SRP_API_KEY)</span></p>
                    <div class="flex items-center gap-1">
                        <code class="text-[11px] bg-muted px-2 py-1 rounded font-mono flex-1 min-w-0 truncate"
                              x-text="envConfig.SRP_API_KEY
                                  ? (showApiKey ? envConfig.SRP_API_KEY : envConfig.SRP_API_KEY.substring(0,12) + '••••••••••••')
                                  : 'Not configured'"></code>
                        <button @click="showApiKey = !showApiKey"
                                :disabled="!envConfig.SRP_API_KEY"
                                :title="showApiKey ? 'Sembunyikan key' : 'Tampilkan key'"
                                class="btn btn-ghost btn-sm text-[10px] flex-shrink-0 px-1.5">
                            <svg x-show="!showApiKey" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showApiKey" x-cloak class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                        <button @click="copyCode('apikey', envConfig.SRP_API_KEY)"
                                :disabled="!envConfig.SRP_API_KEY"
                                class="btn btn-ghost btn-sm text-[10px] flex-shrink-0 px-2">
                            <span x-text="copiedBlock==='apikey' ? 'Copied!' : 'Copy'"></span>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Auth header snippet -->
            <div>
                <p class="text-[10px] text-muted-foreground mb-1">Auth header (semua request)</p>
                <div class="relative">
                    <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-2.5 rounded overflow-x-auto font-mono"><code>X-API-Key: <span class="text-sky-300" x-text="envConfig.SRP_API_KEY || 'your_api_key_here'"></span></code></pre>
                </div>
            </div>
            <!-- Rate limit + tracing note -->
            <div class="flex flex-col gap-1.5 text-[10px] text-muted-foreground">
                <div class="flex items-center gap-2">
                    <svg class="h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Rate limit: <span class="font-medium text-foreground">120 requests / 60 s</span> per IP &nbsp;·&nbsp;
                    Response header: <code class="bg-muted px-1 rounded">Retry-After: 60</code> saat kena limit
                </div>
                <div class="flex items-center gap-2">
                    <svg class="h-3 w-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                    Tracing: kirim <code class="bg-muted px-1 rounded">X-Request-ID: &lt;id&gt;</code> → server echo-back di response header
                </div>
            </div>
        </div>

        <!-- Endpoints Overview -->
        <div class="card p-3">
            <h3 class="text-xs font-semibold mb-2">Endpoints</h3>
            <div class="space-y-1">
                <!-- decision — highlighted -->
                <div class="flex items-center gap-2 py-1.5 px-2 rounded bg-primary/5 border border-primary/15">
                    <span class="badge badge-default text-[10px] font-mono flex-shrink-0">POST</span>
                    <code class="text-[11px] font-mono text-primary font-medium flex-1">/api/v1/decision</code>
                    <span class="text-[10px] text-muted-foreground hidden sm:block">Routing decision &amp; traffic log</span>
                    <button @click="apiDocsSection = 'decision'"
                            class="btn btn-ghost btn-sm text-[10px] px-2 flex-shrink-0">
                        View
                    </button>
                </div>
                <!-- other endpoints -->
                <template x-for="ep in apiEndpoints" :key="ep.path">
                    <div class="flex items-center gap-2 py-1 px-2">
                        <span class="text-[10px] font-mono font-medium flex-shrink-0 w-8"
                              :class="ep.method==='POST' ? 'text-amber-600' : 'text-emerald-600'"
                              x-text="ep.method"></span>
                        <code class="text-[11px] font-mono flex-1" x-text="ep.path"></code>
                        <span class="text-[10px] text-muted-foreground hidden sm:block" x-text="ep.desc"></span>
                        <button @click="apiDocsSection = ep.key"
                                class="btn btn-ghost btn-sm text-[10px] px-2 flex-shrink-0">
                            View
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- ── POST /api/v1/decision ── -->
        <div x-show="apiDocsSection === 'decision'" class="card p-3 space-y-3" x-data="{ open: false }">
            <div class="flex items-center gap-2 select-none" :class="userRole === 'admin' ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'" @click="userRole === 'admin' && (open = !open)">
                <span class="badge badge-default text-[10px] font-mono">POST</span>
                <code class="text-[12px] font-mono font-semibold">/api/v1/decision</code>
                <span class="badge badge-secondary text-[9px]">Main Endpoint</span>
                <svg class="h-3.5 w-3.5 ml-auto text-muted-foreground transition-transform duration-200"
                     :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>

            <div x-show="open" x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-3">

            <p class="text-[11px] text-muted-foreground">
                Evaluasi routing decision berdasarkan device, country, dan VPN. Otomatis log traffic.
            </p>

            <!-- Request body params -->
            <div>
                <p class="text-[10px] font-semibold mb-1.5">Request Body <span class="font-normal text-muted-foreground">(JSON)</span></p>
                <div class="overflow-x-auto">
                    <table class="w-full text-[11px]">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-1 pr-3 font-medium text-muted-foreground w-28">Field</th>
                                <th class="text-left py-1 pr-3 font-medium text-muted-foreground w-16">Type</th>
                                <th class="text-left py-1 pr-3 font-medium text-muted-foreground w-16">Required</th>
                                <th class="text-left py-1 font-medium text-muted-foreground">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b">
                                <td class="py-1 pr-3"><code class="bg-muted px-1 rounded text-[10px]">click_id</code></td>
                                <td class="py-1 pr-3 text-muted-foreground">string</td>
                                <td class="py-1 pr-3"><span class="text-emerald-600 font-medium">Yes</span></td>
                                <td class="py-1 text-muted-foreground">Tracking ID dari affiliate network (alphanumeric, _, -)</td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-3"><code class="bg-muted px-1 rounded text-[10px]">country_code</code></td>
                                <td class="py-1 pr-3 text-muted-foreground">string</td>
                                <td class="py-1 pr-3"><span class="text-emerald-600 font-medium">Yes</span></td>
                                <td class="py-1 text-muted-foreground">ISO Alpha-2 country code (e.g. <code>ID</code>, <code>US</code>)</td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-3"><code class="bg-muted px-1 rounded text-[10px]">user_agent</code></td>
                                <td class="py-1 pr-3 text-muted-foreground">string</td>
                                <td class="py-1 pr-3"><span class="text-emerald-600 font-medium">Yes</span></td>
                                <td class="py-1 text-muted-foreground">Raw UA string atau shorthand: <code>mobile</code> / <code>desktop</code></td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-3"><code class="bg-muted px-1 rounded text-[10px]">ip_address</code></td>
                                <td class="py-1 pr-3 text-muted-foreground">string</td>
                                <td class="py-1 pr-3"><span class="text-emerald-600 font-medium">Yes</span></td>
                                <td class="py-1 text-muted-foreground">IPv4 atau IPv6 visitor</td>
                            </tr>
                            <tr>
                                <td class="py-1 pr-3"><code class="bg-muted px-1 rounded text-[10px]">user_lp</code></td>
                                <td class="py-1 pr-3 text-muted-foreground">string</td>
                                <td class="py-1 pr-3 text-muted-foreground">No</td>
                                <td class="py-1 text-muted-foreground">Landing page / campaign identifier</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Language switcher + code block -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-[10px] font-semibold">Code Example</p>
                    <div class="flex gap-1">
                        <template x-for="lang in ['curl','php','python']" :key="lang">
                            <button
                                @click="apiDocsLang = lang"
                                class="px-2 py-0.5 rounded text-[10px] font-mono font-medium transition-colors"
                                :class="apiDocsLang === lang
                                    ? 'bg-slate-700 text-white'
                                    : 'bg-muted text-muted-foreground hover:text-foreground'"
                                x-text="lang"></button>
                        </template>
                    </div>
                </div>
                <div class="relative">
                    <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 rounded overflow-x-auto font-mono whitespace-pre"><code x-text="getDecisionExample(apiDocsLang)"></code></pre>
                    <button
                        @click="copyCode('decision-code', getDecisionExample(apiDocsLang))"
                        class="absolute top-2 right-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-[10px] px-2 py-0.5 rounded transition-colors">
                        <span x-text="copiedBlock==='decision-code' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
            </div>

            <!-- Response examples -->
            <div>
                <p class="text-[10px] font-semibold mb-1.5">Response</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <!-- Decision A -->
                    <div>
                        <p class="text-[10px] text-emerald-600 font-medium mb-1 flex items-center gap-1">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Decision A — Redirect
                        </p>
                        <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-2.5 rounded font-mono whitespace-pre">{
  "ok": true,
  "decision": "A",
  "target": "https://offer.example.com",
  "reason": "ok",
  "ts": 1741234567
}</pre>
                    </div>
                    <!-- Decision B -->
                    <div>
                        <p class="text-[10px] text-slate-500 font-medium mb-1 flex items-center gap-1">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                            Decision B — Fallback
                        </p>
                        <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-2.5 rounded font-mono whitespace-pre">{
  "ok": true,
  "decision": "B",
  "target": "/_meetups/?click_id=...",
  "reason": "not_mobile",
  "ts": 1741234567
}</pre>
                    </div>
                </div>
            </div>

            <!-- Decision Logic -->
            <div class="rounded-[0.3rem] border border-slate-200 bg-slate-50 p-2.5">
                <p class="text-[10px] font-semibold mb-1.5">Decision A diberikan jika SEMUA kondisi terpenuhi:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 text-[10px]">
                    <div class="flex items-center gap-1.5">
                        <svg class="h-3 w-3 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        System ON
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-3 w-3 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        Country allowed (filter mode)
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-3 w-3 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        Mobile device (UA detection)
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-3 w-3 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        No VPN / proxy detected
                    </div>
                    <div class="flex items-center gap-1.5 sm:col-span-2">
                        <svg class="h-3 w-3 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        IP valid &amp; not blocked
                    </div>
                </div>
            </div>

            <!-- Reason codes reference -->
            <div>
                <p class="text-[10px] font-semibold mb-1.5">
                    Field <code class="bg-muted px-1 rounded">reason</code> — nilai yang mungkin
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-[11px]">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-1 pr-4 font-medium text-muted-foreground w-36">reason</th>
                                <th class="text-left py-1 font-medium text-muted-foreground">Kondisi</th>
                            </tr>
                        </thead>
                        <tbody class="text-muted-foreground">
                            <tr class="border-b">
                                <td class="py-1 pr-4"><code class="bg-emerald-50 text-emerald-700 px-1 rounded text-[10px]">ok</code></td>
                                <td class="py-1">Decision A — semua kondisi terpenuhi</td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-4"><code class="bg-muted px-1 rounded text-[10px]">system_off</code></td>
                                <td class="py-1">System dalam kondisi OFF</td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-4"><code class="bg-muted px-1 rounded text-[10px]">muted</code></td>
                                <td class="py-1">System ON tetapi sedang dalam slot muted (throttle)</td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-4"><code class="bg-muted px-1 rounded text-[10px]">bot</code></td>
                                <td class="py-1">UA terdeteksi sebagai bot / crawler</td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-4"><code class="bg-muted px-1 rounded text-[10px]">not_mobile</code></td>
                                <td class="py-1">Device adalah desktop (bukan mobile/tablet)</td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-4"><code class="bg-muted px-1 rounded text-[10px]">vpn</code></td>
                                <td class="py-1">IP terdeteksi sebagai VPN / proxy</td>
                            </tr>
                            <tr class="border-b">
                                <td class="py-1 pr-4"><code class="bg-muted px-1 rounded text-[10px]">country_blocked</code></td>
                                <td class="py-1">Country code tidak masuk filter whitelist / masuk blacklist</td>
                            </tr>
                            <tr>
                                <td class="py-1 pr-4"><code class="bg-muted px-1 rounded text-[10px]">no_redirect_url</code></td>
                                <td class="py-1">Redirect URL belum dikonfigurasi atau tidak valid HTTPS</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            </div><!-- end collapsible -->
        </div>

        <!-- ── GET /api/v1/status ── -->
        <div x-show="apiDocsSection === 'status'" class="card p-3 space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-mono font-semibold text-emerald-600">GET</span>
                <code class="text-[12px] font-mono font-semibold">/api/v1/status</code>
            </div>
            <p class="text-[11px] text-muted-foreground">Baca konfigurasi sistem saat ini (read-only snapshot).</p>
            <div>
                <p class="text-[10px] font-semibold mb-1.5">Example</p>
                <div class="relative">
                    <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 rounded overflow-x-auto font-mono whitespace-pre"><code x-text="getSimpleGetExample('status')"></code></pre>
                    <button @click="copyCode('status-code', getSimpleGetExample('status'))"
                            class="absolute top-2 right-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-[10px] px-2 py-0.5 rounded transition-colors">
                        <span x-text="copiedBlock==='status-code' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
            </div>
            <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-2.5 rounded font-mono whitespace-pre">// Response
{
  "ok": true,
  "data": {
    "system_on":           true,
    "redirect_url":        "https://offer.example.com",
    "country_filter_mode": "whitelist",
    "country_filter_list": "ID,MY,SG",
    "updated_at":          1710000000
  }
}</pre>
        </div>

        <!-- ── GET /api/v1/stats ── -->
        <div x-show="apiDocsSection === 'stats'" class="card p-3 space-y-3" x-data="{ open: true }">
            <div class="flex items-center gap-2 cursor-pointer select-none" @click="open = !open">
                <span class="text-[10px] font-mono font-semibold text-emerald-600">GET</span>
                <code class="text-[12px] font-mono font-semibold">/api/v1/stats</code>
                <svg class="h-3.5 w-3.5 ml-auto text-muted-foreground transition-transform duration-200"
                     :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
            <div x-show="open" x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-3">
                <p class="text-[11px] text-muted-foreground">Weekly traffic totals + conversion totals (Senin 00:00 s/d sekarang).</p>
                <div>
                    <p class="text-[10px] font-semibold mb-1.5">Example</p>
                    <div class="relative">
                        <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 rounded overflow-x-auto font-mono whitespace-pre"><code x-text="getSimpleGetExample('stats')"></code></pre>
                        <button @click.stop="copyCode('stats-code', getSimpleGetExample('stats'))"
                                class="absolute top-2 right-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-[10px] px-2 py-0.5 rounded transition-colors">
                            <span x-text="copiedBlock==='stats-code' ? 'Copied!' : 'Copy'"></span>
                        </button>
                    </div>
                </div>
                <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-2.5 rounded font-mono whitespace-pre">// Response
{
  "ok": true,
  "data": {
    "traffic":     { "total": 1250, "a_count": 870, "b_count": 380, "since": 1709596800 },
    "conversions": { "total": 34, "total_payout": 102.50 }
  }
}</pre>
            </div>
        </div>

        <!-- ── GET /api/v1/logs ── -->
        <div x-show="apiDocsSection === 'logs'" class="card p-3 space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-mono font-semibold text-emerald-600">GET</span>
                <code class="text-[12px] font-mono font-semibold">/api/v1/logs</code>
                <span class="text-[10px] text-muted-foreground">?limit=50&amp;page=1</span>
            </div>
            <p class="text-[11px] text-muted-foreground">Paginated traffic logs, newest first. Max limit: 200.</p>
            <div>
                <p class="text-[10px] font-semibold mb-1.5">Example</p>
                <div class="relative">
                    <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 rounded overflow-x-auto font-mono whitespace-pre"><code x-text="getSimpleGetExample('logs?limit=50&page=1')"></code></pre>
                    <button @click="copyCode('logs-code', getSimpleGetExample('logs?limit=50&page=1'))"
                            class="absolute top-2 right-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-[10px] px-2 py-0.5 rounded transition-colors">
                        <span x-text="copiedBlock==='logs-code' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
            </div>
            <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-2.5 rounded font-mono whitespace-pre">// Response
{
  "ok":   true,
  "data": [
    { "id": 99, "ts": 1710000000, "ip": "103.10.20.30",
      "ua": "Mozilla/5.0 (Linux; Android 13...)",
      "click_id": "abc123", "country_code": "ID",
      "user_lp": "promo2024", "decision": "A" }
  ],
  "meta": { "page": 1, "limit": 50, "count": 50 }
}</pre>
        </div>

        <!-- ── GET /api/v1/analytics ── -->
        <div x-show="apiDocsSection === 'analytics'" class="card p-3 space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-mono font-semibold text-emerald-600">GET</span>
                <code class="text-[12px] font-mono font-semibold">/api/v1/analytics</code>
                <span class="text-[10px] text-muted-foreground">?days=30</span>
            </div>
            <p class="text-[11px] text-muted-foreground">Daily aggregated traffic + conversion data. Max 90 hari.</p>
            <div>
                <p class="text-[10px] font-semibold mb-1.5">Example</p>
                <div class="relative">
                    <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 rounded overflow-x-auto font-mono whitespace-pre"><code x-text="getSimpleGetExample('analytics?days=30')"></code></pre>
                    <button @click="copyCode('analytics-code', getSimpleGetExample('analytics?days=30'))"
                            class="absolute top-2 right-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-[10px] px-2 py-0.5 rounded transition-colors">
                        <span x-text="copiedBlock==='analytics-code' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
            </div>
            <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-2.5 rounded font-mono whitespace-pre">// Response
{
  "ok":   true,
  "data": [
    { "day": "2026-03-12", "total": 320, "a_count": 218, "b_count": 102,
      "conv_count": 8, "conv_payout": 24.00 }
  ]
}</pre>
        </div>

        <!-- ── GET /api/v1/conversions ── -->
        <div x-show="apiDocsSection === 'conversions'" class="card p-3 space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-mono font-semibold text-emerald-600">GET</span>
                <code class="text-[12px] font-mono font-semibold">/api/v1/conversions</code>
                <span class="text-[10px] text-muted-foreground">?limit=30&amp;page=1</span>
            </div>
            <p class="text-[11px] text-muted-foreground">Paginated inbound conversion records, newest first. Max limit: 200.</p>
            <div>
                <p class="text-[10px] font-semibold mb-1.5">Example</p>
                <div class="relative">
                    <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 rounded overflow-x-auto font-mono whitespace-pre"><code x-text="getSimpleGetExample('conversions?limit=30&page=1')"></code></pre>
                    <button @click="copyCode('conversions-code', getSimpleGetExample('conversions?limit=30&page=1'))"
                            class="absolute top-2 right-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-[10px] px-2 py-0.5 rounded transition-colors">
                        <span x-text="copiedBlock==='conversions-code' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
            </div>
            <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-2.5 rounded font-mono whitespace-pre">// Response
{
  "ok":   true,
  "data": [
    { "id": 12, "ts": 1710000000, "click_id": "abc123",
      "payout": "3.0000", "currency": "USD",
      "status": "approved", "ip": "103.10.20.30" }
  ],
  "meta": { "page": 1, "limit": 30, "count": 30 }
}</pre>
        </div>

        <!-- ── POST /api/v1/settings ── -->
        <div x-show="apiDocsSection === 'settings'" class="card p-3 space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-mono font-semibold text-amber-600">POST</span>
                <code class="text-[12px] font-mono font-semibold">/api/v1/settings</code>
            </div>
            <p class="text-[11px] text-muted-foreground">Partial-update system settings. Hanya field yang disertakan yang diupdate.</p>
            <div>
                <p class="text-[10px] font-semibold mb-1.5">Example</p>
                <div class="relative">
                    <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 rounded overflow-x-auto font-mono whitespace-pre"><code x-text="getSettingsExample()"></code></pre>
                    <button @click="copyCode('settings-code', getSettingsExample())"
                            class="absolute top-2 right-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-[10px] px-2 py-0.5 rounded transition-colors">
                        <span x-text="copiedBlock==='settings-code' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
            </div>
            <div>
                <p class="text-[10px] font-semibold mb-1">Body Fields <span class="font-normal text-muted-foreground">(all optional)</span></p>
                <div class="text-[10px] space-y-0.5 text-muted-foreground">
                    <p><code class="bg-muted px-1 rounded">system_on</code> boolean — nyalakan/matikan sistem</p>
                    <p><code class="bg-muted px-1 rounded">redirect_url</code> string (HTTPS) — target redirect</p>
                    <p><code class="bg-muted px-1 rounded">country_filter_mode</code> <code class="bg-muted px-1 rounded">all</code> | <code class="bg-muted px-1 rounded">whitelist</code> | <code class="bg-muted px-1 rounded">blacklist</code></p>
                    <p><code class="bg-muted px-1 rounded">country_filter_list</code> string — comma-separated ISO codes</p>
                    <p><code class="bg-muted px-1 rounded">postback_token</code> string — inbound postback secret</p>
                </div>
            </div>
        </div>

        <!-- ── PHP Client Integration Guide ── -->
        <div class="card p-3 space-y-3" x-data="{ open: false }">

            <!-- Header -->
            <div class="flex items-center gap-2 select-none" :class="userRole === 'admin' ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'" @click="userRole === 'admin' && (open = !open)">
                <span class="inline-flex items-center rounded-full border border-violet-300/60 bg-violet-50 px-2 py-0.5 text-[10px] font-semibold text-violet-600 uppercase tracking-wide">PHP</span>
                <h3 class="text-xs font-semibold">Client Integration — Server ke Server</h3>
                <div class="flex items-center gap-1.5 ml-auto">
                    <span class="text-[10px] text-muted-foreground hidden sm:block" x-text="open ? 'Sembunyikan' : 'Tampilkan'"></span>
                    <svg class="h-3.5 w-3.5 text-muted-foreground transition-transform duration-200"
                         :class="open ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>

            <div x-show="open" x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="space-y-3">

            <p class="text-[11px] text-muted-foreground">
                Dua cara integrasi: (1) entry.php standalone siap deploy, atau (2) class SrpApiClient reusable + usage.php.
            </p>

            <!-- File tabs -->
            <div class="flex gap-1 flex-wrap">
                <button @click="phpClientTab = 'class'"
                        class="px-2 py-0.5 rounded text-[10px] font-mono font-medium transition-colors"
                        :class="phpClientTab === 'class'
                            ? 'bg-slate-700 text-white'
                            : 'bg-muted text-muted-foreground hover:text-foreground'">
                    SrpApiClient.php
                </button>
                <button @click="phpClientTab = 'usage'"
                        class="px-2 py-0.5 rounded text-[10px] font-mono font-medium transition-colors"
                        :class="phpClientTab === 'usage'
                            ? 'bg-slate-700 text-white'
                            : 'bg-muted text-muted-foreground hover:text-foreground'">
                    usage.php
                </button>
                <button @click="phpClientTab = 'full'"
                        class="px-2 py-0.5 rounded text-[10px] font-mono font-medium transition-colors"
                        :class="phpClientTab === 'full'
                            ? 'bg-slate-700 text-white'
                            : 'bg-muted text-muted-foreground hover:text-foreground'">
                    entry.php
                </button>
            </div>

            <!-- File description -->
            <p class="text-[10px] text-muted-foreground -mt-1"
               x-text="phpClientTab === 'class'
                   ? 'Reusable class — salin ke project, tidak perlu dependensi tambahan'
                   : phpClientTab === 'usage'
                   ? 'Contoh pemakaian SrpApiClient — cocok untuk integrasi cepat'
                   : 'Entry point lengkap siap deploy — termasuk IP detection & fallback'"></p>

            <!-- Code block -->
            <div class="relative">
                <pre class="bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 rounded font-mono whitespace-pre overflow-x-auto scroll-logs"
                     style="max-height:420px;overflow-y:auto;"><code x-text="phpClientTab === 'class'
                        ? getPhpClientClass()
                        : phpClientTab === 'usage'
                        ? getPhpClientUsage()
                        : getPhpClientFull()"></code></pre>
                <button @click="copyCode('php-client',
                            phpClientTab === 'class'  ? getPhpClientClass()  :
                            phpClientTab === 'usage'  ? getPhpClientUsage()  :
                            getPhpClientFull())"
                        class="absolute top-2 right-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-[10px] px-2 py-0.5 rounded transition-colors">
                    <span x-text="copiedBlock === 'php-client' ? 'Copied!' : 'Copy'"></span>
                </button>
            </div>

            <!-- Requirements callouts -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5 text-[10px] text-muted-foreground">
                <div class="flex items-center gap-1.5">
                    <svg class="h-3 w-3 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    PHP 8.1+ · ext-curl · ext-json
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="h-3 w-3 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    SSL verify ON · timeout 5s / connect 3s
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="h-3 w-3 flex-shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    X-Request-ID auto-generated per call
                </div>
            </div>

            <!-- Flow summary -->
            <div class="rounded border border-slate-200 bg-slate-50 p-2.5 text-[10px]">
                <p class="font-semibold mb-1.5">Flow integrasi:</p>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="bg-white border rounded px-1.5 py-0.5 font-mono">Visitor request</span>
                    <span class="text-muted-foreground">→</span>
                    <span class="bg-white border rounded px-1.5 py-0.5 font-mono">entry.php</span>
                    <span class="text-muted-foreground">→</span>
                    <span class="bg-white border rounded px-1.5 py-0.5 font-mono">SrpApiClient::decision()</span>
                    <span class="text-muted-foreground">→</span>
                    <span class="bg-emerald-50 border border-emerald-200 rounded px-1.5 py-0.5 font-mono text-emerald-700">A: 302 redirect</span>
                    <span class="text-muted-foreground">/</span>
                    <span class="bg-slate-100 border rounded px-1.5 py-0.5 font-mono text-slate-600">B: render page</span>
                </div>
            </div>

            </div><!-- end collapsible -->
        </div>

        <!-- Error responses -->
        <div class="card p-3 space-y-2">
            <h3 class="text-xs font-semibold">Error Responses</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px]">
                <div class="bg-muted/60 rounded p-2">
                    <p class="font-mono font-semibold text-red-600">401</p>
                    <p class="text-muted-foreground mt-0.5">Unauthorized</p>
                    <p class="text-[9px] text-muted-foreground">API key salah / tidak ada</p>
                </div>
                <div class="bg-muted/60 rounded p-2">
                    <p class="font-mono font-semibold text-amber-600">429</p>
                    <p class="text-muted-foreground mt-0.5">Too Many Requests</p>
                    <p class="text-[9px] text-muted-foreground">Rate limit terlampaui</p>
                </div>
                <div class="bg-muted/60 rounded p-2">
                    <p class="font-mono font-semibold text-slate-500">400</p>
                    <p class="text-muted-foreground mt-0.5">Bad Request</p>
                    <p class="text-[9px] text-muted-foreground">Body/param tidak valid</p>
                </div>
                <div class="bg-muted/60 rounded p-2">
                    <p class="font-mono font-semibold text-slate-500">500</p>
                    <p class="text-muted-foreground mt-0.5">Internal Error</p>
                    <p class="text-[9px] text-muted-foreground">Server error, cek log</p>
                </div>
            </div>
            <pre class="bg-slate-900 text-slate-200 text-[11px] p-2 rounded font-mono">{ "ok": false, "error": "Unauthorized" }</pre>
        </div>

    </div>
</div>
