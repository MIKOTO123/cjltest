<?php

//如果接收的数据包含图片文件

include "vendor/autoload.php";

include "Log.php";

use \common\log\Log;


Log::Info('第一个字段{file}是内容{smgui}',['smgui'=>'hahahahaha','file'=>'xiamidongxi']);






uploadFile();

function uploadFile(){
    if(isset($_FILES['file'])){

        $aid = intval($_POST['aid']);
        $nickname = ($_POST['nickname']);
        //获取图片的临时路径
        $image = $_FILES["file"]['tmp_name'];
        //只读方式打开图片文件
        $fp = fopen($image, "r");
        //读取文件（可安全用于二进制文件）
        $file = fread($fp, $_FILES["file"]["size"]); //二进制数据流
        //保存地址
        $imgDir = 'account_imgs/';
        //要生成的图片名字
        //$filename = date("Ym")."/".md5(time().mt_rand(10, 99)).".jpg"; //新图片名称
        $filename = "2017new.jpg";
        //新图片的路径
        $newFilePath = $imgDir.$filename;
        $data = $file;
        $newFile = fopen($newFilePath,"w"); //打开文件准备写入
        fwrite($newFile,$data); //写入二进制流到文件
        fclose($newFile); //关闭文件

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        echo finfo_file($finfo, $newFilePath) . "\n";
        //记录日志看看
        echo json_encode(array('result'=>'imgsuccess'));
        exit;

    } else{
        $aid = intval($_REQUEST['aid']);
        $nickname = $_REQUEST['nickname'];

        echo json_encode(array('result'=>'noimgsuccess'));
        exit;

    }
}





?>