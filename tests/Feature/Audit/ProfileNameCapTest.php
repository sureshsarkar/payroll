<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

/**
 * UI/UX audit P0-6 — pins the unified max:100 cap on the `name` field
 * across all PROFILE-UPDATE forms.
 *
 * Before this change the codebase had three different caps:
 *   - admin profile          max:190
 *   - student profile        max:50
 *   - instructor profile     max:50 (shares StudentProfileUpdateRequest)
 *   - student API profile    max:50
 *
 * After standardization they're all max:100. This test inspects the
 * validation rules via reflection so we catch drift without going
 * through the full HTTP / DB stack.
 *
 * REGISTER / CREATE forms (which are NOT profile updates) deliberately
 * stay at max:255 — those are full-name surfaces with different
 * semantics. This test does NOT pin them.
 */
class ProfileNameCapTest extends TestCase
{
    public function test_student_profile_request_caps_name_at_100(): void
    {
        $req = new \App\Http\Requests\Frontend\StudentProfileUpdateRequest();
        $rules = $req->rules();
        $this->assertArrayHasKey('name', $rules);
        $this->assertNameMaxIs100($rules['name'], 'StudentProfileUpdateRequest');
    }

    public function test_admin_profile_controller_inline_rules_cap_name_at_100(): void
    {
        // Admin\ProfileController defines rules inline in the controller
        // method (not in a FormRequest). Read the source and assert the
        // shape.
        $contents = file_get_contents(
            base_path('app/Http/Controllers/Admin/ProfileController.php')
        );
        $this->assertMatchesRegularExpression(
            "/'name'\s*=>\s*'required\|string\|max:100'/",
            $contents,
            'Admin\\ProfileController must declare name max:100 (UI/UX audit P0-6)'
        );
    }

    // LMS removal phase 2 (2026-08-27) — removed
    // test_student_api_profile_update_caps_name_at_100(). It inspected
    // app/Http/Controllers/API/DashboardController.php, the LMS mobile API's
    // student profile endpoint. The whole app/Http/Controllers/API directory
    // is deleted; there is no mobile client for the HR/Payroll product.

    /**
     * Assert that the given Laravel validation rule contains max:100
     * and NOT max:50 / max:190 / any other obvious drift.
     */
    private function assertNameMaxIs100(mixed $rule, string $context): void
    {
        $serialized = is_array($rule) ? implode('|', $rule) : (string) $rule;

        $this->assertStringContainsString('max:100', $serialized,
            "$context must include 'max:100'");
        $this->assertStringNotContainsString('max:50', $serialized,
            "$context must NOT still use max:50");
        $this->assertStringNotContainsString('max:190', $serialized,
            "$context must NOT still use max:190");
    }
}
