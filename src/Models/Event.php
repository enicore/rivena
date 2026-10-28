<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An event log entry: who did what, and when. Controller::logEvent() adds them.
 *
 * @package Enicore\Rivena
 */
class Event extends Model
{
    public $timestamps = false;
}
