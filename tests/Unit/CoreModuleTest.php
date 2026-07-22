<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Modules\Core\DTOs\BaseDTO;
use Modules\Core\ValueObjects\ValueObject;
use Modules\Core\Traits\HasUUID;
use Modules\Core\Traits\HasOptimisticLocking;
use Illuminate\Database\Eloquent\Model;

readonly class DummyDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public int $age
    ) {}
}

readonly class DummyValueObject extends ValueObject
{
    public function __construct(
        public string $currency,
        public int $amount
    ) {}
}

/**
 * @property int $version
 */
class DummyModel extends Model
{
    use HasUUID;
    use HasOptimisticLocking;

    /**
     * @var array<string>
     */
    protected $guarded = [];
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

    /**
     * Test that HasUUID trait methods behave correctly.
     */
    public function test_has_uuid_configuration(): void
    {
        $model = new DummyModel();
        $this->assertFalse($model->getIncrementing());
        $this->assertEquals('string', $model->getKeyType());
    }
}
