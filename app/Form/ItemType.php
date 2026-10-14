<?php

declare(strict_types=1);

namespace KejawenLab\Application\Form;

use KejawenLab\Application\Entity\Item;
use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class ItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('category', null, [
            'required' => true,
            'label' => 'sas.form.field.item.category',
            'choice_label' => function ($category) {
                if ($category instanceof EntityInterface) {
                    return $category->getNullOrString();
                }

                return (string) $category;
            },
            'attr' => [
                'class' => 'select2',
            ],
            'placeholder' => 'sas.form.field.empty_select',
        ]);
        $builder->add('code', null, [
            'required' => true,
            'label' => 'sas.form.field.item.code',
        ]);
        $builder->add('name', null, [
            'required' => true,
            'label' => 'sas.form.field.item.name',
        ]);
        $builder->add('unit', \Symfony\Bridge\Doctrine\Form\Type\EntityType::class, [
            'class' => \KejawenLab\Application\Entity\Unit::class,
            'choice_label' => 'name',
            'label' => 'Satuan Dasar',
            'placeholder' => 'Pilih satuan dasar',
            'required' => true,
            'attr' => ['class' => 'select2'],
        ]);
        $builder->add('status', CheckboxType::class, [
            'required' => false,
            'false_values' => [null, false, '0', 0],
            'label' => 'sas.form.field.item.status',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Item::class,
            'translation_domain' => 'forms',
        ]);
    }
}
