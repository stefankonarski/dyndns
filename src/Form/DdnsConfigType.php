<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DdnsConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

class DdnsConfigType extends AbstractType
{
    /**
     * @param array{
     *   zone_choices?: array<string, string>,
     *   current_zone?: ?string
     * } $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('zoneSelection', ChoiceType::class, [
                'label' => 'Domain (Hetzner Zone)',
                'mapped' => false,
                'required' => true,
                'choices' => $options['zone_choices'],
                'data' => $options['current_zone'],
                'placeholder' => 'Bitte Zone auswählen',
                'constraints' => [
                    new NotBlank(message: 'Bitte eine Domain aus den Hetzner-Zonen auswählen.'),
                ],
            ])
            ->add('subdomain', TextType::class, [
                'label' => 'Subdomain',
                'constraints' => [
                    new NotBlank(message: 'Subdomain darf nicht leer sein.'),
                    new Regex(
                        pattern: '/^(?:@|[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)$/',
                        message: 'Subdomain muss DNS-kompatibel sein (oder @).',
                    ),
                ],
            ])
            ->add('fritzboxUsername', TextType::class, [
                'label' => 'DynDNS Username (Fritzbox)',
                'constraints' => [
                    new NotBlank(message: 'DynDNS-Username darf nicht leer sein.'),
                ],
            ])
            ->add('fritzboxPassword', PasswordType::class, [
                'label' => 'DynDNS Passwort (neu setzen)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ])
            ->add('ttl', IntegerType::class, [
                'label' => 'TTL (Sekunden)',
                'constraints' => [
                    new Range(min: 60, max: 86400, notInRangeMessage: 'TTL muss zwischen {{ min }} und {{ max }} liegen.'),
                ],
            ])
            ->add('ipv4Enabled', CheckboxType::class, [
                'label' => 'IPv4 (A-Record) aktiv',
                'required' => false,
            ])
            ->add('ipv6Enabled', CheckboxType::class, [
                'label' => 'IPv6 (AAAA-Record) aktiv',
                'required' => false,
            ])
            ->add('manualIpv6', TextType::class, [
                'label' => 'Manuelle IPv6',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Konfiguration speichern',
            ])
            ->add('forceSync', SubmitType::class, [
                'label' => 'Force-Sync ausführen',
            ])
            ->add('deleteAaaa', SubmitType::class, [
                'label' => 'AAAA-Record löschen',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DdnsConfig::class,
            'zone_choices' => [],
            'current_zone' => null,
        ]);
        $resolver->setAllowedTypes('zone_choices', 'array');
        $resolver->setAllowedTypes('current_zone', ['null', 'string']);
    }
}
