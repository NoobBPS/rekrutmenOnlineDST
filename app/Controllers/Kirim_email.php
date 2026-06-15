<?php
namespace App\Controllers;

use App\Controllers\BaseController;

class Kirim_email extends BaseController
{
    // Show a simple form for sending a test email (optional view)
    public function index()
    {
        // For quick testing you can directly call send() via POST
        return view('kirim_email_form');
    }

    // Process the form and send the email using the sendMail helper
    public function send()
    {
        // Retrieve POST data (add validation as needed)
        $to      = $this->input->post('email');
        $subject = $this->input->post('subject') ?? 'Test Email';
        $body    = $this->input->post('message') ?? 'Hello from DST Recruitment';

        // Use the existing helper (app/helpers/mail.php)
        $result = sendMail($to, $subject, $body);

        if ($result['success']) {
            setFlash('success', 'Email berhasil dikirim!');
        } else {
            setFlash('error', 'Gagal mengirim email: ' . $result['message']);
        }
        redirect('kirim_email');
    }
}
?>
