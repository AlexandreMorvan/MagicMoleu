<?php

namespace App\Controller\Admin;

use App\Entity\Deck;
use App\Enum\Format;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/**
 * @extends AbstractCrudController<Deck>
 */
class DeckCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Deck::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Deck')
            ->setEntityLabelInPlural('Decks')
            ->setSearchFields(['name', 'owner.username'])
            ->setDefaultSort(['updatedAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewOnSite = Action::new('viewOnSite', 'Voir sur le site', 'fa fa-up-right-from-square')
            ->linkToUrl(fn (Deck $deck) => $this->generateUrl('app_deck_show', ['id' => $deck->getId()]))
            ->setHtmlAttributes(['target' => '_blank']);

        // Les decks se construisent sur le site (il faut un propriétaire et des cartes)
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, $viewOnSite)
            ->add(Crud::PAGE_DETAIL, $viewOnSite)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('format')->setChoices(self::formatChoices()))
            ->add(BooleanFilter::new('public'))
            ->add(EntityFilter::new('owner'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', 'Nom');
        yield AssociationField::new('owner', 'Propriétaire')->setDisabled();
        yield ChoiceField::new('format', 'Format')->setChoices(self::formatChoices());
        yield BooleanField::new('public', 'Public');
        yield IntegerField::new('cardCount', 'Cartes')->hideOnForm();
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Création')->hideOnForm()->hideOnIndex();
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm();
    }

    /**
     * @return array<string, Format>
     */
    private static function formatChoices(): array
    {
        $choices = [];
        foreach (Format::deckFormats() as $format) {
            $choices[$format->label()] = $format;
        }

        return $choices;
    }
}
