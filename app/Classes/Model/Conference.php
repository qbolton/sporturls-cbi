<?php

namespace SportUrls\Classes\Model;

use Illuminate\Database\Eloquent\Model;

class Conference extends Model
{
    // primary key
    public $primaryKey = 'conf_id';
    // timestamps
    public $timestamps = false;
}
