<?php

namespace App\Form\Account;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * Pseudo et email (identifiant de connexion). Le pseudo est affiché mais figé : le changer permettrait de
 * se faire passer pour un autre joueur (ancien pseudo réutilisé, liens vers le profil public…).
 * La modification de l'email est confirmée par le mot de passe actuel.
 *
 * @extends AbstractType<User>
 */
class AccountIdentityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Pseudo',
                // Champ désactivé : une valeur envoyée malgré tout est ignorée par le formulaire
                'disabled' => true,
                'help' => 'Le pseudo ne peut pas être modifié.',
                'attr' => ['autocomplete' => 'username'],
            ])
            ->add('email', EmailType::class, ['label' => 'Email', 'help' => 'Sert aussi à te connecter.', 'attr' => ['autocomplete' => 'email']])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new UserPassword(message: 'Mot de passe incorrect.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
