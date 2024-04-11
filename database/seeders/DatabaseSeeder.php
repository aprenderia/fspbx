<?php

namespace Database\Seeders;

use App\Models\Groups;
use App\Models\Permissions;
use App\Models\DefaultSettings;
use Illuminate\Database\Seeder;
use App\Models\GroupPermissions;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $this->createMobileAppSettings();

        $this->createPermissions();

        $this->createGroupPermissions();

        Model::reguard();

    }

    private function createPermissions() {
        $permissions = [
            [
                'application_name'       => 'Message Settings',
                'permission_name'        => 'message_settings_list_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            // [
            //     'application_name'       => 'Example Application 1',
            //     'permission_name'        => 'edit',
            //     'permission_description' => 'Allows editing of Example Application 1 resources',
            // ],
            // Add more permissions as needed
        ];
    
        foreach ($permissions as $permission) {
            $existingPermission = Permissions::where('application_name', $permission['application_name'])
                                             ->where('permission_name', $permission['permission_name'])
                                             ->first();
    
            if (is_null($existingPermission)) {
                Permissions::create([
                    'application_name'        => $permission['application_name'],
                    'permission_name'        => $permission['permission_name'],
                    'insert_date'       => $permission['insert_date'],
                ]);
            }
        }
    }

    private function createGroupPermissions()
    {

        $group_permissions = [
            [
                'permission_name'        => 'message_settings_list_view',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],

        ];

        foreach ($group_permissions as $permission) {
            $existing_item = GroupPermissions::where('permission_name', $permission['permission_name'])
                ->first();

            if (empty($existing_item)) {
                // Add new permission
                GroupPermissions::create([
                    'permission_name'        => $permission['permission_name'],
                    'permission_protected'  => $permission['permission_protected'],
                    'permission_assigned'  => $permission['permission_assigned'],
                    'group_name'            => $permission['group_name'],
                    'group_uuid'            => $permission['group_uuid'],
                    'insert_date'       => $permission['insert_date'],
                ]);

            }
        }
    }

    private function createMobileAppSettings()
    {
        $settings = [
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'apple_store_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'google_play_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mac_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'windows_link',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mobile_app_conn_protocol',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "sip or tcp or sips",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'mobile_app_proxy',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "",
            ],
            [
                'default_setting_category'      => 'mobile_apps',
                'default_setting_subcategory'   => 'organization_region',
                'default_setting_name'          => 'text',
                'default_setting_value'         => "",
                'default_setting_enabled'       => true,
                'default_setting_description'   => "1 - US East, 2 - US West, 3 - Europe (Frankfurt), 4 - Asia Pacific (Singapore), 5 - Europe (London)",
            ],



        ];

        // Log::alert(Category::where('name', trans('custom-fields::general.categories.cost_recovery'))->where('company_id', $company_id)->value('id'));
        foreach ($settings as $setting) {
            $existing_item = DefaultSettings::where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->first();

            if (empty($existing_item)) {
                // Add new group
                DefaultSettings::create([
                    'default_setting_category'      => $setting['default_setting_category'],
                    'default_setting_subcategory'   => $setting['default_setting_subcategory'],
                    'default_setting_name'          => $setting['default_setting_name'],
                    'default_setting_value'         => $setting['default_setting_value'],
                    'default_setting_enabled'       => $setting['default_setting_enabled'],
                    'default_setting_description'   => $setting['default_setting_description'],
                ]);
            }
        }
    }
}
