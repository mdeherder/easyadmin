<?php

namespace App\Controller\Admin;

use App\Easyadmin\VotesField;
use App\Entity\Question;
use App\Entity\User;
use App\Service\CsvExporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;

class QuestionCrudController extends AbstractCrudController
{
    public function __construct(private RequestStack $requestStack, private AdminUrlGenerator $adminUrlGenerator)
    {
    }

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
        yield Field::new('name')
            ->setSortable(false)
        ;
        yield AssociationField::new('topic');
        yield TextareaField::new('question')
            ->hideOnIndex()
            ->setFormTypeOptions([
                'row_attr' => [
                    'data-controller' => 'snarkdown',
                ],
                'attr' => [
                    'data-snarkdown-target' => 'input',
                    'data-action' => 'snarkdown#render',
                ],
            ])
            ->setHelp('Preview:')
        ;
        yield VotesField::new('votes', 'Total Votes')
            ->setPermission('ROLE_SUPER_ADMIN')
        ;
        yield AssociationField::new('askedBy')
            ->autocomplete()
            ->formatValue(static function ($value, ?Question $question): ?string {
                if (!$user = $question?->getAskedBy()) {
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
        yield AssociationField::new('updatedBy')
            ->onlyOnDetail()
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort([
                'askedBy.enabled' => 'DESC',
                'createdAt' => 'DESC',
            ])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewAction = function () {
            return Action::new('view')
                ->linkToUrl(function (Question $question) {
                    return $this->generateUrl('app_question_show', [
                        'slug' => $question->getSlug(),
                    ]);
                });
        };

        $approveAction = Action::new('approve')
            ->setTemplatePath('admin/approve_action.html.twig')
            ->linkToCrudAction('approve')
            ->addCssClass('btn btn-warning')
            ->setIcon('fa fa-check-circle')
            ->displayAsButton()
            ->displayIf(static function (Question $question): bool {
                return !$question->getIsApproved();
            })
        ;

        $exportAction = Action::new('export')
            ->linkToUrl(function () {
                $request = $this->requestStack->getCurrentRequest();

                return $this->adminUrlGenerator->setAll($request->query->all())
                    ->setAction('export')
                    ->generateUrl()
                ;
            })
            ->addCssClass('btn btn-secondary')
            ->setIcon('fa fa-download')
            ->createAsGlobalAction()
        ;

        return parent::configureActions($actions)
            ->update(Crud::PAGE_INDEX, Action::DELETE, static function (Action $action) {
                $action->displayIf(static function (Question $question) {
                    // always display, so we can try via the subscriber instead
                    return true;
                    // return !$question->getIsApproved();
                });

                return $action;
            })
            ->setPermission(Action::INDEX, 'ROLE_MODERATOR')
            ->setPermission(Action::DETAIL, 'ROLE_MODERATOR')
            ->setPermission(Action::EDIT, 'ROLE_MODERATOR')
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_SUPER_ADMIN')
            ->disable(Action::BATCH_DELETE)
            ->add(Action::INDEX, $viewAction())
            ->add(Action::DETAIL, $viewAction()
                ->addCssClass('btn btn-success')
                ->setIcon('fa fa-eye')
                ->setLabel('View on Site')
            )
            ->add(Crud::PAGE_DETAIL, $approveAction)
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->reorder(Crud::PAGE_DETAIL, [
                'approve',
                'view',
                Action::EDIT,
                Action::INDEX,
                Action::DELETE,
            ])
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add('topic')
            ->add('createdAt')
            ->add('votes')
            ->add('name')
        ;
    }

    /**
     * @param Question $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Currently logged in user is not an instance of User?!');
        }
        $entityInstance->setUpdatedBy($user);
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * @param Question $entityInstance
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getIsApproved()) {
            throw new \Exception('Deleting approved questions is forbidden!');
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function approve(AdminContext $adminContext, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator)
    {
        $question = $adminContext->getEntity()->getInstance();
        if (!$question instanceof Question) {
            throw new \LogicException('Entity is missing or not a Question');
        }
        $question->setIsApproved(true);
        $em->flush();
        $targetUrl = $adminUrlGenerator
            ->setController(QuestionPendingApprovalCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($question->getId())
            ->generateUrl()
        ;

        return $this->redirect($targetUrl);
    }

    public function export(AdminContext $context, CsvExporter $csvExporter)
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        return $csvExporter->createResponseFromQueryBuilder($queryBuilder, $fields, 'questions.csv');
    }
}
