<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Transaction;

use KejawenLab\Application\Transaction\Enum\TransactionType;
use KejawenLab\Application\Transaction\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class Listing extends AbstractController
{
    #[Route('/transactions/receipts/list', name: self::class.'_receipt', defaults: ['transactionType' => 1], methods: ['GET'])]
    #[Route('/transactions/transfers/list', name: self::class.'_transfer', defaults: ['transactionType' => 2], methods: ['GET'])]
    #[Route('/transactions/disposals/list', name: self::class.'_disposal', defaults: ['transactionType' => 3], methods: ['GET'])]
    public function __invoke(Request $request, TransactionService $service, int $transactionType): Response
    {
        $type = TransactionType::from($transactionType);

        return $this->render('transaction/all.html.twig', [
            'page_title' => 'transaction.list.'.$type->value,
            'search_label' => TransactionType::RECEIPT === $type ? 'transaction.search' : 'transaction.search_sk',
            'list' => $service->listing($request->query->getInt('page', 1), $request->query->getString('q'), $type),
        ]);
    }
}
