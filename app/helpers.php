<?php

use App\Models\Settings;
use App\Models\SipProfiles;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use App\Models\Dialplans;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use function PHPUnit\Framework\isEmpty;
use Illuminate\Support\Facades\Session;

if (!function_exists('userCheckPermission')){
    function userCheckPermission($permission){
        $list = Session::get('permissions', false);
        if (!$list) return false;
        
        foreach ($list as $item){
            if ($item->permission_name == $permission){
                return true;
            }
        }
        return false;
    }

}

// Check if currenlty signed in user a superadmin
if (!function_exists('isSuperAdmin')){
    function isSuperAdmin(){
        foreach (Session::get('user.groups') as $group) {
            if ($group->group_name == "superadmin" && $group->group_level >= 80) {
                return true;
            }
        }
        return false;
    }

}

if (!function_exists('getDefaultSetting')){
    function getDefaultSetting($category,$subcategory){
        $settings = Session::get('default_settings', false);

        if (!$settings) return null;
        
        foreach ($settings as $setting){
            if ($setting['default_setting_category'] == $category &&
                $setting['default_setting_subcategory'] == $subcategory){
                return $setting ['default_setting_value'];
            }
        }
        return null;
    }

}

if (!function_exists('getFusionPBXPreviousURL')){
    function getFusionPBXPreviousURL($previous_url) {
        if (strpos($previous_url, "time_condition_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "time_condition_edit.php")) . "time_conditions.php";}
        elseif (strpos($previous_url, "destination_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "destination_edit.php")) . "destinations.php";}
        elseif (strpos($previous_url, "extension_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "extension_edit.php")) . "extensions.php";}
        elseif (strpos($previous_url, "ring_group_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "ring_group_edit.php")) . "ring_groups.php";}
        elseif (strpos($previous_url, "device_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "device_edit.php")) . "devices.php";}
        elseif (strpos($previous_url, "dialplan_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "dialplan_edit.php")) . "dialplans.php";}
        elseif (strpos($previous_url, "ivr_menu_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "ivr_menu_edit.php")) . "ivr_menus.php";}
        elseif (strpos($previous_url, "voicemail_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "voicemail_edit.php")) . "voicemails.php";}
        elseif (strpos($previous_url, "/extensions/")) {$url = substr($previous_url,0,strpos(url()->previous(), "/extensions/")) . "/extensions";}
        else $url = $previous_url;
        return $url;
    }
}

if (!function_exists('appsStoreOrganizationDetails')){
    function appsStoreOrganizationDetails(Request $request) {

        // Delete any existing records
        $deleted = DB::table('v_domain_settings')
        ->where ('domain_uuid','=', $request->organization_uuid)
        ->where('domain_setting_category','=', 'app shell')
        ->delete();

        // Store new records
        $domainSettingsModel = DomainSettings::create([
            'domain_uuid' => $request->organization_uuid,
            'domain_setting_category' => 'app shell',
            'domain_setting_subcategory' => 'org_id',
            'domain_setting_name' => 'text',
            'domain_setting_value' => $request->org_id,
            'domain_setting_enabled' => true,
        ]);
        $saved = $domainSettingsModel->save();
        if ($saved){
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('appsGetOrganizationDetails')){
    function appsGetOrganizationDetails($domain_uuid) {
        // Get Org ID
        $domainSettingsModel = DomainSettings::where('domain_uuid',$domain_uuid)
            ->where ('domain_setting_category', 'app shell')
            ->where ('domain_setting_subcategory', 'org_id')
            ->where ('domain_setting_enabled', true)
            ->first();

        return $domainSettingsModel->domain_setting_value;
    }
}

if (!function_exists('appsGetOrganization')){
    function appsGetOrganization($org_id) {
        $data = array(
            'method' => 'getOrganization',
            'params' => array(
                'id' => $org_id,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to retrive organization",
                    ],
                ])->getData(true);
            })
            ->json();
        return $response;
    }
}


if (!function_exists('appsStoreConnectionDetails')){
    function appsStoreConnectionDetails(Request $request) {

        // Store new records
        $domainSettingsModel = DomainSettings::create([
            'domain_uuid' => $request->connection_organization_uuid,
            'domain_setting_category' => 'app shell',
            'domain_setting_subcategory' => 'conn_id',
            'domain_setting_name' => 'array',
            'domain_setting_value' => $request->conn_id,
            'domain_setting_enabled' => true,
        ]);
        $saved = $domainSettingsModel->save();
        if ($saved){
            return true;
        } else {
            return false;
        }
    }
}

// Get a list of all organizations via Ringotel API call 
if (!function_exists('appsGetOrganizations')){
    function appsGetOrganizations() {
        $data = array(
            'method' => 'getOrganizations',
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to retrive organizations",
                    ],
                ])->getData(true);
            })
            ->json();
        return $response;
    }
}

// Get a list of connections that belong to requested organization via Ringotel API call 
if (!function_exists('appsGetConnections')){
    function appsGetConnections($org_id) {
        $data = array(
            'method' => 'getBranches',
            'params' => array(
                'orgid' => $org_id,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to retrive connections",
                    ],
                ])->getData(true);
            })
            ->json();
        return $response;
    }
}

// Delete connection that belong to requested organization via Ringotel API call 
if (!function_exists('appsDeleteConnection')){
    function appsDeleteConnection($org_id, $conn_id) {
        $data = array(
            'method' => 'deleteBranch',
            'params' => array(
                'id' => $conn_id,
                'orgid' => $org_id,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to delete connection",
                    ],
                ])->getData(true);
            })
            ->json();
        
        return $response;
    }
}

// Get a list of all user for this organizaion and connection
if (!function_exists('appsGetUsers')){
    function appsGetUsers($org_id, $conn_id) {
        $data = array(
            'method' => 'getUsers',
            'params' => array(
                'orgid' => $org_id,
                'branchid' => $conn_id,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to retrive connections",
                    ],
                ])->getData(true);
            })
            ->json();
        return $response;
    }
}


// Create mobile app user via Ringotel API call 
if (!function_exists('appsCreateUser')){
    function appsCreateUser($mobile_app) {

        $data = array(
            'method' => 'createUser',
            'params' => array(
                'orgid' => $mobile_app['org_id'],
                'branchid' => $mobile_app['conn_id'],
                'name' => $mobile_app['name'],
                'email' => $mobile_app['email'],
                'extension' => $mobile_app['ext'],
                'username' => $mobile_app['username'],
                'domain' => $mobile_app['domain'],
                'authname' => $mobile_app['authname'],
                'password' => $mobile_app['password'],
                'status' => $mobile_app['status'],
                )
            );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'error' => 401,
                    'message' => 'Unable to create a new user']);
                })
            ->json();
        
        return $response;
    }
}


