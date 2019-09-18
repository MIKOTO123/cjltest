<?php
/**
 * Created by cjl.
 * User: yunwei
 * Date: 2017/6/16
 * Time: 8:58
 * 此版本为没有注释的版本
 * 
 * 
 * 使用方法输入    php -q reloadarchive.php 11@137.snn /testarchive/haha
 * 其中reloadarchive.php为本文件名,,,,,
 * 11@137.snn  为邮箱名
 * /testarchive/haha 你想要输出的归档文件的路径
 * 
 * 执行时,请将输出内容导出到一个文本,如果有出现网络错误字样,会带上文件名,请用另一个脚本单独导出该文件.
 */

set_time_limit(0);
header("Content-Type:text/html;charset=utf-8");


$begintime=200101;   //此处为开始时间,你们可以更改
$endTime=date("Ym");  //此处为结束时间.也可以更改

$mailaddr=$argv[1];
$savepath=$argv[2];

if(!$mailaddr||!$savepath){
    echo "\n请输入正确的命令格式 例如:  php -q reloadarchive.php 11@137.snn /testarchive/haha \n";
    echo "\n 其中11@137.snn 为你要归档的邮箱\n";  
    echo "\n 其中/testarchive/haha为你要导出的归档邮件路径\n";
    echo "\n 请重新执行文件\n";
    die;   
}


list ( $mailname,$domain) = explode('@', $mailaddr);
$maildir='/mail/'.$domain.'/'.$mailname.'/';

$tmp_path = resetPath($maildir);

$mail_stat_db = $tmp_path . 'mail_stat';

$mail_base_db = $tmp_path . 'mail_base';

$mail_path_db= $tmp_path . 'mail_path';

$output = array();
$output1 = array();
exec("sudo /usr/local/bin/tctmgr list  $mail_stat_db ", $output1);
foreach ($output1 as $folder){
    if((intval($folder)>=intval($begintime))&&(intval($folder)<=intval($endTime))){
        array_push($output,$folder);
    }
}
//进行排序从与月份小的开始.
array_multisort($output);

echo "\n归档中请稍后\n";



