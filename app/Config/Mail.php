<?php
/**
 * Mail Configuration - DST Recruitment
 */

if (!defined('MAIL_HOST')) define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
if (!defined('MAIL_PORT')) define('MAIL_PORT', (int) (getenv('MAIL_PORT') ?: 587));
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
if (!defined('MAIL_FROM')) define('MAIL_FROM', getenv('MAIL_FROM') ?: MAIL_USERNAME);
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'DST Recruitment');
