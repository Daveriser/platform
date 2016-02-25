<?php

namespace Oro\Component\ConfigExpression\Tests\Unit\Action;

use Symfony\Component\PropertyAccess\PropertyPath;

use Oro\Component\ConfigExpression\Action\CreateObject;
use Oro\Component\ConfigExpression\Model\ContextAccessor;
use Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ItemStub;

class CreateObjectTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CreateObject
     */
    protected $action;

    /**
     * @var ContextAccessor
     */
    protected $contextAccessor;

    protected function setUp()
    {
        $this->contextAccessor = new ContextAccessor();
        $this->action = new CreateObject($this->contextAccessor);
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();
        $this->action->setDispatcher($dispatcher);
    }

    protected function tearDown()
    {
        unset($this->contextAccessor);
        unset($this->action);
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\InvalidParameterException
     * @expectedExceptionMessage Class name parameter is required
     */
    public function testInitializeExceptionNoClassName()
    {
        $this->action->initialize(['some' => 1, 'attribute' => $this->getPropertyPath()]);
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\InvalidParameterException
     * @expectedExceptionMessage Attribute name parameter is required
     */
    public function testInitializeExceptionNoAttribute()
    {
        $this->action->initialize(['class' => 'stdClass']);
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\InvalidParameterException
     * @expectedExceptionMessage Attribute must be valid property definition.
     */
    public function testInitializeExceptionInvalidAttribute()
    {
        $this->action->initialize(['class' => 'stdClass', 'attribute' => 'string']);
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\InvalidParameterException
     * @expectedExceptionMessage Object data must be an array.
     */
    public function testInitializeExceptionInvalidData()
    {
        $this->action->initialize(
            ['class' => 'stdClass', 'attribute' => $this->getPropertyPath(), 'data' => 'string_value']
        );
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\InvalidParameterException
     * @expectedExceptionMessage Object constructor arguments must be an array.
     */
    public function testInitializeExceptionInvalidArguments()
    {
        $this->action->initialize(
            ['class' => 'stdClass', 'attribute' => $this->getPropertyPath(), 'arguments' => 'string_value']
        );
    }

    public function testInitialize()
    {
        $options = ['class' => 'stdClass', 'attribute' => $this->getPropertyPath()];
        $this->assertEquals($this->action, $this->action->initialize($options));
        $this->assertAttributeEquals($options, 'options', $this->action);
    }

    /**
     * @param array $options
     * @param array $expectedData
     * @param null|array $contextData
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $options, array $contextData = [], array $expectedData = null)
    {
        $context = new ItemStub($contextData);
        $attributeName = (string)$options['attribute'];
        $this->action->initialize($options);
        $this->action->execute($context);
        $this->assertNotNull($context->$attributeName);
        $this->assertInstanceOf($options['class'], $context->$attributeName);

        if ($context->$attributeName instanceof ItemStub) {
            /** @var ItemStub $entity */
            $entity = $context->$attributeName;
            if (!$expectedData) {
                $expectedData = !empty($options['data']) ? $options['data'] : [];
            }
            $this->assertInstanceOf($options['class'], $entity);
            $this->assertEquals($expectedData, $entity->getData());
        }
    }

    /**
     * @return array
     */
    public function executeDataProvider()
    {
        return [
            'without data' => [
                'options' => [
                    'class'     => 'Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ItemStub',
                    'attribute' => new PropertyPath('test_attribute'),
                ]
            ],
            'with data' => [
                'options' => [
                    'class'     => 'Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ItemStub',
                    'attribute' => new PropertyPath('test_attribute'),
                    'data'      => ['key1' => new PropertyPath('test_attribute'), 'key2' => 'value2'],
                ],
                ['test_attribute' => 'test_value'],
                ['key1' => 'test_value', 'key2' => 'value2']
            ],
            'with arguments' => [
                'options' => [
                    'class'     => '\DateTime',
                    'attribute' => new PropertyPath('test_attribute'),
                    'arguments' => ['now'],
                ]
            ],
            'with complex arguments' => [
                'options' => [
                    'class'     => 'Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ItemStub',
                    'attribute' => new PropertyPath('test_attribute'),
                    'arguments' => [['test', new PropertyPath('test_attribute')]],
                ],
                ['test_attribute' => 'test_value'],
                ['test', 'test_value']
            ]
        ];
    }

    protected function getPropertyPath()
    {
        return $this->getMockBuilder('Symfony\Component\PropertyAccess\PropertyPath')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
