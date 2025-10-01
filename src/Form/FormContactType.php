<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\FormContactEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FormContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label'       => 'Name',
                'required'    => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Bitte geben Sie Ihren Namen an.'),
                    new Assert\Length(max: 120, maxMessage: 'Bitte verwenden Sie höchstens {{ limit }} Zeichen.'),
                ],
                'attr' => [
                    'autocomplete' => 'name',
                    'maxlength'    => 120,
                    'class'        => 'form-control',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('email', EmailType::class, [
                'label'         => 'E‑Mail',
                'required'      => true,
                'property_path' => 'emailAddress',
                'constraints'   => [
                    new Assert\NotBlank(message: 'Bitte geben Sie Ihre E‑Mail‑Adresse an.'),
                    new Assert\Email(message: 'Bitte geben Sie eine gültige E‑Mail‑Adresse an.'),
                    new Assert\Length(max: 200, maxMessage: 'Bitte verwenden Sie höchstens {{ limit }} Zeichen.'),
                ],
                'attr' => [
                    'autocomplete' => 'email',
                    'maxlength'    => 200,
                    'class'        => 'form-control',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('phone', TextType::class, [
                'label'       => 'Telefon (optional)',
                'required'    => false,
                'empty_data'  => '',
                'constraints' => [
                    new Assert\Length(max: 40, maxMessage: 'Bitte verwenden Sie höchstens {{ limit }} Zeichen.'),
                ],
                'attr' => [
                    'autocomplete' => 'tel',
                    'maxlength'    => 40,
                    'class'        => 'form-control',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('message', TextareaType::class, [
                'label'       => 'Nachricht',
                'required'    => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Bitte geben Sie eine Nachricht ein.'),
                    new Assert\Length(min: 10, max: 5000, minMessage: 'Bitte geben Sie mindestens {{ limit }} Zeichen ein.', maxMessage: 'Bitte verwenden Sie höchstens {{ limit }} Zeichen.'),
                ],
                'attr' => [
                    'rows'        => 6,
                    'minlength'   => 10,
                    'maxlength'   => 5000,
                    'placeholder' => 'Nachricht...',
                    'class'       => 'form-control',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            // consent must be true
            ->add('consent', CheckboxType::class, [
                'label'       => 'Ich willige in die Verarbeitung meiner Angaben zum Zweck der Kontaktaufnahme ein.',
                'required'    => true,
                'mapped'      => true,
                'constraints' => [
                    new Assert\IsTrue(message: 'Bitte stimmen Sie der Datenverarbeitung zu.'),
                ],
                'attr'       => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('copy', CheckboxType::class, [
                'label'      => 'Kopie an mich senden',
                'required'   => false,
                'attr'       => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            // spam traps
            ->add('emailrep', TextType::class, [
                'label'      => false,
                'required'   => false,
                'empty_data' => '',
                'attr'       => [
                    'autocomplete' => 'off',
                    'tabindex'     => '-1',
                    'class'        => 'visually-hidden',
                    'aria-hidden'  => 'true',
                    'style'        => 'position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;',
                ],
            ])
            ->add('website', TextType::class, [
                'label'      => false,
                'mapped'     => false,
                'required'   => false,
                'empty_data' => '',
                'attr'       => [
                    'autocomplete' => 'off',
                    'tabindex'     => '-1',
                    'class'        => 'visually-hidden',
                    'aria-hidden'  => 'true',
                    'style'        => 'position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => FormContactEntity::class,
            'csrf_protection' => true,
        ]);
    }
}
