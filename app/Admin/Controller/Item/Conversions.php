<?php

declare(strict_types=1);

namespace KejawenLab\Application\Admin\Controller\Item;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use KejawenLab\Application\Entity\{Item, ItemUnitConversion};
use KejawenLab\Application\Form\ItemUnitConversionType;
use KejawenLab\ApiSkeleton\Security\Annotation\Permission;
use KejawenLab\ApiSkeleton\Security\Service\MenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[Permission(menu: 'ITEM', actions: [Permission::VIEW])]
final class Conversions extends AbstractController
{
    #[Route(path: '/items/{id}/conversions', name: self::class, methods: ['GET', 'POST'], priority: 1)]
    public function __invoke(Request $request, string $id, EntityManagerInterface $em, MenuService $menus): Response
    {
        $item = $em->find(Item::class, $id);
        if (!$item) {
            throw $this->createNotFoundException();
        }
        $conversions = $em->getRepository(ItemUnitConversion::class)->findBy(['item' => $item]);
        $conversion = new ItemUnitConversion();
        $conversion->setItem($item);
        $form = $this->createForm(ItemUnitConversionType::class, $conversion);
        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted('edit', $menus->getMenuByCode('ITEM'));
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $em->persist($conversion);
                    $em->flush();
                    return $this->redirectToRoute(self::class, ['id' => $id], Response::HTTP_SEE_OTHER);
                } catch (UniqueConstraintViolationException) {
                    $form->addError(new FormError('Konversi satuan ini sudah terdaftar.'));
                }
            }
        }
        return $this->render('item/conversions.html.twig', [
            'page_title' => 'sas.page.item_conversion.title',
            'data' => $item,
            'conversions' => $conversions,
            'form' => $form->createView(),
        ]);
    }
}
