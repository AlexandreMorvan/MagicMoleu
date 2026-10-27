<?php

namespace App\Form\Account;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nouveau mot de passe (mêmes règles qu'à l'inscription), avec le mot de passe actuel depuis « Mon compte »,
 * sans lui depuis le lien « mot de passe oublié ».
 *
 * @extends AbstractType<array{currentPassword?: string, plainPassword: string}>
 */
class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['require_current_password']) {
            $builder->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new UserPassword(message: 'Mot de passe incorrect.')],
            ]);
        }

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'options' => ['attr' => ['autocomplete' => 'new-password']],
            'first_options' => ['label' => 'Nouveau mot de passe', 'help' => '8 caractères minimum.'],
            'second_options' => ['label' => 'Confirmation'],
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'error_mapping' => ['.' => 'second'],
            'constraints' => [
                new Assert\NotBlank(message: 'Choisis un mot de passe.'),
                new Assert\Length(min: 8, max: 4096, minMessage: 'Ton mot de passe doit faire au moins {{ limit }} caractères.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['require_current_password' => true]);
        $resolver->setAllowedTypes('require_current_password', 'bool');
    }
}
