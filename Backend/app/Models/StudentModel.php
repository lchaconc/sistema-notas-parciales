<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table = 'estudiantes';
    protected $primaryKey = 'usuario_id';
    protected $useAutoIncrement = false;
    protected $allowedFields = ['usuario_id', 'nombre', 'primer_apellido', 'segundo_apellido'];
    protected $returnType = 'array';
}
