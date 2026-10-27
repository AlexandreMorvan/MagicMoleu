<?php

namespace App\Form\Account;

use App\Entity\User;
use App\Enum\Color;
use App\Enum\Format;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Informations affichées sur le profil public : présentation, couleurs favorites, format préféré.
 *
 * @extends AbstractType<User>
 */
class AccountProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bio', TextareaType::class, [
                'label' => 'Présentation',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 500, 'placeholder' => 'Joueur Commander depuis Kaladesh, fan de decks tokens…'],
            ])
            ->add('favoriteColorList', EnumType::class, [
                'class' => Color::class,
                'label' => 'Couleurs favorites',
                'help' => 'Une couleur, une guilde, un clan… Le nom de la combinaison s\'affiche sur ton profil.',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choice_label' => static fn (Color $c) => $c->label(),
            ])
            ->add('preferredFormat', EnumType::class, [
                'class' => Format::class,
                'label' => 'Format préféré',
                'help' => 'Proposé par défaut quand tu crées un deck.',
                'required' => false,
                'placeholder' => 'Aucun',
                'choices' => Format::deckFormats(),
                'choice_label' => static fn (Format $f) => $f->label(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
