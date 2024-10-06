<?php

namespace App\Models;

enum PointsCategoryModel: int
{
    case Training = 1;
    case Work = 2;
    case Blame = 3;
    case Warning = 4;
    case Correction = 5;

    public function asText()
    {
        switch ($this)
        {
            case static::Training: return "Training";
            case static::Work: return "Métier";
            case static::Blame: return "Blâme";
            case static::Warning: return "Avertissement";
            case static::Correction: return "Correction";
        }
    }
}
