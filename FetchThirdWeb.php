<?php


class FetchThirdWeb
{
    const DS = DIRECTORY_SEPARATOR;
    const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36';
    public static $header = array();
    public static $tokenQueryParam = array();//请求验证参数
    public static $tokenHeader = array();//有些网站需要请求头来验证
    public static $expHeader = 0;
    public static $thirdUrl = '';
    public static $logoutUrl = '';//执行退出操作

    public static function tokenFile(){
        if(!empty(self::$thirdUrl)) {
            $old = array(':', '/', '.');
            $new = array('_');
            $fileName = str_replace($old, $new, self::$thirdUrl);
            $pref = $_SERVER['DOCUMENT_ROOT'] . self::DS . 'cookie' . self::DS;
            return $pref.$fileName.'.token';
        }else{
            return date('Ymd').'.token';
        }
    }

    public static  function loadToken(){
        //self::$tokenHeader[] = $key.': '.$param;
        $path = self::tokenFile();
        if(file_exists($path)){
            $handle = @fopen($path, "r");
            if ($handle) {
                while (($buffer = fgets($handle, 4096)) !== false) {
                    self::$tokenHeader[] = $buffer;
                }
                if (!feof($handle)) {
                    self::log("Error: unexpected fgets() fail\n");
                }
                fclose($handle);
            }
        }
    }

    public static function saveToken($data){
        if(!empty(self::$thirdUrl)){
            $path = self::tokenFile();
            $content = implode(PHP_EOL,$data);
            file_put_contents($path,$content,FILE_APPEND);
        }
    }

    public static function clearToken(){
        $path = self::tokenFile();
        if(file_exists($path)){
            file_put_contents($path,'');
        }
    }

    //读取网页
    public static function curl($durl, $content = '', $post = false)
    {
        $ROOT_PATH =$_SERVER['DOCUMENT_ROOT'];
        $filename = 'temp_cookie' . date('YmdH') . '.txt';
        $cookie_file = $ROOT_PATH . self::DS . 'cookie' . self::DS . $filename;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $durl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_POST, $post);

        if (!file_exists($cookie_file)) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        } else {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        }

        if( count(self::$tokenHeader)>0){
            curl_setopt($ch,CURLOPT_HTTPHEADER,self::$tokenHeader);
        }

        if (!empty($content)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $str = substr($output, 0, $headerSize);
            self::$header = self::dealHeader($str,self::$expHeader);
            $body = substr($output, $headerSize);

			self::response_log($durl,$str,$body);
        }else{
            $body = '';
        }
        curl_close($ch);
        return $body;
    }

    public static function dealHeader($str,$exp=0){
        $header = [];
        if(!empty($str)){
            $temp = explode("\n",$str);
            foreach ($temp as $t){
                $t = trim($t);
                if(!empty($t)){
                    if($exp==1){
                        $tArr = explode(': ',$t);
                        if(count($tArr)==2){
                            $header[$tArr[0]] = $tArr[1];
                        }
                    }else{
                        $header[] = $t;
                    }
                }
            }
        }
        return $header;
    }

    //获取完整URL
    public static function curPageURL()
    {
        $pageURL = 'http';
        if ($_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $pageURL .= "://";

        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }

    //获取完整URL后路径和参数
    public static function curPagePath()
    {
        $pageURL = '';

        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }

    //从第三方网址获取内容
    public static function fetch($third_url)
    {
        self::$thirdUrl = $third_url;
        $currPath = self::curPagePath();
        $post = false;
        $content = '';
        if (self::isGet()) {
            $params = $_GET;
        } elseif (self::isPost()) {
            $post = true;
            $params = $_POST;
            $content = http_build_query($_POST);
        } else {
            return 'not support';
        }
        $data = array();
        //检测请求参数中有没有token
        if(count(self::$tokenQueryParam)>0 && count($params)>0){
            foreach ($params as $key=>$param){
                if(in_array($key,self::$tokenQueryParam)){
                    $data[] = $key.': '.$param;
                }
            }
        }
        self::saveToken($data);
        self::loadToken();
        $durl = $third_url . $currPath;
        if(!empty(self::$logoutUrl)){
            if(strpos($currPath,self::$logoutUrl)!==false){
                self::clearToken();
            }
        }
        return self::curl($durl, $content, $post);
    }

    //是否是AJAx提交的
    public static function isAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            return true;
        } else {
            return false;
        }
    }

    //是否是GET提交的
    public static function isGet()
    {
        return $_SERVER['REQUEST_METHOD'] == 'GET' ? true : false;
    }

    //是否是POST提交
    public static function isPost()
    {
        return ($_SERVER['REQUEST_METHOD'] == 'POST' && (empty($_SERVER['HTTP_REFERER']) || preg_replace("~https?:\/\/([^\:\/]+).*~i", "\\1", $_SERVER['HTTP_REFERER']) == preg_replace("~([^\:]+).*~", "\\1", $_SERVER['HTTP_HOST']))) ? 1 : 0;
    }

	//记录文件返回日志
	public static function response_log($durl,$header,$body){
		$fileName = date('Ymd_His').'_'.md5($durl).'.log';
		return self::log($durl."\n\n".$header."\n\n".$body,$fileName);
	}

	//记录日志
	public static function log($content,$fileName=''){
		$pref = $_SERVER['DOCUMENT_ROOT']. self::DS . 'logs' . self::DS ;
		$path = empty($fileName)?$pref.date('YmdH').'.log':$pref.$fileName; 
		return error_log($content, 3, $path);
	}
}
