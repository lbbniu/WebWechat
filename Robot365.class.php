<?php
class Robot365{
	
	private $url = "";
    private $_text_filter = true;
    public function __construct($id){
        $this->url = 'http://k2vc.app/test.php';//"http://www.365rj.net/rjservlet?Command=talk_{$id}";
        $this->url = "http://www.365rj.net/rjservlet?Command=talk_{$id}";
    }
    public function search($content,$FromUserName) {
        if(strpos($content, '刘兵兵')!==false || strpos($content, 'lbbniu')!==false){
            return '刘兵兵，本机器人开发者(项目地址：http://github.com/lbbniu/WebWechat)，个人技术博客：http://www.lbbniu.com';
        }

        $xml = sprintf('<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[text]]></MsgType>
    <Content><![CDATA[%s]]></Content>
    <MsgId>%s</MsgId>
</xml>', 
                        'gh_fe5b36b12c61',                       
                        $FromUserName,
                        time(), 
                        $content,
                        time().mt_rand(1000,9999));
        $data = $this->_post($this->url,$xml);
        //var_dump($data);
        $array = (array)simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
        //var_dump($array);
        if(empty($array) || !isset($array['Content']) ||strpos($array['Content'], '无法')!==false || strpos($array['Content'], '未找到')!==false )            
            return '';
        return $array['Content'];
    }
   
	private function _post($url,$param,$post_file=false){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (PHP_VERSION_ID >= 50500 && class_exists('\CURLFile')) {
            $is_curlFile = true;
        } else {
            $is_curlFile = false;
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($oCurl, CURLOPT_SAFE_UPLOAD, false);
            }
        }
        if (is_string($param)) {
            $strPOST = $param;
        }elseif($post_file) {
            if($is_curlFile) {
                foreach ($param as $key => $val) {
                    if (substr($val, 0, 1) == '@') {
                        $param[$key] = new \CURLFile(realpath(substr($val,1)));
                    }
                }
            }
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach($param as $key=>$val){
                $aPOST[] = $key."=".urlencode($val);
            }
            $strPOST =  implode("&", $aPOST);
        }
        $header = [
            'Content-Type: text/html; charset=UTF-8',
            'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9) Gecko/2008052906 Firefox/3.0',
        ];
        curl_setopt($oCurl, CURLOPT_HEADER, 0);
        //curl_setopt ($oCurl, CURLOPT_PROXY, 'http://127.0.0.1:8888');
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
        //curl_setopt($oCurl, CURLOPT_COOKIEFILE, $this->cookie);
        //curl_setopt($oCurl, CURLOPT_COOKIEJAR, $this->cookie);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        //print_r($aStatus);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }
}