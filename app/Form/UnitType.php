<?php

declare(strict_types=1);

namespace KejawenLab\Application\Form;

use KejawenLab\Application\Entity\Unit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class UnitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('type', ChoiceType::class, [
            'label' => 'Jenis Satuan',
            'empty_data' => '',
            'placeholder' => 'Pilih jenis satuan',
            'attr' => ['class' => 'select2'],
            'choices' => ['Massa' => 'mass', 'Volume' => 'volume', 'Hitungan' => 'count', 'Panjang' => 'length', 'Luas' => 'area', 'Kemasan' => 'package'],
        ]);
        $builder->add('code', null, [
            'required' => true,
            'label' => 'sas.form.field.unit.code',
        ]);
        $builder->add('name', null, [
            'required' => true,
            'label' => 'sas.form.field.unit.name',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Unit::class,
            'translation_domain' => 'forms',
        ]);
    }
}
