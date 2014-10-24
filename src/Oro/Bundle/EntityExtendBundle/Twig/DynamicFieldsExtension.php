<?php

namespace Oro\Bundle\EntityExtendBundle\Twig;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Util\ClassUtils;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\Collections\Collection;

use Oro\Bundle\LocaleBundle\Formatter\DateTimeFormatter;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Extend\FieldTypeHelper;
use Oro\Bundle\FormBundle\Entity\PriorityItem;

use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\ConfigIdInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Entity\OptionSetRelation;
use Oro\Bundle\EntityConfigBundle\Entity\Repository\OptionSetRelationRepository;
use Oro\Bundle\EntityConfigBundle\Metadata\EntityMetadata;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;

class DynamicFieldsExtension extends \Twig_Extension
{
    const NAME = 'oro_entity_config_fields';

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var FieldTypeHelper
     */
    protected $fieldTypeHelper;

    /**
     * @var ConfigProviderInterface
     */
    protected $extendProvider;

    /**
     * @var ConfigProviderInterface
     */
    protected $entityProvider;

    /**
     * @var ConfigProviderInterface
     */
    protected $viewProvider;

    /**
     * @var DateTimeFormatter
     */
    protected $dateTimeFormatter;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * @var PropertyAccessor
     */
    protected $propertyAccessor;

