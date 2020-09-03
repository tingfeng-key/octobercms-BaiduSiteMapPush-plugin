<?php namespace Tingfeng\Baidusitemappush\Models;

use Model;

/**
 * Settings Model
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    // A unique code
    public $settingsCode = 'tingfeng_baidusitemappush_settings';

    // Reference to field configuration
    public $settingsFields = 'fields.yaml';
}
