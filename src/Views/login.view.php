<?php

declare(strict_types=1);

$pageTitle = 'Hantuin-v2 Login';
require __DIR__ . '/components/header.php';
?>

<header class="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="flex h-12 max-w-4xl mx-auto items-center px-3 sm:px-5">
        <div class="flex items-center space-x-2">
            <img src="/assets/icons/logo.svg" alt="Ghost logo" class="h-8 w-8" width="20" height="20">
            <div class="flex flex-col leading-tight">
                <span class="text-sm font-semibold tracking-tight">Hantuin-v2 Decision Logic</span>
                <span class="text-[11px] text-muted-foreground">No "smart" buzzword without actual routing logic.</span>
            </div>
        </div>
    </div>
</header>

<?php if (!empty($errorMessage)) : ?>
    <div class="fixed top-4 right-4 z-50 max-w-xs">
        <div id="login-toast"
             class="card shadow-lg px-3 py-2 text-[11px] flex items-start gap-2 border-destructive/60 bg-destructive/10 text-destructive transition-opacity transition-transform duration-200">
            <div class="mt-[2px]">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M12 3a9 9 0 110 18 9 9 0 010-18z"></path>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-medium">Login failed</p>
                <p class="mt-0.5">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var t = document.getElementById('login-toast');
            if (!t) {
                return;
            }
            setTimeout(function () {
                t.classList.add('opacity-0', 'translate-y-2');
            }, 4000);
        });
    </script>
<?php endif; ?>

<main class="flex-1 w-full">
    <div class="max-w-4xl mx-auto px-5 pt-10 md:pt-16 pb-8">
        <div class="w-full max-w-sm mx-auto">
            <div class="mb-6 flex flex-col items-center text-center space-y-2">
                <div class="inline-flex h-24 w-24 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <img src="/assets/icons/fox-head.png" alt="Ghost logo" class="h-16 w-16" width="64" height="64">
                </div>
                <div>
                    <h1 class="text-base font-semibold tracking-tight">Sign in</h1>
                    <p class="text-[11px] text-muted-foreground mt-1">
                        Access Hantuin-v2 Decision Logic dashboard.
                    </p>
                </div>
            </div>

            <div class="card p-4 space-y-4">
                <form method="post" autocomplete="off" class="space-y-3">
    <input type="hidden" name="csrf_token"
           value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">

    <!-- USERNAME -->
    <div class="space-y-1.5">
        <label for="username" class="text-xs font-medium leading-none">
            Username or Email
        </label>
        <input
            type="text"
            class="input"
            id="username"
            name="username"
            required
            autocomplete="username"
            maxlength="191"
            placeholder="username">
    </div>

    <!-- PASSWORD -->
    <div class="space-y-1.5">
        <label for="password" class="text-xs font-medium leading-none">
            Password
        </label>
        <input
            type="password"
            class="input"
            id="password"
            name="password"
            required
            autocomplete="current-password"
            minlength="8"
            placeholder="••••••••">
    </div>

    <!-- REMEMBER -->
    <div class="flex items-center justify-between">
        <label class="inline-flex items-center gap-1.5 text-[11px] text-muted-foreground cursor-pointer">
            <input
                type="checkbox"
                name="remember"
                value="1"
                class="h-3 w-3 rounded border border-[color:var(--border-color)]">
            <span>Remember this device</span>
        </label>
    </div>

    <!-- SUBMIT -->
    <button type="submit" class="btn btn-default btn-default-size w-full mt-1">
        <svg class="h-3.5 w-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M5 12h14M12 5l7 7-7 7"></path>
        </svg>
        <span class="text-xs">Sign In</span>
    </button>
</form>

            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/components/footer.php'; ?>
