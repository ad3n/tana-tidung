<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Transaction;

use KejawenLab\Application\Transaction\Enum\TransactionType;
use KejawenLab\Application\Transaction\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class Main extends AbstractController
{
    #[Route('/transactions', name: self::class, methods: ['GET', 'POST'])]
    #[Route('/transactions/receipts', name: self::class.'_receipt', defaults: ['transactionType' => 1], methods: ['GET', 'POST'], priority: 2)]
    #[Route('/transactions/transfers', name: self::class.'_transfer', defaults: ['transactionType' => 2], methods: ['GET', 'POST'], priority: 2)]
    #[Route('/transactions/disposals', name: self::class.'_disposal', defaults: ['transactionType' => 3], methods: ['GET', 'POST'], priority: 2)]
    public function __invoke(Request $request, TransactionService $service, int $transactionType = 1): Response
    {
        $service->access->requirePermission('add');
        if ($service->access->isApprover() && !$service->access->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        $error = null;
        $type = TransactionType::from($transactionType);
        $values = ['type' => $type->value];
        if ($request->isMethod('POST')) {
            $values = array_replace($request->request->all(), ['type' => $type->value]);
            try {
                $header = $values;
                $lines = $header['items'] ?? [];
                unset($header['items']);
                if (!is_array($lines)) {
                    throw new BadRequestHttpException('Format detail barang tidak valid.');
                }

                $transaction = $service->submitWithLines($header, $lines);

                return $this->redirectToRoute(Get::class, ['id' => $transaction->getId()], 303);
            } catch (HttpExceptionInterface $exception) {
                if (!in_array($exception->getStatusCode(), [400, 409], true)) {
                    throw $exception;
                }

                $error = $exception->getMessage();
            }
        }

        return $this->render('transaction/create.html.twig', array_merge($service->choices(), [
            'page_title' => $type->label(),
            'error' => $error, 'values' => $values, 'transaction' => null,
            'stocks' => TransactionType::RECEIPT === $type ? [] : $service->stocks(), 'line_values' => [],
        ]));
    }
}
