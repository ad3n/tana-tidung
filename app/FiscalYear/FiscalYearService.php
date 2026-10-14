<?php

declare(strict_types=1);

namespace KejawenLab\Application\FiscalYear;

use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\Application\Entity\FiscalYear;
use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;
use KejawenLab\Application\FiscalYear\Model\FiscalYearRepositoryInterface;
use KejawenLab\ApiSkeleton\Pagination\AliasHelper;
use KejawenLab\ApiSkeleton\Service\AbstractService;
use KejawenLab\ApiSkeleton\Service\Model\ServiceInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class FiscalYearService extends AbstractService implements ServiceInterface
{
    public function __construct(MessageBusInterface $messageBus, FiscalYearRepositoryInterface $repository, AliasHelper $aliasHelper, private readonly EntityManagerInterface $em)
    {
        parent::__construct($messageBus, $repository, $aliasHelper);
    }

    public function save(EntityInterface $object): void
    {
        $this->withActiveYearLock(function () use ($object): void {
            if ($object->getId() !== null) {
                $year = $object->getYear();
                $status = $object->getStatus();
                $this->em->refresh($object);
                if ($object->getDeletedAt() !== null) {
                    throw new ConflictHttpException('Tahun anggaran sudah dihapus.');
                }
                $object->setYear($year);
                $object->setStatus($status);
            }
            $active = $this->em->getConnection()->fetchFirstColumn('SELECT id FROM fiscal_years WHERE status = true AND deleted_at IS NULL');
            if (!$object->getStatus() && in_array($object->getId(), $active, true)) {
                throw new ConflictHttpException('Tahun anggaran aktif tidak dapat dinonaktifkan. Aktifkan tahun anggaran lain terlebih dahulu.');
            }
            if (!$active) {
                $object->setStatus(true);
            }
            if ($object->getStatus()) {
                foreach ($active as $id) {
                    if ($id !== $object->getId()) {
                        $previous = $this->em->find(FiscalYear::class, $id);
                        $this->em->refresh($previous);
                        $previous->setStatus(false);
                    }
                }
            }
            parent::save($object);
        });
    }

    public function remove(EntityInterface $object): void
    {
        $this->withActiveYearLock(function () use ($object): void {
            $active = $this->em->getConnection()->fetchOne('SELECT status FROM fiscal_years WHERE id = ? AND deleted_at IS NULL', [$object->getId()]);
            if ($active) {
                throw new ConflictHttpException('Tahun anggaran aktif tidak dapat dihapus. Aktifkan tahun anggaran lain terlebih dahulu.');
            }
            parent::remove($object);
        });
    }

    private function withActiveYearLock(callable $operation): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            // Serialize activation/deletion, including when no fiscal year exists yet.
            $connection->executeQuery('SELECT pg_advisory_xact_lock(74261014)');
            $operation();
            $connection->commit();
        } catch (Throwable $error) {
            $connection->rollBack();
            throw $error;
        }
    }
}
