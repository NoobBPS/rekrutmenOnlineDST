<?php
namespace App\Models;

use CodeIgniter\Model;

class ApplicationModel extends Model
{
    protected $table = 'applications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id', 'job_id', 'cv_file', 'cover_letter',
        'status', 'score', 'notes', 'decision_reason',
        'decision_saw_summary', 'decision_at'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'applied_at';
    protected $updatedField = 'updated_at';
}
