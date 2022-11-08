<?php

namespace App\Controller\Admin;

use App\Entity\Question;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class QuestionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Question::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex()
        ;
        yield TextField::new('slug')
            ->hideOnIndex()
            ->setFormTypeOption('disabled', Crud::PAGE_NEW !== $pageName)
        ;
        yield Field::new('name');
        yield AssociationField::new('topic');
        yield TextareaField::new('question')
            ->hideOnIndex()
        ;
        yield Field::new('votes', 'Total Votes')
            ->setTextAlign('right')
        ;
        yield AssociationField::new('askedBy')
            ->autocomplete()
            ->formatValue(static function ($value, Question $question): ?string {
                if (!$user = $question->getAskedBy()) {
                    return null;
                }

                return sprintf('%s&nbsp;(%s)', $user->getEmail(), $user->getQuestions()->count());
            })
            ->setQueryBuilder(function (QueryBuilder $qb) {
                $qb->andWhere('entity.enabled = :enabled')
                    ->setParameter('enabled', true);
            })
        ;
        yield AssociationField::new('answers')
            ->autocomplete()
            // ->setFormTypeOption('choice_label', 'id')
            ->setFormTypeOption('by_reference', false)
        ;
        yield Field::new('createdAt')
            ->hideOnForm()
        ;
    }
}
