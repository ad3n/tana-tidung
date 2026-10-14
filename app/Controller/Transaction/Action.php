<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Transaction;

use KejawenLab\Application\Transaction\{TransactionService, TransactionPresenter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class Action extends AbstractController
{
    #[Route('/transactions/{id}/{action}', name: self::class, requirements: ['action' => 'post|send|receive|cancel'], methods: ['POST'])]
    public function __invoke(string $id, string $action, TransactionService $service, TransactionPresenter $presenter): JsonResponse
    {
        $service->transition($id, $action);
        return $this->json($presenter->present($service->get($id)));
    }
}
