<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;
    protected $helpers = ['url', 'custom'];
    protected $session;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->session = \Config\Services::session();
    }

    protected function requireLogin()
    {
        if (!isLoggedIn()) {
            setFlash('warning', 'Silakan login terlebih dahulu');
            return redirect()->to(base_url('auth/login'));
        }
        return null;
    }

    protected function requireHRD()
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        if (!hasRole('hrd')) {
            setFlash('error', 'Akses ditolak');
            return redirect()->to(base_url('dashboard'));
        }
        return null;
    }

    protected function requireAdmin()
    {
        if ($redirect = $this->requireLogin()) {
            return $redirect;
        }

        if (!hasRole('admin')) {
            setFlash('error', 'Akses ditolak. Hanya admin yang dapat mengelola lowongan.');
            return redirect()->to(base_url('dashboard'));
        }
        return null;
    }
}
