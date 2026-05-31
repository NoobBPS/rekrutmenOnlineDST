<?php
/**
 * Mail Configuration - DST Recruitment
 * 
 * For Gmail SMTP:
 * 1. Enable 2-Factor Authentication on Google account
 * 2. Go to https://myaccount.google.com/apppasswords
 * 3. Create an App Password and use it below
 */

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', (int) (getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'DST Recruitment');
