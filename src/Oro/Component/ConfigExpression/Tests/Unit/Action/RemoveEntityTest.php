<?php

namespace Oro\Component\ConfigExpression\Tests\Unit\Action;

use Symfony\Component\PropertyAccess\PropertyPath;

use Oro\Component\ConfigExpression\Model\ContextAccessor;
use Oro\Component\ConfigExpression\Action\ActionInterface;
use Oro\Component\ConfigExpression\Action\RemoveEntity;

class RemoveEntityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContextAccessor
     */
    protected $contextAccessor;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    /**
     * @var ActionInterface
     */
    protected $action;

    protected function setUp()
    {
        $this->contextAccessor = new ContextAccessor();

        $this->registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')
            ->getMock();

        $this->action = new RemoveEntity($this->contextAccessor, $this->registry);
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();
        $this->action->setDispatcher($dispatcher);
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\InvalidParameterException
     * @dataProvider invalidOptionsDataProvider
     * @param array $options
     */
    public function testInitializeException(array $options)
    {
        $this->action->initialize($options);
    }

    /**
     * @return array
     */
    public function invalidOptionsDataProvider()
    {
        return [
            [[]],
            [[1, 2]]
        ];
    }

    public function testInitialize()
    {
        $target = new \stdClass();
        $this->action->initialize([$target]);
        $this->assertAttributeEquals($target, 'target', $this->action);
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\InvalidParameterException
     * @expectedExceptionMessage Action "remove_entity" expects reference to entity as parameter, string is given.
     */
    public function testExecuteNotObjectException()
    {
        $context = new \stdClass();
        $target = 'test';
        $this->action->initialize([$target]);
        $this->action->execute($context);
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\NotManageableEntityException
     * @expectedExceptionMessage Entity class "stdClass" is not manageable.
     */
    public function testExecuteNotManageableException()
    {
        $context = new \stdClass();
        $context->test = new \stdClass();
        $target = new PropertyPath('test');

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(get_class($context->test))
            ->will($this->returnValue(null));

        $this->action->initialize([$target]);
        $this->action->execute($context);
    }

    public function testExecute()
    {
        $context = new \stdClass();
        $context->test = new \stdClass();
        $target = new PropertyPath('test');

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $em->expects($this->once())
            ->method('remove')
            ->with($context->test);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(get_class($context->test))
            ->will($this->returnValue($em));

        $this->action->initialize([$target]);
        $this->action->execute($context);
    }
}