// Delete mobile app user via Ringotel API call 
if (!function_exists('appsDeleteUser')){
    function appsDeleteUser($org_id, $user_id) {
        $data = array(
            'method' => 'deleteUser',
            'params' => array(
                'id' => $user_id,
                'orgid' => $org_id,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to delete user",
                    ],
                ])->getData(true);
            })
            ->json();
        
        return $response;
    }
}

// Update mobile app user via Ringotel API call 
if (!function_exists('appsUpdateUser')){
    function appsUpdateUser($mobile_app) {
        $data = array(
            'method' => 'updateUser',
            'params' => array(
                'id' => $mobile_app['user_id'],
                'orgid' => $mobile_app['org_id'],
                'name' => $mobile_app['name'],
                'email' => $mobile_app['email'],
                'extension' => $mobile_app['ext'],
                'username' => $mobile_app['ext'],
                'authname' => $mobile_app['ext'],
                'password' => $mobile_app['password'],
                'status' => $mobile_app['status'],
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to update user",
                    ],
                ])->getData(true);
            })
            ->json();
        
        return $response;
    }
}

// Reset password for mobile app user via Ringotel API call 
if (!function_exists('appsResetPassword')){
    function appsResetPassword($org_id, $user_id) {
        $data = array(
            'method' => 'resetUserPassword',
            'params' => array(
                'id' => $user_id,
                'orgid' => $org_id,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to reset password",
                    ],
                ])->getData(true);
            })
            ->json();
        
        return $response;
    }
}

