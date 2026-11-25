<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;

/** GET /api/health — used by the hosting platform to check the API and DB are up. */
final class HealthController
{
    public function show(): Response
    {
        Database::connection()->query('SELECT 1');
        return Response::json(['status' => 'ok']);
    }
}
