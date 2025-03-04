<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultSettings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_default_settings";

    public $timestamps = false;

    protected $primaryKey = 'default_setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    // Add fields to allow mass assignment
    protected $fillable = [
        'default_setting_uuid',
        'default_setting_category',
        'default_setting_subcategory',
        'default_setting_name',
        'default_setting_value',
        'default_setting_order',
        'default_setting_enabled',
        'default_setting_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];
}