// Set Status for mobile app user via Ringotel API call 
if (!function_exists('appsSetStatus')){
    function appsSetStatus($org_id, $user_id, $status) {
        $data = array(
            'method' => 'setUserStatus',
            'params' => array(
                'id' => $user_id,
                'orgid' => $org_id,
                'status' => $status,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to set new status",
                    ],
                ])->getData(true);
            })
            ->json();
        
        return $response;
    }    
}

// Delete organizaion via Ringotel API call 
if (!function_exists('appsDeleteOrganization')){
    function appsDeleteOrganization($org_id) {
        $data = array(
            'method' => 'deleteOrganization',
            'params' => array(
                'id' => $org_id,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to delete organization",
                    ],
                ])->getData(true);
            })
            ->json();
 
            Log::info($response);

            if (!isset($array) || empty($array)){
                Log::info("here");
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Organization not found",
                    ],
                ])->getData(true);
            }

        return $response;
    }
}

if (!function_exists('event_socket_create')){
    function event_socket_create($host, $port, $password) {
        $esl = new event_socket;
        if ($esl->connect($host, $port, $password)) {
            return $esl->reset_fp();
        }
        return false;
    }
}

if (!function_exists('event_socket_request')){
    function event_socket_request($fp, $cmd) {
        $esl = new event_socket($fp);
        $result = $esl->request($cmd);
        $esl->reset_fp();
        return $result;
    }
}

