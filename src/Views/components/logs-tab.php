<!-- Traffic Logs Tab -->
<div x-show="activeTab === 'logs'" x-cloak>
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border-b">
            <div class="space-y-0.5">
                <h3 class="font-semibold tracking-tight text-sm">Traffic Logs</h3>
                <p class="text-[12px] text-muted-foreground">Real-time traffic monitoring</p>
            </div>
            <button @click="clearLogs"
                    class="btn btn-default btn-sm"
                    :disabled="isClearingLogs"
                    data-sniper="1">
                <svg x-show="!isClearingLogs"
                     class="h-3.5 w-3.5 mr-1.5"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="m15 9-6 6m0-6 6 6m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <svg x-show="isClearingLogs"
                     class="h-3.5 w-3.5 mr-1.5 animate-spin"
                     fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75"
                          d="M4 12a8 8 0 0 1 8-8"
                          stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                </svg>
                <span x-text="isClearingLogs ? 'Clearing...' : 'Clear All'"></span>
            </button>
        </div>

        <div class="relative overflow-x-auto overflow-y-auto max-h-[calc(100vh-220px)] scroll-logs">
            <table class="w-full text-[12px]">
                <thead class="border-b bg-white sticky top-0 z-10">
                <tr>
                    <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground">
                        Click ID
                    </th>
                    <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground hidden lg:table-cell">
                        IP Address
                    </th>
                    <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground">
                        Country
                    </th>
                    <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground">
                        Decision
                    </th>
                    <th class="h-8 px-3 text-left align-middle font-medium text-muted-foreground hidden md:table-cell">
                        User Agent
                    </th>
                </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                <template x-if="logs.length === 0">
                    <tr class="border-b">
                        <td colspan="5" class="p-3 text-center text-[11px] text-muted-foreground">
                            No traffic logs yet.
                        </td>
                    </tr>
                </template>
                <template x-for="r in logs" :key="r.id">
                    <tr class="border-b transition-colors hover:bg-muted/50"
                        :class="r.decision === 'A'
                            ? 'bg-emerald-50/40'
                            : (r.decision === 'B' ? 'bg-slate-50' : '')">
                        <td class="p-2 align-middle">
                            <template x-if="(currentTime - r.ts) < 60">
                                <code
                                    class="relative rounded bg-muted px-1 py-[0.1rem] font-mono text-[11px] font-semibold"
                                    x-text="r.click_id || '-'"></code>
                            </template>
                            <template x-if="(currentTime - r.ts) >= 60">
                                <code class="relative rounded bg-muted px-1 py-[0.1rem] font-mono text-[11px] font-semibold text-muted-foreground/40 select-none"
                                      title="Hidden after 1 minute">••••••••</code>
                            </template>
                        </td>
                        <td class="p-2 align-middle hidden lg:table-cell">
                            <template x-if="(currentTime - r.ts) < 60">
                                <code
                                    class="relative rounded bg-muted px-1 py-[0.1rem] font-mono text-[11px] text-muted-foreground"
                                    x-text="r.ip"></code>
                            </template>
                            <template x-if="(currentTime - r.ts) >= 60">
                                <code class="relative rounded bg-muted px-1 py-[0.1rem] font-mono text-[11px] text-muted-foreground/40 select-none"
                                      title="Hidden after 1 minute">•••.•••.•••.•</code>
                            </template>
                        </td>
                        <td class="p-2 align-middle">
                            <div class="flex items-center gap-1">
                                <svg class="h-3 w-3 text-muted-foreground flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253M3.284 14.253A8.959 8.959 0 013 12c0-1.016.17-1.994.457-2.918"/>
                                </svg>
                                <template x-if="(currentTime - r.ts) < 60">
                                    <span class="badge badge-outline text-[11px]" x-text="r.country_code || 'XX'"></span>
                                </template>
                                <template x-if="(currentTime - r.ts) >= 60">
                                    <span class="badge badge-outline text-[11px] text-muted-foreground/40 select-none">••</span>
                                </template>
                            </div>
                        </td>
                        <td class="p-2 align-middle">
                            <span class="badge text-[11px]"
                                  :class="r.decision === 'A' ? 'badge-default' : 'badge-secondary'"
                                  x-text="r.decision === 'A' ? 'Redirect' : 'Fallback'"></span>
                        </td>
                        <td class="p-2 align-middle hidden md:table-cell">
                            <div class="flex items-center gap-1.5" :title="r.ua">
                                <!-- Mobile icon -->
                                <svg x-show="/mobile|android|iphone|ipad|ipod|tablet/i.test(r.ua || '')"
                                     class="h-3.5 w-3.5 text-muted-foreground flex-shrink-0"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3m-3 3h3m-6 3h.008v.008H7.5v-.008zm0-3h.008v.008H7.5v-.008zm0-3h.008v.008H7.5v-.008z"/>
                                </svg>
                                <!-- Desktop icon -->
                                <svg x-show="!/mobile|android|iphone|ipad|ipod|tablet/i.test(r.ua || '')"
                                     class="h-3.5 w-3.5 text-muted-foreground flex-shrink-0"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-[11px] text-muted-foreground max-w-[200px] truncate up-text"
                                      x-text="r.ua"></span>
                            </div>
                        </td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
