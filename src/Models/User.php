<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Models;

use DateTimeImmutable;
use DateTimeZone;
use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Config;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

/**
 * A user. Set the password with Auth::setPassword(), which also hashes it.
 *
 * @package Enicore\Rivena
 */
class User extends Model
{
    use HasPublicId;

    public $timestamps = false;
    protected $hidden = ['password'];
    protected $casts = ['disabled' => 'bool', 'session_version' => 'int'];

    /**
     * Returns the user's settings (empty ones if the user has none).
     *
     * @return HasOne
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class, 'user_id')->withDefault();
    }

    /**
     * Returns the user's roles (without role 1, which every user has).
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Returns true if the user (the logged-in one by default) has the permission: an admin, or one of its roles (with
     * role 1, every user's) has it. A permission that isn't in Role::permissions() throws (a typo).
     *
     * @param string $permission
     * @param int|null $id
     * @return bool
     * @throws InvalidArgumentException
     */
    public static function can(string $permission, ?int $id = null): bool
    {
        if (!isset(Role::permissions()[$permission])) {
            throw new InvalidArgumentException("Unknown permission: $permission");
        }

        $id ??= Auth::id();

        if (!$id) {
            return false;
        }

        if (self::isAdmin($id)) {
            return true;
        }

        return DB::table('role_permissions')->where('permission', $permission)
            ->where(fn($query) => $query->where('role_id', Role::USER)
                ->orWhereIn('role_id', DB::table('user_roles')->select('role_id')->where('user_id', $id)))
            ->exists();
    }

    /**
     * Returns true if the user (the logged-in one by default) is an admin (admins in .env).
     *
     * @param int|null $id
     * @return bool
     */
    public static function isAdmin(?int $id = null): bool
    {
        $id ??= Auth::id();

        return $id > 0 && in_array($id, array_map('intval', explode(',', (string)Config::get('admins'))), true);
    }

    /**
     * Returns the time formatted with the user's date and time format, in the user's time zone.
     *
     * @param int $time
     * @return string
     * @throws \DateInvalidTimeZoneException
     */
    public function formatTime(int $time): string
    {
        $settings = $this->settings;

        return (new DateTimeImmutable("@$time"))
            ->setTimezone(new DateTimeZone($settings->get('timezone')))
            ->format($settings->get('date_format') . ' ' . $settings->get('time_format'));
    }
}
