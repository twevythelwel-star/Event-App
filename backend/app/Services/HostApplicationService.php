<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\TenantRole;
use App\Exceptions\SecurityException;
use App\Models\HostApplication;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HostApplicationService
{
    /**
     * Submit a new Event Host application on behalf of an authenticated Attendee.
     *
     * @param User $user
     * @param array<string, mixed> $data
     * @return HostApplication
     * @throws \InvalidArgumentException|SecurityException
     */
    public static function submitApplication(User $user, array $data): HostApplication
    {
        if ($user->isSuspended()) {
            throw new SecurityException('Suspended accounts are prohibited from applying for Event Host privileges.');
        }

        // An existing host does not need to reapply
        if ($user->hasRole(AccountType::EVENT_HOST)) {
            throw new \InvalidArgumentException('You already hold active Event Host privileges on Island Events.');
        }

        // Check for existing pending application
        $existingPending = HostApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            throw new \InvalidArgumentException('You already have a pending Event Host application under review.');
        }

        $orgName = trim((string) ($data['organization_name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $country = strtoupper(trim((string) ($data['country'] ?? 'JM')));
        $contactPhone = trim((string) ($data['contact_phone'] ?? $user->phone ?? ''));
        $website = trim((string) ($data['website_or_social'] ?? ''));

        if (empty($orgName)) {
            throw new \InvalidArgumentException('Organization, brand, or promotional name is required.');
        }

        if (strlen($orgName) < 3) {
            throw new \InvalidArgumentException('Organization name must be at least 3 characters in length.');
        }

        if (!in_array($country, ['JM', 'US'], true)) {
            $country = 'JM';
        }

        if (empty($contactPhone)) {
            throw new \InvalidArgumentException('A contact phone number is required for event operations verification.');
        }

        $application = HostApplication::create([
            'user_id' => $user->id,
            'organization_name' => $orgName,
            'description' => $description ?: null,
            'country' => $country,
            'contact_phone' => $contactPhone,
            'website_or_social' => $website ?: null,
            'status' => 'pending',
        ]);

        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $user->getActiveRole()->value,
            'action' => 'host_application_submitted',
            'target_entity' => 'host_applications',
            'target_id' => (string) $application->id,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'WebBrowser/1.0',
            'details' => "User {$user->email} submitted Event Host application for '{$orgName}' (Country: {$country}).",
            'is_pii_access' => false,
            'created_at' => now(),
        ]);

        return $application;
    }

    /**
     * Approve an Event Host application. Only Administrators or System Administrators can approve.
     *
     * @param User $reviewer
     * @param int|string $applicationId
     * @param string|null $notes
     * @return HostApplication
     * @throws SecurityException|\InvalidArgumentException
     */
    public static function approveApplication(User $reviewer, int|string $applicationId, ?string $notes = null): HostApplication
    {
        $isAdmin = $reviewer->hasRole(AccountType::ADMINISTRATOR) || $reviewer->hasRole(AccountType::SYSTEM_ADMIN);
        if (!$isAdmin) {
            throw new SecurityException('Unauthorized: Only platform Administrators may approve Event Host applications.');
        }

        $application = HostApplication::findOrFail($applicationId);

        if (!$application->isPending()) {
            throw new \InvalidArgumentException("Cannot approve application in '{$application->status}' status.");
        }

        $applicant = $application->user;
        if (!$applicant) {
            throw new \InvalidArgumentException('Applicant user account could not be found.');
        }

        // Nobody grants themselves Event Host privileges
        if ($applicant->id === $reviewer->id) {
            throw new SecurityException('Unauthorized: administrators cannot approve their own Event Host application.');
        }

        return DB::transaction(function () use ($application, $applicant, $reviewer, $notes) {
            // 1. Update application status
            $application->status = 'approved';
            $application->reviewed_by = $reviewer->id;
            $application->reviewed_at = now();
            $application->review_notes = $notes;
            $application->save();

            // 2. Assign explicit Event Host role in user_roles
            $existingRole = UserRole::where('user_id', $applicant->id)
                ->where('role', AccountType::EVENT_HOST->value)
                ->first();

            if (!$existingRole) {
                UserRole::create([
                    'user_id' => $applicant->id,
                    'role' => AccountType::EVENT_HOST->value,
                    'is_active' => true,
                    'assigned_by' => $reviewer->id,
                    'assigned_at' => now(),
                ]);
            } else {
                $existingRole->is_active = true;
                $existingRole->save();
            }

            // 3. Provision the applicant's own organizer (tenant) unless they already own one
            $alreadyOwnsOrganizer = OrganizerUser::where('user_id', $applicant->id)
                ->where('role', TenantRole::EVENT_OWNER->value)
                ->exists();

            if (!$alreadyOwnsOrganizer) {
                $organizer = Organizer::create([
                    'name' => $application->organization_name,
                    'slug' => Str::slug($application->organization_name) . '-' . Str::lower(Str::random(6)),
                    'status' => 'active',
                    'business_location' => $application->country,
                    'default_currency' => $application->country === 'US' ? 'USD' : 'JMD',
                    'branding' => ['contact_phone' => $application->contact_phone],
                ]);

                OrganizerUser::create([
                    'organizer_id' => $organizer->id,
                    'user_id' => $applicant->id,
                    'role' => TenantRole::EVENT_OWNER->value,
                    'is_active' => true,
                ]);
                // Ownership lives in organizer_users; the users table has no organizer_id column
            }

            // 4. Audit Log
            SecurityAuditLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $reviewer->id,
                'actor_role' => $reviewer->getActiveRole()->value,
                'action' => 'host_application_approved',
                'target_entity' => 'host_applications',
                'target_id' => (string) $application->id,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'WebBrowser/1.0',
                'details' => "Administrator {$reviewer->email} approved Event Host application #{$application->id} for {$applicant->email} ('{$application->organization_name}').",
                'is_pii_access' => false,
                'created_at' => now(),
            ]);

            return $application;
        });
    }

    /**
     * Reject an Event Host application with audit justification.
     *
     * @param User $reviewer
     * @param int|string $applicationId
     * @param string $reason
     * @param string|null $notes
     * @return HostApplication
     * @throws SecurityException|\InvalidArgumentException
     */
    public static function rejectApplication(User $reviewer, int|string $applicationId, string $reason, ?string $notes = null): HostApplication
    {
        $isAdmin = $reviewer->hasRole(AccountType::ADMINISTRATOR) || $reviewer->hasRole(AccountType::SYSTEM_ADMIN);
        if (!$isAdmin) {
            throw new SecurityException('Unauthorized: Only platform Administrators may reject Event Host applications.');
        }

        $application = HostApplication::findOrFail($applicationId);

        if (!$application->isPending()) {
            throw new \InvalidArgumentException("Cannot reject application in '{$application->status}' status.");
        }

        $reason = trim($reason);
        if (empty($reason)) {
            throw new \InvalidArgumentException('A clear rejection reason must be provided for the applicant.');
        }

        $application->status = 'rejected';
        $application->rejection_reason = $reason;
        $application->reviewed_by = $reviewer->id;
        $application->reviewed_at = now();
        $application->review_notes = $notes;
        $application->save();

        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $reviewer->id,
            'actor_role' => $reviewer->getActiveRole()->value,
            'action' => 'host_application_rejected',
            'target_entity' => 'host_applications',
            'target_id' => (string) $application->id,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'WebBrowser/1.0',
            'details' => "Administrator {$reviewer->email} rejected Event Host application #{$application->id} for {$application->user?->email}. Reason: {$reason}",
            'is_pii_access' => false,
            'created_at' => now(),
        ]);

        return $application;
    }

    /**
     * Retrieve all pending host applications.
     *
     * @return Collection<int, HostApplication>
     */
    public static function getPendingApplications(): Collection
    {
        return HostApplication::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
    }
}
