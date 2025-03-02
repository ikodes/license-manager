<?php
/**
 * ==============================================================
 * WARNING: DO NOT MODIFY THIS FILE WITHOUT PROPER UNDERSTANDING!
 * ==============================================================
 * 
 * This file is a core part of the system, and any modifications 
 * may cause unexpected behavior, security vulnerabilities, or 
 * complete system failure.
 * 
 * If you need to make changes, please consult the development 
 * team or refer to the official documentation.
 * 
 * Changes made without proper understanding may lead to:
 * - System instability or crashes.
 * - Security vulnerabilities.
 * - Compatibility issues with future updates.
 * - Loss of critical functionality.
 * 
 * Proceed with caution and ensure you have a backup before making 
 * any modifications.
 * 
 * ==============================================================
 * LAST MODIFIED: 27 02 2025
 * AUTHOR: ikodes team
 * ==============================================================
 */
namespace Ikodes\LicenseManager;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Service;

class LicenseCore {
    protected   $product_id;
    protected   $api_url;
    protected   $api_key;
    protected   $api_language = 'english';
    protected   $current_version = 'v1.0.0';
    protected   $verify_type = 'envato';
    protected   $verification_period = 1;
    private     $client;
    private     $aesKey;
    private     $service;
    private     $secure = false;
    private     $current_path;
    private     $root_path;
    private     $license_file;
    private     $LB_API_DEBUG =  false;
    private     $LB_SHOW_UPDATE_PROGRESS = true;

    

    public function __construct(?string $product_id = null, ?string $api_url = null, ?string $api_key = null)
    {   
        $this->api_url = $api_url ?? getenv('API_URL') ?: '//crm.ikodes.net/v1/';
        $this->product_id = $product_id ?? getenv('PRODUCT_ID') ?: '05C52105';
        $this->api_key = $api_key ?? getenv('API_KEY') ?: '64A9AD39F722B6E29949';
        $this->client = new Client([
            'base_uri' => $this->api_url,
            'timeout'  => 30,
            'verify'   => false, 
        ]);        
    }

    public function get_current_version(){
        return $this->current_version;
    }

    private function getDataSecure($data){
       return $data;
    }

