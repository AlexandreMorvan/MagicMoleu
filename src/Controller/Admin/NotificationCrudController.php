<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Notifications envoyées aux utilisateurs ; l'admin peut aussi en envoyer une (annonce, message personnel…).
 *
 * @extends AbstractCrudController<Notification>
 */
class NotificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Notification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Notification')
            ->setEntityLabelInPlural('Notifications')
            ->setSearchFields(['title', 'message', 'user.username'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_NEW, 'Envoyer une notification');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a) => $a->setLabel('Envoyer une notification'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('user', 'Destinataire')->autocomplete();
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('message', 'Message');
        yield TextField::new('link', 'Lien (optionnel)')->hideOnIndex();
        yield TextField::new('icon', 'Icône (symboles, ex. {W}{U})')->hideOnIndex();
        yield BooleanField::new('read', 'Lue')->renderAsSwitch(false)->hideOnForm();
        yield DateTimeField::new('createdAt', 'Envoyée le')->hideOnForm();
    }
}
