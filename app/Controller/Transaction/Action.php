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

final class Action extends AbstractController
{
    #[Route('/transactions/{id}/{action}', name: self::class, requirements: ['action' => 'submit|verify|approve|return|post|send|receive|cancel'], methods: ['POST'])]
    public function __invoke(string $id, string $action, Request $request, TransactionService $service, TransactionPresenter $presenter): JsonResponse
    {
        $input = '' !== $request->getContent() ? $request->toArray() : [];
        $note = $input['note'] ?? null;
        if (null !== $note && !is_string($note)) {
            throw new BadRequestHttpException('Alasan harus berupa teks.');
        }

        $service->transition($id, $action, $note);

        return $this->json($presenter->present($service->get($id)));
    }
}
