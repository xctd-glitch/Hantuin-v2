<!-- Analytics Tab -->
<div x-show="activeTab === 'analytics'" x-cloak>
    <div class="space-y-4">

        <!-- Header + Day Range Selector -->
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold tracking-tight text-sm">Daily Analytics</h3>
                <p class="text-[11px] text-muted-foreground">Traffic breakdown per day</p>
            </div>
            <div class="flex items-center gap-1">
                <template x-for="d in [7, 14, 30]" :key="d">
                    <button type="button"
                            @click="selectAnalyticsDays(d)"
                            class="px-2.5 py-1 text-[11px] font-medium rounded-md transition-colors"
                            :class="analyticsDays === d
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground hover:bg-muted'">
                        <span x-text="d + 'd'"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="analyticsLoading" class="flex items-center justify-center py-12 text-muted-foreground text-xs gap-2">
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            Loading analytics…
        </div>

        <!-- Empty State -->
        <div x-show="!analyticsLoading && analytics.length === 0"
             class="card p-8 text-center text-muted-foreground text-xs">
            <svg class="h-8 w-8 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            No traffic data for the selected period.
        </div>

        <!-- Summary Cards -->
        <template x-if="!analyticsLoading && analytics.length > 0">
            <div>
                <div class="grid gap-3 grid-cols-2 md:grid-cols-4 mb-4">
                    <!-- Total -->
                    <div class="card p-3 text-center">
                        <div class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide mb-1">
                            Total Hits
                        </div>
                        <div class="text-xl font-semibold leading-tight"
                             x-text="analytics.reduce((s,r) => s + r.total, 0).toLocaleString()"></div>
                        <p class="text-[10px] text-muted-foreground mt-0.5"
                           x-text="'Last ' + analyticsDays + ' days'"></p>
                    </div>
                    <!-- Decision A -->
                    <div class="card p-3 text-center">
                        <div class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide mb-1">
                            Redirect A
                        </div>
                        <div class="text-xl font-semibold leading-tight text-emerald-600"
                             x-text="analytics.reduce((s,r) => s + r.a_count, 0).toLocaleString()"></div>
                        <p class="text-[10px] text-muted-foreground mt-0.5">Decision A</p>
                    </div>
                    <!-- Decision B -->
                    <div class="card p-3 text-center">
                        <div class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide mb-1">
                            Fallback B
                        </div>
                        <div class="text-xl font-semibold leading-tight text-amber-600"
                             x-text="analytics.reduce((s,r) => s + r.b_count, 0).toLocaleString()"></div>
                        <p class="text-[10px] text-muted-foreground mt-0.5">Decision B</p>
                    </div>
                    <!-- Leads -->
                    <div class="card p-3 text-center">
                        <div class="text-[10px] font-medium text-muted-foreground uppercase tracking-wide mb-1">
                            Leads
                        </div>
                        <div class="text-xl font-semibold leading-tight text-violet-600"
                             x-text="analytics.reduce((s,r) => s + (r.conv_count || 0), 0).toLocaleString()"></div>
                        <p class="text-[10px] text-muted-foreground mt-0.5"
                           x-text="'$' + analytics.reduce((s,r) => s + (r.conv_payout || 0), 0).toFixed(2) + ' payout'"></p>
                    </div>
                </div>

                <!-- Chart + Table -->
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
                        <template x-for="row in analytics" :key="row.day">
                            <div class="px-4 py-2.5 hover:bg-muted/30 transition-colors">
                                <div class="flex items-center gap-3">
                                    <!-- Date -->
                                    <span class="text-[11px] font-mono text-muted-foreground w-20 flex-shrink-0"
                                          x-text="row.day"></span>

                                    <!-- Stacked bar -->
                                    <div class="flex-1 flex items-center gap-1 min-w-0">
                                        <div class="flex-1 h-4 bg-muted rounded-sm overflow-hidden flex">
                                            <div class="h-full bg-emerald-500 transition-all duration-300"
                                                 :style="'width:' + analyticsBarPct(row, 'a') + '%'"
                                                 :title="'A: ' + row.a_count"></div>
                                            <div class="h-full bg-amber-400 transition-all duration-300"
                                                 :style="'width:' + analyticsBarPct(row, 'b') + '%'"
                                                 :title="'B: ' + row.b_count"></div>
                                        </div>
                                    </div>

                                    <!-- Numbers -->
                                    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0 text-[11px] text-right">
                                        <span class="w-10 sm:w-12 font-medium tabular-nums" x-text="row.total.toLocaleString()"></span>
                                        <span class="hidden sm:block w-10 text-emerald-600 font-medium tabular-nums"
                                              x-text="row.a_count > 0 ? row.a_count.toLocaleString() : '—'"></span>
                                        <span class="hidden sm:block w-10 text-amber-600 font-medium tabular-nums"
                                              x-text="row.b_count > 0 ? row.b_count.toLocaleString() : '—'"></span>
                                        <span class="hidden sm:block w-10 text-violet-600 font-medium tabular-nums"
                                              x-text="(row.conv_count || 0) > 0 ? row.conv_count.toLocaleString() : '—'"></span>
                                        <span class="w-10 sm:w-12 text-muted-foreground tabular-nums" x-text="row.total > 0
                                            ? (row.a_count / row.total * 100).toFixed(0) + '%'
                                            : '—'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Table header (footer style) -->
                    <div class="px-4 py-2 bg-muted/40 border-t flex items-center gap-3 text-[10px] text-muted-foreground font-medium uppercase tracking-wide">
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
                </div>
            </div>
        </template>

    </div>
</div>
