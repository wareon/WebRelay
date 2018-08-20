<?php
include_once('FetchThirdWeb.php');
date_default_timezone_set('PRC'); 
$third_url='https://calef.cn';

FetchThirdWeb::$expHeader = 1;
FetchThirdWeb::$tokenQueryParam = array('token');//如果请求参数带这个则保存到token
FetchThirdWeb::$logoutUrl = '/logout';
$content = FetchThirdWeb::fetch($third_url);
$header = FetchThirdWeb::$header;
if(count($header)>0){
    $allow_head = array('Cache-Control','Connection','Content-Type','Date','Expires','Server','Location');
    //print_r($header);
    foreach ($header as $key=>$head){
        if(in_array($key,$allow_head))
            //echo $key.': '.$head."\n";
            header($key.': '.$head);
    }
}
$old = array(

);
$new = array(

);
$content2 = str_replace($old,$new, $content);
echo $content2;
/*
<script>
    var mod = location.hash;
    console.log(mod);
</script>
 */
?>