if (!function_exists('event_socket_request_cmd')){
    function event_socket_request_cmd($cmd) {

        // Get event socket credentials
        $settings = Settings::first();

        $esl = new event_socket;
        if (!$esl->connect($settings->event_socket_ip_address, $settings->event_socket_port, $settings->event_socket_password)) {
            return false;
        }
        $response = $esl->request($cmd);

        $esl->close();
        return $response;
    }
}
if (!function_exists('outbound_route_to_bridge')){
    function outbound_route_to_bridge($domain_uuid, $destination_number, array $channel_variables=null) {

        $destination_number = trim($destination_number);
        preg_match('/^[\*\+0-9]*$/', $destination_number, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) > 0) {
            //not found, continue to process the function
        }
        else {
            //not a number, brige_array and exit the function
            $bridge_array[0] = $destination_number;
            return $bridge_array;
        }

        //get the hostname
        $hostname = trim(event_socket_request_cmd('api switchname'));
        if (strlen($hostname) == 0) {
            $hostname = 'unknown';
        }

        $dialplans = Dialplans::where('dialplan_enabled','true')
            ->where ('app_uuid', '8c914ec3-9fc0-8ab5-4cda-6c9288bdc9a3')
            ->where(function($q) use ($domain_uuid) {
                $q->where('domain_uuid', $domain_uuid)
                  ->orWhere('domain_uuid', null);
            })
            ->where(function($q) use ($hostname) {
                $q->where('hostname', $hostname)
                  ->orWhere('hostname', null);
            })
            ->get([
                'dialplan_uuid',
                'dialplan_continue',
                'dialplan_name'
            ]);


        // if (is_array($result) && @sizeof($result) != 0) {
        //     foreach ($result as &$row) {
        //         $dialplan_uuid = $row["dialplan_uuid"];
        //         $dialplan_detail_uuid = $row["dialplan_detail_uuid"];
        //         $outbound_routes[$dialplan_uuid][$dialplan_detail_uuid]["dialplan_detail_tag"] = $row["dialplan_detail_tag"];
        //         $outbound_routes[$dialplan_uuid][$dialplan_detail_uuid]["dialplan_detail_type"] = $row["dialplan_detail_type"];
        //         $outbound_routes[$dialplan_uuid][$dialplan_detail_uuid]["dialplan_detail_data"] = $row["dialplan_detail_data"];
        //         $outbound_routes[$dialplan_uuid]["dialplan_continue"] = $row["dialplan_continue"];
        //     }
        // }

        if ($dialplans->isEmpty()) {return;}

        $x = 0;
        foreach($dialplans as $dialplan){
            $condition_match = false;
            foreach($dialplan->dialplan_details as $dialplan_detail){
                if ($dialplan_detail['dialplan_detail_tag'] == "condition") {

                    if ($dialplan_detail['dialplan_detail_type'] == "destination_number"){
                        $pattern = '/'.$dialplan_detail['dialplan_detail_data'].'/';
                        preg_match($pattern, $destination_number, $matches, PREG_OFFSET_CAPTURE);
                        if (count($matches) == 0) {
                            $condition_match = 'false';
                        }
                        else {
                            $condition_match = 'true';
                            if (isset($matches[1])){
                                $regex_match_1 = $matches[1][0];
                            }
                            if (isset($matches[2])){
                                $regex_match_2 = $matches[2][0];
                            }
                            if (isset($matches[3])){
                                $regex_match_3 = $matches[1][0];
                            }
                            if (isset($matches[4])){
                                $regex_match_4 = $matches[4][0];
                            }
                            if (isset($matches[5])){
                                $regex_match_5 = $matches[5][0];
                            }
                        } 

                    } elseif ($dialplan_detail['dialplan_detail_type'] == "\${toll_allow}") {
                        $pattern = '/'.$dialplan_detail['dialplan_detail_data'].'/';
                        preg_match($pattern, $channel_variables['toll_allow'], $matches, PREG_OFFSET_CAPTURE);
                        if (count($matches) == 0) {
                            $condition_match = 'false';
                        } 
                        else {
                            $condition_match = 'true';
                        }
                    }
                }
            }

            if ($condition_match == 'true') {
                foreach ($dialplan->dialplan_details as $dialplan_detail) {
                    // log::alert($dialplan_detail);
                        $dialplan_detail_data = $dialplan_detail['dialplan_detail_data'];
                        if ($dialplan_detail['dialplan_detail_tag'] == "action" && $dialplan_detail['dialplan_detail_type'] == "bridge" && $dialplan_detail_data != "\${enum_auto_route}") {
                            if (isset($regex_match_1)){
                                $dialplan_detail_data = str_replace("\$1", $regex_match_1, $dialplan_detail_data);
                            }
                            if (isset($regex_match_2)){
                                $dialplan_detail_data = str_replace("\$2", $regex_match_2, $dialplan_detail_data);
                            }
                            if (isset($regex_match_3)){
                                $dialplan_detail_data = str_replace("\$3", $regex_match_3, $dialplan_detail_data);
                            }
                            if (isset($regex_match_4)){
                                $dialplan_detail_data = str_replace("\$4", $regex_match_4, $dialplan_detail_data);
                            }
                            if (isset($regex_match_5)){
                                $dialplan_detail_data = str_replace("\$5", $regex_match_5, $dialplan_detail_data);
                            }
                            $bridge_array[$x] = $dialplan_detail_data;
                            $x++;
                        }
                }
                
                if ($dialplan["dialplan_continue"] == "false") {
                    break;
                }
            }
        }
        
        return $bridge_array;
    }
}

