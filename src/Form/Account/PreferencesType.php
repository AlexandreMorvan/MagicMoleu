<?php

namespace App\Form\Account;

use App\Entity\User;
use App\Enum\DeckView;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<User>
 */
class PreferencesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('notificationToasts', CheckboxType::class, [
                'label' => 'Afficher les notifications en bas de l\'écran (succès débloqués, messages)',
                'required' => false,
                'getter' => static fn (User $u) => $u->wantsNotificationToasts(),
                'setter' => static fn (User $u, bool $v) => $u->setNotificationToasts($v),
            ])
            ->add('defaultDeckView', EnumType::class, [
                'class' => DeckView::class,
                'label' => 'Vue des decks par défaut',
                'expanded' => true,
                'choice_label' => static fn (DeckView $v) => $v->label(),
            ])
            ->add('defaultCollectionView', ChoiceType::class, [
                'label' => 'Vue des collections par défaut',
                'expanded' => true,
                'choices' => ['Vignettes' => 'grid', 'Liste' => 'list'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
