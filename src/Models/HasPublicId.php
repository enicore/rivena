<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Models;

use Enicore\Rivena\Classes\Crypt;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Random\RandomException;

/**
 * Gives a model a random public_id (16 characters) to use in URLs and JSON instead of the database ID. The table needs
 * the column (see sql/app.sql). Find a row with User::findByPublicId($id).
 *
 * @package Enicore\Rivena
 */
trait HasPublicId
{
    /**
     * Returns the row with the public ID, or null (a malformed ID too: the column is ASCII, other characters would make
     * the query fail).
     *
     * @param string $id
     * @return static|null
     */
    public static function findByPublicId(string $id): ?static
    {
        return preg_match('/^[a-z0-9]{16}$/', $id) ? static::where('public_id', $id)->first() : null;
    }

    /**
     * Sets a public ID on new rows.
     *
     * @return void
     */
    protected static function bootHasPublicId(): void
    {
        static::creating(fn(self $model) => $model->setAttribute('public_id', self::newPublicId()));
    }

    /**
     * Returns the public ID, setting one first for rows added without Eloquent.
     *
     * @return Attribute
     */
    protected function publicId(): Attribute
    {
        return Attribute::get(function (?string $value) {
            if ($value === null && $this->exists) {
                $this->forceFill(['public_id' => $value = self::newPublicId()])->saveQuietly();
            }

            return $value;
        });
    }

    /**
     * Returns a random public ID that no row has.
     *
     * @return string
     * @throws RandomException
     */
    private static function newPublicId(): string
    {
        do {
            // alphabet without vowels and 01234 to prevent swear words
            $id = Crypt::generateRandomToken(16, 'bcdfghjklmnpqrstvwxyz256789');
        } while (static::where('public_id', $id)->exists());

        return $id;
    }
}
