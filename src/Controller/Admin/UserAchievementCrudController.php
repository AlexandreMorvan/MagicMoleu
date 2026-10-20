<?php

namespace App\Controller\Admin;

use App\Entity\UserAchievement;
use App\Enum\Achievement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/**
 * Succès débloqués : consultation et retrait (un succès retiré sera redonné s'il est toujours mérité).
 *
 * @extends AbstractCrudController<UserAchievement>
 */
class UserAchievementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserAchievement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Succès débloqué')
            ->setEntityLabelInPlural('Succès débloqués')
            ->setSearchFields(['user.username'])
            ->setDefaultSort(['unlockedAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('achievement')->setChoices(self::achievementChoices()))
            ->add(EntityFilter::new('user'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('user', 'Utilisateur');
        yield ChoiceField::new('achievement', 'Succès')->setChoices(self::achievementChoices());
        yield DateTimeField::new('unlockedAt', 'Débloqué le');
    }

    /**
     * @return array<string, Achievement>
     */
    private static function achievementChoices(): array
    {
        $choices = [];
        foreach (Achievement::cases() as $achievement) {
            $choices[$achievement->label()] = $achievement;
        }

        return $choices;
    }
}
