<!-- Tabs Navigation -->
<div class="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 sticky top-12 z-40">
    <div class="max-w-4xl mx-auto px-3 sm:px-5">
        <nav class="flex overflow-x-auto no-scrollbar" aria-label="Tabs">
            <!-- Overview -->
            <button
                @click="activeTab = 'overview'"
                class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                :class="activeTab === 'overview'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                type="button">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Overview</span>
            </button>

            <!-- Routing Config -->
            <button
                @click="activeTab = 'routing'"
                class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                :class="activeTab === 'routing'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                type="button">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="hidden sm:inline">Routing</span>
                <span class="sm:hidden">Route</span>
            </button>

            <!-- Environment -->
            <button
                @click="activeTab = 'env-config'"
                class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                :class="activeTab === 'env-config'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                type="button">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span class="hidden sm:inline">Environment</span>
                <span class="sm:hidden">Env</span>
            </button>

            <!-- Traffic Logs -->
            <button
                @click="activeTab = 'logs'"
                class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                :class="activeTab === 'logs'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                type="button">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="hidden sm:inline">Traffic Logs</span>
                <span class="sm:hidden">Logs</span>
                <span class="ml-0.5 rounded-full bg-muted px-1.5 py-0.5 text-[9px] font-medium leading-none"
                      x-text="logs.length"></span>
            </button>

            <!-- API Docs -->
            <button
                @click="activeTab = 'api-docs'"
                class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                :class="activeTab === 'api-docs'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                type="button">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
                <span class="hidden sm:inline">API Docs</span>
                <span class="sm:hidden">API</span>
            </button>

            <!-- Analytics -->
            <button
                @click="activeTab = 'analytics'; scheduleAnalyticsLoad()"
                class="flex-shrink-0 flex items-center gap-1.5 py-3 px-2.5 sm:px-3 border-b-2 font-medium text-[11px] sm:text-xs whitespace-nowrap transition-colors"
                :class="activeTab === 'analytics'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'"
                type="button">
                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 8v8m-4-5v5m-4-2v2M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Analytics</span>
            </button>
        </nav>
    </div>
</div>
