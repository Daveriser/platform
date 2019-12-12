<?php

namespace Oro\Bundle\DraftBundle\Tests\Unit\Action;

use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\DraftBundle\Action\AssignDraftableFields;
use Oro\Bundle\DraftBundle\Helper\DraftHelper;
use Oro\Bundle\DraftBundle\Tests\Unit\Stub\DraftableEntityStub;
use Oro\Bundle\SecurityBundle\Tools\UUIDGenerator;
use Oro\Component\Action\Action\ActionInterface;
use Oro\Component\ConfigExpression\ContextAccessor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyPath;

class AssignDraftableFieldsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ContextAccessor
     */
    private $contextAccessor;

    /**
     * @var DraftHelper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $draftHelper;

    /**
     * @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $eventDispatcher;

    /**
     * @var AssignDraftableFields
     */
    private $action;

    protected function setUp(): void
    {
        $this->contextAccessor = new ContextAccessor();
        $this->draftHelper = $this->createMock(DraftHelper::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->action = new AssignDraftableFields($this->contextAccessor, $this->draftHelper);
        $this->action->setDispatcher($this->eventDispatcher);
    }

    public function testInitialize(): void
    {
        $this->expectExceptionMessage('The required options "attribute", "object" are missing.');
        $this->action->initialize([]);

        $options = [
            'object' => new PropertyPath('object'),
            'attribute' => new PropertyPath('attribute')
        ];

        $this->assertInstanceOf(ActionInterface::class, $this->action->initialize($options));
    }

    public function testExecute(): void
    {
        $draft = new DraftableEntityStub();
        $draft->setDraftUuid(UUIDGenerator::v4());
        $context = new ActionData(['object' => $draft]);

        $this->draftHelper->expects($this->once())
            ->method('getDraftableProperties')
            ->willReturn(['content']);

        $this->action->initialize([
            'object' => new PropertyPath('object'),
            'attribute' => new PropertyPath('attribute')
        ]);

        $this->action->execute($context);
        $this->assertSame(['content'], $context->get('attribute'));
    }

    public function testExecuteNotDraftable(): void
    {
        $entity = new \stdClass();
        $context = new ActionData(['object' => $entity]);

        $this->action->execute($context);
        $this->assertNull($context->get('attribute'));
    }
}
