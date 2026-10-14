<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Item;

use Doctrine\ORM\EntityManagerInterface;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\Application\Entity\Item;
use KejawenLab\Application\Entity\ItemUnitConversion;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Permission(menu: 'ITEM', actions: [Permission::EDIT])]
final class DeleteConversion extends AbstractController
{
    #[Route(path: '/items/{id}/conversions/{conversionId}/delete', name: self::class, methods: ['POST'], priority: 1)]
    public function __invoke(string $id, string $conversionId, EntityManagerInterface $em): Response
    {
        $item = $em->find(Item::class, $id);
        $conversion = $em->getRepository(ItemUnitConversion::class)->findOneBy(['id' => $conversionId, 'item' => $item]);
        if (!$item || !$conversion) {
            throw $this->createNotFoundException();
        }

        $em->remove($conversion);
        $em->flush();

        return $this->redirectToRoute(Conversions::class, ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
