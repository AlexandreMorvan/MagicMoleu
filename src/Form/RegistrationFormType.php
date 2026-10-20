<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<User>
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Pseudo',
                'attr' => ['autocomplete' => 'username', 'placeholder' => 'PlaneswalkerDu91'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'email', 'placeholder' => 'toi@exemple.fr'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                // Le composant live re-rend le formulaire : sans ça, les champs mot de passe se videraient
                'options' => ['always_empty' => false, 'attr' => ['autocomplete' => 'new-password']],
                'first_options' => ['label' => 'Mot de passe', 'help' => '8 caractères minimum.'],
                'second_options' => ['label' => 'Confirmation'],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                // Affiché sous la confirmation : pas d'erreur tant qu'elle n'a pas été saisie
                'error_mapping' => ['.' => 'second'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisis un mot de passe.'),
                    new Assert\Length(min: 8, max: 4096, minMessage: 'Ton mot de passe doit faire au moins {{ limit }} caractères.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
