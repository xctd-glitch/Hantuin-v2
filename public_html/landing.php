<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use SRP\Controllers\LandingController;

// Always evaluate routing decision.
// route() redirects on decision A, renders landing page on decision B.
LandingController::route();
