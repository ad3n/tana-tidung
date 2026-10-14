<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Transaction;

use KejawenLab\Application\Transaction\{TransactionService, TransactionPresenter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class Get extends AbstractController
{
    #[Route('/transactions/{id}', name: self::class, methods: ['GET', 'POST'])]
    public function __invoke(string $id, Request $request, TransactionService $service, TransactionPresenter $presenter): Response
    {
        $transaction = $service->get($id);
        $error = null;
        $lineValues = [];
        $values = $presenter->present($transaction, false);
        if ($request->isMethod('POST')) {
            $input = $request->request->all();
            $operation = $input['_operation'] ?? '';
            unset($input['_operation']);
            if ($operation === 'header') { $input['type'] = $transaction->getType()->value; }
            try {
                match ($operation) {
                    'header' => $service->saveDraft($input, $id),
                    'line' => $service->addLine($id, $input),
                    'remove_line' => $service->removeLine($id, (string) ($input['line_id'] ?? '')),
                    'post', 'send', 'receive', 'cancel' => $service->transition($id, $operation),
                    default => throw $this->createNotFoundException(),
                };
                return $this->redirectToRoute(self::class, ['id' => $id], 303);
            } catch (HttpExceptionInterface $exception) {
                if (!in_array($exception->getStatusCode(), [400, 409], true)) { throw $exception; }
                $error = $exception->getMessage();
                if ($operation === 'header') { $values = $input; }
                if ($operation === 'line') { $lineValues = $input; }
            }
            $transaction = $service->get($id);
        }
        $choices = $service->choices();
        return $this->render('transaction/view.html.twig', array_merge($choices, [
            'page_title' => 'transaction.detail', 'transaction' => $transaction, 'data' => $presenter->present($transaction),
            'can_operate' => $service->access->canOperate($transaction), 'can_receive' => $service->access->canOperate($transaction, true),
            'error' => $error, 'values' => $values, 'line_values' => $lineValues,
            'stocks' => $service->access->canOperate($transaction) && $transaction->getFromOrganization() ? $service->stocks($transaction->getFromOrganization()->getId()) : [],
        ]));
    }
}
