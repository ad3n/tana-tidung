<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Transaction;

use KejawenLab\Application\Transaction\{TransactionService, TransactionPresenter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

final class Lines extends AbstractController
{
    #[Route('/transactions/{id}/items', name: self::class, methods: ['POST'])]
    #[Route('/transactions/{id}/items/{lineId}', name: self::class . '_delete', methods: ['DELETE'])]
    public function __invoke(string $id, Request $request, TransactionService $service, TransactionPresenter $presenter, ?string $lineId = null): JsonResponse
    {
        if ($lineId) { $service->removeLine($id, $lineId); } else { $service->addLine($id, $request->toArray()); }
        return $this->json($presenter->present($service->get($id)), $lineId ? 200 : 201);
    }
}
