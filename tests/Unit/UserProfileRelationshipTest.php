<?php

namespace Tests\Unit;

use App\Models\ReportProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_a_profile_relationship(): void
    {
        $user = User::factory()->create();

        $profile = $user->profile()->create([
            'user_id' => $user->id,
            'name' => 'Jane Doe',
            'relation' => \App\Enums\ProfileRelation::SELF->value,
        ]);

        $this->assertInstanceOf(ReportProfile::class, $user->profile);
        $this->assertSame($profile->id, $user->profile->id);
    }
}
