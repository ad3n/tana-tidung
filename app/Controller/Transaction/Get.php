<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Transaction;

use KejawenLab\Application\Transaction\TransactionPresenter;
use KejawenLab\Application\Transaction\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class Get extends AbstractController
{
    #[Route('/transactions/{id}', name: self::class, methods: ['GET'])]
    public function __invoke(string $id, TransactionService $service, TransactionPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->present($service->get($id)));
    }
}
