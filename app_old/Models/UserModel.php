<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'email', 'password', 'full_name', 'role', 'phone',
        'education', 'skills', 'experience_years', 'cv_file',
        'bio', 'avatar', 'status', 'reset_token', 'reset_token_expires'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
