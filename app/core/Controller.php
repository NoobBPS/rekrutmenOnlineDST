<?php
/**
 * Base Controller
 */

class Controller {
    
    protected $db;
    
    public function __construct() {
        $this->db = new Model();
    }
    
    protected function view($view, $data = []) {
        extract($data);
        
        $view_file = APPPATH . 'Views/' . $view . '.php';
        
        if (!file_exists($view_file)) {
            echo "View not found: $view";
            return;
        }
        
        require $view_file;
    }
    
    protected function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function redirect($url) {
        header('Location: ' . BASE_URL . $url);
        exit;
    }
    
    protected function requireLogin() {
        if (!isLoggedIn()) {
            setFlash('warning', 'Silakan login terlebih dahulu');
            $this->redirect('auth/login');
        }
    }
    
    protected function requireHRD() {
        $this->requireLogin();

        if (!hasRole('hrd') && !hasRole('admin')) {
            setFlash('error', 'Akses ditolak');
            $this->redirect('dashboard');
        }
    }
}