foreach ($output as $fileFolder){

    
    echo "\n##################$fileFolder#######################\n";
    

    $lists=array();
    if (file_exists($tmp_path."/to_split/mail_base_".$fileFolder))
    {
        $dbPath = $tmp_path."/to_split/mail_base_".$fileFolder;
        $is_split = true;
        $tdb = PlatformArchiveMailHandler::getTokyocabinet($dbPath);
        $tdb->qrynew();
        $lists = $tdb->qrysearch();
    }else{

        $y = floor($fileFolder / 100);
        $m = $fileFolder % 100;
        $begintime=mktime(0, 0, 0, $m, 1, $y);
        $endTime = strtotime('+1 month', $begintime);
        $tdb = PlatformArchiveMailHandler::getTokyocabinet($mail_base_db);
        $tdb->qrynew();
        $tdb->qryaddcond("date", TOKYOCABINET_TDB_TDBQCNUMBT, "$begintime,$endTime");
        $lists = $tdb->qrysearch();
    }

    foreach($lists as $key=>$folder){


         if (file_exists($mail_path_db)) {
             $hdb = PlatformArchiveMailHandler::getTokyocabinetHdb($mail_path_db);
             $md5Key=$hdb->get($folder['filename']);
             unset($hdb);
             if(strstr($md5Key,"/archive")){
                 if (file_exists($md5Key)) {
                     $hdb3 = PlatformArchiveMailHandler::getTokyocabinetHdb($md5Key);
                     $data = $hdb3->get($folder['filename']);
                     unset($hdb3);
                     putDateIntoFile($savepath,$mailname, $domain, $data, $fileFolder,$key);
                     continue;
                 }
             }else if ($md5Key){
                 list ($null1 ,$archive,$uniquekey, $mailname,$null2)=  explode ("/",$tmp_path) ;
                 $po_all_path = "/" . $archive ."/" . $uniquekey . "/"  . 'po_all'  ;
         
         
                 if (!file_exists($po_all_path)) {
                     return null;
                 }
                 $tdb2 = PlatformArchiveMailHandler::getTokyocabinet($po_all_path, PlatformArchiveMailHandler::CONNECTION_TYPE_READ);
                 $md5kdy_array = $tdb2->get($md5Key);
                 unset($tdb2);
                 $d1=$md5kdy_array['archive_db'];
                 if (file_exists($d1)) {
                     $hdb4 = PlatformArchiveMailHandler::getTokyocabinetHdb($d1);
                     $data = $hdb4->get($md5Key);
                     unset($hdb4);
                     putDateIntoFile($savepath,$mailname, $domain, $data, $fileFolder,$key);
               
                     continue;
                 }else{
                     //文件名不存在,发送网络请求,md5-key & po_unique_key & archive_db.?
                     $postarray=array();
                     $postarray['pouniquekey']=$uniquekey;
                     // 			         $postarray['md5-key']=$md5Key;
                     //$dbname= strstr($d1,"archive_db");
					 $dbname= basename($d1);
                     $postarray['archive_db']=$dbname;
                     $postarray['addFlag']='0';
                     $nsip=SysConfigSettingHelper::getDatabaseNsIp();
                     $nsPort=SysConfigSettingHelper::getDatabaseNsPort();
                     if(!$nsip || !$nsPort){
						 echo "nsip或者nsport为空";
                         return false;
                     }
             
                     $remoteServer= "http://$nsip:$nsPort/archiveApi/podbList/getPoDbListIp";
                     $obj = json_decode(BossmailRequestHelper::requestArchive($remoteServer,$postarray)) ;

                     if ($obj->returncode !== 0) {
                         for ($re_request_i = 1; $re_request_i <= 5; $re_request_i++) {
                             sleep(5);
                             $obj = json_decode(BossmailRequestHelper::requestArchive($remoteServer, $postarray));
                             if($obj->returncode === 0){
                                 break;
                             }
                         }
                     }
                     if($obj->returncode === 0){
                         list($ip,$partition) = explode(" ", $obj->response);
                         $dsPort=SysConfigSettingHelper::getDatabaseDsPort();
                         if(!$ip || !$dsPort){
                             //                                     Logger::archiveLog("ds 服务器未获取到ip 或者端口未配置");
                             echo "\n ds服务器未获取到ip 或者端口未配置\n";
                             return false;
                         }
     
                         $url="http://$ip:$dsPort/archiveApi/dsMaildata/getMailEntitySingle";
                         $postarray['md5-key']=$md5Key;
                         $postarray['partition']=$partition;
                         unset($postarray['addFlag']);
                         $retundata = BossmailRequestHelper::requestArchive($url,$postarray);

                         if (substr($retundata,0,13) !="returncode:0 ") {
                             for ($re_retundata_i = 1; $re_retundata_i <= 5; $re_retundata_i++) {
                                 sleep(5);
                                 $retundata = BossmailRequestHelper::requestArchive($url,$postarray);
                                 if(substr($retundata,0,13) =="returncode:0 "){
                                     break;
                                 }
                             }
                         }

                         if(substr($retundata,0,13) =="returncode:0 "){
                             putDateIntoFile($savepath,$mailname, $domain,  substr($retundata,13), $fileFolder,$key);
                             continue;
                         }else{
							 echo "\n网络请求有误,邮箱名为:{$mailaddr},日期为:{$fileFolder},文件名为:".$folder['filename']."\n";
							  continue;
                             return false;
                         }
                     }else{
						  echo "\n网络请求有误2,邮箱名为:{$mailaddr},日期为:{$fileFolder},文件名为:".$folder['filename']."\n";
                         continue;
                         return false;
                     }
         
                 }
                  
             }
         }
         
         
         $db = $tmp_path . 'mail_all';
         if (!file_exists($db)) {
             echo "\n您要找的归档文件可能已经丢失,请联系管理员.\n";
             continue;
             return false;
         }
         $hdb5 = PlatformArchiveMailHandler::getTokyocabinetHdb($db);
         $data = $hdb5->get($folder['filename']);
         unset($hdb5);
         putDateIntoFile($savepath,$mailname, $domain,  $data, $fileFolder,$key);

//          return $data;

         
    }
    unset($tdb);

    
}

