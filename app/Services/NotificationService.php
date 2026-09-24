<?php

namespace App\Services;

use App\Models\AcademicClass;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\PermissionsUpdatedNotification;
use App\Notifications\StaffCreatedNotification;
use App\Notifications\StaffWelcomeNotification;
use App\Notifications\StudentAdmittedNotification;
use App\Notifications\TeacherAllocatedNotification;
use App\Notifications\TimetablePublishedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationService
{
    /**
     * Deliver a notification to the owner of an institute.
     */
    public static function toInstituteOwner(int $instituteId, BaseNotification|array|Notification $notification): void
    {
        $owner = Institute::query()->find($instituteId)?->owner;

        if ($owner?->email) {
            foreach (Arr::wrap($notification) as $item) {
                NotificationFacade::send($owner, $item);
            }
        }
    }

    /**
     * Deliver a notification to one or more users.
     */
    public static function toUsers(User|iterable $users, BaseNotification|array|Notification $notification): void
    {
        $recipients = $users instanceof User ? [$users] : $users;

        foreach ($recipients as $user) {
            if ($user instanceof User && $user->email) {
                foreach (Arr::wrap($notification) as $item) {
                    NotificationFacade::send($user, $item);
                }
            }
        }
    }

    /**
     * Deliver a notification to every super admin user. If a dedicated super
     * admin inbox is configured, the notification email is also queued there.
     */
    public static function toSuperAdmins(BaseNotification|array|Notification $notification): void
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->whereNotNull('email')
            ->get();

        if ($admins->isNotEmpty()) {
            foreach (Arr::wrap($notification) as $item) {
                NotificationFacade::send($admins, $item);
            }
        }

        $address = config('services.super_admin_email');

        if ($address && $notification instanceof BaseNotification) {
            Mail::to($address)->queue($notification->email());
        }
    }

    /**
     * Instantly meaningfully notify newly created staff: a standard in-app
     * welcome plus a high priority email with the temporary password.
     */
    public static function staffCreated(User $staff, string $password, int $instituteId): void
    {
        $instituteName = Institute::query()->whereKey($instituteId)->value('name') ?? 'your institute';
        $loginUrl = config('app.staff_login_url') ?: url('/');

        $notifications = [
            new StaffCreatedNotification($staff, $instituteName),
            new StaffWelcomeNotification($staff, $password, $loginUrl),
        ];

        foreach ($notifications as $item) {
            NotificationFacade::send($staff, $item);
        }
    }

    /**
     * Notify a teacher that they have been allocated to a class/section/subject.
     */
    public static function teacherAssigned(User $teacher, int $instituteId, string $details): void
    {
        $instituteName = Institute::query()->whereKey($instituteId)->value('name') ?? 'Your institute';

        self::toUsers($teacher, new TeacherAllocatedNotification($teacher, $instituteName, $details));
    }

    /**
     * Notify the teachers affected by a newly published timetable.
     */
    public static function timetablePublished(int $instituteId, int $sessionId, array $classIds): void
    {
        if ($classIds === []) {
            return;
        }

        $instituteName = Institute::query()->whereKey($instituteId)->value('name') ?? 'Your institute';
        $classNames = AcademicClass::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $classIds)
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->implode(', ');

        $teacherIds = TimetableEntry::query()
            ->where('session_id', $sessionId)
            ->whereIn('class_id', $classIds)
            ->whereNotNull('teacher_user_id')
            ->pluck('teacher_user_id')
            ->unique()
            ->values();

        $teachers = User::query()->whereIn('id', $teacherIds)->get();

        foreach ($teachers as $teacher) {
            self::toUsers($teacher, new TimetablePublishedNotification($instituteName, $classNames));
        }
    }

    /**
     * Notify admins and receptionists of an institute about a completed
     * student admission.
     */
    public static function studentAdmitted(int $instituteId, string $studentName, string $classSection, int $requesterId): void
    {
        $instituteName = Institute::query()->whereKey($instituteId)->value('name') ?? 'Your institute';

        $recipients = User::query()
            ->whereKeyNot($requesterId)
            ->whereHas('instituteUsers', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('is_active', true))
            ->whereHas('roles', function ($query) use ($instituteId) {
                $query->where('roles.institute_id', $instituteId);
                $query->where(function ($roleQuery) {
                    $roleQuery->whereIn('roles.name', ['admin', 'receptionist'])
                        ->orWhere('roles.name', 'like', '%reception%')
                        ->orWhere('roles.name', 'like', '%admin%');
                });
            })
            ->get();

        self::toUsers($recipients, new StudentAdmittedNotification($instituteName, $studentName, $classSection));
    }

    /**
     * Notify every user currently holding a role about a permissions change.
     */
    public static function permissionsUpdated(int $instituteId, int $roleId, string $roleName): void
    {
        $users = User::query()
            ->whereHas('instituteUsers', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('is_active', true))
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.id', $roleId)
                ->where('roles.institute_id', $instituteId))
            ->get();

        foreach ($users as $user) {
            self::toUsers($user, new PermissionsUpdatedNotification($user, $roleName));
        }
    }

    /**
     * Total unread notifications for a user (used for the badge).
     */
    public static function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
