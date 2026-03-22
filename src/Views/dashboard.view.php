<?php
$pageTitle = 'Hantuin-v2 Decision Logic';
require __DIR__ . '/components/header.php';
?>
<div x-data="dash" x-cloak>
<header class="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="flex h-12 max-w-4xl mx-auto items-center px-3 sm:px-5">
        <div class="mr-3 hidden md:flex">
            <a href="/" class="mr-4 flex items-center space-x-2">
                <img src="/assets/icons/logo.svg" alt="Ghost logo" class="h-8 w-8" width="20" height="20">
                <div class="flex flex-col leading-tight">
                <span class="font-semibold text-sm tracking-tight">Hantuin-v2 Decision Logic</span>
                 <span class="text-[11px] text-muted-foreground">No "smart" buzzword without actual routing logic.</span>
                 </div>
            </a>
        </div>

        <button @click="mobileMenuOpen = !mobileMenuOpen" class="mr-2 md:hidden btn btn-ghost btn-icon" aria-label="Toggle navigation">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>

        <div class="flex md:hidden items-center space-x-2">
            <img src="/assets/icons/fox-head.png" alt="Ghost logo" class="h-4 w-4" width="16" height="16">
            <span class="font-semibold text-xs tracking-tight">Hantuin-v2</span>
        </div>

        <div class="flex flex-1 items-center justify-end space-x-2">
            <div class="flex items-center space-x-2 rounded-md px-2 sm:px-2.5 py-1 transition-colors duration-200"
                 :class="cfg.system_on ? (muteStatus.isMuted ? 'bg-amber-500 text-white shadow-sm' : 'bg-primary text-primary-foreground shadow-sm') : 'border'">
                <div class="h-1.5 w-1.5 rounded-full transition-all duration-200"
                     :class="cfg.system_on ? (muteStatus.isMuted ? 'bg-white animate-pulse' : 'bg-emerald-500 animate-pulse') : 'bg-gray-400'"></div>
                <span class="text-[11px] font-medium hidden sm:inline"
                      x-text="cfg.system_on ? (muteStatus.isMuted ? 'Muted' : 'Active') : 'Offline'"></span>
            </div>

            <form method="post" action="/logout.php" class="hidden sm:block">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Logout</button>
            </form>

            <form method="post" action="/logout.php" class="sm:hidden">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-ghost btn-icon" aria-label="Logout">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v1" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</header>

<!-- Toast & Confirm Modal -->
<?php require __DIR__ . '/components/toast.php'; ?>

<main class="flex-1 w-full">
    <?php require __DIR__ . '/components/dashboard-content.php'; ?>
