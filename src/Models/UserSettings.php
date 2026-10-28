<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A user's settings (date and time format, time zone), stored as JSON.
 *
 * @package Enicore\Rivena
 */
class UserSettings extends Model
{
    public const array DATE_FORMATS = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'm/d/Y', 'j M Y', 'M j, Y'];
    public const array TIME_FORMATS = ['H:i', 'g:i A'];
    public const array DEFAULTS = [
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        'timezone' => 'UTC',
    ];

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'user_id';
    protected $casts = ['settings' => 'array'];
    protected $attributes = ['settings' => '{}'];

    /**
     * Returns the setting, or its default if it isn't set.
     *
     * @param string $name
     * @return mixed
     */
    public function get(string $name): mixed
    {
        return $this->settings[$name] ?? self::DEFAULTS[$name] ?? null;
    }

    /**
     * Sets the settings, keeping the others.
     *
     * @param array $settings
     * @return void
     */
    public function set(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);
    }
}
