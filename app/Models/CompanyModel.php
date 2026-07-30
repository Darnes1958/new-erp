<?php

namespace App\Models;

use App\Models\Concerns\UsesCompanyConnection;
use Illuminate\Database\Eloquent\Model;

abstract class CompanyModel extends Model
{
    use UsesCompanyConnection;
}
