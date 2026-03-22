<!-- Tabs Navigation -->
<?php require __DIR__ . '/tabs-navigation.php'; ?>

<div class="max-w-4xl mx-auto py-3 lg:py-4 px-3 sm:px-5">
    <!-- Overview Tab -->
    <?php require __DIR__ . '/overview-tab.php'; ?>

    <!-- Routing Config Tab -->
    <?php require __DIR__ . '/routing-tab.php'; ?>

    <!-- Environment Config Tab -->
    <?php require __DIR__ . '/env-config-tab.php'; ?>

    <!-- Logs Tab -->
    <?php require __DIR__ . '/logs-tab.php'; ?>

    <!-- API Docs Tab -->
    <?php require __DIR__ . '/api-docs-tab.php'; ?>

    <!-- Analytics Tab -->
    <?php require __DIR__ . '/analytics-tab.php'; ?>
</div>

<!-- OLD CONTENT BELOW (can be removed after testing) -->
<div class="max-w-4xl mx-auto py-3 lg:py-4 space-y-4 lg:space-y-5 px-5" style="display:none;">
    <!-- Row 1: 4 small boxes -->
    <div class="grid gap-3 grid-cols-2 md:grid-cols-4">
        <!-- Total Requests -->
        <div class="card p-3 text-center">
            <div class="inline-flex items-center justify-center gap-1.5 mb-1.5">
                <img src="/assets/icons/fox-head.png" alt="Ghost logo" class="h-3.5 w-3.5" width="14" height="14">
                <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Total</h3>
            </div>
            <div class="text-xl font-semibold leading-tight" x-text="logs.length"></div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Last 50 records</p>
        </div>

        <!-- Redirected A -->
        <div class="card p-3 text-center">
            <div class="inline-flex items-center justify-center gap-1.5 mb-1.5">
                <svg class="h-3.5 w-3.5 text-emerald-500/80" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
                </svg>
                <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Redirect A</h3>
            </div>
            <div class="text-xl font-semibold leading-tight"
                 x-text="logs.filter(l => l.decision === 'A').length"></div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Decision A</p>
        </div>

        <!-- Fallback B -->
        <div class="card p-3 text-center">
            <div class="inline-flex items-center justify-center gap-1.5 mb-1.5">
                <svg class="h-3.5 w-3.5 text-amber-500/80" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
                </svg>
                <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Fallback B</h3>
            </div>
            <div class="text-xl font-semibold leading-tight"
                 x-text="logs.filter(l => l.decision === 'B').length"></div>
            <p class="text-[10px] text-muted-foreground mt-0.5">Desktop / Bot / VPN</p>
        </div>

        <!-- System summary -->
        <div class="card p-3 text-center">
            <div class="inline-flex items-center justify-center gap-1.5 mb-1.5">
                <span class="inline-flex h-2 w-2 rounded-full"
                      :class="cfg.system_on ? (muteStatus.isMuted ? 'bg-amber-500' : 'bg-emerald-500') : 'bg-black'"></span>
                <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">System</h3>
            </div>
            <div class="text-xl font-semibold leading-tight"
                 x-text="cfg.system_on ? (muteStatus.isMuted ? 'MUTED' : 'ACTIVE') : 'OFF'"></div>
            <p class="text-[10px] text-muted-foreground mt-0.5"
               x-text="cfg.system_on ? muteStatus.timeRemaining : 'System disabled'"></p>
        </div>
    </div>

    <!-- Row 2: Configuration -->
    <div class="card">
        <div class="p-4 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="font-semibold tracking-tight text-sm">Configuration</h3>
                        <span x-show="cfg.system_on && !muteStatus.isMuted"
                              class="badge badge-default text-[10px] px-1.5 py-0.5 inline-flex items-center gap-1">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                            </svg>
                            Active
                        </span>
                        <span x-show="cfg.system_on && muteStatus.isMuted"
                              class="badge badge-secondary text-[10px] px-1.5 py-0.5 inline-flex items-center gap-1">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/>
                            </svg>
                            Muted
                        </span>
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                        <span x-show="!cfg.system_on">System toggle, redirect target, and country filters</span>
                        <span x-show="cfg.system_on" x-text="'Auto cycle: ' + muteStatus.timeRemaining"></span>
                    </p>
                </div>
                <button type="button"
                        x-show="userRole === 'admin'"
                        @click="cfg.system_on = !cfg.system_on; save()"
                        class="btn btn-sm"
                        :class="cfg.system_on ? 'btn-default' : 'btn-outline'"
                        :disabled="isSavingCfg">
                    <svg x-show="!isSavingCfg"
                         class="h-3.5 w-3.5 text-muted-foreground"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="m16 10 3-3m0 0-3-3m3 3H5v3m3 4-3 3m0 0 3 3m-3-3h14v-3"/>
                    </svg>
                    <svg x-show="isSavingCfg"
                         class="h-3.5 w-3.5 mr-1.5 animate-spin"
                         fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75"
                              d="M4 12a8 8 0 0 1 8-8"
                              stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                    </svg>
                    <span class="text-xs"
                          x-text="isSavingCfg ? 'Saving...' : (cfg.system_on ? 'Turn Off' : 'Turn On')"></span>
                </button>
            </div>

            <div class="space-y-3">
                <!-- Redirect URL -->
                <div class="space-y-1.5">
                    <label class="text-xs font-medium leading-none">
                        Redirect URL
                    </label>
                    <input type="url" class="input" placeholder="https://example.com"
                           x-model="cfg.redirect_url"
                           @input.debounce.400ms="save()">
                    <p class="text-[11px] text-muted-foreground">Must use HTTPS protocol</p>
                </div>

                <!-- Country Filter Mode -->
                <div class="relative">
                    <select class="input pr-7 appearance-none"
                            x-model="cfg.country_filter_mode"
                            @change="save()">
                        <option value="all">All Countries</option>
                        <option value="whitelist">Whitelist Only</option>
                        <option value="blacklist">Blacklist Only</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-[10px] text-muted-foreground">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </span>
                </div>

                <!-- Country List -->
                <div x-show="cfg.country_filter_mode !== 'all'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     class="space-y-1.5">
                    <label class="text-xs font-medium leading-none">
                        <span
                            x-text="cfg.country_filter_mode === 'whitelist' ? 'Allowed Countries' : 'Blocked Countries'"></span>
                    </label>
                    <textarea class="textarea font-mono text-[11px] scroll-logs" rows="3"
                              placeholder="US, GB, ID, AU, CA"
                              x-model="cfg.country_filter_list"
                              @input.debounce.400ms="save()"></textarea>
                    <p class="text-[11px] text-muted-foreground">ISO Alpha-2 codes, comma separated</p>
                </div>
            </div>

            <div class="pt-2 border-t">
                <div class="text-[11px] text-muted-foreground">
                    Last updated:
                    <span class="font-medium" x-text="fmt(cfg.updated_at)"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Decision Tester -->
    <div class="card">
        <div class="p-4 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold tracking-tight text-sm">Decision Tester</h3>
                    <p class="text-[11px] text-muted-foreground">
                        Simulate redirect decision based on current configuration
                    </p>
                </div>
                <button type="button"
                        class="btn btn-sm btn-ghost"
                        @click="testerOpen = !testerOpen">
                    <span class="text-[11px]" x-text="testerOpen ? 'Hide' : 'Show'"></span>
                </button>
            </div>

            <div x-show="testerOpen"
                 x-transition
                 class="space-y-3">
                <div class="grid gap-3 md:grid-cols-3">
                    <!-- Country code -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium leading-none">
                            Country (ISO Alpha-2)
                        </label>
                        <input type="text"
                               class="input font-mono up-text text-[11px]"
                               placeholder="e.g. ID"
                               maxlength="2"
                               x-model="testInput.country">
                        <p class="text-[11px] text-muted-foreground">
                            Will be uppercased automatically
                        </p>
                    </div>

                    <!-- Device -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium leading-none">
                            Device
                        </label>
                        <div class="relative">
                            <select class="input pr-7 appearance-none" x-model="testInput.device">
                                <option value="mobile">Mobile / WAP</option>
                                <option value="desktop">Desktop / WEB</option>
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-[10px] text-muted-foreground">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Simplified device bucket
                        </p>
                    </div>

                    <!-- VPN -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-medium leading-none">
                            VPN / Proxy
                        </label>
                        <div class="relative">
                            <select class="input pr-7 appearance-none" x-model="testInput.vpn">
                                <option value="no">No VPN detected</option>
                                <option value="yes">VPN / Proxy</option>
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-[10px] text-muted-foreground">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Assumed detection result
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <button type="button"
                            class="btn btn-default btn-sm"
                            @click="runTest()">
                        Run Test
                    </button>

                    <template x-if="testResult">
                        <div class="text-right text-[11px] space-y-0.5">
                            <div>
                                Decision:
                                <span class="badge"
                                      :class="testResult.decision === 'A' ? 'badge-default' : 'badge-secondary'"
                                      x-text="testResult.decision === 'A' ? 'Redirect (A)' : 'Fallback (B)'">
                                </span>
                            </div>
                            <div class="text-muted-foreground max-w-xs md:max-w-sm"
                                 x-text="testResult.reason"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 4: Traffic Logs -->
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border-b">
            <div class="space-y-0.5">
                <h3 class="font-semibold tracking-tight text-sm">Traffic Logs</h3>
                <p class="text-[12px] text-muted-foreground">Real-time traffic monitoring</p>
            </div>
            <button @click="clearLogs"
                    class="btn btn-default btn-sm"
                    :disabled="isClearingLogs">
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

        <div class="relative overflow-x-auto overflow-y-auto max-h-[420px] scroll-logs">
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
                            <code
                                class="relative rounded bg-muted px-1 py-[0.1rem] font-mono text-[11px] font-semibold"
                                x-text="r.click_id || '-'"></code>
                        </td>
                        <td class="p-2 align-middle hidden lg:table-cell">
                            <code
                                class="relative rounded bg-muted px-1 py-[0.1rem] font-mono text-[11px] text-muted-foreground"
                                x-text="r.ip"></code>
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
                            <span
                                class="text-[11px] text-muted-foreground max-w-md truncate block up-text"
                                x-text="r.ua"></span>
                        </td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
