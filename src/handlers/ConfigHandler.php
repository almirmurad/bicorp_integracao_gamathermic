<?php
namespace src\handlers;
use Dotenv\Dotenv;


class ConfigHandler{

    public static function configOmieMpr($SK='',$APPK='',$NCC=''){
        
        $file = parse_ini_file('../.env');
        switch($file){
            case isset($file['SECRETS_MPR']) && !empty($file['SECRETS_MPR']) && $file['SECRETS_MPR'] != $SK && $SK != '':
                $file['SECRETS_MPR'] = $SK;
                break;
            case isset($file['APPK_MPR']) && !empty($file['APPK_MPR']) && $file['APPK_MPR'] != $APPK && $APPK !='':
                $file['APPK_MPR'] = $APPK;
                break;
            case isset($file['NCC_MPR']) && !empty($file['NCC_MPR']) && $file['NCC_MPR'] != $NCC && $NCC != $NCC:
                $file['NCC_MPR'] = $NCC;
                break;  
        }
        $keys = array('SECRETS_MPR'=>$SK,'APPK_MPR'=>$APPK,'NCC_MPR'=>$NCC);
        return Self::montaArray($file, $keys); 


    }
    public static function configOmieMsc($SK='',$APPK='',$NCC=''){
        $file = parse_ini_file('../.env');   
        switch($file){
            case isset($file['SECRETS_MSC']) && !empty($file['SECRETS_MSC']) && $file['SECRETS_MSC'] != $SK && $SK != '':
                $file['SECRETS_MSC'] = $SK;
                break;
            case isset($file['APPK_MSC']) && !empty($file['APPK_MSC']) && $file['APPK_MSC'] != $APPK && $APPK != '':
                $file['APPK_MSC'] = $APPK;
                break;
            case isset($file['NCC_MSC']) && !empty($file['NCC_MSC']) && $file['NCC_MSC'] != $NCC && $NCC != '':
                $file['NCC_MSC'] = $NCC;
                break;  
        }
        $keys = array('SECRETS_MSC'=>$SK,'APPK_MSC'=>$APPK,'NCC_MSC'=>$NCC);
        return Self::montaArray($file, $keys);
    }

    public static function configOmieMhl($SK='',$APPK='',$NCC=''){  
        $file = parse_ini_file('../.env');
        switch($file){  
            case isset($file['SECRETS_MHL']) && !empty($file['SECRETS_MHL']) && $file['SECRETS_MHL'] != $SK && $SK !='':
                $file['SECRETS_MHL'] = $SK;
                break;
            case isset($file['APPK_MHL']) && !empty($file['APPK_MHL']) && $file['APPK_MHL'] != $APPK && $APPK !='':
                $file['APPK_MHL'] = $APPK;
                break;
            case isset($file['NCC_MHL']) && !empty($file['NCC_MHL']) && $file['NCC_MHL'] != $NCC && $NCC != '':
                $file['NCC_MHL'] = $NCC;
                break;  
            
        } 
        $keys = array('SECRETS_MHL'=>$SK,'APPK_MHL'=>$APPK,'NCC_MHL'=>$NCC);
        return Self::montaArray($file, $keys);
    }

    public static function configPloomesApk($PLM_APK=''){  
        $file = parse_ini_file('../.env');
        switch($file){  
            case isset($file['API_KEY']) && !empty($file['API_KEY']) && $file['API_KEY'] != $PLM_APK && $PLM_APK !='':
                $file['API_KEY'] = $PLM_APK;
                break;
            
        } 
        $keys = array('API_KEY'=>$PLM_APK);
        return Self::montaArray($file, $keys);
    }

    public static function montaArray($file, $keys){

        $c = array_merge($file,$keys);
        $str="";
        foreach($c as $k=>$value){
            $str .= $k.'='.$value."\n";

        }

        file_put_contents('../.env',$str);

        return true;
    }

}