if (!function_exists('get_registrations')){
    function get_registrations ($show=null) {
        // //Check FusionPBX login status
        // session_start();
        // if(session_status() === PHP_SESSION_NONE) {
        //     return redirect()->route('logout');
        // }

        //create the event socket connection
		$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);

        $sip_profiles = SipProfiles::where('sip_profile_enabled','true')
            ->get();

        $registrations = array();
        $id=0;
        foreach ($sip_profiles as $sip_profile) {
            $cmd = "api sofia xmlstatus profile '".$sip_profile['sip_profile_name']."' reg";
			$xml_response = trim(event_socket_request($fp, $cmd));
            if (function_exists('iconv')) { $xml_response = iconv("utf-8", "utf-8//IGNORE", $xml_response); }
            $xml_response = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $xml_response);
            if ($xml_response == "Invalid Profile!") { $xml_response = "<error_msg>".'Error'."</error_msg>"; }
            $xml_response = str_replace("<profile-info>", "<profile_info>", $xml_response);
            $xml_response = str_replace("</profile-info>", "</profile_info>", $xml_response);
            $xml_response = str_replace("&lt;", "", $xml_response);
            $xml_response = str_replace("&gt;", "", $xml_response);
            if (strlen($xml_response) > 101) {
                try {
                    $xml = new SimpleXMLElement($xml_response);
                }
                catch(Exception $e) {
                    echo basename(__FILE__)."<br />\n";
                    echo "line: ".__line__."<br />\n";
                    echo "error: ".$e->getMessage()."<br />\n";
                    //echo $xml_response;
                    exit;
                }
                $array = json_decode(json_encode($xml), true);
            }
            //Log::alert($array);
            //normalize the array
            if (isset($array) && !isset($array['registrations']['registration'][0])) {
                $row = $array['registrations']['registration'];
                unset($array['registrations']['registration']);
                $array['registrations']['registration'][0] = $row;
            }

            //set the registrations array
            if (isset($array)) {
                foreach ($array['registrations']['registration'] as $row) {

                    //build the registrations array
                        //$registrations[0] = $row;
                        $user_array = explode('@', $row['user']);
                        $registrations[$id]['user'] = $row['user'] ?: '';
                        $registrations[$id]['call-id'] = $row['call-id'] ?: '';
                        $registrations[$id]['contact'] = $row['contact'] ?: '';
                        $registrations[$id]['sip-auth-user'] = $row['sip-auth-user'] ?: '';
                        $registrations[$id]['agent'] = $row['agent'] ?: '';
                        $registrations[$id]['host'] = $row['host'] ?: '';
                        $registrations[$id]['network-port'] = $row['network-port'] ?: '';
                        $registrations[$id]['sip-auth-realm'] = $row['sip-auth-realm'] ?: '';
                        $registrations[$id]['mwi-account'] = $row['mwi-account'] ?: '';
                        $registrations[$id]['status'] = $row['status'] ?: '';
                        $registrations[$id]['ping-time'] = $row['ping-time'] ?: '';
                        $registrations[$id]['sip_profile_name'] = $sip_profile['sip_profile_name'];

                    //get network-ip to url or blank
                        if (isset($row['network-ip'])) {
                            $registrations[$id]['network-ip'] = $row['network-ip'];
                        }
                        else {
                            $registrations[$id]['network-ip'] = '';
                        }

                    //get the LAN IP address if it exists replace the external ip
                        $call_id_array = explode('@', $row['call-id']);
                        if (isset($call_id_array[1])) {
                            $agent = $row['agent'];
                            $lan_ip = $call_id_array[1];
                            if (false !== stripos($agent, 'grandstream')) {
                                $lan_ip = str_ireplace(
                                    array('A','B','C','D','E','F','G','H','I','J'),
                                    array('0','1','2','3','4','5','6','7','8','9'),
                                    $lan_ip);
                            }
                            elseif (1 === preg_match('/\ACL750A/', $agent)) {
                                //required for GIGASET Sculpture CL750A puts _ in it's lan ip account
                                $lan_ip = preg_replace('/_/', '.', $lan_ip);
                            }
                            $registrations[$id]['lan-ip'] = $lan_ip;
                        }
                        else if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $row['contact'], $ip_match)) {
                            $lan_ip = preg_replace('/_/', '.', $ip_match[0]);
                            $registrations[$id]['lan-ip'] = "$lan_ip";
                        }
                        else {
                            $registrations[$id]['lan-ip'] = '';
                        }

                    //remove unrelated domains
                        if (!userCheckPermission('registration_all') || $show != 'all') {
                            if ($registrations[$id]['sip-auth-realm'] == $_SESSION['domain_name']) {}
                            else if ($user_array[1] == $_SESSION['domain_name']) {}
                            else {
                                unset($registrations[$id]);
                            }
                        }

                    //increment the array id
                        $id++;
                }
                
                unset($array);
            }
        }
        return $registrations;

    }
}

