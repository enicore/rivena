<?php
// The site's configuration; .env overrides these values. Config::load() includes this file.

/** @var array $config */

// The site's name, in the page titles, the admin area's header and the emails.
//
// Type: string
// Default: none, required
$config['site_name'] = 'Website';

// The path of the user and admin pages.
//
// Type: string
// Default: '/usr'
$config['user_path'] = '/usr';

// How many months the event log keeps.
//
// Type: integer
// Allowed: positive integer
// Default: 6
$config['event_log_retention_months'] = 6;

// The address that gets the site's errors, at most one email an hour, never with app_env=dev.
//
// Type: string
// Allowed: an email address, or '' for no emails
// Default: ''
$config['error_notification_email'] = '';

// The rows of a table page in the admin area.
//
// Type: integer
// Allowed: positive integer
// Default: 25
$config['table_rows_per_page'] = 25;

// The two-factor authentication methods users can set up, in this order.
//
// Type: array
// Allowed: array containing 'totp', 'webauthn'
// Default: ['totp', 'webauthn']
$config['two_factor_methods'] = ['totp', 'webauthn'];

// Whether every user has to set up two-factor authentication.
//
// Type: boolean
// Allowed: true, false
// Default: false
$config['two_factor_required'] = false;

// Whether users get an email when their two-factor authentication changes (needs SMTP).
//
// Type: boolean
// Allowed: true, false
// Default: true
$config['two_factor_email_notifications'] = true;

// The shortest password allowed.
//
// Type: integer
// Allowed: 10 to 30
// Default: 12
$config['password_min_length'] = 12;

// The longest password allowed.
//
// Type: integer
// Allowed: 40 to 80
// Default: 60
$config['password_max_length'] = 60;

// The password strength needed (zxcvbn's score).
//
// Type: integer
// Allowed: 1 to 4
// Default: 3
$config['password_min_score'] = 3;

// The largest uploaded image, in megapixels; GD takes about 4 MB a megapixel.
//
// Type: integer
// Allowed: positive integer
// Default: 25
$config['image_max_megapixels'] = 25;
