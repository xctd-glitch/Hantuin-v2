<?php

declare(strict_types=1);

/**
 * Dashboard Statistik Terpisah
 *
 * Halaman standalone yang menampilkan data statistik
 * dengan memanggil Public API (X-API-Key).
 * Tidak memerlukan session/login — cukup API key.
 */

require_once __DIR__ . '/../src/Config/Bootstrap.php';

require __DIR__ . '/../src/Views/stats.view.php';
