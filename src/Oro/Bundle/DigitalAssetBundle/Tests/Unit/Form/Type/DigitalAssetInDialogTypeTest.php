<?php

namespace Oro\Bundle\DigitalAssetBundle\Tests\Unit\Form\Type;

use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Form\Type\FileType;
use Oro\Bundle\AttachmentBundle\Validator\Constraints\FileConstraintFromEntityFieldConfig;
use Oro\Bundle\AttachmentBundle\Validator\Constraints\FileConstraintFromEntityFieldConfigValidator;
use Oro\Bundle\DigitalAssetBundle\Entity\DigitalAsset;
use Oro\Bundle\DigitalAssetBundle\Form\Type\DigitalAssetInDialogType;
use Oro\Bundle\DigitalAssetBundle\Validator\Constraints\DigitalAssetSourceFileMimeTypeValidator;
use Oro\Bundle\FormBundle\Form\Extension\DataBlockExtension;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizationCollectionType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedPropertyType;
use Oro\Bundle\LocaleBundle\Tests\Unit\Form\Type\Stub\LocalizationCollectionTypeStub;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Testing\Unit\FormIntegrationTestCase;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Form\Extension\HttpFoundation\Type\FormTypeHttpFoundationExtension;
use Symfony\Component\Form\Test\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;

class DigitalAssetInDialogTypeTest extends FormIntegrationTestCase
{
    use EntityTrait;

    private const SAMPLE_TITLE = 'sample title';
    private const SAMPLE_CLASS = 'SampleClass';
    private const SAMPLE_FIELD = 'sampleField';

    /** @var DigitalAssetInDialogType */
    private $formType;

    protected function setUp(): void
    {
        $this->formType = new DigitalAssetInDialogType();

        parent::setUp();
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertEquals('oro_digital_asset_in_dialog', $this->formType->getBlockPrefix());
    }

    public function testConfigureOptions(): void
    {
        $resolver = $this->createMock(OptionsResolver::class);

        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with(
                [
                    'data_class' => DigitalAsset::class,
                    'validation_groups' => ['Default', 'DigitalAssetInDialog'],
                    'is_image_type' => false,
                ]
            );

        $this->formType->configureOptions($resolver);
    }

    /**
     * @dataProvider buildFormDataProvider
     *
     * @param array $options
     * @param string $expectedTooltip
     * @param string $expectedLabel
     * @param array $expectedConstraints
     */
    public function testBuildForm(
        array $options,
        string $expectedTooltip,
        string $expectedLabel,
        array $expectedConstraints
    ): void {
        $builder = $this->createMock(FormBuilderInterface::class);

        $builder
            ->expects($this->exactly(2))
            ->method('add')
            ->withConsecutive(
                [
                    'titles',
                    LocalizedFallbackValueCollectionType::class,
                    [
                        'label' => 'oro.digitalasset.titles.label',
                        'tooltip' => $options['is_image_type']
                            ? 'oro.digitalasset.titles.tooltip_image'
                            : 'oro.digitalasset.titles.tooltip_file',
                        'required' => true,
                        'entry_options' => ['constraints' => [new NotBlank()]],
                    ],
                ],
                [
                    'sourceFile',
                    FileType::class,
                    [
                        'label' => $options['is_image_type']
                            ? 'oro.digitalasset.dam.dialog.image.label'
                            : 'oro.digitalasset.dam.dialog.file.label',
                        'required' => true,
                        'allowDelete' => false,
                        'addEventSubscriber' => false,
                        'fileOptions' => [
                            'required' => true,
                            'constraints' => [
                                new NotBlank(),
                                new FileConstraintFromEntityFieldConfig(
                                    [
                                        'entityClass' => $options['parent_entity_class'],
                                        'fieldName' => $options['parent_entity_field_name'],
                                    ]
                                ),
                            ],
                        ],
                    ],
                ]
            )
            ->willReturnSelf();

        $this->formType->buildForm($builder, $options);
    }

    /**
     * @return array
     */
    public function buildFormDataProvider(): array
    {
        return [
            'not image type' => [
                'options' => [
                    'is_image_type' => false,
                    'parent_entity_class' => self::SAMPLE_CLASS,
                    'parent_entity_field_name' => self::SAMPLE_FIELD,
                ],
                'expectedTooltip' => 'oro.digitalasset.titles.tooltip_file',
                'expectedLabel' => 'oro.digitalasset.dam.dialog.file.label',
                'expectedConstraints' => [
                    new NotBlank(),
                    new FileConstraintFromEntityFieldConfig(
                        [
                            'entityClass' => self::SAMPLE_CLASS,
                            'fieldName' => self::SAMPLE_FIELD,
                        ]
                    ),
                ],
            ],
            'image type' => [
                'options' => [
                    'is_image_type' => true,
                    'parent_entity_class' => self::SAMPLE_CLASS,
                    'parent_entity_field_name' => self::SAMPLE_FIELD,
                ],
                'expectedTooltip' => 'oro.digitalasset.titles.tooltip_image',
                'expectedLabel' => 'oro.digitalasset.dam.dialog.image.label',
                'expectedConstraints' => [
                    new NotBlank(),
                    new FileConstraintFromEntityFieldConfig(
                        [
                            'entityClass' => self::SAMPLE_CLASS,
                            'fieldName' => self::SAMPLE_FIELD,
                        ]
                    ),
                ],
            ],
        ];
    }

