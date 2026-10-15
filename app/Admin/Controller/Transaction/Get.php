<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Transaction;

use KejawenLab\Application\Transaction\Enum\TransactionStatus;
use KejawenLab\Application\Transaction\TransactionPresenter;
use KejawenLab\Application\Transaction\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class Get extends AbstractController
{
    #[Route('/transactions/{id}', name: self::class, methods: ['GET', 'POST'])]
    public function __invoke(string $id, Request $request, TransactionService $service, TransactionPresenter $presenter): Response
    {
        $transaction = $service->get($id);
        $revising = $request->query->getBoolean('revise');
        if ($revising) {
            $service->access->requirePermission('edit');
            if (!$service->access->canOperate($transaction)) {
                throw $this->createAccessDeniedException();
            }

            if (TransactionStatus::RETURNED !== $transaction->getStatus()) {
                throw new ConflictHttpException('Revisi hanya tersedia untuk transaksi Perlu Revisi.');
            }
        }

        $error = null;
        $lineValues = [];
        $values = $presenter->present($transaction, false);
        $values['items'] = array_map(static fn (array $line): array => [
            'line_id' => $line['id'], 'item_id' => $line['item_id'], 'unit_id' => $line['unit_id'],
            'qty' => $line['qty'], 'base_qty' => $line['base_qty'], 'batch_number' => $line['batch_number'],
            'price' => $line['price'],
            'expiry_date' => $line['expiry_date'], 'stock_lot_id' => $line['stock_lot_id'],
        ], $presenter->present($transaction)['items']);
        if ($request->isMethod('POST')) {
            $input = $request->request->all();
            $operation = $input['_operation'] ?? '';
            unset($input['_operation']);
            if (in_array($operation, ['header', 'revision'], true)) {
                $input['type'] = $transaction->getType()->value;
            }

            try {
                match ($operation) {
                    'revision' => $service->revise($id, $input),
                    'line' => $service->addLine($id, $input),
                    'remove_line' => $service->removeLine($id, (string) ($input['line_id'] ?? '')),
                    'submit', 'verify', 'approve', 'return', 'post', 'send', 'receive', 'cancel' => $service->transition($id, $operation, $request->request->getString('note')),
                    default => throw $this->createNotFoundException(),
                };

                $parameters = ['id' => $id];
                if ($revising && in_array($operation, ['header', 'line', 'remove_line'], true)) {
                    $parameters['revise'] = 1;
                }

                return $this->redirectToRoute(self::class, $parameters, 303);
            } catch (HttpExceptionInterface $exception) {
                if (!in_array($exception->getStatusCode(), [400, 409], true)) {
                    throw $exception;
                }

                $error = $exception->getMessage();
                if (in_array($operation, ['header', 'revision'], true)) {
                    $values = $input;
                }

                if ('line' === $operation) {
                    $lineValues = $input;
                }
            }

            $transaction = $service->get($id);
        }

        $choices = $service->choices();

        return $this->render($revising ? 'transaction/revision.html.twig' : 'transaction/view.html.twig', array_merge($choices, [
            'page_title' => 'transaction.detail', 'transaction' => $transaction, 'data' => $presenter->present($transaction),
            'can_operate' => $service->access->canOperate($transaction) && $service->access->hasMenuPermission('TRANSACTION_'.$transaction->getType()->name, 'edit'), 'can_receive' => $service->access->canOperate($transaction, true),
            'revising' => $revising, 'error' => $error, 'values' => $values, 'line_values' => $lineValues,
            'stocks' => $service->access->canOperate($transaction) && $transaction->getFromOrganization() ? $service->stocks($transaction->getFromOrganization()->getId()) : [],
        ]));
    }
}
