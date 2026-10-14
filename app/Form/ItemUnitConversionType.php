<?php

declare(strict_types=1);

namespace KejawenLab\Application\Form;

use KejawenLab\Application\Entity\{Unit, ItemUnitConversion};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemUnitConversionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $base = $builder->getData()?->getItem()?->getUnit();
        $builder->add('unit', EntityType::class, [
            'class' => Unit::class,
            'choice_label' => 'name',
            'query_builder' => static function ($repository) use ($base) {
                $query = $repository->createQueryBuilder('u')->orderBy('u.name', 'ASC');
                if (!$base || !in_array($base->getType(), Unit::TYPES, true)) {
                    return $query->where('1 = 0');
                }
                return $query->where('u.id != :base')->andWhere('u.type IN (:types)')
                    ->setParameter('base', $base->getId())->setParameter('types', [$base->getType(), 'package']);
            },
            'label' => 'sas.form.field.item_conversion.unit',
            'placeholder' => 'sas.form.field.item_conversion.placeholder',
            'attr' => ['class' => 'select2'],
        ])->add('factor', TextType::class, [
            'label' => 'sas.form.field.item_conversion.factor',
            'empty_data' => '',
            'help' => 'sas.form.field.item_conversion.help',
        ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ItemUnitConversion::class, 'translation_domain' => 'forms']);
    }
}
