<?php
/**
 * Created by chenjialin
 * User: ys-8564
 * Date: 2019/5/22
 * Time: 15:45
 */



print_r(phpinfo());

die();
//
// 错误处理函数
function customError($errno, $errstr)
{
    echo "<b>Error:</b> [$errno] $errstr";
}

// 设置错误处理函数
set_error_handler("customError");

// 触发错误
echo($test);
?>