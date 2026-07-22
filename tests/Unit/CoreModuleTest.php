<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Modules\Core\DTOs\BaseDTO;
use Modules\Core\ValueObjects\ValueObject;

class DummyDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public int $age
    ) {}
}

class DummyValueObject extends ValueObject
{
    public function __construct(
        public string $currency,
        public int $amount
    ) {}
}

class CoreModuleTest extends TestCase
{
    /**
     * Test that BaseDTO can correctly instantiate and convert to array.
     */
    public function test_dto_can_be_created_from_array(): void
    {
        $data = ['name' => 'John Doe', 'age' => 30];
        $dto = DummyDTO::fromArray($data);

        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals(30, $dto->age);
        $this->assertEquals($data, $dto->toArray());
    }

    /**
     * Test that ValueObject equality checks function as expected.
     */
    public function test_value_object_equality(): void
    {
        $vo1 = new DummyValueObject('USD', 100);
        $vo2 = new DummyValueObject('USD', 100);
        $vo3 = new DummyValueObject('EUR', 100);
        $vo4 = new DummyValueObject('USD', 200);

        $this->assertTrue($vo1->equals($vo2));
        $this->assertFalse($vo1->equals($vo3));
        $this->assertFalse($vo1->equals($vo4));
    }
}
