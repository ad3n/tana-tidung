<?php

declare(strict_types=1);

namespace KejawenLab\Application\Form;

use KejawenLab\Application\Entity\FiscalYear;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class FiscalYearType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('year', IntegerType::class, [
            'required' => true,
            'label' => 'sas.form.field.fiscalyear.year',
            'attr' => ['min' => 1000, 'max' => 9999],
        ]);
        $builder->add('status', CheckboxType::class, [
            'required' => false,
            'false_values' => [null, false, '0', 0],
            'label' => 'sas.form.field.fiscalyear.status',
        ]);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $year = $event->getData()['year'] ?? null;
            if (!is_scalar($year) || !preg_match('/^[1-9][0-9]{3}$/D', (string) $year)) {
                $event->getForm()->get('year')->addError(new FormError('Tahun harus berupa empat digit angka.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FiscalYear::class,
            'translation_domain' => 'forms',
        ]);
    }
}