    private function callApi(string $method, string $endpoint, array $data): array
    {
        try {
            $this_ip = getenv('SERVER_ADDR')?: $_SERVER['SERVER_ADDR']?: $this->get_ip_from_third_party()?: gethostbyname(gethostname());
            $this_server_name = getenv('SERVER_NAME')?: $_SERVER['SERVER_NAME']?: getenv('HTTP_HOST')?: $_SERVER['HTTP_HOST'];
            $this_http_or_https = ((
                (isset($_SERVER['HTTPS'])&&($_SERVER['HTTPS']=="on"))or
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])and
                    $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            )?'https://':'http://');
            $this_url = $this_http_or_https.$this_server_name.$_SERVER['REQUEST_URI'];
            $this->aesKey = bin2hex(random_bytes(32)); 
            $secureBody = $this->getDataSecure(json_encode($data));
            $response = $this->client->request($method, $endpoint, [
                'body'=>$secureBody,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'method'        => $method,
                    'IK-API-KEY'    => $this->api_key, // Ensure this is correct
                    'IK-URL'        => $this_url, 
                    'IK-IP'         => $this_ip,
                    'LB-LANG'       => $this->api_language,
                    'IK-SECURE'     => $this->secure,
                    'IK-AES-KEY'    => $this->aesKey, // Ensure this is correct
                ]
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException | Exception $e) {

            return [$e->getMessage()];
        }
    }

    private function get_ip_from_third_party(){
        $curl = curl_init ();
        curl_setopt($curl, CURLOPT_URL, "http://ipecho.net/plain");
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10); 
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    private function generateSignature(array $data): string
    {
        return hash_hmac('sha256', json_encode($data), $this->api_key);
    }

    private function generateJwtToken(): string
    {
        $payload = [
            'iss' => 'Ikodes',
            'iat' => time(),
            'exp' => time() + 3600,
            'product_id' => $this->product_id
        ];
        return JWT::encode($payload, $this->api_key, 'HS256');
    }

    public function verifyLicense($license = false, $client = false): array
    {   
        
        return $this->callApi('POST', 'api/verify_license', [
            'product_id' => $this->product_id,
            'license_code' => $license,
            'client_name' => $client,
            'license_file' => null,
            
        ]);
    }
    public function checkLicensetime($time_based_check = false, $license = false, $client = false){
        $res = array();
        $data_array =  array(
            "product_id"  => $this->product_id,
            "license_file" => null,
            "license_code" => $license,
            "client_name" => $client
        );
        
        if($time_based_check && $this->verification_period > 0){
            ob_start();
            if(session_status() == PHP_SESSION_NONE){
                session_start();
            }
            $type = (int) $this->verification_period;
            $today = date('d-m-Y');
            if(empty($_SESSION["b611d4743ce464c"])){
                $_SESSION["b611d4743ce464c"] = '00-00-0000';
            }
            if($type == 1){
                $type_text = '1 day';
            }elseif($type == 3){
                $type_text = '3 days';
            }elseif($type == 7){
                $type_text = '1 week';
            }elseif($type == 30){
                $type_text = '1 month';
            }elseif($type == 90){
                $type_text = '3 months';
            }elseif($type == 365) {
                $type_text = '1 year';
            }else{
                $type_text = $type.' days';
            }
            if(strtotime($today) >= strtotime($_SESSION["b611d4743ce464c"])){
                $get_data = $this->callApi(
                    'POST',
                    $this->api_url.'api/verify_license', 
                    ($data_array)
                );
                $res = $get_data;
                if($res['status']==true){
                    $tomo = date('d-m-Y', strtotime($today. ' + '.$type_text));
                    $_SESSION["b611d4743ce464c"] = $tomo;
                }
            }
            ob_end_clean();
           

        }
        return json_encode($res);    

    }

    public function activateLicense(string $license, string $client): array
    {   
        return $this->callApi('POST', 'api/activate_license', [
            'product_id' => $this->product_id,
            'license_code' => $license,
            'client_name' => $client,
            'verify_type' => $this->verify_type
        ]);
    }

    public function deactivateLicense(string $license, string $client): array
    {   
        return $this->callApi('POST', 'api/deactivate_license', [
            'product_id' => $this->product_id,
            'license_code' => $license,
            'client_name' => $client,
        ]);
    }

    public function checkUpdate(): array
    {
        return $this->callApi('POST', 'api/check_update', [
            'product_id' => $this->product_id,
            'current_version' => $this->current_version,
        ]);
    }
    public function check_connection(){
        $data_array =  array();
        $response = $this->callApi(
            'POST',
            $this->api_url.'api/check_connection_ext', 
            $data_array
        );
        return $response;
    }

    private function get_remote_filesize($url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, TRUE);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_NOBODY, TRUE);
        $this_server_name = getenv('SERVER_NAME')?: $_SERVER['SERVER_NAME']?:  getenv('HTTP_HOST')?: $_SERVER['HTTP_HOST'];
        $this_http_or_https = ((
            (isset($_SERVER['HTTPS'])&&($_SERVER['HTTPS']=="on"))or
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])and
                $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        )?'https://':'http://');
        $this_url = $this_http_or_https.$this_server_name.$_SERVER['REQUEST_URI'];
        $this_ip = getenv('SERVER_ADDR')?:
            $_SERVER['SERVER_ADDR']?:
            $this->get_ip_from_third_party()?:
            gethostbyname(gethostname());
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'IK-API-KEY: '.$this->api_key, 
            'IK-URL: '.$this_url, 
            'IK-IP: '.$this_ip, 
            'LB-LANG: '.$this->api_language)
        );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30); 
        $result = curl_exec($curl);
        $filesize = curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        if ($filesize){
            switch ($filesize){
                case $filesize < 1024:
                    $size = $filesize .' B'; break;
                case $filesize < 1048576:
                    $size = round($filesize / 1024, 2) .' KB'; break;
                case $filesize < 1073741824:
                    $size = round($filesize / 1048576, 2) . ' MB'; break;
                case $filesize < 1099511627776:
                    $size = round($filesize / 1073741824, 2) . ' GB'; break;
            }
            return $size; 
        }
    }

    public function download_update($update_id, $type, $version, $license = false, $client = false, $db_for_import = false){ 
        if(!empty($license)&&!empty($client)){
            $data_array =  array(
                "license_file" => null,
                "license_code" => $license,
                "client_name" => $client
            );
        } 
        ob_end_flush(); 
        ob_implicit_flush(true);  
        $version = str_replace(".", "_", $version);
        ob_start();
        $source_size = $this->api_url."api/get_update_size/main/".$update_id; 
        echo IKODES_LICENSE_TEXT_PREPARING_MAIN_DOWNLOAD."<br>";
        if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 1;</script>';}
        ob_flush();
        echo IKODES_LICENSE_TEXT_MAIN_UPDATE_SIZE." ".$this->get_remote_filesize($source_size)." ".IKODES_LICENSE_TEXT_DONT_REFRESH."<br>";
        if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 5;</script>';}
        ob_flush();
        $temp_progress = '';
        $ch = curl_init();
        $source = $this->api_url."api/download_update/main/".$update_id; 
        curl_setopt($ch, CURLOPT_URL, $source);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_array);
        $this_server_name = getenv('SERVER_NAME')?:
            $_SERVER['SERVER_NAME']?:
            getenv('HTTP_HOST')?:
            $_SERVER['HTTP_HOST'];
        $this_http_or_https = ((
            (isset($_SERVER['HTTPS'])&&($_SERVER['HTTPS']=="on"))or
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])and
                $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        )?'https://':'http://');
        $this_url = $this_http_or_https.$this_server_name.$_SERVER['REQUEST_URI'];
        $this_ip = getenv('SERVER_ADDR')?:
            $_SERVER['SERVER_ADDR']?:
            $this->get_ip_from_third_party()?:
            gethostbyname(gethostname());
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'IK-API-KEY: '.$this->api_key, 
            'IK-URL: '.$this_url, 
            'IK-IP: '.$this_ip, 
            'LB-LANG: '.$this->api_language)
        );
        if($this->LB_SHOW_UPDATE_PROGRESS){curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array($this, 'progress'));}
        if($this->LB_SHOW_UPDATE_PROGRESS){curl_setopt($ch, CURLOPT_NOPROGRESS, false);}
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); 
        echo IKODES_LICENSE_TEXT_DOWNLOADING_MAIN."<br>";
        if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 10;</script>';}
        ob_flush();
        $data = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($http_status != 200){
            if($http_status == 401){
                curl_close($ch);
                exit("<br>".IKODES_LICENSE_TEXT_UPDATE_PERIOD_EXPIRED);
            }else{
                curl_close($ch);
                exit("<br>".IKODES_LICENSE_TEXT_INVALID_RESPONSE);
            }
        }
        curl_close($ch);
        $destination = $this->root_path."/update_main_".$version.".zip"; 
        $file = fopen($destination, "w+");
        if(!$file){
            exit("<br>".IKODES_LICENSE_TEXT_UPDATE_PATH_ERROR);
        }
        fputs($file, $data);
        fclose($file);
        if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 65;</script>';}
        ob_flush();
        $zip = new ZipArchive;
        $res = $zip->open($destination);
        if($res === TRUE){
            $zip->extractTo($this->root_path."/"); 
            $zip->close();
            unlink($destination);
            echo IKODES_LICENSE_TEXT_MAIN_UPDATE_DONE."<br><br>";
            if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 75;</script>';}
            ob_flush();
        }else{
            echo IKODES_LICENSE_TEXT_UPDATE_EXTRACTION_ERROR."<br><br>";
            ob_flush();
        }
        if($type == true){
            $source_size = $this->api_url."api/get_update_size/sql/".$update_id; 
            echo IKODES_LICENSE_TEXT_PREPARING_SQL_DOWNLOAD."<br>";
            ob_flush();
            echo IKODES_LICENSE_TEXT_SQL_UPDATE_SIZE." ".$this->get_remote_filesize($source_size)." ".IKODES_LICENSE_TEXT_DONT_REFRESH."<br>";
            if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 85;</script>';}
            ob_flush();
            $temp_progress = '';
            $ch = curl_init();
            $source = $this->api_url."api/download_update/sql/".$update_id;
            curl_setopt($ch, CURLOPT_URL, $source);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_array);
            $this_server_name = getenv('SERVER_NAME')?:
                $_SERVER['SERVER_NAME']?:
                getenv('HTTP_HOST')?:
                $_SERVER['HTTP_HOST'];
            $this_http_or_https = ((
                (isset($_SERVER['HTTPS'])&&($_SERVER['HTTPS']=="on"))or
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])and
                    $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            )?'https://':'http://');
            $this_url = $this_http_or_https.$this_server_name.$_SERVER['REQUEST_URI'];
            $this_ip = getenv('SERVER_ADDR')?:
                $_SERVER['SERVER_ADDR']?:
                $this->get_ip_from_third_party()?:
                gethostbyname(gethostname());
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'IK-API-KEY: '.$this->api_key, 
                'IK-URL: '.$this_url, 
                'IK-IP: '.$this_ip, 
                'LB-LANG: '.$this->api_language)
            ); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            echo IKODES_LICENSE_TEXT_DOWNLOADING_SQL."<br>";
            if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 90;</script>';}
            ob_flush();
            $data = curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if($http_status!=200){
                curl_close($ch);
                exit(IKODES_LICENSE_TEXT_INVALID_RESPONSE);
            }
            curl_close($ch);
            $destination = $this->root_path."/update_sql_".$version.".sql"; 
            $file = fopen($destination, "w+");
            if(!$file){
                exit(IKODES_LICENSE_TEXT_UPDATE_PATH_ERROR);
            }
            fputs($file, $data);
            fclose($file);
            echo IKODES_LICENSE_TEXT_SQL_UPDATE_DONE."<br><br>";
            if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 95;</script>';}
            ob_flush();
            if(is_array($db_for_import)){
                if(!empty($db_for_import["db_host"])&&!empty($db_for_import["db_user"])&&!empty($db_for_import["db_name"])){
                    $db_host = strip_tags(trim($db_for_import["db_host"]));
                    $db_user = strip_tags(trim($db_for_import["db_user"]));
                    $db_pass = strip_tags(trim($db_for_import["db_pass"]));
                    $db_name = strip_tags(trim($db_for_import["db_name"]));
                    $con = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);
                    if(mysqli_connect_errno()){
                        echo IKODES_LICENSE_TEXT_UPDATE_WITH_SQL_IMPORT_FAILED;
                    }else{
                        $templine = '';
                        $lines = file($destination);
                        foreach($lines as $line){
                            if(substr($line, 0, 2) == '--' || $line == '')
                                continue;
                            $templine .= $line;
                            $query = false;
                            if(substr(trim($line), -1, 1) == ';'){
                                $query = mysqli_query($con, $templine);
                                $templine = '';
                            }
                        }
                        @chmod($destination,0777);
                        if(is_writeable($destination)){
                            unlink($destination);
                        }
                        echo IKODES_LICENSE_TEXT_UPDATE_WITH_SQL_IMPORT_DONE;
                    }
                }else{
                    echo IKODES_LICENSE_TEXT_UPDATE_WITH_SQL_IMPORT_FAILED;
                }
            }else{
                echo IKODES_LICENSE_TEXT_UPDATE_WITH_SQL_DONE;
            }
            if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 100;</script>';}
            ob_flush();
        }else{
            if($this->LB_SHOW_UPDATE_PROGRESS){echo '<script>document.getElementById(\'prog\').value = 100;</script>';}
            echo IKODES_LICENSE_TEXT_UPDATE_WITHOUT_SQL_DONE;
            ob_flush();
        }
        ob_end_flush(); 
    }
}
