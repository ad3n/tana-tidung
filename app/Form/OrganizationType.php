<?php

declare(strict_types=1);

namespace KejawenLab\Application\Form;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\Application\Entity\Organization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class OrganizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('parent', null, [
            'required' => false,
            'label' => 'sas.form.field.organization.parent',
            'choice_label' => function ($parent) {
                if ($parent instanceof EntityInterface) {
                    return $parent->getNullOrString();
                }

                return (string) $parent;
            },
            'attr' => [
                'class' => 'select2',
            ],
            'placeholder' => 'sas.form.field.empty_select',
        ]);
        $builder->add('code', null, [
            'required' => true,
            'label' => 'sas.form.field.organization.code',
        ]);
        $builder->add('name', null, [
            'required' => true,
            'label' => 'sas.form.field.organization.name',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organization::class,
            'translation_domain' => 'forms',
        ]);
    }
}
