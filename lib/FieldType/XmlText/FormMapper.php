<?php

namespace eZ\Publish\Core\FieldType\XmlText;

use EzSystems\RepositoryForms\Data\Content\FieldData;
use EzSystems\RepositoryForms\FieldType\FieldValueFormMapperInterface;
use Symfony\Component\Form\FormInterface;

class FormMapper implements FieldValueFormMapperInterface
{
    public function mapFieldValueForm(FormInterface $fieldForm, FieldData $data)
    {
        $fieldForm
            ->add(
                $fieldForm->getConfig()->getFormFactory()->createBuilder()
                    ->create(
                        'value',
                        FormType::class,
                        [
                            'required' => $data->fieldDefinition->isRequired,
                            'label' => $data->fieldDefinition->getName(),
                        ]
                    )
                    ->setAutoInitialize(false)
                    ->getForm()
            );
    }
}
