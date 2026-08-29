<?php

namespace App\Models;

use CodeIgniter\Model;

class GalleryCategoryModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function get_all_categories()
    {
        $query = "SELECT slug, name FROM gallery_category ORDER BY id DESC";
        $result = $this->db->query($query);
        return $result->getResult();
    }
}
