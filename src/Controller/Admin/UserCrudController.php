<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission('ADMIN_USER_EDIT')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();
        yield AvatarField::new('avatar')
            ->formatValue(static function ($value, ?User $user) {
                return $user?->getAvatarUrl();
            })
            ->setLabel('Avatar')
            ->hideOnForm();
        yield ImageField::new('avatar')
            ->setBasePath('uploads/avatars')
            ->setUploadDir('public/uploads/avatars')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->onlyOnForms();
        yield EmailField::new('email');
        yield TextField::new('firstName')
            ->onlyOnForms();
        yield TextField::new('lastName')
            ->onlyOnForms();
        yield TextField::new('fullName')
            ->hideOnForm();
        yield BooleanField::new('enabled')
            ->renderAsSwitch(false);
        yield DateField::new('createdAt')
            ->hideOnForm();
        $roles = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_MODERATOR', 'ROLE_USER'];
        yield ChoiceField::new('roles')
            ->setChoices(array_combine($roles, $roles))
            ->allowMultipleChoices()
            ->renderExpanded()
            ->renderAsBadges();
        // ->setHelp('Available roles: ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_MODERATOR, ROLE_USER');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $queryBuilder;
        }
        $user = $this->getUser();
        assert($user instanceof User);
        $queryBuilder
            ->andWhere('entity.id = :id')
            ->setParameter('id', $user->getId());

        return $queryBuilder;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            // ->add(BooleanFilter::new('enabled')->setFormTypeOption('expanded', false))
            ->add(BooleanFilter::new('enabled'))
            // ->add('enabled')
        ;
    }
}
