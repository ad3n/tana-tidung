<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Transaction;

use KejawenLab\Application\Transaction\TransactionPresenter;
use KejawenLab\Application\Transaction\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class Write extends AbstractController
{
    #[Route('/transactions', name: self::class, methods: ['POST'])]
    #[Route('/transactions/{id}', name: self::class.'_edit', methods: ['PUT'])]
    public function __invoke(Request $request, TransactionService $service, TransactionPresenter $presenter, ?string $id = null): JsonResponse
    {
        $input = $request->toArray();
        if ($id) {
            $transaction = $service->revise($id, $input);
        } else {
            $lines = $input['items'] ?? [];
            unset($input['items']);
            if (!is_array($lines)) {
                throw new BadRequestHttpException('Format detail barang tidak valid.');
            }

            $transaction = $service->submitWithLines($input, $lines);
        }

        return $this->json($presenter->present($transaction), $id ? 200 : 201);
    }
}
