<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    use HasFactory;

     /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'persons';

    protected $fillable = [
        'name',
        'lastname',
    ];

    public function contacts() 
    {
        return $this->hasMany(Contact::class);
    }
    
}
