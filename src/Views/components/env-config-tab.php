<!-- Environment Configuration Tab -->
<div x-show="activeTab === 'env-config'" x-cloak>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold">Environment Configuration</h2>
                <p class="text-[10px] text-muted-foreground mt-0.5">
                    Manage application settings without editing .env file manually
                </p>
            </div>
            <button
                type="button"
                x-show="userRole === 'admin'"
                class="btn btn-sm btn-primary"
                @click="saveEnvConfig()"
                :disabled="isSavingEnv"
                data-sniper="1">
                <svg x-show="isSavingEnv" class="h-3 w-3 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                </svg>
                <span x-text="isSavingEnv ? 'Saving...' : 'Save All Changes'"></span>
            </button>
        </div>

        <!-- API Configuration -->
        <div class="card p-3">
            <div class="flex items-start gap-2 mb-2.5">
                <svg class="h-4 w-4 text-primary mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="text-xs font-semibold">Public API Key</h3>
                    <p class="text-[10px] text-muted-foreground">Key untuk autentikasi semua request ke <code class="bg-muted px-0.5 rounded">/api/v1/*</code></p>
                </div>
                <button
                    type="button"
                    class="btn btn-ghost btn-sm text-[10px]"
                    @click="testSrpConnection()"
                    :disabled="isTestingSrp"
                    data-sniper="1">
                    <svg x-show="isTestingSrp" class="h-3 w-3 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                    </svg>
                    <span x-text="isTestingSrp ? 'Testing...' : 'Test Key'"></span>
                </button>
            </div>

            <div class="space-y-2">
                <div>
                    <label class="block text-[10px] font-medium mb-1">API Key</label>
                    <div class="relative">
                        <input
                            :type="showApiKey ? 'text' : 'password'"
                            class="input input-sm w-full font-mono pr-16"
                            x-model="envConfig.SRP_API_KEY"
                            :disabled="userRole !== 'admin'"
                            autocomplete="off"
                            placeholder="Enter your API key">
                        <button
                            type="button"
                            class="absolute right-1 top-1/2 -translate-y-1/2 btn btn-ghost btn-sm text-[10px] px-2 h-6"
                            @click="showApiKey = !showApiKey"
                            :title="showApiKey ? 'Sembunyikan key' : 'Tampilkan key'">
                            <svg x-show="!showApiKey" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showApiKey" x-cloak class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Credentials -->
        <div class="card p-3">
            <div class="flex items-start gap-2 mb-2.5">
                <svg class="h-4 w-4 text-primary mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="text-xs font-semibold">Admin Credentials</h3>
                    <p class="text-[10px] text-muted-foreground">Dashboard login account</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-medium mb-1">Admin Username</label>
                    <input
                        type="text"
                        class="input input-sm w-full"
                        x-model="envConfig.SRP_ADMIN_USER"
                        :disabled="userRole !== 'admin'"
                        placeholder="admin">
                </div>
                <div>
                    <label class="block text-[10px] font-medium mb-1">Password Hash <span class="text-muted-foreground font-normal">(bcrypt)</span></label>
                    <input
                        type="password"
                        class="input input-sm w-full"
                        x-model="envConfig.SRP_ADMIN_PASSWORD_HASH"
                        :disabled="userRole !== 'admin'"
                        placeholder="password_hash('pass', PASSWORD_DEFAULT)">
                </div>
            </div>
            <p class="text-[10px] text-muted-foreground mt-1.5">
                Generate hash:
                <code class="bg-muted px-1 rounded text-[10px]">php -r "echo password_hash('yourpass', PASSWORD_DEFAULT);"</code>
            </p>
        </div>

        <!-- Application Settings -->
        <div class="card p-3">
            <div class="flex items-start gap-2 mb-2.5">
                <svg class="h-4 w-4 text-primary mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="text-xs font-semibold">Application Settings</h3>
                    <p class="text-[10px] text-muted-foreground">General application configuration</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-[10px] font-medium mb-1">Environment</label>
                    <select class="input input-sm w-full" x-model="envConfig.APP_ENV" :disabled="userRole !== 'admin'">
                        <option value="development">Development</option>
                        <option value="staging">Staging</option>
                        <option value="production">Production</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-medium mb-1">Debug Mode</label>
                    <select class="input input-sm w-full" x-model="envConfig.APP_DEBUG" :disabled="userRole !== 'admin'">
                        <option value="false">Disabled</option>
                        <option value="true">Enabled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-medium mb-1">Session Lifetime (sec)</label>
                    <input
                        type="number"
                        class="input input-sm w-full"
                        x-model="envConfig.SESSION_LIFETIME"
                        :disabled="userRole !== 'admin'"
                        placeholder="3600">
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="card p-3">
            <div class="flex items-start gap-2 mb-2.5">
                <svg class="h-4 w-4 text-primary mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="text-xs font-semibold">Security Settings</h3>
                    <p class="text-[10px] text-muted-foreground">Login rate limiting</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-medium mb-1">Rate Limit Attempts</label>
                    <input
                        type="number"
                        class="input input-sm w-full"
                        x-model="envConfig.RATE_LIMIT_ATTEMPTS"
                        :disabled="userRole !== 'admin'"
                        placeholder="5"
                        min="1">
                </div>
                <div>
                    <label class="block text-[10px] font-medium mb-1">Rate Limit Window (sec)</label>
                    <input
                        type="number"
                        class="input input-sm w-full"
                        x-model="envConfig.RATE_LIMIT_WINDOW"
                        :disabled="userRole !== 'admin'"
                        placeholder="900"
                        min="60">
                </div>
            </div>
        </div>

        <!-- Warning Notice -->
        <div class="rounded-[0.3rem] border border-amber-500/30 bg-amber-50/50 p-2.5">
            <div class="flex items-start gap-2">
                <svg class="h-4 w-4 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <p class="text-[10px] font-medium text-amber-800">Important Notice</p>
                    <p class="text-[10px] text-amber-700 mt-0.5">
                        Changes update the <code class="bg-amber-100 px-0.5 rounded">.env</code> file.
                        A <code class="bg-amber-100 px-0.5 rounded">.env.backup</code> is created automatically before saving.
                        Server-level env vars (e.g. from <code class="bg-amber-100 px-0.5 rounded">.htaccess</code>) take priority over .env values.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
