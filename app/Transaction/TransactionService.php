<?php

declare(strict_types=1);

namespace KejawenLab\Application\Transaction;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Entity\ItemUnitConversion;
use KejawenLab\Application\Entity\Organization;
use KejawenLab\Application\Entity\Source;
use KejawenLab\Application\Entity\StockLot;
use KejawenLab\Application\Entity\StockMovement;
use KejawenLab\Application\Entity\Transaction;
use KejawenLab\Application\Entity\TransactionItem;
use KejawenLab\Application\Entity\TransactionItemAllocation;
use KejawenLab\Application\Entity\Unit;
use KejawenLab\Application\Transaction\Enum\TransactionStatus as Status;
use KejawenLab\Application\Transaction\Enum\TransactionType as Type;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class TransactionService
{
    public function __construct(private EntityManagerInterface $em, public readonly TransactionAccess $access)
    {
    }

    public function get(string $id): Transaction
    {
        $this->access->requirePermission('view');
        $transaction = $this->reference(Transaction::class, $id);
        if (!$this->access->canView($transaction)) {
            throw new NotFoundHttpException();
        }

        return $transaction;
    }

    public function listing(int $page = 1, string $search = '', ?Type $type = null, ?string $organizationId = null): array
    {
        $this->access->requirePermission('view');
        $query = $this->em->createQueryBuilder()->select('t')->from(Transaction::class, 't');
        if ($org = $this->access->organization()) {
            $query->andWhere('t.fromOrganization = :org OR t.toOrganization = :org')->setParameter('org', $org);
        }

        if (null !== $type) {
            $query->andWhere('t.type = :type')->setParameter('type', $type->value);
        }

        if (null !== $organizationId && '' !== $organizationId && $this->access->isAdmin()) {
            $organization = $this->reference(Organization::class, $organizationId);
            $condition = match ($type) {
                Type::RECEIPT => 't.toOrganization = :filterOrganization',
                Type::DISPOSAL => 't.fromOrganization = :filterOrganization',
                default => 't.fromOrganization = :filterOrganization OR t.toOrganization = :filterOrganization',
            };
            $query->andWhere($condition)->setParameter('filterOrganization', $organization);
        }

        if ('' !== $search) {
            $query->andWhere('LOWER(t.code) LIKE :q OR LOWER(t.referenceNumber) LIKE :q')->setParameter('q', '%'.mb_strtolower($search).'%');
        }

        $total = (clone $query)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
        $rows = $query->orderBy('t.createdAt', 'DESC')->addOrderBy('t.id', 'DESC')->setFirstResult((max(1, $page) - 1) * 25)->setMaxResults(25)->getQuery()->getResult();

        $amounts = [];
        foreach ($rows as $transaction) {
            if (Type::RECEIPT === $transaction->getType()) {
                $amounts[$transaction->getId()] = $this->receiptAmount($transaction);
            }
        }

        return ['items' => $rows, 'total' => (int) $total, 'page' => max(1, $page), 'amounts' => $amounts];
    }

    private function receiptAmount(Transaction $transaction): array
    {
        $total = BigDecimal::of(0);
        foreach ($this->lines($transaction) as $line) {
            if (null === $line->getPrice()) {
                return ['value' => null, 'estimated' => false];
            }

            $total = $total->plus(BigDecimal::of($line->getQty())->multipliedBy($line->getPrice()));
        }

        return ['value' => (string) $total->toScale(2, RoundingMode::HalfUp), 'estimated' => false];
    }

    public function lines(Transaction $transaction): array
    {
        return $this->em->getRepository(TransactionItem::class)->findBy(['transaction' => $transaction], ['id' => 'ASC']);
    }

    private function saveHeader(array $input, ?string $id = null): Transaction
    {
        $this->access->requirePermission($id ? 'edit' : 'add');
        if ($this->access->isApprover() && !$this->access->isAdmin()) {
            throw new AccessDeniedHttpException('Approver hanya dapat memeriksa pengajuan transaksi.');
        }

        return $this->locked(function () use ($input, $id): Transaction {
            $this->keys($input, ['type', 'date', 'from_organization_id', 'to_organization_id', 'source_id', 'reference_number', 'notes']);
            $transaction = $id ? $this->get($id) : new Transaction();
            if (!$id) {
                $transaction->setStatus(Status::RETURNED);
            }

            if ($id) {
                $this->em->refresh($transaction);
                $this->editableOwner($transaction);
            }

            $typeValue = $this->text($input, 'type');
            $type = ctype_digit($typeValue) ? Type::tryFrom((int) $typeValue) : null;
            if (!$type) {
                throw new BadRequestHttpException('Jenis transaksi tidak valid.');
            }

            if ($id && $transaction->getType() !== $type) {
                throw new ConflictHttpException('Jenis transaksi tidak dapat diubah. Buat transaksi baru.');
            }

            $year = $id ? $transaction->getFiscalYear() : $this->em->getRepository(FiscalYear::class)->findOneBy(['status' => true]);
            if (!$year || !$year->getStatus()) {
                throw new ConflictHttpException('Transaksi harus menggunakan tahun anggaran aktif.');
            }

            $date = $this->date($this->text($input, 'date'));
            if ((int) $date->format('Y') !== $year->getYear()) {
                throw new BadRequestHttpException('Tanggal harus berada dalam tahun anggaran aktif.');
            }

            $own = $this->access->organization();
            $fromId = $this->text($input, 'from_organization_id');
            $toId = $this->text($input, 'to_organization_id');
            if ($own) {
                $ownerId = Type::RECEIPT === $type ? $toId : $fromId;
                if ('' !== $ownerId && $ownerId !== $own->getId()) {
                    throw new AccessDeniedHttpException('SKPD/OPD harus sesuai dengan pengguna login.');
                }

                if (Type::RECEIPT === $type) {
                    $toId = $own->getId();
                } else {
                    $fromId = $own->getId();
                }
            }

            $from = Type::RECEIPT === $type ? null : $this->reference(Organization::class, $fromId);
            $to = Type::DISPOSAL === $type ? null : $this->reference(Organization::class, $toId);
            if ((Type::RECEIPT === $type && '' !== $fromId) || (Type::DISPOSAL === $type && '' !== $toId)) {
                throw new BadRequestHttpException('SKPD/OPD asal atau tujuan tidak sesuai jenis transaksi.');
            }

            if ($from && $to && $from->getId() === $to->getId()) {
                throw new BadRequestHttpException('SKPD/OPD asal dan tujuan harus berbeda.');
            }

            if ($id && $transaction->getFromOrganization()?->getId() !== $from?->getId() && $this->lines($transaction)) {
                throw new ConflictHttpException('Hapus detail barang sebelum mengubah SKPD/OPD asal.');
            }

            $notes = $this->text($input, 'notes');
            if (Type::DISPOSAL === $type && '' === $notes) {
                throw new BadRequestHttpException('Catatan wajib diisi untuk pengeluaran akhir.');
            }

            $reference = $this->text($input, 'reference_number');
            $referenceLabel = Type::RECEIPT === $type ? 'No Faktur' : 'No SK';
            if ('' === $reference) {
                throw new BadRequestHttpException($referenceLabel.' wajib diisi.');
            }

            if (mb_strlen($reference) > 255 || mb_strlen($notes) > 10000) {
                throw new BadRequestHttpException($referenceLabel.' maksimal 255 karakter dan catatan maksimal 10000 karakter.');
            }

            $sourceId = $this->text($input, 'source_id');
            if (Type::RECEIPT !== $type && '' !== $sourceId) {
                throw new BadRequestHttpException('Sumber barang hanya untuk penerimaan eksternal.');
            }

            $transaction->setType($type);
            $transaction->setDate($date);
            $transaction->setFiscalYear($year);
            $transaction->setFromOrganization($from);
            $transaction->setToOrganization($to);
            $transaction->setSource(Type::RECEIPT === $type ? $this->reference(Source::class, $sourceId) : null);
            $transaction->setReferenceNumber($reference);
            $transaction->setNotes($notes ?: null);
            if (!$id) {
                $owner = Type::RECEIPT === $type ? $to : $from;
                $transaction->setCode($this->generateCode($year, $owner, $date));
                $transaction->setCreatedBy($this->access->account());
                $transaction->setCreatedAt(new DateTimeImmutable());
                $this->em->persist($transaction);
            }

            if ($id) {
                $this->validateStock($transaction);
            }

            return $transaction;
        });
    }

    public function submitWithLines(array $header, array $lines): Transaction
    {
        $this->access->requirePermission('add');
        if ($this->access->isApprover() && !$this->access->isAdmin()) {
            throw new AccessDeniedHttpException('Approver hanya dapat memeriksa pengajuan transaksi.');
        }

        if (!$lines || count($lines) > 100) {
            throw new BadRequestHttpException('Transaksi wajib berisi 1 sampai 100 detail barang.');
        }

        return $this->locked(function () use ($header, $lines): Transaction {
            $transaction = $this->saveHeader($header);
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    throw new BadRequestHttpException('Format detail barang tidak valid.');
                }

                $this->addLine($transaction->getId(), $line);
            }

            $this->transition($transaction->getId(), 'submit');

            return $transaction;
        });
    }

    public function revise(string $id, array $input): Transaction
    {
        $this->access->requirePermission('edit');

        return $this->locked(function () use ($id, $input): Transaction {
            $transaction = $this->get($id);
            $this->em->refresh($transaction);
            $this->editableOwner($transaction);
            if (Status::RETURNED !== $transaction->getStatus()) {
                throw new ConflictHttpException('Revisi hanya tersedia untuk transaksi Perlu Revisi.');
            }

            $lines = $input['items'] ?? [];
            unset($input['items']);
            if (!is_array($lines) || count($lines) > 100) {
                throw new BadRequestHttpException('Detail barang tidak valid atau melebihi 100 baris.');
            }

            $existing = [];
            foreach ($this->lines($transaction) as $line) {
                $existing[$line->getId()] = $line;
            }

            $retained = [];
            $added = [];
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    throw new BadRequestHttpException('Format detail barang tidak valid.');
                }

                $lineId = $line['line_id'] ?? null;
                unset($line['line_id']);
                $this->keys($line, ['item_id', 'unit_id', 'qty', 'price', 'batch_number', 'expiry_date', 'stock_lot_id']);
                if (null !== $lineId) {
                    if (!is_string($lineId) || !isset($existing[$lineId]) || isset($retained[$lineId])) {
                        throw new BadRequestHttpException('Referensi detail barang tidak valid.');
                    }

                    $old = $existing[$lineId];
                    $original = [
                        'item_id' => $old->getItem()->getId(), 'unit_id' => $old->getUnit()->getId(),
                        'qty' => $old->getQty(), 'price' => $old->getPrice(), 'batch_number' => $old->getBatchNumber(),
                        'expiry_date' => $old->getExpiryDate()?->format('Y-m-d'),
                        'stock_lot_id' => $old->getSelectedLot()?->getId(),
                    ];
                    foreach ($original as $key => $value) {
                        if ($this->text($line, $key) !== trim((string) $value)) {
                            throw new BadRequestHttpException('Hapus dan tambahkan kembali baris yang perlu diubah.');
                        }
                    }

                    $retained[$lineId] = true;
                } else {
                    $added[] = $line;
                }
            }

            foreach ($existing as $lineId => $line) {
                if (!isset($retained[$lineId])) {
                    $this->em->remove($line);
                }
            }

            $this->em->flush();
            $input['type'] = (string) $transaction->getType()->value;
            $this->saveHeader($input, $id);
            foreach ($added as $line) {
                $this->addLine($id, $line);
            }

            $this->transition($id, 'submit');

            return $transaction;
        });
    }

    public function addLine(string $id, array $input): void
    {
        $this->access->requirePermission('edit');
        $this->locked(function () use ($id, $input): void {
            $this->keys($input, ['item_id', 'unit_id', 'qty', 'price', 'batch_number', 'expiry_date', 'stock_lot_id']);
            $transaction = $this->get($id);
            $this->em->refresh($transaction);
            $this->editableOwner($transaction);
            $item = $this->reference(Item::class, $this->text($input, 'item_id'));
            $unit = $this->reference(Unit::class, $this->text($input, 'unit_id'));
            if (!$item->getUnit() || !in_array($item->getUnit()->getType(), Unit::TYPES, true)) {
                throw new BadRequestHttpException('Lengkapi satuan dasar dan jenis satuan barang terlebih dahulu.');
            }

            $price = $this->text($input, 'price');
            if (Type::RECEIPT === $transaction->getType() && '' === $price) {
                throw new BadRequestHttpException('Harga satuan wajib diisi untuk penerimaan.');
            }

            if ('' !== $price && (Type::RECEIPT !== $transaction->getType() || !preg_match('/^\d{1,18}(\.\d{1,2})?$/D', $price))) {
                throw new BadRequestHttpException('Harga satuan hanya untuk penerimaan, tidak boleh negatif, maksimal 18 digit dan 2 angka desimal.');
            }

            $qty = $this->decimal($this->text($input, 'qty'));
            $factor = '1';
            if ($unit->getId() !== $item->getUnit()->getId()) {
                $conversion = $this->em->getRepository(ItemUnitConversion::class)->findOneBy(['item' => $item, 'unit' => $unit]);
                if (!$conversion || ('package' !== $unit->getType() && $unit->getType() !== $item->getUnit()->getType())) {
                    throw new BadRequestHttpException('Satuan konversi tidak terdaftar atau jenis satuannya tidak sesuai.');
                }

                $factor = $conversion->getFactor();
            }

            try {
                $baseQty = (string) BigDecimal::of($qty)->multipliedBy($factor)->toScale(6);
                $this->decimal($baseQty);
            } catch (MathException) {
                throw new BadRequestHttpException('Hasil konversi melebihi ketelitian 6 desimal.');
            }

            $line = new TransactionItem();
            $line->setTransaction($transaction);
            $line->setItem($item);
            $line->setUnit($unit);
            $line->setQty($qty);
            $line->setPrice('' === $price ? null : (string) BigDecimal::of($price)->toScale(2));
            $line->setConversionFactor($factor);
            $line->setBaseUnit($item->getUnit());
            $line->setBaseQty($baseQty);
            $expiry = $this->text($input, 'expiry_date');
            $batch = $this->text($input, 'batch_number');
            $lotId = $this->text($input, 'stock_lot_id');
            if (Type::RECEIPT === $transaction->getType()) {
                if ('' !== $lotId) {
                    throw new BadRequestHttpException('Penerimaan membuat batch baru.');
                }

                if (mb_strlen($batch) > 100) {
                    throw new BadRequestHttpException('Nomor batch maksimal 100 karakter.');
                }

                if ($item->getStatus() && '' === $expiry) {
                    throw new BadRequestHttpException('Tanggal kedaluwarsa wajib untuk barang ini.');
                }

                if (!$item->getStatus() && '' !== $expiry) {
                    throw new BadRequestHttpException('Barang ini tidak memiliki tanggal kedaluwarsa.');
                }

                $line->setExpiryDate('' === $expiry ? null : $this->date($expiry));
                if ($line->getExpiryDate() && $line->getExpiryDate() < $transaction->getDate()) {
                    throw new BadRequestHttpException('Tanggal kedaluwarsa tidak boleh sebelum penerimaan.');
                }

                $line->setBatchNumber($batch ?: null);
            } else {
                if ('' !== $expiry || '' !== $batch) {
                    throw new BadRequestHttpException('Batch dan kedaluwarsa mengikuti stok yang sudah diterima.');
                }

                if ('' !== $lotId) {
                    $lot = $this->reference(StockLot::class, $lotId);
                    if ($lot->getItem()->getId() !== $item->getId() || !$this->availableLots($transaction, $item, $lotId)) {
                        throw new BadRequestHttpException('Batch tidak tersedia untuk barang dan SKPD/OPD asal ini.');
                    }

                    $line->setSelectedLot($lot);
                }
            }

            $this->validateStock($transaction, $line);
            $this->em->persist($line);
        });
    }

    public function removeLine(string $id, string $lineId): void
    {
        $this->access->requirePermission('edit');
        $this->locked(function () use ($id, $lineId): void {
            $transaction = $this->get($id);
            $this->em->refresh($transaction);
            $this->editableOwner($transaction);
            $line = $this->reference(TransactionItem::class, $lineId);
            if ($line->getTransaction()->getId() !== $id) {
                throw new NotFoundHttpException();
            }

            $this->em->remove($line);
        });
    }

    public function availableActions(Transaction $transaction): array
    {
        $actions = match ($transaction->getStatus()) {
            Status::RETURNED => ['submit', 'cancel'],
            Status::SUBMITTED => [Type::RECEIPT === $transaction->getType() ? 'verify' : 'approve', 'return', 'cancel'],
            Status::APPROVED => Type::TRANSFER === $transaction->getType() ? ['send', 'cancel'] : ['approve', 'return', 'cancel'],
            Status::SENT => ['receive'],
            default => [],
        };

        return array_values(array_filter($actions, fn (string $action): bool => $this->canPerform($transaction, $action)));
    }

    private function canPerform(Transaction $transaction, string $action): bool
    {
        if ($this->access->isOperator() && !$this->access->isAdmin() && !in_array($action, ['submit', 'send', 'receive'], true)) {
            return false;
        }

        if ('cancel' === $action && $this->access->isApprover() && !$this->access->isAdmin()) {
            return false;
        }

        $review = in_array($action, ['verify', 'approve', 'return'], true)
            || ('cancel' === $action && Status::APPROVED === $transaction->getStatus());
        if (!$this->access->hasPermission('cancel' === $action && !$review ? 'delete' : 'edit')) {
            return false;
        }

        if ($review) {
            return $this->access->canReview($transaction);
        }

        if ('cancel' === $action && !$this->access->isAdmin() && $transaction->getCreatedBy()->getId() !== $this->access->account()->getId()) {
            return false;
        }

        return $this->access->canOperate($transaction, 'receive' === $action);
    }

    public function transition(string $id, string $action, ?string $note = null): void
    {
        $this->access->requirePermission('view');
        $this->locked(function () use ($id, $action, $note): void {
            $transaction = $this->get($id);
            $this->em->refresh($transaction);
            if (!$this->canPerform($transaction, $action)) {
                throw new AccessDeniedHttpException('Anda tidak berwenang melakukan tindakan ini.');
            }

            if (!in_array($action, $this->availableActions($transaction), true)) {
                throw new ConflictHttpException('Perpindahan status tidak diperbolehkan.');
            }

            $note = trim((string) $note);
            if (mb_strlen($note) > 10000 || (in_array($action, ['return', 'cancel'], true) && '' === $note)) {
                throw new BadRequestHttpException('Alasan pengembalian atau pembatalan wajib diisi, maksimal 10000 karakter.');
            }

            if (in_array($action, ['cancel', 'return'], true)) {
                if ('cancel' === $action && Type::TRANSFER === $transaction->getType() && Status::APPROVED === $transaction->getStatus()) {
                    $this->moveReservedStock($transaction, 'AVAILABLE', 'release');
                }

                $transaction->setStatus('cancel' === $action ? Status::CANCELLED : Status::RETURNED);
                if ('cancel' === $action) {
                    $transaction->setCancelledAt(new DateTimeImmutable());
                    $transaction->setCancelledBy($this->access->account());
                }

                $transaction->recordTransition($action, $this->access->account(), $note);

                return;
            }

            if ('receive' === $action) {
                foreach ($this->lines($transaction) as $line) {
                    foreach ($this->em->getRepository(TransactionItemAllocation::class)->findBy(['transactionItem' => $line]) as $allocation) {
                        $this->movement($allocation, $transaction->getFromOrganization(), 'IN_TRANSIT', 'receive', '-'.$allocation->getBaseQty());
                        $this->movement($allocation, $transaction->getToOrganization(), 'AVAILABLE', 'receive', $allocation->getBaseQty());
                    }
                }

                $transaction->setStatus(Status::COMPLETED);
                $transaction->setReceivedBy($this->access->account());
                $transaction->setReceivedAt(new DateTimeImmutable());
                $transaction->recordTransition($action, $this->access->account());

                return;
            }

            $this->em->refresh($transaction->getFiscalYear());
            if ('' === trim((string) $transaction->getReferenceNumber())) {
                throw new BadRequestHttpException((Type::RECEIPT === $transaction->getType() ? 'No Faktur' : 'No SK').' wajib diisi.');
            }

            if (!$transaction->getFiscalYear()->getStatus()) {
                throw new ConflictHttpException('Tahun anggaran transaksi tidak aktif.');
            }

            if (Type::DISPOSAL === $transaction->getType() && '' === trim((string) $transaction->getNotes())) {
                throw new BadRequestHttpException('Catatan pengeluaran wajib diisi.');
            }

            foreach ([$transaction->getFromOrganization(), $transaction->getToOrganization(), $transaction->getSource()] as $reference) {
                if ($reference && null !== $reference->getDeletedAt()) {
                    throw new ConflictHttpException('SKPD/OPD atau sumber barang sudah dihapus.');
                }
            }

            $lines = $this->lines($transaction);
            if (!$lines) {
                throw new BadRequestHttpException('Tambahkan minimal satu barang sebelum memproses transaksi.');
            }

            if ('submit' === $action || ('approve' === $action && Type::TRANSFER === $transaction->getType())) {
                $this->validateStock($transaction);
                if ('approve' === $action) {
                    $this->reserveTransfer($transaction);
                }

                $transaction->setStatus('submit' === $action ? Status::SUBMITTED : Status::APPROVED);
                $transaction->recordTransition($action, $this->access->account());

                return;
            }

            if ('send' === $action) {
                $this->moveReservedStock($transaction, 'IN_TRANSIT', 'send');
                $transaction->setStatus(Status::SENT);
                $transaction->setPostedBy($this->access->account());
                $transaction->setPostedAt(new DateTimeImmutable());
                $transaction->recordTransition($action, $this->access->account());

                return;
            }

            usort($lines, static fn ($a, $b) => (int) (null !== $b->getSelectedLot()) <=> (int) (null !== $a->getSelectedLot()));
            foreach ($lines as $line) {
                if (null !== $line->getItem()->getDeletedAt() || null !== $line->getBaseUnit()->getDeletedAt()) {
                    throw new ConflictHttpException('Barang atau satuan sudah dihapus.');
                }

                if (Type::RECEIPT === $transaction->getType()) {
                    if (($line->getItem()->getStatus() && !$line->getExpiryDate()) || ($line->getExpiryDate() && $line->getExpiryDate() < $transaction->getDate())) {
                        throw new ConflictHttpException('Periksa tanggal kedaluwarsa detail barang sebelum menyelesaikan penerimaan.');
                    }

                    $lot = new StockLot();
                    $lot->setItem($line->getItem());
                    $lot->setReceiptItem($line);
                    $batch = trim((string) $line->getBatchNumber());
                    if ('' === $batch) {
                        $batch = $this->generateBatchNumber($transaction->getDate());
                    }

                    $line->setBatchNumber($batch);
                    $lot->setBatchNumber($batch);
                    $lot->setExpiryDate($line->getExpiryDate());
                    $this->em->persist($lot);
                    $allocation = $this->allocation($line, $lot, $line->getBaseQty());
                    $this->movement($allocation, $transaction->getToOrganization(), 'AVAILABLE', 'post', $line->getBaseQty());
                } else {
                    $remaining = BigDecimal::of($line->getBaseQty());
                    foreach ($this->availableLots($transaction, $line->getItem(), $line->getSelectedLot()?->getId()) as $stock) {
                        $amount = $remaining->isLessThan($stock['qty']) ? $remaining : BigDecimal::of($stock['qty']);
                        $allocation = $this->allocation($line, $this->reference(StockLot::class, $stock['id']), (string) $amount);
                        $this->movement($allocation, $transaction->getFromOrganization(), 'AVAILABLE', 'post', (string) $amount->negated());
                        $remaining = $remaining->minus($amount);
                        if ($remaining->isZero()) {
                            break;
                        }
                    }

                    if (!$remaining->isZero()) {
                        throw new ConflictHttpException('Stok tersedia tidak mencukupi, atau batch sudah kedaluwarsa untuk transfer.');
                    }
                }

                $this->em->flush();
            }

            $transaction->setStatus(Status::COMPLETED);
            $transaction->setPostedBy($this->access->account());
            $transaction->setPostedAt(new DateTimeImmutable());
            $transaction->recordTransition($action, $this->access->account());
        });
    }

    public function reserveApprovedTransfers(): int
    {
        return $this->locked(function (): int {
            $count = 0;
            $transactions = $this->em->getRepository(Transaction::class)->findBy(['type' => Type::TRANSFER, 'status' => Status::APPROVED], ['createdAt' => 'ASC', 'id' => 'ASC']);
            foreach ($transactions as $transaction) {
                $reserved = $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM stock_movements m JOIN transaction_item_allocations a ON a.id=m.allocation_id JOIN transaction_items i ON i.id=a.transaction_item_id WHERE i.transaction_id=? AND m.phase='reserve'", [$transaction->getId()]);
                if ($reserved) {
                    continue;
                }

                $this->reserveTransfer($transaction);
                ++$count;
            }

            return $count;
        });
    }

    private function reserveTransfer(Transaction $transaction): void
    {
        $lines = $this->lines($transaction);
        if (!$lines) {
            throw new ConflictHttpException('Transfer tidak memiliki detail barang untuk dicadangkan.');
        }

        usort($lines, static fn ($a, $b) => (int) (null !== $b->getSelectedLot()) <=> (int) (null !== $a->getSelectedLot()));
        foreach ($lines as $line) {
            if (null !== $line->getItem()->getDeletedAt() || null !== $line->getBaseUnit()->getDeletedAt()) {
                throw new ConflictHttpException('Barang atau satuan sudah dihapus.');
            }

            if ($this->em->getRepository(TransactionItemAllocation::class)->findOneBy(['transactionItem' => $line])) {
                throw new ConflictHttpException('Transfer sudah memiliki alokasi batch.');
            }

            $remaining = BigDecimal::of($line->getBaseQty());
            foreach ($this->availableLots($transaction, $line->getItem(), $line->getSelectedLot()?->getId()) as $stock) {
                $amount = $remaining->isLessThan($stock['qty']) ? $remaining : BigDecimal::of($stock['qty']);
                $allocation = $this->allocation($line, $this->reference(StockLot::class, $stock['id']), (string) $amount);
                $this->movement($allocation, $transaction->getFromOrganization(), 'AVAILABLE', 'reserve', (string) $amount->negated());
                $this->movement($allocation, $transaction->getFromOrganization(), 'RESERVED', 'reserve', (string) $amount);
                $remaining = $remaining->minus($amount);
                if ($remaining->isZero()) {
                    break;
                }
            }

            if (!$remaining->isZero()) {
                throw new ConflictHttpException('Stok tersedia tidak mencukupi untuk mencadangkan transfer.');
            }

            $this->em->flush();
        }
    }

    private function moveReservedStock(Transaction $transaction, string $destination, string $phase): void
    {
        foreach ($this->lines($transaction) as $line) {
            $total = BigDecimal::of(0);
            foreach ($this->em->getRepository(TransactionItemAllocation::class)->findBy(['transactionItem' => $line]) as $allocation) {
                $reserved = $this->em->getConnection()->fetchOne("SELECT COALESCE(SUM(quantity_delta),0) FROM stock_movements WHERE allocation_id=? AND organization_id=? AND location='RESERVED'", [$allocation->getId(), $transaction->getFromOrganization()->getId()]);
                if (!BigDecimal::of($reserved)->isEqualTo($allocation->getBaseQty())) {
                    throw new ConflictHttpException('Cadangan stok transfer tidak lengkap.');
                }

                if ('send' === $phase) {
                    $expiry = $allocation->getStockLot()->getExpiryDate();
                    $cutoff = max(new DateTimeImmutable('today'), $transaction->getDate());
                    if ($expiry && $expiry < $cutoff) {
                        throw new ConflictHttpException('Batch yang dicadangkan sudah kedaluwarsa. Batalkan transfer untuk melepas cadangan.');
                    }

                    if (null !== $line->getItem()->getDeletedAt() || null !== $line->getBaseUnit()->getDeletedAt()) {
                        throw new ConflictHttpException('Barang atau satuan sudah dihapus.');
                    }
                }

                $this->movement($allocation, $transaction->getFromOrganization(), 'RESERVED', $phase, '-'.$allocation->getBaseQty());
                $this->movement($allocation, $transaction->getFromOrganization(), $destination, $phase, $allocation->getBaseQty());
                $total = $total->plus($allocation->getBaseQty());
            }

            if (!$total->isEqualTo($line->getBaseQty())) {
                throw new ConflictHttpException('Alokasi cadangan transfer tidak sesuai jumlah barang.');
            }
        }
    }

    private function allocation(TransactionItem $line, StockLot $lot, string $qty): TransactionItemAllocation
    {
        $allocation = new TransactionItemAllocation();
        $allocation->setTransactionItem($line);
        $allocation->setStockLot($lot);
        $allocation->setBaseQty($qty);
        $this->em->persist($allocation);

        return $allocation;
    }

    private function movement(TransactionItemAllocation $allocation, Organization $organization, string $location, string $phase, string $qty): void
    {
        $movement = new StockMovement();
        $movement->setAllocation($allocation);
        $movement->setOrganization($organization);
        $movement->setStockLot($allocation->getStockLot());
        $movement->setLocation($location);
        $movement->setPhase($phase);
        $movement->setQuantityDelta($qty);
        $movement->setPostedAt(new DateTimeImmutable());
        $this->em->persist($movement);
    }

    private function validateStock(Transaction $transaction, ?TransactionItem $extra = null): void
    {
        if (Type::RECEIPT === $transaction->getType()) {
            return;
        }

        $lines = $this->lines($transaction);
        if ($extra) {
            $lines[] = $extra;
        }

        usort($lines, static fn ($a, $b) => (int) (null !== $b->getSelectedLot()) <=> (int) (null !== $a->getSelectedLot()));
        $balances = [];
        foreach ($lines as $line) {
            $itemId = $line->getItem()->getId();
            if (!isset($balances[$itemId])) {
                $balances[$itemId] = [];
                foreach ($this->availableLots($transaction, $line->getItem()) as $stock) {
                    $balances[$itemId][$stock['id']] = BigDecimal::of($stock['qty']);
                }
            }

            $remaining = BigDecimal::of($line->getBaseQty());
            foreach ($balances[$itemId] as $lotId => $balance) {
                if ($line->getSelectedLot() && $line->getSelectedLot()->getId() !== $lotId) {
                    continue;
                }

                $amount = $remaining->isLessThan($balance) ? $remaining : $balance;
                $balances[$itemId][$lotId] = $balance->minus($amount);
                $remaining = $remaining->minus($amount);
                if ($remaining->isZero()) {
                    break;
                }
            }

            if (!$remaining->isZero()) {
                throw new ConflictHttpException('Jumlah barang melebihi stok tersedia pada SKPD/OPD asal atau batch yang dipilih.');
            }
        }
    }

    private function availableLots(Transaction $transaction, Item $item, ?string $lotId = null): array
    {
        $sql = "SELECT l.id, SUM(m.quantity_delta) AS qty FROM stock_movements m JOIN stock_lots l ON l.id=m.stock_lot_id WHERE m.organization_id=:org AND l.item_id=:item AND m.location='AVAILABLE'";
        $params = ['org' => $transaction->getFromOrganization()->getId(), 'item' => $item->getId()];
        if ($lotId) {
            $sql .= ' AND l.id=:lot';
            $params['lot'] = $lotId;
        }

        if (Type::TRANSFER === $transaction->getType()) {
            $sql .= ' AND (l.expiry_date IS NULL OR l.expiry_date >= :today)';
            $params['today'] = max((new DateTimeImmutable('today'))->format('Y-m-d'), $transaction->getDate()->format('Y-m-d'));
        }

        $sql .= ' GROUP BY l.id, l.expiry_date HAVING SUM(m.quantity_delta)>0 ORDER BY l.expiry_date ASC NULLS LAST, l.id';

        return $this->em->getConnection()->fetchAllAssociative($sql, $params);
    }

    public function stocks(?string $organizationId = null, string $search = ''): array
    {
        $this->access->requirePermission('view');
        $org = $this->access->organization();
        if ($org && $organizationId && $organizationId !== $org->getId()) {
            throw new AccessDeniedHttpException();
        }

        $organizationId = $org?->getId() ?? $organizationId;
        if ($organizationId) {
            $this->reference(Organization::class, $organizationId);
        }

        $sql = 'SELECT o.id AS organization_id, o.name AS organization, i.id AS item_id, i.name AS item, i.code AS item_code, u.id AS unit_id, u.name AS unit, l.id AS stock_lot_id, l.batch_number, l.expiry_date, m.location, SUM(m.quantity_delta) AS qty FROM stock_movements m JOIN stock_lots l ON l.id=m.stock_lot_id JOIN items i ON i.id=l.item_id JOIN transaction_items ti ON ti.id=l.receipt_item_id JOIN units u ON u.id=ti.base_unit_id JOIN organizations o ON o.id=m.organization_id';
        $params = [];
        $conditions = [];
        if ($organizationId) {
            $conditions[] = 'o.id=:org';
            $params['org'] = $organizationId;
        }

        if ('' !== trim($search)) {
            $conditions[] = '(STRPOS(LOWER(i.name), LOWER(:search)) > 0 OR STRPOS(LOWER(i.code), LOWER(:search)) > 0)';
            $params['search'] = trim($search);
        }

        if ($conditions) {
            $sql .= ' WHERE '.implode(' AND ', $conditions);
        }

        return $this->em->getConnection()->fetchAllAssociative($sql.' GROUP BY o.id, i.id, u.id, l.id, m.location HAVING SUM(m.quantity_delta)<>0 ORDER BY i.name, i.id, o.name, l.expiry_date NULLS LAST, l.id, m.location', $params);
    }

    public function stockSummary(?string $organizationId = null, string $search = ''): array
    {
        $summary = [];
        foreach ($this->stocks($organizationId, $search) as $stock) {
            $key = $stock['item_id'].'_'.$stock['unit_id'];
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'item_id' => $stock['item_id'], 'item_code' => $stock['item_code'],
                    'item' => $stock['item'], 'unit_id' => $stock['unit_id'], 'unit' => $stock['unit'],
                    'available' => '0.000000', 'reserved' => '0.000000', 'in_transit' => '0.000000', 'total' => '0.000000', 'batches' => [],
                ];
            }

            $column = match ($stock['location']) {
                'AVAILABLE' => 'available',
                'RESERVED' => 'reserved',
                'IN_TRANSIT' => 'in_transit',
            };
            $summary[$key][$column] = (string) BigDecimal::of($summary[$key][$column])->plus($stock['qty']);
            $summary[$key]['total'] = (string) BigDecimal::of($summary[$key]['total'])->plus($stock['qty']);
            $summary[$key]['batches'][] = $stock;
        }

        return array_values($summary);
    }

    public function stockOrganizations(): array
    {
        $this->access->requirePermission('view');
        $own = $this->access->organization();

        return $own ? [$own] : $this->em->getRepository(Organization::class)->findBy([], ['name' => 'ASC']);
    }

    public function choices(): array
    {
        $this->access->requirePermission('view');

        return [
            'organizations' => $this->em->getRepository(Organization::class)->findBy([], ['name' => 'ASC']),
            'sources' => $this->em->getRepository(Source::class)->findBy([], ['name' => 'ASC']),
            'items' => $this->em->getRepository(Item::class)->findBy([], ['name' => 'ASC']),
            'units' => $this->em->getRepository(Unit::class)->findBy([], ['name' => 'ASC']),
            'conversions' => $this->em->getRepository(ItemUnitConversion::class)->findAll(),
            'own_organization' => $this->access->organization(),
            'active_year' => $this->em->getRepository(FiscalYear::class)->findOneBy(['status' => true]),
        ];
    }

    private function generateBatchNumber(DateTimeImmutable $date): string
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $number = 'BATCH'.$date->format('Ymd').random_int(10000, 99999);
            if (!$this->em->getRepository(StockLot::class)->findOneBy(['batchNumber' => $number])) {
                return $number;
            }
        }

        throw new ConflictHttpException('Nomor batch unik belum tersedia. Silakan coba kembali.');
    }

    private function generateCode(FiscalYear $year, Organization $organization, DateTimeImmutable $date): string
    {
        $prefix = 'TRX'.$year->getYear().$organization->getCode().$date->format('Ymd');
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $code = $prefix.random_int(10000, 99999);
            if (!$this->em->getRepository(Transaction::class)->findOneBy(['code' => $code])) {
                return $code;
            }
        }

        throw new ConflictHttpException('Nomor transaksi unik belum tersedia. Silakan coba kembali.');
    }

    private function editableOwner(Transaction $transaction): void
    {
        if (Status::RETURNED !== $transaction->getStatus()) {
            throw new ConflictHttpException('Hanya transaksi yang perlu revisi dapat diubah.');
        }

        if (!$this->access->canOperate($transaction)) {
            throw new AccessDeniedHttpException();
        }
    }

    private function reference(string $class, string $id): object
    {
        if (!Uuid::isValid($id) || !$entity = $this->em->find($class, $id)) {
            throw new BadRequestHttpException('Referensi data tidak ditemukan.');
        }

        if (method_exists($entity, 'getDeletedAt') && null !== $entity->getDeletedAt()) {
            throw new BadRequestHttpException('Referensi data sudah dihapus.');
        }

        return $entity;
    }

    private function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) && !is_int($value)) {
            throw new BadRequestHttpException('Format '.$key.' tidak valid.');
        }

        return trim((string) $value);
    }

    private function keys(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw new BadRequestHttpException('Field transaksi tidak dikenal atau tidak boleh diubah langsung.');
        }
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException('Tanggal tidak valid. Gunakan YYYY-MM-DD.');
        }

        return $date;
    }

    private function decimal(string $value): string
    {
        if (!preg_match('/^\d{1,18}(\.\d{1,6})?$/D', $value) || !BigDecimal::of($value)->isPositive()) {
            throw new BadRequestHttpException('Jumlah harus positif, maksimal 18 digit dan 6 angka desimal.');
        }

        return (string) BigDecimal::of($value)->toScale(6);
    }

    private function locked(callable $operation): mixed
    {
        $db = $this->em->getConnection();
        $db->beginTransaction();
        try {
            $db->executeQuery('SELECT pg_advisory_xact_lock(74261015)');
            $db->executeQuery('SELECT pg_advisory_xact_lock(74261014)');
            $result = $operation();
            $this->em->flush();
            $db->commit();

            return $result;
        } catch (Throwable $error) {
            $db->rollBack();
            $this->em->clear();
            throw $error;
        }
    }
}
