<!-- Overview Tab -->
<div x-show="activeTab === 'overview'" x-cloak>
    <div class="space-y-4">
        <!-- Statistics Cards -->
        <div class="grid gap-3 grid-cols-2 md:grid-cols-4">
            <!-- Total Requests (weekly, from DB) -->
            <div class="card p-3 text-center">
                <div class="inline-flex items-center justify-center gap-1.5 mb-1.5">
                    <img src="/assets/icons/fox-head.png" alt="Ghost logo" class="h-3.5 w-3.5" width="14" height="14">
                    <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Total</h3>
                </div>
                <div class="text-xl font-semibold leading-tight"
                     x-text="weekStats.total.toLocaleString()"></div>
                <p class="text-[10px] text-muted-foreground mt-0.5"
                   x-text="weekStats.since ? 'Since ' + weekStats.since : 'This week'"></p>
            </div>

            <!-- Redirected A (weekly, from DB) -->
            <div class="card p-3 text-center">
                <div class="inline-flex items-center justify-center gap-1.5 mb-1.5">
                    <svg class="h-3.5 w-3.5 text-emerald-500/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
                    </svg>
                    <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Redirect A</h3>
                </div>
                <div class="text-xl font-semibold leading-tight text-emerald-600"
                     x-text="weekStats.a_count.toLocaleString()"></div>
                <p class="text-[10px] text-muted-foreground mt-0.5">Decision A · this week</p>
            </div>

            <!-- Fallback B (weekly, from DB) -->
            <div class="card p-3 text-center">
                <div class="inline-flex items-center justify-center gap-1.5 mb-1.5">
                    <svg class="h-3.5 w-3.5 text-amber-500/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
                    </svg>
                    <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Fallback B</h3>
                </div>
                <div class="text-xl font-semibold leading-tight text-amber-600"
                     x-text="weekStats.b_count.toLocaleString()"></div>
                <p class="text-[10px] text-muted-foreground mt-0.5">Decision B · this week</p>
            </div>

            <!-- Conversions / Leads (weekly) -->
            <div class="card p-3 text-center">
                <div class="inline-flex items-center justify-center gap-1.5 mb-1.5">
                    <svg class="h-3.5 w-3.5 text-violet-500/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Leads</h3>
                </div>
                <div class="text-xl font-semibold leading-tight text-violet-600"
                     x-text="weekConversions.total.toLocaleString()"></div>
                <p class="text-[10px] text-muted-foreground mt-0.5"
                   x-text="weekConversions.total_payout > 0 ? '$' + weekConversions.total_payout.toFixed(2) + ' payout' : 'This week'"></p>
            </div>
        </div>

        <!-- Incoming Conversions / Leads -->
        <div class="card">
            <div class="flex items-center justify-between p-4 pb-2">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-sm font-semibold">Incoming Conversions</h3>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="text-muted-foreground"
                          x-text="conversions.length + ' record' + (conversions.length !== 1 ? 's' : '')"></span>
                    <span class="font-semibold text-violet-600"
                          x-text="'$' + conversions.reduce((s, c) => s + (parseFloat(c.payout) || 0), 0).toFixed(2) + ' total'"></span>
                </div>
            </div>

            <template x-if="conversions.length === 0">
                <div class="px-4 pb-4 pt-2">
                    <p class="text-xs text-muted-foreground text-center py-6">No conversions received yet. Configure your postback URL in Routing Config.</p>
                </div>
            </template>

            <template x-if="conversions.length > 0">
                <div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-t text-left text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Time</th>
                                    <th class="px-4 py-2 font-medium">Click ID</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                    <th class="px-4 py-2 font-medium text-right">Payout</th>
                                    <th class="px-4 py-2 font-medium hidden md:table-cell">Country</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="c in convPagedList" :key="c.id">
                                    <tr class="border-t hover:bg-muted/50 transition-colors">
                                        <td class="px-4 py-2 tabular-nums text-muted-foreground"
                                            x-text="new Date(c.ts * 1000).toLocaleString(undefined, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'})"></td>
                                        <td class="px-4 py-2 font-mono truncate max-w-[140px]" x-text="c.click_id"></td>
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                                  :class="c.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
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
                            <tfoot>
                                <tr class="border-t bg-muted/30 font-medium">
                                    <td class="px-4 py-2" colspan="3">
                                        <span x-text="conversions.length + ' conversion' + (conversions.length !== 1 ? 's' : '')"></span>
                                    </td>
                                    <td class="px-4 py-2 text-right text-violet-600 tabular-nums"
                                        x-text="'$' + conversions.reduce((s, c) => s + (parseFloat(c.payout) || 0), 0).toFixed(2)"></td>
                                    <td class="hidden md:table-cell"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Pagination controls -->
                    <div x-show="convTotalPages > 1"
                         class="flex items-center justify-between px-4 py-2.5 border-t text-xs text-muted-foreground">
                        <span x-text="'Page ' + convPage + ' / ' + convTotalPages + ' · ' + conversions.length + ' total'"></span>
                        <div class="flex items-center gap-1">
                            <button type="button"
                                    @click="convPage = Math.max(1, convPage - 1)"
                                    :disabled="convPage === 1"
                                    class="px-2 py-1 rounded border text-[11px] hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                &lsaquo; Prev
                            </button>
                            <template x-for="p in Array.from({length: convTotalPages}, (_, i) => i + 1)" :key="p">
                                <button type="button"
                                        @click="convPage = p"
                                        class="px-2 py-1 rounded border text-[11px] transition-colors min-w-[28px]"
                                        :class="convPage === p ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted'"
                                        x-text="p"></button>
                            </template>
                            <button type="button"
                                    @click="convPage = Math.min(convTotalPages, convPage + 1)"
                                    :disabled="convPage === convTotalPages"
                                    class="px-2 py-1 rounded border text-[11px] hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                Next &rsaquo;
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Quick Actions -->
        <div class="card p-4">
            <h3 class="text-sm font-semibold mb-3">Quick Actions</h3>
            <div class="grid gap-2 grid-cols-1 md:grid-cols-3">
                <button
                    type="button"
                    @click="activeTab = 'routing'"
                    class="btn text-left justify-start">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Configure Routing
                </button>

                <button
                    type="button"
                    @click="activeTab = 'env-config'"
                    class="btn text-left justify-start">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                    </svg>
                    Environment Settings
                </button>

                <button
                    type="button"
                    @click="activeTab = 'logs'"
                    class="btn text-left justify-start">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    View Traffic Logs
                </button>
            </div>
        </div>

        <!-- System Status -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold">System Status</h3>
                <button
                    type="button"
                    x-show="userRole === 'admin'"
                    @click="cfg.system_on = !cfg.system_on; save()"
                    class="btn btn-sm"
                    :class="cfg.system_on ? 'btn-primary' : 'btn-outline'"
                    :disabled="isSavingCfg"
                    data-sniper="1">
                    <svg x-show="isSavingCfg" class="h-3 w-3 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                    </svg>
                    <span x-text="isSavingCfg ? 'Saving...' : (cfg.system_on ? 'Turn Off' : 'Turn On')"></span>
                </button>
            </div>

            <div class="space-y-2 text-xs">
                <div class="flex justify-between py-2 border-b">
                    <span class="text-muted-foreground">Status</span>
                    <span class="font-medium"
                          :class="cfg.system_on ? (muteStatus.isMuted ? 'text-amber-600' : 'text-emerald-600') : 'text-muted-foreground'"
                          x-text="cfg.system_on ? (muteStatus.isMuted ? 'Muted' : 'Active') : 'Offline'"></span>
                </div>
                <div class="flex justify-between py-2 border-b" x-show="cfg.system_on">
                    <span class="text-muted-foreground">Redirect URL</span>
                    <span class="font-medium truncate ml-2 max-w-xs" x-text="cfg.redirect_url || 'Not set'"></span>
                </div>
                <div class="flex justify-between py-2 border-b">
                    <span class="text-muted-foreground">This Week</span>
                    <span class="font-medium"
                          x-text="weekStats.total.toLocaleString() + ' hits'"></span>
                </div>
                <div class="flex justify-between py-2 border-b">
                    <span class="text-muted-foreground">Leads This Week</span>
                    <span class="font-medium text-violet-600"
                          x-text="weekConversions.total.toLocaleString() + (weekConversions.total_payout > 0 ? ' · $' + weekConversions.total_payout.toFixed(2) : '')"></span>
                </div>
                <div class="flex justify-between py-2" x-show="cfg.system_on">
                    <span class="text-muted-foreground">Cycle Status</span>
                    <span class="font-medium" x-text="muteStatus.timeRemaining"></span>
                </div>
            </div>
        </div>
    </div>
</div>
