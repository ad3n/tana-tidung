<?php

declare(strict_types=1);

namespace KejawenLab\Application\Form;

use KejawenLab\ApiSkeleton\Entity\EntityInterface;
use KejawenLab\ApiSkeleton\Entity\Group;
use KejawenLab\ApiSkeleton\Security\Validator\PasswordLength;
use KejawenLab\Application\Employee\EmployeeChoices;
use KejawenLab\Application\Entity\Employee;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @author Muhamad Surya Iksanudin<surya.iksanudin@gmail.com>
 */
final class EmployeeType extends AbstractType
{
    public function __construct(private readonly EmployeeChoices $choices)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('organization', null, [
            'required' => true,
            'label' => 'sas.form.field.employee.organization',
            'choice_label' => function ($organization) {
                if ($organization instanceof EntityInterface) {
                    return $organization->getNullOrString();
                }

                return (string) $organization;
            },
            'attr' => [
                'class' => 'select2',
            ],
            'placeholder' => 'sas.form.field.empty_select',
        ]);
        $builder->add('level', null, [
            'required' => true,
            'label' => 'sas.form.field.employee.level',
            'choice_label' => function ($level) {
                if ($level instanceof EntityInterface) {
                    return $level->getNullOrString();
                }

                return (string) $level;
            },
            'attr' => [
                'class' => 'select2',
            ],
            'placeholder' => 'sas.form.field.empty_select',
        ]);
        $builder->add('code', null, [
            'required' => true,
            'label' => 'sas.form.field.employee.code',
        ]);
        $builder->add('name', null, [
            'required' => true,
            'label' => 'sas.form.field.employee.name',
        ]);
        $builder->add('group', EntityType::class, [
            'class' => Group::class,
            'property_path' => 'user.group',
            'required' => true,
            'constraints' => [new NotNull()],
            'query_builder' => $this->choices->groups(),
            'choice_label' => 'name',
            'label' => 'sas.form.field.employee.group',
            'placeholder' => 'sas.form.field.empty_select',
            'attr' => ['class' => 'select2'],
        ]);
        foreach (['username' => TextType::class, 'email' => EmailType::class] as $field => $type) {
            $builder->add($field, $type, [
                'property_path' => 'user.'.$field,
                'required' => true,
                'empty_data' => '',
                'label' => 'sas.form.field.employee.'.$field,
            ]);
        }

        $builder->add('plainPassword', PasswordType::class, ['mapped' => false]);
        $protectedUser = false;
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$protectedUser): void {
            $employee = $event->getData();
            $new = !$employee || !$employee->getId();
            $event->getForm()->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => $new,
                'constraints' => $new ? [new NotBlank(), new PasswordLength()] : [new PasswordLength()],
                'label' => 'sas.form.field.employee.plainPassword',
                'help' => $new ? null : 'sas.form.field.employee.password_help',
                'attr' => ['autocomplete' => 'new-password'],
            ]);
            $protectedUser = $employee?->getUser()?->getGroup() && !$this->choices->allowsGroup($employee->getUser()->getGroup()->getCode());
        });
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use (&$protectedUser): void {
            if ($protectedUser) {
                $event->getForm()->addError(new FormError('Akun superadmin tidak dapat diubah melalui Pegawai.'));
            }
        });
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $employee = $event->getData();
            $employee->getUser()->setFullName($employee->getName() ?? '');
            $password = $event->getForm()->get('plainPassword')->getData();
            if (is_string($password) && '' !== $password) {
                $event->getData()->getUser()->setPlainPassword($password);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employee::class,
            'translation_domain' => 'forms',
        ]);
    }
}