    /**
     * @var string
     */
    protected $entityViewRoute = 'oro_entity_view';

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param ConfigManager         $configManager
     * @param FieldTypeHelper       $fieldTypeHelper
     * @param DateTimeFormatter     $dateTimeFormatter
     * @param UrlGeneratorInterface $router
     * @param EntityManager         $entityManager
     */
    public function __construct(
        ConfigManager $configManager,
        FieldTypeHelper $fieldTypeHelper,
        DateTimeFormatter $dateTimeFormatter,
        UrlGeneratorInterface $router,
        EntityManager $entityManager
    ) {
        $this->configManager = $configManager;
        $this->fieldTypeHelper = $fieldTypeHelper;
        $this->dateTimeFormatter = $dateTimeFormatter;
        $this->router = $router;

        $this->extendProvider = $configManager->getProvider('extend');
        $this->entityProvider = $configManager->getProvider('entity');
        $this->viewProvider = $configManager->getProvider('view');
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('oro_get_dynamic_fields', array($this, 'getFields')),
        );
    }

    /**
     * @param object $entity
     * @param null|string $entityClass
     * @return array
     */
    public function getFields($entity, $entityClass = null)
    {
        $dynamicRow = array();
        if (null === $entityClass) {
            $entityClass = ClassUtils::getRealClass($entity);
        }

        $fields = $this->extendProvider->filter(array($this, 'filterFields'), $entityClass);

        foreach ($fields as $field) {
            /** @var FieldConfigId $fieldConfigId */
            $fieldConfigId = $field->getId();

            $fieldName = $fieldConfigId->getFieldName();
            $fieldType = $fieldConfigId->getFieldType();
            $underlyingFieldType = $this->fieldTypeHelper->getUnderlyingType($fieldType);
            $value = $this->propertyAccessor->getValue($entity, $fieldName);

            /** Prepare OptionSet field type */
            if ($fieldType == 'optionSet') {
                $value = $this->getValueForOptionSet($entity, $fieldConfigId);
            }

            if ($value && $underlyingFieldType == 'manyToOne') {
                $value = $this->getValueForManyToOne($value, $field);
            }

            /** Prepare Relation field type */
            if ($value && $value instanceof Collection) {
                $value = $this->getValueForCollection($value, $fieldConfigId);
            }

            $fieldConfig = $this->entityProvider->getConfigById($fieldConfigId);
            $label = $fieldConfig->get('label');
            if (!$label) {
                $label = $fieldName;
            }
            $dynamicRow[$fieldName] = array(
                'type'  => $fieldType,
                'label' => $label,
                'value' => $value,
            );
        }

        return $dynamicRow;
    }

    /**
     * @param \DateTime $value
     * @return string
     */
    protected function getValueForDateTime(\DateTime $value)
    {
        return $this->dateTimeFormatter->formatDate($value);
    }

    /**
     * @param object $entity
     * @param FieldConfigId $fieldConfig
     * @return OptionSetRelation[]
     */
    protected function getValueForOptionSet($entity, FieldConfigId $fieldConfig)
    {
        /** @var $optionSetRepository OptionSetRelationRepository */
        $optionSetRepository = $this->configManager
            ->getEntityManager()
            ->getRepository(OptionSetRelation::ENTITY_NAME);

        $model = $this->configManager->getConfigFieldModel(
            $fieldConfig->getClassName(),
            $fieldConfig->getFieldName()
        );

        $value = $optionSetRepository->findByFieldId($model->getId(), $entity->getId());
        array_walk(
            $value,
            function (OptionSetRelation &$item) {
                $item = array('title' => $item->getOption()->getLabel());
            }
        );

        $value['values'] = $value;

        return $value;
    }

    /**
     * @param Collection        $collection
     * @param ConfigIdInterface $fieldConfig
     *
     * @return array
     */
    protected function getValueForCollection(Collection $collection, ConfigIdInterface $fieldConfig)
    {
        $extendConfig   = $this->extendProvider->getConfigById($fieldConfig);
        $titleFieldName = $extendConfig->get('target_title');

        $value = $this->getEntityRouteOptions($extendConfig->get('target_entity'));

        $values     = [];
        $priorities = [];
        /** @var object $item */
        foreach ($collection as $item) {
            $value['route_params']['id'] = $item->getId();

            $title = [];
            foreach ($titleFieldName as $fieldName) {
                $title[] = $this->propertyAccessor->getValue($item, $fieldName);
            }

            $values[] = [
                'id'    => $item->getId(),
                'link'  => $value['route'] ? $this->router->generate($value['route'], $value['route_params']) : false,
                'title' => implode(' ', $title)
            ];
            if ($item instanceof PriorityItem) {
                $priorities[] = $item->getPriority();
            }
        }

        // sort values by priority if needed
        if (!empty($priorities) && count($priorities) === count($values)) {
            array_multisort($priorities, $values);
        }

        $value['values'] = $values;

        return $value;
    }

    /**
     * @param string $entityClassName
     *
     * @return array
     */
    protected function getEntityRouteOptions($entityClassName)
    {
        if (class_exists($entityClassName)) {
            $relationExtendConfig = $this->extendProvider->getConfig($entityClassName);

            return $relationExtendConfig->is('owner', ExtendScope::OWNER_CUSTOM)
                ? $this->getCustomEntityViewRouteOptions($entityClassName)
                : $this->getClassViewRouteOptions($entityClassName);
        }

        return [
            'route'        => false,
            'route_params' => false
        ];
    }

    /**
     * @param ConfigInterface $config
     * @return bool
     */
    public function filterFields(ConfigInterface $config)
    {
        $extendConfig = $this->extendProvider->getConfigById($config->getId());
        /** @var FieldConfigId $fieldConfigId */
        $fieldConfigId = $extendConfig->getId();

        // skip system, new and deleted fields
        if (!$config->is('owner', ExtendScope::OWNER_CUSTOM)
            || $config->is('state', ExtendScope::STATE_NEW)
            || $config->is('is_deleted')
        ) {
            return false;
        }

        // skip invisible fields
        if (!$this->viewProvider->getConfigById($config->getId())->is('is_displayable')) {
            return false;
        }

        // skip relations if they are referenced to deleted entity
        $underlyingFieldType = $this->fieldTypeHelper->getUnderlyingType($fieldConfigId->getFieldType());
        if (in_array($underlyingFieldType, array('oneToMany', 'manyToOne', 'manyToMany'))
            && $this->extendProvider->getConfig($extendConfig->get('target_entity'))->is('is_deleted', true)
        ) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * Return view link options or simple text
     *
     * @param object $targetEntity
     * @param ConfigInterface $field
     * @return array|mixed
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    protected function getValueForManyToOne($targetEntity, ConfigInterface $field)
    {
        $targetFieldName = $field->get('target_field');
        $targetClassName = $field->get('target_entity');

        $title = $this->propertyAccessor->getValue(
            $targetEntity,
            $targetFieldName
        );

        $targetMetadata = $this->entityManager->getClassMetadata($targetClassName);
        $id = $this->propertyAccessor->getValue(
            $targetEntity,
            $targetMetadata->getSingleIdentifierFieldName()
        );

        if (class_exists($targetClassName)) {
            $relationExtendConfig = $this->extendProvider->getConfig($targetClassName);
            $routeOptions = $relationExtendConfig->is('owner', ExtendScope::OWNER_CUSTOM)
                ? $this->getCustomEntityViewRouteOptions($targetClassName, $id)
                : $this->getClassViewRouteOptions($targetClassName, $id);
            if ($routeOptions['route']) {
                return [
                    'link'  => $this->router->generate($routeOptions['route'], $routeOptions['route_params']),
                    'title' => $title
                ];
            }
        }

        return $title;
    }

    /**
     * @param string $entityClassName
     * @param mixed  $id
     * @return array
     */
    protected function getClassViewRouteOptions($entityClassName, $id = null)
    {
        $routeOptions = array('route' => false, 'route_params' => false);
        /** @var EntityMetadata $metadata */
        $metadata = $this->configManager->getEntityMetadata($entityClassName);
        if ($metadata && $metadata->routeView) {
            $routeOptions['route'] = $metadata->routeView;
            $routeOptions['route_params'] = [
                'id' => $id
            ];
            return $routeOptions;
        }
        return $routeOptions;
    }

    /**
     * @param string   $entityClassName
     * @param mixed    $id
     * @return array
     */
    protected function getCustomEntityViewRouteOptions($entityClassName, $id = null)
    {
        return [
            'route'        => $this->entityViewRoute,
            'route_params' => [
                'entityName' => str_replace('\\', '_', $entityClassName),
                'id'         => $id
            ]
        ];
    }
}