echo "\n归档文件导出成功\n";

// exec("zip -r archive_{$mailname}.zip {$mailname}\@{$domain}");

// echo "\n文件在当前目录下邮箱名.zip\n";





















die;







function putDateIntoFile($savepath,$mailname, $domain, $data, $folder,$key){

   $tmpFolder=$savepath."/".$domain.'/'.$mailname;
    
    if (file_exists("{$tmpFolder}/{$folder}/")){
//                echo "\n{$tmpFolder}/{$folder}/"."已经存在该目录\n";
    }else{
        exec("sudo mkdir -pm 777 '{$tmpFolder}/{$folder}/'");
        exec("sudo chmod 777 '{$tmpFolder}/{$folder}/'");
    }

   if(file_exists("{$tmpFolder}/{$folder}/{$key}".".eml")){
       echo "\n{$tmpFolder}/{$folder}/{$key}"."已经存在该目录\n";
//         $f = file_put_contents("{$tmpFolder}/{$folder}/{$key}"."{$filename}.eml", $data."\n");       
   }else{
//        echo "{$tmpFolder}/{$folder}/{$subject}".".eml";
       $f = file_put_contents("{$tmpFolder}/{$folder}/{$key}".".eml", $data."\n");
   }
    
}


class SysConfigSettingHelper {
    
    
    /**
     * ds机端口
     */
    const DATABASE_DS_PORT='DATABASE_DS_PORT';
    
    
    /**
     * ns机端口
     */
    const DATABASE_NS_PORT ='DATABASE_NS_PORT';
    
    /**
     * ns机ip
     */
    const DATABASE_NS_IP ='DATABASE_NS_IP';
    
    /**
     * 配置信息
     */
    private static $_sysConfig = array();

    /**
     * ns机ip
     */
    public static function getDatabaseNsIp(){
        self::initSysConfig();
        return self::$_sysConfig[self::DATABASE_NS_IP];
    }
    
    
    /**
     * ns机port
     *
     */
    public static function getDatabaseNsPort(){
        self::initSysConfig();
        return self::$_sysConfig[self::DATABASE_NS_PORT]?self::$_sysConfig[self::DATABASE_NS_PORT]:80;
    }
    
    
    /**
     * ds机port
     *
     */
    public static function getDatabaseDsPort(){
        self::initSysConfig();
        return self::$_sysConfig[self::DATABASE_DS_PORT]?self::$_sysConfig[self::DATABASE_DS_PORT]:80;
    }
    
    
    /**
     * 初始化系统配置信息
     */
    private static function initSysConfig(){
        if(!self::$_sysConfig){
            
            $link = mysql_connect('127.0.0.1', 'postfix', 'postfix');
            if (!$link) {
                die('Could not connect: ' . mysql_error());
            }else{
//                 echo "我成功的连接了数据库了,更新数据中,请等待,";
            }
            mysql_select_db('postfix_a');
            mysql_query("set names 'utf8'");
            
            $sql ="select * from sys_config_setting ";
            
            
            $result=mysql_query($sql);
            
            $configArray=array();//存放着要改的po_id
            while($row = mysql_fetch_array($result))
            {
                $configArray[$row[1]]=$row[2];
            }

            self::$_sysConfig = $configArray;
        }
    }
    
    
}








class PlatformArchiveMailHandler {

    const CONNECTION_TYPE_READ = TOKYOCABINET_TDB_TDBOREADER;
    const HDB_READ = TOKYOCABINET_HDB_HDBOREADER;
    
public static function getTokyocabinet($filePath, $type = self::CONNECTION_TYPE_READ) {
    return new tokyocabinet_tdb($filePath, $type);
}

/**
 * 创建tokyocabinet_hdb对象
 */
public static function getTokyocabinetHdb($filePath, $type = self::HDB_READ) {
    return new tokyocabinet_hdb($filePath, $type);
}

}




/**
 * 根据邮件的域名获取uniquekey
 * @param unknown $domain
 */
