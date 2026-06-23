<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;

class Mail extends BaseConfig
{
    public string $host = '';
    public int $port = 587;
    public string $secure = '';
    public string $username = '';
    public string $password = '';
    public string $from = '';
    public string $fromName = 'DST Recruitment';
    public int $timeout = 30;

    public function __construct()
    {
        parent::__construct();
        $this->host = (string) env('MAIL_HOST', $this->host);
        $this->port = (int) env('MAIL_PORT', $this->port);
        $this->secure = (string) env('MAIL_SECURE', $this->secure);
        $this->username = (string) env('MAIL_USERNAME', $this->username);
        $this->password = (string) env('MAIL_PASSWORD', $this->password);
        $this->from = (string) env('MAIL_FROM', $this->from);
        $this->fromName = (string) env('MAIL_FROM_NAME', $this->fromName);
        $this->timeout = (int) env('MAIL_TIMEOUT', $this->timeout);
        if ($this->secure === '') {
            $this->secure = $this->port === 465 ? 'ssl' : 'tls';
        }
    }
}
