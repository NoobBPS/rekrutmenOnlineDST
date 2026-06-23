<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session()->get('user_id')) {
            session()->setFlashdata('warning', 'Silakan login terlebih dahulu');
            return redirect()->to(base_url('auth/login'));
        }
        if (session()->get('role') !== 'admin') {
            session()->setFlashdata('error', 'Akses ditolak. Hanya admin yang dapat mengelola lowongan.');
            return redirect()->to(base_url('dashboard'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
