<?php
namespace Tests\Models;

use Enicore\Rivena\Models\HasPublicId;
use Enicore\Rivena\Models\User;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Tests\Support\Database;

#[CoversTrait(HasPublicId::class)]
class HasPublicIdTest extends TestCase
{
    protected function setUp(): void
    {
        Database::create();
    }

    public function testCreate(): void
    {
        $user = User::forceCreate(['email' => 'a@example.com', 'password' => 'x', 'created_at' => 1]);

        $this->assertMatchesRegularExpression('/^[bcdfghjklmnpqrstvwxyz256789]{16}$/', $user->public_id);
        $this->assertSame($user->id, User::findByPublicId($user->public_id)->id);
        // not queried (MySQL can't compare non-ASCII with the ASCII column)
        $this->assertNull(User::findByPublicId(''));
        $this->assertNull(User::findByPublicId(str_repeat('é', 16)));
        $this->assertNull(new User()->public_id);
    }

    public function testFirstUse(): void
    {
        // a row inserted without Eloquent, like rivena init's first admin
        User::getConnectionResolver()->connection()->insert(
            "INSERT INTO users (email, password, created_at) VALUES ('b@example.com', 'x', 1)");
        $publicId = User::first()->public_id;

        $this->assertMatchesRegularExpression('/^[bcdfghjklmnpqrstvwxyz256789]{16}$/', $publicId);
        $this->assertSame($publicId, User::first()->public_id);
    }
}
