<?php
//session_id();
//session_start();
//var_dump($_SESSION);
//if(isset($_SESSION['views']))
//{
//    $_SESSION['views']=$_SESSION['views']+1;
//}
//else
//{
//    $_SESSION['views']=1;
//}
//echo "浏览量：". $_SESSION['views'];
//echo "hahahah";
//print_r(phpinfo());






require_once  __DIR__."/vendor/autoload.php";
//require_once __DIR__."/php-mime-mail-parser/src/Parser.php";
//require_once __DIR__."/php-mime-mail-parser/src/Contracts/CharsetManager.php";
//require_once __DIR__."/php-mime-mail-parser/src/Charset.php";
//require_once __DIR__."/php-mime-mail-parser/src/*";

$path = '/tmp/123.eml';
$parser = new PhpMimeMailParser\Parser();
print_r(123);
$parser ->setPath($path);
echo $parser->getHeader("to");
echo $parser->getHeader("from");
print_r($parser->getAddresses("from"));



?>