function getUniqueKeyByDomainName($domain){
    $link = mysql_connect('127.0.0.1', 'postfix', 'postfix');
    if (!$link) {
        die('Could not connect: ' . mysql_error());
    }else{
//         echo "我成功的连接了数据库了,更新数据中,请等待,";
    }
    mysql_select_db('postoffice_a');
    mysql_query("set names 'utf8'");

    $sql ="select unique_key from postoffice_a.domain where domain='$domain'";


    $result=mysql_query($sql);
    
    return  mysql_result($result,0);

}




















/**
 * 从新定义地址
 * @param unknown $maildir
 * @return string
 */
function resetPath($maildir) {
     
    list ( $domain, $mailname) = explode('/', substr( $maildir, 6 ));

    $uniqueKey=getUniqueKeyByDomainName($domain);
    $path='/archive/' . $uniqueKey . "/"  . $mailname ."/";
    if(file_exists($path)){
        return  $path;
    }else{
        return '/archive/' . substr( $maildir, 6 );//旧版的
    }
    return '/archive/' . $uniqueKey . "/"  . $mailname ."/" ;
     
    // 		return '/archive/' . substr( $maildir, 6 );//旧版的
}





/**
 * 请求 辅助类
 *
 * @author rubekid
 */
class BossmailRequestHelper {
    /**
     * USERAGENT
     */
    const USERAGENT =  "XMZZY_WEBMAIL";

    /**
     * 超时限制
     */
    const TIMEOUT = 5;




    /**
     * 通过CURL 请求获取数据
     * @param string $remoteServer
     * @param array $posts
     */
    public static function requestByCurl($remoteServer, $posts = array(),$flag='other'){
        $randId = rand(0, 1000000);
        $postString = "";
        foreach ($posts as $key =>$value){
            $postString .= "{$key}=" . urlencode($value). "&";
        }
        $postString = rtrim($postString, "&");
        $startLogtime=date("Y-m-d H:i:s",time());
        if($flag=='archive'){
//             Logger::archiveLog("startTime:$startLogtime,RequestID:$randId,RemoteSever:".$remoteServer.",Poststring:".$postString);
        }else{
//             Logger::debug("startTime:$startLogtime,RequestID:$randId,RemoteSever:".$remoteServer.",Poststring:".$postString);
        }

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$remoteServer);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $postString);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_USERAGENT, self::USERAGENT);
		curl_setopt($ch,CURLOPT_TIMEOUT,300); 
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
        $data = curl_exec($ch);
        $endLogtime=date("Y-m-d H:i:s",time());
        if($flag=='archive'){
            if(strpos($remoteServer,"getMailEntitySingle")!==false && strpos($data,"returncode:0")!==false){//读邮件成功不记录邮件体数据
//                 Logger::archiveLog("endTime:$endLogtime,RequestID:$randId,returnData:{returncode:0}");
            }else{
//                 Logger::archiveLog("endTime:$endLogtime,RequestID:$randId,returnData:$data");
            }
            	
        }else{
//             Logger::debug("endTime:$endLogtime,RequestID:$randId,returnData:$data");
        }
        curl_close($ch);
        return $data;
    }


    /**
     * 返回归档文件访问的接口
     * @param unknown $remoteServer
     * @param unknown $posts
     * @param unknown $key
     */
    public static function requestArchive($remoteServer, $postarrray = array(),$secretkey=Config::OUTSIDE_API_CHECKSUM_KEY){
        ksort($postarrray);
        foreach ( $postarrray as $key => $value ) {
            $checkParams .= "&$key=$value";
        }
        $checksum=md5($secretkey.$checkParams);
        $postarrray['checksum']=$checksum;
        return self::requestByCurl($remoteServer,$postarrray,'archive');
    }



}




/**
 * 服务器配置文参数类
 *
 * @author lazier
 */
class Config {

/**
 *  请求归档ds和ns的外部的api的秘钥
 * @var unknown
 */
const OUTSIDE_API_CHECKSUM_KEY = 'aslxmzzy@%^adds8*d*zzys~da';

}