if (!function_exists('pr')){
    function pr($arr){
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
    }
}

if (!function_exists('setDefaultS3')){
    function setDefaultS3($arr){
       
    }
}

if (!function_exists('getCredentialKey')){
    function getCredentialKey($string){
       switch($string){
        case 'region':
            return 'region';
        case 'secret_key':
            return 'secret';
        case 'bucket_name':
            return 'bucket';
        case 'access_key':
            return 'key';
        default:
            return $string;
       }
    }
}

if (!function_exists('sendEmail')){
function sendEmail($data)
{
    try {
        Mail::send($data['email_layout'], ['data' => $data], function ($mail) use ($data) {
            $mail->to($data['user']->email, $data['user']->name)
                ->subject($data['subject']);
            $mail->from('noc@nemerald.com', 'Nemerald Support');
        });
        return '';
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}
}

if (!function_exists('getS3Setting')){
function getS3Setting($domain_id){
        $config=[];
        $settings=DomainSettings::where('domain_uuid',$domain_id)
            ->where('domain_setting_category','aws')
            ->get();
        $config['driver']='s3';
        $config['url']='';
        $config['endpoint']='';
        $config['region']='us-west-2';
        $config['use_path_style_endpoint']=false;
       
        if(!blank($settings)){
            foreach($settings as $conf){
                $config[getCredentialKey($conf->domain_setting_subcategory)]=trim($conf->domain_setting_value);
            }
            $config['type'] = 'custom';
        } else {
            $config=getDefaultS3Configuration();
            $config['type'] = 'default';
        }
        

        $setting['default']='s3';
        $setting['disks']['s3']=$config;
         
        return $config;
    }
}
    
if (!function_exists('getDefaultS3Configuration')){
       function getDefaultS3Configuration(){
        $default_credentials=DefaultSettings::where('default_setting_category','aws')->get();
        $config=[];
        foreach($default_credentials as $d_conf){
            $config[getCredentialKey($d_conf->default_setting_subcategory)]=$d_conf->default_setting_value;
        }
        return $config;
    }
}

if (!function_exists('getSignedURL')){
    function getSignedURL($s3Client,$bucket,$key){
        //  $s3Client = new Aws\S3\S3Client($sharedConfig);

        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $key
        ]);

        $request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
        $presignedUrl = (string) $request->getUri();
        return $presignedUrl;
    }
}

if (!function_exists('arr_to_map')){
    function arr_to_map(&$arr){
        if(is_array($arr)){
            $map = Array();
            foreach($arr as &$val){
                $map[$val] = true;
            }
            return $map;
        }
        return false;
    }
}

if (!function_exists('get_local_time_zone')){
    /**
     * Get tenant's local time zone or return the system default setting
     *
     * @return string
     */
    function get_local_time_zone($domain_uuid){
        $local_time_zone_setting = DomainSettings::where('domain_uuid',$domain_uuid)
            ->where('domain_setting_category', 'domain')
            ->where('domain_setting_subcategory', 'time_zone')
            ->where('domain_setting_enabled', 'true')
            ->first();

        if(!$local_time_zone_setting) {
            $system_time_zone_setting = DefaultSettings::where('default_setting_category', 'domain')
            ->where('default_setting_subcategory', 'time_zone')
            ->where('default_setting_enabled', 'true')
            ->first();

            if($system_time_zone_setting) {
                $local_time_zone = $system_time_zone_setting->default_setting_value;
            } else {
                $local_time_zone = "UTC";
            }
        } else {
            $local_time_zone = $local_time_zone_setting->domain_setting_value;
        }
        
        return $local_time_zone;
    }
}
