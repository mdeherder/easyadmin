<?php

namespace App\Easyadmin;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class VotesField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string /* TranslatableInterface|string|false|null */ $label = null)
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            // this template is used in 'index' and 'detail' pages
            ->setTemplatePath('admin/field/votes.html.twig')
            ->setTextAlign('center')
            // this is used in 'edit' and 'new' pages to edit the field contents
            // you can use your own form types too
            ->setFormType(IntegerType::class)
            ->addCssClass('field-integer')
            ->setDefaultColumns('col-md-4 col-xxl-3')
            // ->setCustomOption(self::OPTION_NUMBER_FORMAT, null)
        ;
    }
}
