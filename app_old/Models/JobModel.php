<?php
namespace App\Models;

use CodeIgniter\Model;

class JobModel extends Model
{
    protected $table = 'jobs';
    protected $primaryKey = 'job_id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'title', 'department', 'location', 'type',
        'salary_min', 'salary_max', 'description',
        'requirements', 'skills', 'status', 'created_by'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