</main>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dash', () => ({
        // Role
        userRole: '<?= htmlspecialchars($userRole ?? 'admin', ENT_QUOTES, 'UTF-8') ?>',

        // Navigation
        activeTab: 'overview',
        mobileMenuOpen: false,

        // Configuration
        cfg: {
            system_on: false,
            redirect_url: '',
            country_filter_mode: 'all',
            country_filter_list: '',
            postback_token: '',
            updated_at: 0
        },

        // Analytics
        analytics: [],
        analyticsLoading: false,
        analyticsDays: 30,

        // API Docs
        apiDocsSection: 'decision',
        apiDocsLang: 'curl',
        copiedBlock: '',
        phpClientTab: 'class',
        apiEndpoints: [
            { key: 'status',      method: 'GET',  path: '/api/v1/status',      desc: 'System status snapshot'       },
            { key: 'stats',       method: 'GET',  path: '/api/v1/stats',       desc: 'Weekly traffic + conversions' },
            { key: 'logs',        method: 'GET',  path: '/api/v1/logs',        desc: 'Paginated traffic logs'       },
            { key: 'analytics',   method: 'GET',  path: '/api/v1/analytics',   desc: 'Daily aggregated data'        },
            { key: 'conversions', method: 'GET',  path: '/api/v1/conversions', desc: 'Paginated conversions'        },
            { key: 'settings',    method: 'POST', path: '/api/v1/settings',    desc: 'Partial-update settings'      },
        ],

        // Environment Config — pre-filled from server, overwritten by loadEnvConfig()
        envConfig: {
            SRP_API_KEY:   '<?= htmlspecialchars($initialApiKey ?? '', ENT_QUOTES, 'UTF-8') ?>',
            SRP_REMOTE_DECISION_URL: '',
            SRP_REMOTE_API_KEY:      '',
            SRP_ADMIN_USER:          'admin',
            SRP_ADMIN_PASSWORD_HASH: '',
            APP_URL:   '<?= htmlspecialchars($appUrl ?? '', ENT_QUOTES, 'UTF-8') ?>',
            APP_ENV:   'production',
            APP_DEBUG: 'false',
            SRP_TRUSTED_PROXIES:      '',
            SRP_FORCE_SECURE_COOKIES: 'true',
            SESSION_LIFETIME:     '3600',
            RATE_LIMIT_ATTEMPTS:  '5',
            RATE_LIMIT_WINDOW:    '900',
        },
        isSavingEnv: false,
        isTestingSrp: false,
        showApiKey: false,

        // Logs
        logs: [],
        currentTime: Math.floor(Date.now() / 1000),

        // Weekly stats (from DB, accumulates Mon–Sun)
        weekStats:       { total: 0, a_count: 0, b_count: 0, since: '' },
        weekConversions: { total: 0, total_payout: 0 },
        conversions: [],
        convPage: 1,
        convLimit: 15,
        get convTotalPages() { return Math.max(1, Math.ceil(this.conversions.length / this.convLimit)); },
        get convPagedList()   { const s = (this.convPage - 1) * this.convLimit; return this.conversions.slice(s, s + this.convLimit); },

        // Flash messages
        flash: '',
        flashType: 'info',

        // Decision Tester
        testerOpen: false,
        testInput: {
            country: '',
            device: 'mobile',
            vpn: 'no'
        },
        testResult: null,

        // Loading states
        isSavingCfg: false,
        savingCfgCount: 0,
        isClearingLogs: false,
        flashAction: '',

        // Mute status
        muteStatus: {
            isMuted: false,
            timeRemaining: '',
            cyclePosition: 0
        },

        // Request control
        requestCache: {},
        inflightRequests: {},
        debounceTimers: {},
        heavyQueue: Promise.resolve(),
        refreshPending: false,
        refreshRunning: false,
        lastRefreshAt: 0,
        consecutiveErrors: 0,
        maxConsecutiveErrors: 20,
        refreshPaused: false,
        requestProfile: {
            realtimeThrottleMs: 2500,
            realtimeCacheTtlMs: 1500,
            analyticsCacheTtlMs: 30000,
            analyticsDebounceMs: 250,
            envCacheTtlMs: 10000,
            retryBaseDelayMs: 250,
            retryMaxDelayMs: 2000
        },

        init() {
            this.scheduleRefresh(true);
            this.loadEnvConfig();
            this.updateMuteStatus();
            setInterval(() => this.scheduleRefresh(false), 3000);
            setInterval(() => this.updateMuteStatus(), 1000);
            setInterval(() => { this.currentTime = Math.floor(Date.now() / 1000); }, 1000);

            // BroadcastChannel: sync state across tabs in the same browser instantly.
            // Cross-device sync is handled by the 3-second poll above.
            this._bc = null;
            if (typeof BroadcastChannel !== 'undefined') {
                this._bc = new BroadcastChannel('srp-dashboard');
                this._bc.onmessage = (ev) => {
                    if (ev.data === 'refresh') {
                        this.scheduleRefresh(true);
                    }
                };
            }
        },

        // Notify all other tabs in this browser to refresh immediately.
        broadcastRefresh() {
            if (this._bc) {
                this._bc.postMessage('refresh');
            }
        },

        cloneJsonValue(value) {
            if (value === null || value === undefined) {
                return value;
            }

            return JSON.parse(JSON.stringify(value));
        },

        buildRequestKey(url, options = {}) {
            const method = (options.method || 'GET').toUpperCase();
            const body = typeof options.body === 'string' ? options.body : '';

            return method + ':' + url + ':' + body;
        },

        getCachedResponse(cacheKey) {
            const entry = this.requestCache[cacheKey] || null;
            if (!entry) {
                return null;
            }

            if (typeof entry.expiresAt !== 'number' || entry.expiresAt <= Date.now()) {
                delete this.requestCache[cacheKey];
                return null;
            }

            return this.cloneJsonValue(entry.data);
        },

        setCachedResponse(cacheKey, data, ttlMs) {
            if (ttlMs <= 0) {
                return;
            }

            this.requestCache[cacheKey] = {
                data: this.cloneJsonValue(data),
                expiresAt: Date.now() + ttlMs
            };
        },

        debounce(key, delayMs, callback) {
            if (this.debounceTimers[key]) {
                clearTimeout(this.debounceTimers[key]);
            }

            this.debounceTimers[key] = setTimeout(() => {
                delete this.debounceTimers[key];
                callback();
            }, delayMs);
        },

        enqueueHeavyRequest(executor) {
            const queued = this.heavyQueue
                .catch(function () {
                    return undefined;
                })
                .then(executor);

            this.heavyQueue = queued.catch(function () {
                return undefined;
            });

            return queued;
        },

        delay(ms) {
            return new Promise((resolve) => {
                setTimeout(resolve, ms);
            });
        },

        isRetryableStatus(status) {
            return [408, 425, 429, 500, 502, 503, 504].includes(status);
        },

        getRetryDelay(attempt, retryAfterHeader, baseDelayMs, maxDelayMs) {
            const headerValue = (retryAfterHeader || '').trim();
            if (/^\d+$/.test(headerValue)) {
                const retryAfterMs = parseInt(headerValue, 10) * 1000;
                if (retryAfterMs > 0) {
                    return Math.min(retryAfterMs, maxDelayMs);
                }
            }

            return Math.min(baseDelayMs * Math.pow(2, attempt), maxDelayMs);
        },

        shouldRetryRequest(method, status, policy) {
            if (this.isRetryableStatus(status)) {
                return method === 'GET' || policy.allowRetry === true;
            }

            return status === 0 && (method === 'GET' || policy.allowRetry === true);
        },

        async fetchJson(url, options = {}, policy = {}) {
            // Pastikan Accept header selalu ada agar server proxy tidak reject (415)
            if (!options.headers) {
                options.headers = {};
            }
            if (!options.headers['Accept']) {
                options.headers['Accept'] = 'application/json';
            }

            const mergedPolicy = {
                lane: policy.lane || 'realtime',
                maxRetries: Number.isInteger(policy.maxRetries) ? policy.maxRetries : 0,
                cacheTtlMs: Number.isInteger(policy.cacheTtlMs) ? policy.cacheTtlMs : 0,
                dedupe: policy.dedupe !== false,
                allowRetry: policy.allowRetry === true,
                baseDelayMs: Number.isInteger(policy.baseDelayMs)
                    ? policy.baseDelayMs
                    : this.requestProfile.retryBaseDelayMs,
                maxDelayMs: Number.isInteger(policy.maxDelayMs)
                    ? policy.maxDelayMs
                    : this.requestProfile.retryMaxDelayMs
            };

            const method = (options.method || 'GET').toUpperCase();
            const cacheKey = this.buildRequestKey(url, options);
            const canCache = method === 'GET' && mergedPolicy.cacheTtlMs > 0;

            if (canCache) {
                const cached = this.getCachedResponse(cacheKey);
                if (cached !== null) {
                    return cached;
                }
            }

            if (mergedPolicy.dedupe && this.inflightRequests[cacheKey]) {
                return this.inflightRequests[cacheKey];
            }

            const executeRequest = async () => {
                let attempt = 0;

                while (true) {
                    try {
                        const response = await fetch(url, options);
                        if (!response.ok) {
                            const responseError = new Error('HTTP ' + response.status);
                            responseError.status = response.status;
                            responseError.retryAfter = response.headers.get('Retry-After') || '';
                            try { responseError.responseData = await response.json(); } catch (_) {}
                            throw responseError;
                        }

                        const data = await response.json();
                        if (canCache) {
                            this.setCachedResponse(cacheKey, data, mergedPolicy.cacheTtlMs);
                        }

                        return data;
                    } catch (error) {
                        const status = Number.isInteger(error.status) ? error.status : 0;
                        if (
                            attempt >= mergedPolicy.maxRetries
                            || !this.shouldRetryRequest(method, status, mergedPolicy)
                        ) {
                            throw error;
                        }

                        const retryAfter = typeof error.retryAfter === 'string' ? error.retryAfter : '';
                        const delayMs = this.getRetryDelay(
                            attempt,
                            retryAfter,
                            mergedPolicy.baseDelayMs,
                            mergedPolicy.maxDelayMs
                        );

                        attempt += 1;
                        await this.delay(delayMs);
                    }
                }
            };

            const requestPromise = mergedPolicy.lane === 'heavy'
                ? this.enqueueHeavyRequest(executeRequest)
                : executeRequest();

            if (!mergedPolicy.dedupe) {
                return requestPromise;
            }

            this.inflightRequests[cacheKey] = requestPromise.finally(() => {
                delete this.inflightRequests[cacheKey];
            });

            return this.inflightRequests[cacheKey];
        },

        scheduleRefresh(force = false) {
            const now = Date.now();

            if (this.refreshPaused) {
                return;
            }

            if (!force && this.refreshRunning) {
                this.refreshPending = true;
                return;
            }

            // Backoff: semakin banyak error berturut-turut, semakin lama jeda
            const backoffMs = this.consecutiveErrors > 0
                ? Math.min(3000 * Math.pow(2, this.consecutiveErrors - 1), 60000)
                : this.requestProfile.realtimeThrottleMs;

            if (
                !force
                && now - this.lastRefreshAt < backoffMs
            ) {
                return;
            }

            this.refreshRunning = true;
            this.lastRefreshAt = now;

            this.refresh()
                .finally(() => {
                    this.refreshRunning = false;

                    if (this.refreshPending) {
                        this.refreshPending = false;
                        this.scheduleRefresh(true);
                    }
                });
        },

        selectAnalyticsDays(days) {
            this.analyticsDays = days;
            this.scheduleAnalyticsLoad();
        },

        scheduleAnalyticsLoad() {
            this.debounce('analytics-load', this.requestProfile.analyticsDebounceMs, () => {
                this.loadAnalytics();
            });
        },

        csrf() {
            const el = document.querySelector('meta[name="csrf-token"]');
            return el && el.content ? el.content : '';
        },

        setFlash(message, type = 'info') {
            this.flashType = type;
            this.flash = message;

            if (!message || type === 'confirm') {
                return;
            }

            setTimeout(() => {
                if (this.flash === message && this.flashType === type) {
                    this.flash = '';
                }
            }, 4000);
        },

        async refresh() {
            try {
                const data = await this.fetchJson('data.php', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }, {
                    lane: 'realtime',
                    maxRetries: 1,
                    cacheTtlMs: this.requestProfile.realtimeCacheTtlMs,
                    dedupe: true
                });

                if (data && data.ok) {
                    this.consecutiveErrors = 0;
                    if (data.db_error && !this.flash) {
                        this.setFlash('Database belum dikonfigurasi. Isi SRP_DB_PASS dan import schema.sql', 'error');
                    }
                    data.cfg.system_on      = Boolean(Number(data.cfg.system_on));
                    data.cfg.postback_token = data.cfg.postback_token || '';
                    this.cfg             = data.cfg;
                    this.logs            = Array.isArray(data.logs) ? data.logs : [];
                    this.weekStats       = data.weekStats       || { total: 0, a_count: 0, b_count: 0, since: '' };
                    this.weekConversions = data.weekConversions || { total: 0, total_payout: 0 };
                    this.conversions     = Array.isArray(data.conversions) ? data.conversions : [];
                    if (this.convPage > this.convTotalPages) { this.convPage = 1; }
                }
            } catch (e) {
                this.consecutiveErrors++;

                if (this.consecutiveErrors >= this.maxConsecutiveErrors) {
                    this.refreshPaused = true;
                    this.setFlash(
                        'Auto-refresh dihentikan setelah ' + this.consecutiveErrors
                            + ' error berturut-turut. Reload halaman untuk mencoba lagi.',
                        'error'
                    );
                    return;
                }

                if (!this.flash) {
                    const status = e?.status || 0;
                    let msg = e?.responseData?.error ?? 'Gagal memuat data. Periksa koneksi database di .env';

                    if (status === 415) {
                        msg = 'Server menolak request (415). Periksa konfigurasi web server & .htaccess';
                    } else if (status === 404) {
                        msg = 'Endpoint data.php tidak ditemukan (404). Periksa file deployment.';
                    }

                    this.setFlash(msg, 'error');
                }
            }
        },

        async save() {
            if (this.cfg.redirect_url && !this.cfg.redirect_url.startsWith('https://')) {
                this.setFlash('Redirect URL must start with https://', 'error');
                return;
            }

            this.savingCfgCount += 1;
            this.isSavingCfg = true;

            try {
                const result = await this.fetchJson('data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': this.csrf()
                    },
                    body: JSON.stringify({
                        system_on: this.cfg.system_on,
                        redirect_url: this.cfg.redirect_url,
                        country_filter_mode: this.cfg.country_filter_mode,
                        country_filter_list: this.cfg.country_filter_list,
                        postback_token: this.cfg.postback_token
                    })
                }, {
                    lane: 'realtime',
                    maxRetries: 0,
                    dedupe: false
                });

                if (!result || result.ok !== true) {
                    this.setFlash('Failed to save configuration', 'error');
                    return;
                }

                this.setFlash('Configuration saved', 'info');
                this.broadcastRefresh();
            } catch (e) {
                this.setFlash('Failed to save configuration', 'error');
            } finally {
                this.savingCfgCount -= 1;
                if (this.savingCfgCount <= 0) {
                    this.savingCfgCount = 0;
                    this.isSavingCfg = false;
                }
            }
        },

        clearLogs() {
            if (this.isClearingLogs) {
                return;
            }

            this.flashAction = 'clearLogs';
            this.setFlash('Clear all traffic logs? This action cannot be undone.', 'confirm');
        },

        cancelFlashAction() {
            this.flash = '';
            this.flashType = 'info';
            this.flashAction = '';
        },

        async confirmFlashAction() {
            if (this.flashType !== 'confirm') {
                return;
            }

            if (this.flashAction === 'clearLogs') {
                await this.performClearLogs();
            }
        },

        async performClearLogs() {
            if (this.isClearingLogs) {
                return;
            }

            this.isClearingLogs = true;

            try {
                const result = await this.fetchJson('data.php', {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': this.csrf()
                    }
                }, {
                    lane: 'realtime',
                    maxRetries: 0,
                    dedupe: false
                });

                if (result && result.ok) {
                    this.logs = [];
                    const deleted = typeof result.deleted === 'number' ? result.deleted : 0;
                    this.setFlash('Successfully deleted ' + deleted + ' log entries', 'info');
                    this.broadcastRefresh();
                } else {
                    this.setFlash('Failed to clear logs', 'error');
                }
            } catch (e) {
                this.setFlash('Failed to clear logs', 'error');
            } finally {
                this.isClearingLogs = false;
                this.flashAction = '';
            }
        },

        isCountryAllowed(countryCode) {
            const code = (countryCode || '').toUpperCase().trim();
            const mode = this.cfg.country_filter_mode || 'all';

            if (mode === 'all') {
                return true;
            }

            const raw = this.cfg.country_filter_list || '';
            const parts = raw.split(',');
            const list = [];
            for (let i = 0; i < parts.length; i += 1) {
                const p = parts[i].trim().toUpperCase();
                if (p !== '') {
                    list.push(p);
                }
            }

            const inList = list.length === 0 ? true : list.indexOf(code) !== -1;

            if (mode === 'whitelist') {
                return inList;
            }
            if (mode === 'blacklist') {
                return !inList;
            }

            return true;
        },

        runTest() {
            const normalizedCountry = (this.testInput.country || '').toUpperCase().trim();
            this.testInput.country = normalizedCountry;

            let decision = 'B';
            let reason = '';

            if (!this.cfg.system_on) {
                decision = 'B';
                reason = 'System is OFF';
            } else if (this.testInput.vpn === 'yes') {
                decision = 'B';
                reason = 'VPN / proxy detected';
            } else if (!this.isCountryAllowed(normalizedCountry)) {
                decision = 'B';
                reason = 'Country not allowed by current filter mode';
            } else if (this.testInput.device !== 'mobile') {
                decision = 'B';
                reason = 'Non-mobile device falls back';
            } else {
                decision = 'A';
                reason = 'System ON, allowed country, mobile device, no VPN';
            }

            this.testResult = {
                decision: decision,
                reason: reason
            };
        },

        fmt(t) {
            return t ? new Date(t * 1000).toLocaleString() : '';
        },

        async loadAnalytics() {
            this.analyticsLoading = true;
            try {
                const data = await this.fetchJson(`data.php?analytics=1&days=${encodeURIComponent(this.analyticsDays)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }, {
                    lane: 'heavy',
                    maxRetries: 2,
                    cacheTtlMs: this.requestProfile.analyticsCacheTtlMs,
                    dedupe: true
                });

                if (data && data.ok) {
                    this.analytics = data.stats;
                }
            } catch (e) {
                // silent fail
            } finally {
                this.analyticsLoading = false;
            }
        },

        // Width % of A or B bar relative to max day total
        analyticsBarPct(row, decision) {
            const max = Math.max(...this.analytics.map(r => r.total), 1);
            return decision === 'a'
                ? (row.a_count / max * 100).toFixed(1)
                : (row.b_count / max * 100).toFixed(1);
        },

        genPostbackToken() {
            const arr = new Uint8Array(24);
            crypto.getRandomValues(arr);
            return Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
        },

        updateMuteStatus() {
            const currentMinute = Math.floor(Date.now() / 1000 / 60);
            const currentSecond = Math.floor(Date.now() / 1000);
            const cyclePosition = currentMinute % 5;

            this.muteStatus.cyclePosition = cyclePosition;
            this.muteStatus.isMuted = cyclePosition >= 2;

            let secondsInCycle = currentSecond % 300;
            let secondsRemaining;

            if (this.muteStatus.isMuted) {
                secondsRemaining = 300 - secondsInCycle;
                const mins = Math.floor(secondsRemaining / 60);
                const secs = secondsRemaining % 60;
                this.muteStatus.timeRemaining = `Unmute in ${mins}m ${secs}s`;
            } else {
                secondsRemaining = 120 - secondsInCycle;
                const mins = Math.floor(secondsRemaining / 60);
                const secs = secondsRemaining % 60;
                this.muteStatus.timeRemaining = `Mute in ${mins}m ${secs}s`;
            }
        },

        // Environment Config Methods
        async loadEnvConfig() {
            try {
                const data = await this.fetchJson('env-config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': this.csrf()
                    },
                    body: JSON.stringify({ action: 'get' })
                }, {
                    lane: 'heavy',
                    maxRetries: 1,
                    cacheTtlMs: this.requestProfile.envCacheTtlMs,
                    dedupe: true,
                    allowRetry: true
                });

                if (data && data.ok) {
                    this.envConfig = data.config;
                }
            } catch (e) {
                console.error('Failed to load environment config:', e);
            }
        },

        async saveEnvConfig() {
            if (this.isSavingEnv) {
                return;
            }

            this.isSavingEnv = true;

            try {
                const data = await this.fetchJson('env-config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': this.csrf()
                    },
                    body: JSON.stringify({
                        action: 'update',
                        config: this.envConfig
                    })
                }, {
                    lane: 'heavy',
                    maxRetries: 0,
                    dedupe: false
                });

                if (data && data.ok) {
                    this.setFlash('Environment configuration saved successfully', 'info');
                    this.broadcastRefresh();
                } else {
                    this.setFlash(data.error || 'Failed to save configuration', 'error');
                }
            } catch (e) {
                this.setFlash('Failed to save environment configuration', 'error');
            } finally {
                this.isSavingEnv = false;
            }
        },

        async testSrpConnection() {
            if (this.isTestingSrp) {
                return;
            }

            this.isTestingSrp = true;

            try {
                const data = await this.fetchJson('env-config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': this.csrf()
                    },
                    body: JSON.stringify({
                        action: 'test_srp',
                        api_url: this.envConfig.SRP_API_URL,
                        api_key: this.envConfig.SRP_API_KEY
                    })
                }, {
                    lane: 'heavy',
                    maxRetries: 1,
                    dedupe: false,
                    allowRetry: true
                });

                if (data && data.ok) {
                    this.setFlash('API connection successful', 'info');
                } else {
                    this.setFlash(data.message || 'API connection failed', 'error');
                }
            } catch (e) {
                this.setFlash('Failed to test API connection', 'error');
            } finally {
                this.isTestingSrp = false;
            }
        },

        // ── API Docs helpers ─────────────────────────────────────────

        getApiBase() {
            return window.location.origin + '/api/v1';
        },

        copyCode(blockId, text) {
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                this.copiedBlock = blockId;
                setTimeout(() => { this.copiedBlock = ''; }, 1500);
            }).catch(() => {});
        },

        getDecisionExample(lang) {
            const key  = this.envConfig.SRP_API_KEY || 'YOUR_API_KEY';
            const url  = this.getApiBase() + '/decision';
            const origin = window.location.origin;
            const body = '{"click_id":"abc123","country_code":"ID","user_agent":"Mozilla/5.0 (Linux; Android 13; Pixel 7)","ip_address":"103.10.20.30","user_lp":"promo2024"}';

            if (lang === 'curl') {
                return `curl -s -X POST "${url}" \\\n  -H "Content-Type: application/json" \\\n  -H "X-API-Key: ${key}" \\\n  -H "X-Request-ID: req-$(date +%s)" \\\n  -d '${body}'\n\n# Response:\n# {"ok":true,"decision":"A","target":"https://...","reason":"ok","ts":1741234567}`;
            }

            if (lang === 'php') {
                return `\x3C?php\ndeclare(strict_types=1);\n\nrequire_once __DIR__ . '/SrpApiClient.php';\n\n$srp = new SrpApiClient(\n    '${origin}',\n    '${key}',\n    timeout: 8,\n    connectTimeout: 3,\n    failureCooldown: 30,\n    rateLimitCooldown: 60,\n    maxRetries: 0,\n    backoffBaseMs: 250,\n    backoffMaxMs: 1500,\n    responseCacheSeconds: 3,\n    inflightWaitMs: 300,\n);\n\n$clickId = trim((string)($_GET['cid'] ?? ''));\nif ($clickId === '') {\n    $clickId = 'AUTO_' . bin2hex(random_bytes(4));\n}\n\ntry {\n    $res = $srp->decision(\n        $clickId,\n        trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')),\n        strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'XX'))),\n        (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),\n        'promo2024',\n    );\n\n    if (($res['decision'] ?? 'B') === 'A' && preg_match('#^https?://#i', (string)($res['target'] ?? ''))) {\n        header('Location: ' . $res['target'], true, 302);\n        exit;\n    }\n} catch (Throwable $e) {\n    $message = $e->getMessage();\n    if (\n        !str_contains($message, 'skipped during cooldown')\n        && !str_contains($message, 'HTTP 429')\n        && !str_contains($message, 'Operation timed out')\n    ) {\n        error_log('[SRP] ' . $message);\n    }\n}\n\n// Decision B fallback`;
            }

            if (lang === 'python') {
                return `import secrets\nimport requests\nfrom flask import request, redirect\n\ndef handle_visitor():\n    res = requests.post(\n        "${url}",\n        json={\n            "click_id":     request.args.get("cid", ""),\n            "country_code": request.headers.get("CF-IPCountry", "XX"),\n            "user_agent":   request.headers.get("User-Agent", ""),\n            "ip_address":   request.remote_addr,\n            "user_lp":      "promo2024",\n        },\n        headers={\n            "X-API-Key": "${key}",\n            "X-Request-ID": secrets.token_hex(8),\n        },\n        timeout=5,\n    ).json()\n\n    if res.get("ok") and res.get("decision") == "A":\n        return redirect(res["target"])\n    # Decision B — reason: res.get("reason")`;
            }

            return '';
        },

        getSimpleGetExample(endpoint) {
            const key = this.envConfig.SRP_API_KEY || 'YOUR_API_KEY';
            const url = this.getApiBase() + '/' + endpoint;
            return `curl -s "${url}" \\\n  -H "X-API-Key: ${key}"`;
        },

        getSettingsExample() {
            const key = this.envConfig.SRP_API_KEY || 'YOUR_API_KEY';
            const url = this.getApiBase() + '/settings';
            return `curl -s -X POST "${url}" \\\n  -H "Content-Type: application/json" \\\n  -H "X-API-Key: ${key}" \\\n  -d '{"system_on":true,"redirect_url":"https://offer.example.com","country_filter_mode":"whitelist","country_filter_list":"ID,MY,SG"}'`;
        },

        // ── PHP Client Integration ────────────────────────────────────────

        getPhpClientClass() {
            return `\x3C?php
declare(strict_types=1);

/**
 * SrpApiClient — PHP client for Hantuin-v2 Public API
 * Requirements: PHP 8.3+, ext-curl, ext-json
 */
class SrpApiClient
{
    private string $cooldownFile;
    private string $responseCacheDir;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int    $timeout = 8,
        private readonly int    $connectTimeout = 3,
        private readonly int    $failureCooldown = 30,
        private readonly int    $rateLimitCooldown = 60,
        private readonly int    $maxRetries = 0,
        private readonly int    $backoffBaseMs = 250,
        private readonly int    $backoffMaxMs = 1500,
        private readonly int    $responseCacheSeconds = 3,
        private readonly int    $inflightWaitMs = 300,
    ) {
        $this->cooldownFile = sys_get_temp_dir() . '/srp-api-cooldown-' . sha1($this->baseUrl) . '.json';
        $this->responseCacheDir = sys_get_temp_dir();
    }

    /**
     * POST /api/v1/decision — evaluate routing decision
     *
     * @param string $clickId      Unique click identifier
     * @param string $ip           Visitor IP address
     * @param string $countryCode  ISO 3166-1 alpha-2 country code
     * @param string $userAgent    Visitor User-Agent string
     * @param string $userLp       Landing page / campaign ID
     *
     * @throws RuntimeException  on network / HTTP / API error
     * @return array{ok:bool, decision:string, target:string, reason:string, ts:int}
     */
    public function decision(
        string $clickId,
        string $ip,
        string $countryCode,
        string $userAgent,
        string $userLp = '',
    ): array {
        return $this->post('/api/v1/decision', [
            'click_id'     => $clickId,
            'ip_address'   => $ip,
            'country_code' => $countryCode,
            'user_agent'   => $userAgent,
            'user_lp'      => $userLp,
        ]);
    }

    /** @throws RuntimeException */
    private function post(string $path, array $payload): array
    {
        $url  = rtrim($this->baseUrl, '/') . $path;
        // JSON_INVALID_UTF8_SUBSTITUTE — replaces malformed UA bytes with U+FFFD
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $cacheKey = sha1($url . '|' . $json);
        $cachedResponse = $this->readCachedResponse($cacheKey);
        if ($cachedResponse !== null) {
            return $cachedResponse;
        }

        if ($this->isCooldownActive()) {
            throw new RuntimeException('cURL: skipped during cooldown after recent failure');
        }

        try {
            $lockHandle = $this->acquireLock($cacheKey);

            try {
                $cachedResponse = $this->readCachedResponse($cacheKey);
                if ($cachedResponse !== null) {
                    return $cachedResponse;
                }

                $attempt = 0;
                while (true) {
                    $result = $this->executeRequest($url, $json);

                    if ($result['error'] !== '') {
                        if ($this->shouldRetry(0, $attempt)) {
                            $this->sleepBeforeRetry($attempt, '');
                            $attempt += 1;
                            continue;
                        }

                        $this->activateCooldown($this->failureCooldown);
                        throw new RuntimeException('cURL: ' . $result['error']);
                    }

                    if ($result['http_code'] === 429) {
                        $retryAfter = $this->parseRetryAfter((string)$result['retry_after']);
                        if ($this->shouldRetry(429, $attempt)) {
                            $this->sleepBeforeRetry($attempt, (string)$result['retry_after']);
                            $attempt += 1;
                            continue;
                        }

                        $this->activateCooldown($retryAfter);
                        throw new RuntimeException('HTTP 429');
                    }

                    if ($result['http_code'] !== 200) {
                        if ($this->shouldRetry((int)$result['http_code'], $attempt)) {
                            $this->sleepBeforeRetry($attempt, (string)$result['retry_after']);
                            $attempt += 1;
                            continue;
                        }

                        throw new RuntimeException('HTTP ' . $result['http_code']);
                    }

                    $this->clearCooldown();

                    $res = json_decode((string)$result['body'], true, 512, JSON_THROW_ON_ERROR);
                    if (!($res['ok'] ?? false)) {
                        throw new RuntimeException($res['error'] ?? 'API error');
                    }

                    $this->writeCachedResponse($cacheKey, $res);
                    return $res;
                }
            } finally {
                $this->releaseLock($lockHandle);
            }
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function isCooldownActive(): bool
    {
        if ($this->failureCooldown <= 0) {
            return false;
        }

        if (!is_file($this->cooldownFile)) {
            return false;
        }

        try {
            $raw = file_get_contents($this->cooldownFile);
            if ($raw === false || $raw === '') {
                return false;
            }

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $until = (int)($decoded['until'] ?? 0);

            if ($until <= time()) {
                $this->clearCooldown();
                return false;
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function activateCooldown(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $directory = dirname($this->cooldownFile);
        if (!is_dir($directory) || !is_writable($directory)) {
            return;
        }

        try {
            $payload = json_encode(['until' => time() + $seconds], JSON_THROW_ON_ERROR);
            file_put_contents($this->cooldownFile, $payload, LOCK_EX);
        } catch (Throwable $e) {
            // Ignore cooldown persistence error
        }
    }

    private function clearCooldown(): void
    {
        if (is_file($this->cooldownFile)) {
            try {
                unlink($this->cooldownFile);
            } catch (Throwable $e) {
                // Ignore cleanup error
            }
        }
    }

    private function parseRetryAfter(string $retryAfter): int
    {
        $fallback = $this->rateLimitCooldown > 0 ? $this->rateLimitCooldown : $this->failureCooldown;
        if ($fallback <= 0) {
            $fallback = 60;
        }

        $value = trim($retryAfter);
        if ($value === '') {
            return $fallback;
        }

        if (ctype_digit($value)) {
            $seconds = (int)$value;
            if ($seconds < 1) {
                return 1;
            }

            if ($seconds > 3600) {
                return 3600;
            }

            return $seconds;
        }

        return $fallback;
    }

    /**
     * @return array{ok:bool, decision:string, target:string, reason:string, ts:int}|null
     */
    private function readCachedResponse(string $cacheKey): ?array
    {
        if ($this->responseCacheSeconds <= 0) {
            return null;
        }

        $file = $this->getResponseCacheFile($cacheKey);
        if (!is_file($file)) {
            return null;
        }

        try {
            $raw = file_get_contents($file);
            if ($raw === false || $raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !isset($decoded['until'], $decoded['data'])) {
                return null;
            }

            if ((int)$decoded['until'] <= time() || !is_array($decoded['data'])) {
                return null;
            }

            return $decoded['data'];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array{ok:bool, decision:string, target:string, reason:string, ts:int} $payload
     */
    private function writeCachedResponse(string $cacheKey, array $payload): void
    {
        if ($this->responseCacheSeconds <= 0) {
            return;
        }

        if (!is_dir($this->responseCacheDir) || !is_writable($this->responseCacheDir)) {
            return;
        }

        try {
            $raw = json_encode([
                'until' => time() + $this->responseCacheSeconds,
                'data' => $payload,
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            file_put_contents($this->getResponseCacheFile($cacheKey), $raw, LOCK_EX);
        } catch (Throwable $e) {
            // Ignore cache persistence error
        }
    }

    private function getResponseCacheFile(string $cacheKey): string
    {
        return $this->responseCacheDir . '/srp-api-response-' . $cacheKey . '.json';
    }

    private function getLockFile(string $cacheKey): string
    {
        return $this->responseCacheDir . '/srp-api-inflight-' . $cacheKey . '.lock';
    }

    /**
     * @return resource|null
     */
    private function acquireLock(string $cacheKey)
    {
        if ($this->inflightWaitMs <= 0) {
            return null;
        }

        if (!is_dir($this->responseCacheDir) || !is_writable($this->responseCacheDir)) {
            return null;
        }

        $handle = fopen($this->getLockFile($cacheKey), 'cb');
        if ($handle === false) {
            return null;
        }

        $deadline = microtime(true) + ($this->inflightWaitMs / 1000);

        do {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return $handle;
            }

            if ($this->readCachedResponse($cacheKey) !== null) {
                fclose($handle);
                return null;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        fclose($handle);
        return null;
    }

    /**
     * @param resource|null $handle
     */
    private function releaseLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        try {
            flock($handle, LOCK_UN);
        } catch (Throwable $e) {
            // Ignore unlock error
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{body:string|false,http_code:int,error:string,retry_after:string}
     */
    private function executeRequest(string $url, string $json): array
    {
        $responseHeaders = [];
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'body' => false,
                'http_code' => 0,
                'error' => 'init failed',
                'retry_after' => '',
            ];
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-API-Key: '    . $this->apiKey,
                    'X-Request-ID: ' . bin2hex(random_bytes(8)),
                ],
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADERFUNCTION => function ($curl, string $headerLine) use (&$responseHeaders): int {
                    $len = strlen($headerLine);
                    $parts = explode(':', $headerLine, 2);
                    if (count($parts) === 2) {
                        $name = strtolower(trim($parts[0]));
                        $value = trim($parts[1]);
                        if ($name !== '') {
                            $responseHeaders[$name] = $value;
                        }
                    }

                    return $len;
                },
            ]);

            $raw  = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
        } finally {
            curl_close($ch);
        }

        return [
            'body' => is_string($raw) ? $raw : false,
            'http_code' => $code,
            'error' => $err !== '' ? $err : ($raw === false ? 'request failed' : ''),
            'retry_after' => (string)($responseHeaders['retry-after'] ?? ''),
        ];
    }

    private function shouldRetry(int $statusCode, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        return in_array($statusCode, [0, 408, 429, 500, 502, 503, 504], true);
    }

    private function sleepBeforeRetry(int $attempt, string $retryAfter): void
    {
        $delayMs = $this->getBackoffDelayMs($attempt, $retryAfter);
        if ($delayMs <= 0) {
            return;
        }

        usleep($delayMs * 1000);
    }

    private function getBackoffDelayMs(int $attempt, string $retryAfter): int
    {
        $retryAfterSeconds = $this->parseRetryAfter($retryAfter);
        if ($retryAfterSeconds > 0) {
            return min($retryAfterSeconds * 1000, $this->backoffMaxMs);
        }

        $delayMs = $this->backoffBaseMs * (2 ** $attempt);
        if ($delayMs < $this->backoffBaseMs) {
            return $this->backoffBaseMs;
        }

        if ($delayMs > $this->backoffMaxMs) {
            return $this->backoffMaxMs;
        }

        return $delayMs;
    }
}`;
        },

        getPhpClientUsage() {
            const key    = this.envConfig.SRP_API_KEY || 'YOUR_API_KEY';
            const origin = window.location.origin;
            return `\x3C?php
// usage.php — Contoh pemakaian SrpApiClient (class-based)
declare(strict_types=1);
require_once 'SrpApiClient.php';

// ── Config ──────────────────────────────────────────────
$client = new SrpApiClient(
    '${origin}',  // baseUrl — URL server Hantuin-v2
    '${key}',     // apiKey  — dari dashboard Hantuin-v2
    timeout: 8,
    connectTimeout: 3,
    failureCooldown: 30,
    rateLimitCooldown: 60,
    maxRetries: 0,
    backoffBaseMs: 250,
    backoffMaxMs: 1500,
    responseCacheSeconds: 3,
    inflightWaitMs: 300,
);

// ── Capture query string sebelum modifikasi ─────────────
$originalQueryString = $_SERVER['QUERY_STRING'] ?? '';

// ── click_id: auto-generate jika tidak ada ─────────────
$clickId = trim((string)($_GET['click_id'] ?? ''));
if ($clickId === '') {
    $clickId = 'AUTO_' . bin2hex(random_bytes(4));
}

// ── IP: hanya trusted headers ───────────────────────────
$ip = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

// ── Parameter lain ──────────────────────────────────────
$cc = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'XX')));
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$lp = trim((string)($_GET['user_lp'] ?? ''));

// ── Resolve routing ─────────────────────────────────────
$redirectTo = null;

try {
    $res = $client->decision($clickId, $ip, $cc, $ua, $lp);

    if (($res['decision'] ?? 'B') === 'A') {
        $target = (string)($res['target'] ?? '');
        if (preg_match('#^https?://#i', $target) && filter_var($target, FILTER_VALIDATE_URL) !== false) {
            $redirectTo = $target;
        }
    }
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (!str_contains($msg, 'cooldown') && !str_contains($msg, '429') && !str_contains($msg, 'timed out')) {
        error_log('[Hantuin-v2] ' . $msg);
    }
}

// ── Redirect ────────────────────────────────────────────
if ($redirectTo !== null) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Location: ' . $redirectTo, true, 302);
    exit;
}

$fallbackUrl = '/_meetups/' . ($originalQueryString !== '' ? '?' . $originalQueryString : '');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ' . $fallbackUrl, true, 302);
exit;`;
        },

        getPhpClientFull() {
            const key = this.envConfig.SRP_API_KEY || 'YOUR_API_KEY';
            const origin = window.location.origin;
            return `\x3C?php
// =============================================================================
// entry.php — Hantuin-v2 Client Entry Point
// =============================================================================
// Upload file ini ke SERVER CLIENT (server yang terima traffic).
// File ini request keputusan ke server Hantuin-v2, lalu redirect visitor.
//
// Flow: Visitor → entry.php → POST /api/v1/decision → redirect ke target
//
// Contoh URL traffic masuk:
//   https://client-domain.com/entry.php?click_id=ABC123&user_lp=campaign1
// =============================================================================
declare(strict_types=1);

// ── CONFIG — WAJIB DIISI SEBELUM UPLOAD ─────────────────
define('HANTUIN_API_URL', '${origin}/api/v1/decision');
define('HANTUIN_API_KEY', '${key}');
define('FALLBACK_PATH',   '/_meetups/');
define('API_TIMEOUT',      5);
define('API_CONNECT_TIMEOUT', 3);

// ── Decision Request ────────────────────────────────────
function getDecision(array $params): ?array
{
    if (HANTUIN_API_KEY === '' || HANTUIN_API_KEY === 'YOUR_API_KEY' || HANTUIN_API_KEY === 'GANTI_DENGAN_API_KEY_ANDA') {
        error_log('Hantuin-v2: API key belum dikonfigurasi');
        return null;
    }

    $ch = curl_init(HANTUIN_API_URL);
    if ($ch === false) { return null; }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . HANTUIN_API_KEY,
            'User-Agent: Hantuin-v2-Client/1.0',
        ],
        CURLOPT_POSTFIELDS     => (string) json_encode($params),
        CURLOPT_TIMEOUT        => API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => API_CONNECT_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error !== '') { error_log("Hantuin-v2: {$error}"); return null; }
    if ($httpCode !== 200 || !is_string($response)) { return null; }

    $data = json_decode($response, true);
    return (is_array($data) && ($data['ok'] ?? false)) ? $data : null;
}

// ── Country Detection (CDN geo headers → query → XX) ────
function detectCountry(): string
{
    $code = strtoupper(trim((string) (
        $_SERVER['HTTP_CF_IPCOUNTRY']
        ?? $_SERVER['HTTP_X_VERCEL_IP_COUNTRY']
        ?? $_SERVER['HTTP_X_COUNTRY_CODE']
        ?? $_SERVER['HTTP_X_APPENGINE_COUNTRY']
        ?? $_SERVER['HTTP_X_GEO_COUNTRY']
        ?? $_GET['country_code'] ?? 'XX'
    )));
    return preg_match('/\\\\A[A-Z]{2}\\\\z/', $code) ? $code : 'XX';
}

// ── Device Detection (BOT → mobile/tablet → desktop) ────
function detectDevice(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ov = strtolower((string) ($_GET['user_agent'] ?? ''));

    if (preg_match('~bot|crawl|spider|facebook|whatsapp|telegram~i', $ua)) {
        return 'BOT';
    }

    $d = 'web';
    if (preg_match('/tablet|ipad/i', $ua)) { $d = 'wap'; }
    elseif (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $ua)) { $d = 'wap'; }

    $map = ['mobile' => 'wap', 'desktop' => 'web'];
    $ov = $map[$ov] ?? $ov;
    if (in_array($ov, ['wap', 'web'], true)) { $d = $ov; }

    return $d;
}

// ── IP Detection (Cloudflare → proxy → REMOTE_ADDR) ─────
function detectIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP'] as $h) {
        if (!empty($_SERVER[$h]) && filter_var(trim((string) $_SERVER[$h]), FILTER_VALIDATE_IP)) {
            return trim((string) $_SERVER[$h]);
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// ── MAIN ────────────────────────────────────────────────
$decision = getDecision([
    'click_id'     => (string) ($_GET['click_id'] ?? ''),
    'country_code' => detectCountry(),
    'user_agent'   => detectDevice(),
    'ip_address'   => detectIp(),
    'user_lp'      => (string) ($_GET['user_lp'] ?? ''),
]);

if ($decision !== null && isset($decision['target'])) {
    $target = (string) $decision['target'];
    if (preg_match('#^https?://#i', $target) && filter_var($target, FILTER_VALIDATE_URL) !== false) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Location: ' . $target, true, 302);
        exit;
    }
}

// Fallback
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ' . FALLBACK_PATH . ($qs !== '' ? '?' . $qs : ''), true, 302);
exit;`;
        },

    }));
});
</script>

<?php require __DIR__ . '/components/footer.php'; ?>
