<!-- Toast (top-right) untuk info / error -->
<div
    x-show="flash && flashType !== 'confirm'"
    x-transition:enter="transform ease-out duration-200"
    x-transition:enter-start="translate-y-2 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transform ease-in duration-150"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-2 opacity-0"
    class="fixed top-4 right-4 z-50 max-w-xs"
    aria-live="polite">
    <div
        class="rounded-[0.3rem] border px-3 py-2 text-[11px] flex items-start gap-2 shadow-sm bg-white/95"
        :class="flashType === 'error'
            ? 'border-destructive/70'
            : 'border-secondary/70'">

        <!-- Icon bubble -->
        <div class="mt-[1px]">
            <div class="flex h-4 w-4 items-center justify-center rounded-full"
                 :class="flashType === 'error'
                    ? 'bg-destructive/10 text-destructive'
                    : 'bg-emerald-50 text-emerald-600'">
                <svg x-show="flashType === 'error'"
                     class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18z"></path>
                </svg>
                <svg x-show="flashType === 'info'"
                     class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>

        <!-- Text -->
        <div class="flex-1">
            <p class="font-medium leading-tight mb-0.5"
               x-text="flashType === 'error' ? 'Error' : 'Info'"></p>
            <p class="text-[10px] leading-snug text-muted-foreground"
               x-text="flash"></p>
        </div>
    </div>
</div>

<!-- Confirm modal (center screen) -->
<div
    x-show="flash && flashType === 'confirm'"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <!-- backdrop gelap -->
    <div class="absolute inset-0 bg-black/40"></div>

    <div class="relative card max-w-sm w-full p-4 shadow-lg">
        <div class="flex items-start gap-2">
            <div class="mt-[2px]">
                <svg class="h-4 w-4 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18z"></path>
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-xs font-semibold mb-0.5">Confirm action</p>
                <p class="text-[11px] text-muted-foreground" x-text="flash"></p>

                <div class="mt-2.5 flex justify-end gap-1.5">
                    <button type="button"
                            class="btn btn-ghost btn-sm"
                            @click="cancelFlashAction()">
                        Cancel
                    </button>
                    <button type="button"
                            class="btn btn-destructive btn-sm"
                            :disabled="isClearingLogs"
                            @click="confirmFlashAction()"
                            data-sniper="1">
                        <svg x-show="isClearingLogs"
                             class="h-3 w-3 mr-1 animate-spin"
                             fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75"
                                  d="M4 12a8 8 0 0 1 8-8"
                                  stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                        </svg>
                        <span x-text="isClearingLogs ? 'Clearing...' : 'Yes, clear'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
