<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BookingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('coursePeriod', HiddenType::class, [
                'required' => true,
            ])
            ->add('desiredTimeSlot', ChoiceType::class, [
                'label'       => 'Gewünschte Kurszeit',
                'required'    => true,
                'placeholder' => 'Bitte wählen…',
                'choices'     => [
                    '15:00–15:45 Uhr' => '15:00–15:45',
                    '16:00–16:45 Uhr' => '16:00–16:45',
                ],
                'constraints' => [new Assert\NotBlank(message: 'Bitte eine Kurszeit auswählen.')],
                'attr'        => ['class' => 'form-select'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            // Kinderdaten
            ->add('childName', TextType::class, [
                'label'       => 'Vor- und Nachname des Kindes',
                'required'    => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Bitte geben Sie den Namen des Kindes an.'),
                    new Assert\Length(max: 160),
                ],
                'attr'       => ['maxlength' => 160, 'class' => 'form-control'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('childBirthdate', BirthdayType::class, [
                'label'       => 'Geburtsdatum',
                'required'    => true,
                'widget'      => 'single_text',
                'input'       => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(message: 'Bitte geben Sie das Geburtsdatum an.'),
                ],
                'attr'       => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('childAddress', TextareaType::class, [
                'label'       => 'Adresse',
                'required'    => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Bitte geben Sie die Adresse an.'),
                    new Assert\Length(min: 5, max: 1000),
                ],
                'attr'       => ['rows' => 3, 'maxlength' => 1000, 'class' => 'form-control'],
                'label_attr' => ['class' => 'form-label'],
            ])
            // Gesundheit
            ->add('hasSwimExperience', ChoiceType::class, [
                'label'       => 'Hat Ihr Kind bereits Schwimmerfahrung?',
                'required'    => true,
                'choices'     => ['Ja' => true, 'Nein' => false],
                'expanded'    => true,
                'constraints' => [new Assert\NotNull(message: 'Bitte wählen Sie aus.')],
                'attr'        => ['class' => 'choice-expanded'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            ->add('swimExperienceDetails', TextType::class, [
                'label'       => 'Wenn ja, welche',
                'required'    => false,
                'empty_data'  => '',
                'constraints' => [new Assert\Length(max: 500)],
                'attr'        => ['maxlength' => 500, 'class' => 'form-control'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            ->add('healthNotes', TextareaType::class, [
                'label'       => 'Gesundheitliche Einschränkungen (z. B. Asthma, Epilepsie, Allergien)',
                'required'    => false,
                'empty_data'  => '',
                'constraints' => [new Assert\Length(max: 5000)],
                'attr'        => ['rows' => 3, 'maxlength' => 5000, 'class' => 'form-control'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            ->add('maySwimWithoutAid', ChoiceType::class, [
                'label'       => 'Darf Ihr Kind ohne Schwimmhilfe ins Wasser?',
                'required'    => true,
                'choices'     => ['Ja' => true, 'Nein' => false],
                'expanded'    => true,
                'constraints' => [new Assert\NotNull(message: 'Bitte wählen Sie aus.')],
                'attr'        => ['class' => 'choice-expanded'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            // Eltern
            ->add('parentName', TextType::class, [
                'label'       => 'Vor- & Nachname (Eltern/Erziehungsberechtigte)',
                'required'    => true,
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 160)],
                'attr'        => ['maxlength' => 160, 'class' => 'form-control'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            ->add('parentPhone', TextType::class, [
                'label'       => 'Telefonnummer (optional)',
                'required'    => false,
                'empty_data'  => '',
                'constraints' => [new Assert\Length(max: 40)],
                'attr'        => ['maxlength' => 40, 'class' => 'form-control'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            ->add('parentEmail', EmailType::class, [
                'label'       => 'E‑Mail‑Adresse',
                'required'    => true,
                'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 200)],
                'attr'        => ['maxlength' => 200, 'class' => 'form-control', 'autocomplete' => 'email'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            // Organisatorisches
            ->add('isMemberOfClub', ChoiceType::class, [
                'label'       => 'Ist Ihr Kind Mitglied im Verein/Bad?',
                'required'    => true,
                'choices'     => ['Ja' => true, 'Nein' => false],
                'expanded'    => true,
                'constraints' => [new Assert\NotNull(message: 'Bitte wählen Sie aus.')],
                'attr'        => ['class' => 'choice-expanded'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label'    => 'Zahlungsart',
                'required' => true,
                'choices'  => [
                    'Barzahlung'  => 'barzahlung',
                    'Überweisung' => 'ueberweisung',
                    'PayPal'      => 'paypal',
                ],
                'constraints' => [new Assert\NotBlank(message: 'Bitte wählen Sie eine Zahlungsart.')],
                'attr'        => ['class' => 'form-select'],
                'label_attr'  => ['class' => 'form-label'],
            ])
            // Einverständnisse
            ->add('participationConsent', CheckboxType::class, [
                'label'       => 'Ich bestätige, dass mein Kind am Schwimmkurs teilnehmen darf.',
                'required'    => true,
                'constraints' => [new Assert\IsTrue(message: 'Bitte bestätigen Sie die Teilnahmeberechtigung.')],
                'attr'        => ['class' => 'form-check-input'],
                'label_attr'  => ['class' => 'form-check-label'],
            ])
            ->add('liabilityAcknowledged', CheckboxType::class, [
                'label'       => 'Ich habe den <a href="/haftungsausschluss" target="_blank" rel="noopener">Haftungsausschluss</a> zur Kenntnis genommen.',
                'label_html'  => true,
                'required'    => true,
                'constraints' => [new Assert\IsTrue(message: 'Bitte bestätigen Sie den Haftungsausschluss.')],
                'attr'        => ['class' => 'form-check-input'],
                'label_attr'  => ['class' => 'form-check-label'],
            ])
            ->add('photoConsent', CheckboxType::class, [
                'label'      => 'Ich erkläre mich einverstanden, dass Fotos/Videos zu Vereinszwecken genutzt werden dürfen.',
                'required'   => false,
                'attr'       => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('dataConsent', CheckboxType::class, [
                'label'       => 'Ich stimme der Verarbeitung meiner Daten gemäß <a href="/datenschutz" target="_blank" rel="noopener">Datenschutzerklärung</a> zu.',
                'label_html'  => true,
                'required'    => true,
                'constraints' => [new Assert\IsTrue(message: 'Bitte stimmen Sie der Datenverarbeitung zu.')],
                'attr'        => ['class' => 'form-check-input'],
                'label_attr'  => ['class' => 'form-check-label'],
            ])
            ->add('bookingConfirmation', CheckboxType::class, [
                'label'       => 'Ich bestätige meine verbindliche Anmeldung.',
                'required'    => true,
                'constraints' => [new Assert\IsTrue(message: 'Bitte bestätigen Sie Ihre verbindliche Anmeldung.')],
                'attr'        => ['class' => 'form-check-input'],
                'label_attr'  => ['class' => 'form-check-label'],
            ])
            // Spam traps
            ->add('emailrep', TextType::class, [
                'label'      => false,
                'required'   => false,
                'mapped'     => false,
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
            'data_class'      => Booking::class,
            'csrf_protection' => true,
        ]);
    }
}
