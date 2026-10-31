<?php

namespace App\Models;

use CodeIgniter\Model;

class SessionModel extends Model
{
    protected $table = 'sesiones_asistencia';
    protected $primaryKey = 'id';
    protected $allowedFields = ['curso_id', 'sesion_cuatrimestre_id', 'asistencia_registrada'];
    protected $returnType = 'array';
}
