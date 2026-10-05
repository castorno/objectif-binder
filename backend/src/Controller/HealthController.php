<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route('/api/health', name: 'health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $databaseStatus = 'ok';
        $httpStatus = 200;

        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (DbalException) {
            $databaseStatus = 'unreachable';
            $httpStatus = 503;
        }

        return new JsonResponse(
            [
                'status' => $databaseStatus === 'ok' ? 'ok' : 'degraded',
                'database' => $databaseStatus,
            ],
            $httpStatus,
        );
    }
}
