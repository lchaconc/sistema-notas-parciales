<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialDataSeeder extends Seeder
{
    public function run()
    {
        $this->db->table('dominios_permitidos')->ignore(true)->insert(['dominio' => 'ucatolica.ac.cr']);

        foreach ([
            'prueba parcial',
            'proyecto',
            'tarea',
            'trabajo en clase',
            'indagación y exposición',
            'prueba corta',
            'repositorio GitHub',
        ] as $nombre) {
            $this->db->table('catalogo_actividades')->ignore(true)->insert(['nombre' => $nombre]);
        }
    }
}
