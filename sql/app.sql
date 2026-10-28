-- Rivena's tables, for a new database (rivena init and rivena migrate); the existing ones get the changes from
-- sql/migrations.

-- the migrations of sql/migrations that the database has (Console)
CREATE TABLE IF NOT EXISTS `rivena_migrations` (
  `name` varchar(100) CHARACTER SET ascii NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the users (Enicore\Rivena\Models\User); Auth checks the passwords (password_hash()) and the sessions (a session
-- ends when its session_version isn't the user's, e.g. after a password change)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(16) CHARACTER SET ascii DEFAULT NULL,
  `email` varchar(249) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET ascii NOT NULL,
  `disabled` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `session_version` int unsigned NOT NULL DEFAULT 0,
  `created_at` int unsigned NOT NULL,
  `last_login_at` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `public_id` (`public_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the roles (Enicore\Rivena\Models\Role); role 1 is "User", every user's, built in; the admins (admins in .env) have
-- every permission without a role
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (1, 'User');

-- the users' roles (not role 1, which every user has)
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_roles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the roles' permissions (the controllers declare them, see Role::permissions()); users can log in by default
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int unsigned NOT NULL,
  `permission` varchar(100) CHARACTER SET ascii NOT NULL,
  PRIMARY KEY (`role_id`, `permission`),
  CONSTRAINT `role_permissions_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission`) VALUES (1, 'login');

-- the "remember me" cookies (Auth): the cookie has the selector and the validator, the table the validator's SHA-256;
-- a token works only with the user's session_version of its creation
CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
  `selector` char(24) CHARACTER SET ascii NOT NULL,
  `user_id` int unsigned NOT NULL,
  `hash` char(64) CHARACTER SET ascii NOT NULL,
  `session_version` int unsigned NOT NULL,
  `expires_at` int unsigned NOT NULL,
  PRIMARY KEY (`selector`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `user_remember_tokens_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the password reset links (Auth), one a user: the link has the token, the table its SHA-256; a link works for an
-- hour, once
CREATE TABLE IF NOT EXISTS `password_resets` (
  `user_id` int unsigned NOT NULL,
  `hash` char(64) CHARACTER SET ascii NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `password_resets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the failed logins of the last 10 minutes (Auth throttles an email from an IP address with too many)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` int unsigned NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(249) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `ip_email` (`ip`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the users' settings (Enicore\Rivena\Models\UserSettings), as a JSON object, so new settings need no schema change
CREATE TABLE IF NOT EXISTS `user_settings` (
  `user_id` int unsigned NOT NULL,
  `settings` json NOT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_settings_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the site's errors and warnings (Enicore\Rivena\Classes\ErrorLog, the Error Log page): a row a message, with the
-- times it was seen and the last one's path; kept 30 days after the last time
CREATE TABLE IF NOT EXISTS `error_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `hash` char(40) CHARACTER SET ascii NOT NULL,
  `level` varchar(10) CHARACTER SET ascii NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int unsigned NOT NULL,
  `first_at` int unsigned NOT NULL,
  `last_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `last_at` (`last_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the event log (Enicore\Rivena\Models\Event): who did what, and when
CREATE TABLE IF NOT EXISTS `events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `user_email` varchar(249) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the users' two-factor authentication methods (Enicore\Rivena\TwoFactor\TwoFactor): a row a method, its data (e.g. the
-- TOTP secret, the recovery codes) encrypted with app_key (Crypt::encrypt())
CREATE TABLE IF NOT EXISTS `user_two_factor` (
  `user_id` int unsigned NOT NULL,
  `method` varchar(20) CHARACTER SET ascii NOT NULL,
  `data` text CHARACTER SET ascii NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`user_id`, `method`),
  CONSTRAINT `user_two_factor_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