    /**
     * @dataProvider submitDataProvider
     *
     * @param DigitalAsset $defaultData
     * @param array $submittedData
     * @param DigitalAsset $expectedData
     */
    public function testSubmit(DigitalAsset $defaultData, array $submittedData, DigitalAsset $expectedData): void
    {
        $form = $this->factory->create(
            DigitalAssetInDialogType::class,
            $defaultData,
            [
                'parent_entity_class' => self::SAMPLE_CLASS,
                'parent_entity_field_name' => self::SAMPLE_FIELD,
                'is_image_type' => false,
            ]
        );

        $this->assertEquals($defaultData, $form->getData());
        $this->assertEquals($defaultData, $form->getViewData());

        $form->submit($submittedData);

        $this->assertTrue($form->isValid());
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($expectedData->getTitles(), $form->getData()->getTitles());
        $this->assertEquals($expectedData->getSourceFile()->getFile(), $form->getData()->getSourceFile()->getFile());
        $this->assertInstanceOf(\DateTime::class, $form->getData()->getSourceFile()->getUpdatedAt());
    }

    /**
     * @return array
     */
    public function submitDataProvider(): array
    {
        $sourceFile = new File();
        $sourceFile->setFile($file = new SymfonyFile('sample-path', false));

        return [
            'title is set, source file is uploaded' => [
                'defaultData' => new DigitalAsset(),
                'submittedData' => [
                    'titles' => ['values' => ['default' => self::SAMPLE_TITLE]],
                    'sourceFile' => ['file' => $file],
                ],
                'expectedData' => (new DigitalAsset())
                    ->addTitle((new LocalizedFallbackValue())->setString(self::SAMPLE_TITLE))
                    ->setSourceFile($sourceFile),
            ],
        ];
    }

    public function testSubmitWhenNoFile(): void
    {
        $form = $this->factory->create(
            DigitalAssetInDialogType::class,
            $defaultData = new DigitalAsset(),
            [
                'parent_entity_class' => self::SAMPLE_CLASS,
                'parent_entity_field_name' => self::SAMPLE_FIELD,
                'is_image_type' => false,
            ]
        );

        $this->assertEquals($defaultData, $form->getData());
        $this->assertEquals($defaultData, $form->getViewData());

        $form->submit(
            [
                'titles' => ['values' => ['default' => self::SAMPLE_TITLE]],
                'sourceFile' => ['file' => null],
            ]
        );

        $this->assertFalse($form->isValid());
        $this->assertTrue($form->isSynchronized());
        $this->assertContains('This value should not be blank', (string)$form->getErrors(true, false));
    }

    public function testSubmitWhenNoTitle(): void
    {
        $form = $this->factory->create(
            DigitalAssetInDialogType::class,
            $defaultData = new DigitalAsset(),
            [
                'parent_entity_class' => self::SAMPLE_CLASS,
                'parent_entity_field_name' => self::SAMPLE_FIELD,
                'is_image_type' => false,
            ]
        );

        $this->assertEquals($defaultData, $form->getData());
        $this->assertEquals($defaultData, $form->getViewData());

        $sourceFile = new File();
        $sourceFile->setFile($file = new SymfonyFile('sample-path', false));

        $form->submit(
            [
                'titles' => ['values' => ['default' => '']],
                'sourceFile' => ['file' => $sourceFile],
            ]
        );

        $this->assertFalse($form->isValid());
        $this->assertTrue($form->isSynchronized());
        $this->assertContains('This value should not be blank', (string)$form->getErrors(true, false));
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtensions(): array
    {
        $doctrine = $this->createMock(ManagerRegistry::class);

        return array_merge(
            parent::getExtensions(),
            [
                new PreloadedExtension(
                    [
                        DigitalAssetInDialogType::class => $this->formType,
                        LocalizedFallbackValueCollectionType::class => new LocalizedFallbackValueCollectionType(
                            $doctrine
                        ),
                        LocalizedPropertyType::class => new LocalizedPropertyType(),
                        LocalizationCollectionType::class => new LocalizationCollectionTypeStub(),
                    ],
                    []
                ),
                $this->getValidatorExtension(true),
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTypeExtensions(): array
    {
        return array_merge(
            parent::getExtensions(),
            [
                new DataBlockExtension(),
                new FormTypeHttpFoundationExtension(),
            ]
        );
    }

    /**
     * @return array
     */
    protected function getValidators(): array
    {
        $digitalAssetSourceFileConfiguredValidator = $this->createMock(
            FileConstraintFromEntityFieldConfigValidator::class
        );
        $digitalAssetSourceFileConfiguredValidator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $digitalAssetSourceFileMimeTypeValidator = $this->createMock(DigitalAssetSourceFileMimeTypeValidator::class);
        $digitalAssetSourceFileMimeTypeValidator
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        return [
            NotBlank::class => new NotBlank(),
            FileConstraintFromEntityFieldConfigValidator::class => $digitalAssetSourceFileConfiguredValidator,
            DigitalAssetSourceFileMimeTypeValidator::class => $digitalAssetSourceFileMimeTypeValidator,
        ];
    }
}
