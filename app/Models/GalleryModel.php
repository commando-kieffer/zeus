<?php

namespace App\Models;

use CodeIgniter\Model;

class GalleryModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->table = "-";
    }

    public function create_entry($name, $category, $description, $url)
    {
        $query = "INSERT INTO gallery (name, category, description, url) VALUES (?, ?, ?, ?)";
        $this->db->query($query, array($name, $category, $description, $url));
        return $this->db->insertID();
    }
}
