<?php

namespace App\Models;

use CodeIgniter\Model;

class EnrollmentModel extends Model
{
    protected $table = 'matriculas';
    protected $primaryKey = 'id';
    protected $allowedFields = ['estudiante_id', 'curso_id'];
    protected $returnType = 'array';
}
