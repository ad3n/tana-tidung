<?php

declare(strict_types=1);

namespace KejawenLab\Application\Form;

use KejawenLab\Application\Entity\Source;
use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class SourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', null, [
            'required' => true,
            'label' => 'sas.form.field.source.name',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Source::class,
            'translation_domain' => 'forms',
        ]);
    }
}
