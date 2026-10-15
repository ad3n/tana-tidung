<?php

declare(strict_types=1);

namespace KejawenLab\Application\Transaction;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\Application\Entity\Transaction;
use KejawenLab\Application\Entity\TransactionItemAllocation;

final class TransactionPresenter
{
    public function __construct(private TransactionService $service, private EntityManagerInterface $em)
    {
    }

    public function present(Transaction $transaction, bool $details = true): array
    {
        $result = [
            'id' => $transaction->getId(), 'code' => $transaction->getCode(),
            'type' => $transaction->getType()->value, 'status' => $transaction->getStatus()->value,
            'date' => $transaction->getDate()->format('Y-m-d'),
            'fiscal_year_id' => $transaction->getFiscalYear()->getId(),
            'source_id' => $transaction->getSource()?->getId(),
            'from_organization_id' => $transaction->getFromOrganization()?->getId(),
            'to_organization_id' => $transaction->getToOrganization()?->getId(),
            'reference_number' => $transaction->getReferenceNumber(), 'notes' => $transaction->getNotes(),
            'workflow_history' => $transaction->getWorkflowHistory(),
            'available_actions' => $this->service->availableActions($transaction),
            'created_by' => $transaction->getCreatedBy()->getId(),
            'created_at' => $transaction->getCreatedAt()->format(DATE_ATOM),
            'posted_by' => $transaction->getPostedBy()?->getId(), 'posted_at' => $transaction->getPostedAt()?->format(DATE_ATOM),
            'received_by' => $transaction->getReceivedBy()?->getId(), 'received_at' => $transaction->getReceivedAt()?->format(DATE_ATOM),
            'cancelled_by' => $transaction->getCancelledBy()?->getId(), 'cancelled_at' => $transaction->getCancelledAt()?->format(DATE_ATOM),
        ];
        if ($details) {
            $result['items'] = [];
            foreach ($this->service->lines($transaction) as $line) {
                $allocations = $this->em->getRepository(TransactionItemAllocation::class)->findBy(['transactionItem' => $line]);
                $result['items'][] = [
                    'id' => $line->getId(), 'item_id' => $line->getItem()->getId(), 'item_name' => $line->getItem()->getName(), 'item_code' => $line->getItem()->getCode(),
                    'unit_id' => $line->getUnit()->getId(), 'unit_name' => $line->getUnit()->getName(), 'qty' => $line->getQty(),
                    'price' => $line->getPrice(),
                    'total' => null === $line->getPrice() ? null : (string) BigDecimal::of($line->getQty())->multipliedBy($line->getPrice())->toScale(2, RoundingMode::HalfUp),
                    'conversion_factor' => $line->getConversionFactor(), 'base_unit_id' => $line->getBaseUnit()->getId(),
                    'base_unit_name' => $line->getBaseUnit()->getName(), 'base_unit_code' => $line->getBaseUnit()->getCode(), 'base_qty' => $line->getBaseQty(),
                    'batch_number' => $line->getBatchNumber(), 'expiry_date' => $line->getExpiryDate()?->format('Y-m-d'),
                    'stock_lot_id' => $line->getSelectedLot()?->getId(),
                    'allocations' => array_map(static fn ($a) => ['stock_lot_id' => $a->getStockLot()->getId(), 'batch_number' => $a->getStockLot()->getBatchNumber(), 'expiry_date' => $a->getStockLot()->getExpiryDate()?->format('Y-m-d'), 'base_qty' => $a->getBaseQty()], $allocations),
                ];
            }
        }

        return $result;
    }
}
