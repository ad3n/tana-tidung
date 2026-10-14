<?php

declare(strict_types=1);

namespace KejawenLab\Application\Controller\Transaction;

use KejawenLab\Application\Transaction\{TransactionService, TransactionPresenter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

final class GetAll extends AbstractController
{
    #[Route('/transactions', name: self::class, methods: ['GET'])]
    public function __invoke(Request $request, TransactionService $service, TransactionPresenter $presenter): JsonResponse
    {
        $list = $service->listing(max(1, $request->query->getInt('page', 1)), $request->query->getString('q'));
        $list['items'] = array_map(fn ($t) => $presenter->present($t, false), $list['items']);
        return $this->json($list);
    }
}
