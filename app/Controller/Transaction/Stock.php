<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Transaction;

use KejawenLab\Application\Transaction\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

final class Stock extends AbstractController
{
    #[Route('/transactions/stocks', name: self::class, methods: ['GET'], priority: 2)]
    public function __invoke(Request $request, TransactionService $service): JsonResponse
    {
        return $this->json(['items' => $service->stocks($request->query->getString('organization_id') ?: null)]);
    }
}
