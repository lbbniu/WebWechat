#!/usr/bin/env php
<?php
require_once 'QRcode.class.php';
require_once 'Robot365.class.php';

function raw_input($str){
   fwrite(STDOUT,$str);
   return trim(fgets(STDIN));
}
class WebWeiXin{
	public function __toString(){
        $description = 
            "=========================\n" .
            "[#] Web Weixin\n" .
            "[#] Debug Mode: {$this->DEBUG}\n" .
            "[#] Uuid: {$this->uuid}\n" . 
            "[#] Uin: {$this->uin}\n" . 
            "[#] Sid: {$this->sid}\n" . 
            "[#] Skey: {$this->skey}\n" . 
            "[#] PassTicket: {$this->pass_ticket}\n" . 
            "[#] DeviceId: {$this->deviceId}\n" . 
            "[#] synckey: {$this->synckey}\n" . 
            "[#] SyncKey: ".self::json_encode($this->SyncKey)."\n" . 
            "[#] syncHost: {$this->syncHost}\n" . 
            "=========================\n";
        return $description;
    }
    private $NoReplyGroup = [
        '优才网全栈工程师',
        'Laravel学院微信群',
        'PHP和测试',
        '微明项目特工队',
    ];
	public function __construct(){ 
		$this->DEBUG = false;
        $this->uuid = '';
        $this->base_uri = 'https://wx.qq.com/cgi-bin/mmwebwx-bin';
        $this->redirect_uri = 'https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxnewloginpage';//
        $this->uin = '';
        $this->sid = '';
        $this->skey = '';
        $this->pass_ticket = '';
        $this->deviceId = 'e' .substr(md5(uniqid()),2,15);
        $this->BaseRequest = [];
        $this->synckey = '';
        $this->SyncKey = [];
        $this->User = [];
        $this->MemberList = [];
        $this->ContactList = [];  # 好友
        $this->GroupList = [];  # 群
        $this->GroupMemeberList = [];  # 群友
        $this->PublicUsersList = [];  # 公众号／服务号
        $this->SpecialUsersList = [];  # 特殊账号
        $this->autoReplyMode = false;
        $this->syncHost = '';
        $this->user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.109 Safari/537.36';
        $this->interactive = false;
        $this->autoOpen = false;
        $this->saveFolder =   getcwd()."/saved/";
        $this->saveSubFolders = ['webwxgeticon'=> 'icons', 'webwxgetheadimg'=> 'headimgs', 
            'webwxgetmsgimg'=> 'msgimgs','webwxgetvideo'=> 'videos', 'webwxgetvoice'=> 'voices', 
            '_showQRCodeImg'=> 'qrcodes'
        ];
        $this->appid = 'wx782c26e4c19acffb';
        $this->lang = 'zh_CN';
        $this->lastCheckTs = time();
        $this->memberCount = 0;
        $this->SpecialUsers = ['newsapp', 'fmessage', 'filehelper', 'weibo', 'qqmail', 
            'fmessage', 'tmessage', 'qmessage', 'qqsync', 'floatbottle', 'lbsapp', 'shakeapp', 
            'medianote', 'qqfriend', 'readerapp', 'blogapp', 'facebookapp', 'masssendapp', 
            'meishiapp', 'feedsapp','voip', 'blogappweixin', 'weixin', 'brandsessionholder', 
            'weixinreminder', 'wxid_novlwrv3lqwv11', 'gh_22b87fa7cb3c', 'officialaccounts', 
            'notification_messages', 'wxid_novlwrv3lqwv11', 'gh_22b87fa7cb3c', 'wxitil', 
            'userexperience_alarm', 'notification_messages'
        ];
        $this->TimeOut = 20;  # 同步最短时间间隔（单位：秒）
        $this->media_count = -1;

        $this->cookie = "cookie.cookie";
	}
    public function loadConfig($config){
        if (isset($config['DEBUG'])){
            $this->DEBUG = $config['DEBUG'];
        }
        if (isset($config['autoReplyMode'])){
            $this->autoReplyMode = $config['autoReplyMode'];
        }
        if (isset($config['user_agent'])){
            $this->user_agent = $config['user_agent'];
        }
        if (isset($config['interactive'])){
            $this->interactive = $config['interactive'];
        }
        if (isset($config['autoOpen'])){
            $this->autoOpen = $config['autoOpen'];
        }
    }

    /**
     * 获取
     * @return bool
     */
	public function getUUID(){
        /**
         * https://login.weixin.qq.com/jslogin
         * https://login.wx.qq.com/jslogin
         * https://login.wx1.qq.com/jslogin
         * https://login.wx2.qq.com/jslogin
         */
		$url = 'https://login.wx.qq.com/jslogin';
        $params = [
            'appid'=>$this->appid,
            //'redirect_uri'=> $this->redirect_uri,
            'fun'=>'new',
            'lang'=> $this->lang,
            '_'=>time(),
        ];
        $data = $this->_get($url, $params);
        $regx = '/window.QRLogin.code = (\d+); window.QRLogin.uuid = "(\S+?)"/';
        if (preg_match($regx, $data,$pm)){
        	$code = $pm[1];
            $this->uuid = $pm[2];
            return $code == '200';
        } 
        return false;
	}

	public function genQRCode(){
        if(PHP_OS !='Darwin'&&strpos(PHP_OS, 'win')!==false){
            $this->_showQRCodeImg();
        }else{
            $this->_str2qr('https://login.weixin.qq.com/l/' . $this->uuid);
        }
	}
    public function _showQRCodeImg(){
        $url = 'https://login.weixin.qq.com/qrcode/' . $this->uuid;
        $params = [
            't'=> 'webwx',
            '_'=> time()
        ];

        $data = $this->_post($url, $params, false);
        $QRCODE_PATH = $this->_saveFile('qrcode.jpg', $data, '_showQRCodeImg');
        //os.startfile(QRCODE_PATH)
        //TODO:没有完成
        system($QRCODE_PATH);
    }

    public function waitForLogin($tip=1){
        sleep($tip);
        $url = sprintf('https://login.wx.qq.com/cgi-bin/mmwebwx-bin/login?tip=%s&uuid=%s&_=%s', $tip, $this->uuid, time());
        $data = $this->_get($url);
        preg_match('/window.code=(\d+);/', $data,$pm);
        $code = $pm[1];

        if($code == '201')
            return true;
        elseif ($code == '200'){
            preg_match('/window.redirect_uri="(\S+?)";/', $data,$pm);
            $r_uri = $pm[1] . '&fun=new';
            $this->redirect_uri = $r_uri;
            $this->base_uri = substr($r_uri,0,strrpos($r_uri, '/'));
            //var_dump($this->base_uri);
            return true;
        } elseif ($code == '408'){
            $this->_echo("[登陆超时]");
        } else {
            $this->_echo("[登陆异常]");
        }
        return false;
    }

	public function login(){
		$data = $this->_get($this->redirect_uri);
        $array = (array)simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
       	//var_dump($array);
        if (!isset($array['skey'])||!isset($array['wxsid'])||!isset($array['wxuin'])||!isset($array['pass_ticket']))
            return False;
        $this->skey = $array['skey'];
        $this->sid = $array['wxsid'];
        $this->uin = $array['wxuin'];
        $this->pass_ticket = $array['pass_ticket'];

        $this->BaseRequest = [
            'Uin'=> intval($this->uin),
            'Sid'=> $this->sid,
            'Skey'=> $this->skey,
            'DeviceID'=> $this->deviceId
        ];
        $this->initSave();
        return true;
	}

	public function webwxinit($first=true){
		$url = sprintf($this->base_uri.'/webwxinit?pass_ticket=%s&skey=%s&r=%s',$this->pass_ticket, $this->skey, time());
        $params = [
            'BaseRequest'=> $this->BaseRequest
        ];
        $dic = $this->_post($url, $params);
        $this->SyncKey = $dic['SyncKey'];
        $this->User = $dic['User'];
        # synckey for synccheck
        $tempArr = [];
        if(is_array($this->SyncKey['List'])){
            foreach ($this->SyncKey['List'] as $val) {
                # code...
                $tempArr[] = "{$val['Key']}_{$val['Val']}";
            }
        }elseif($first){
            return $this->webwxinit(false);
        }
        //$this->skey = $dic['SKey'];
        $this->synckey = implode('|', $tempArr);
        //$this->initSave();
        //var_dump($this->synckey);
        return $dic['BaseResponse']['Ret'] == 0;
	}

	public function webwxstatusnotify(){
		$url = sprintf($this->base_uri .'/webwxstatusnotify?lang=zh_CN&pass_ticket=%s',$this->pass_ticket);
        $params = [
            'BaseRequest'=> $this->BaseRequest,
            "Code"=> 3,
            "FromUserName"=> $this->User['UserName'],
            "ToUserName"=> $this->User['UserName'],
            "ClientMsgId"=> time()
        ];
        $dic = $this->_post($url, $params);
        return $dic['BaseResponse']['Ret'] == 0;
	}
	public function webwxgetcontact(){
		$SpecialUsers = $this->SpecialUsers;
        //print $this->base_uri;
        $url = sprintf($this->base_uri . '/webwxgetcontact?pass_ticket=%s&skey=%s&r=%s',
            $this->pass_ticket, $this->skey, time());
        $dic = $this->_post($url, []);

        $this->MemberCount = $dic['MemberCount']-1;//把自己减去
        $this->MemberList = $dic['MemberList'];
        $ContactList = $this->MemberList;
        $GroupList = $this->GroupList;
        $PublicUsersList = $this->PublicUsersList;
        $SpecialUsersList = $this->SpecialUsersList;
        //var_dump($ContactList);
        if(is_array($ContactList)){
            foreach($ContactList as $key => $Contact){
                //$this->_echo(sprintf("%s--------%d-------%d----%s",$Contact['UserName'] ,$Contact['VerifyFlag'],$Contact['VerifyFlag']&8,$Contact['VerifyFlag'] & 8 != 0));
            	if (in_array($Contact['UserName'] , $SpecialUsers)){  # 特殊账号
                    unset($ContactList[$key]);
                    $this->SpecialUsersList[] = $Contact;
                }elseif (($Contact['VerifyFlag'] & 8) != 0){  # 公众号/服务号
                    unset($ContactList[$key]);
                    $this->PublicUsersList[] = $Contact;
                }elseif (strpos($Contact['UserName'],'@@') !== false){  # 群聊
                    unset($ContactList[$key]);
                    $this->GroupList[] = $Contact;
                }elseif ($Contact['UserName'] == $this->User['UserName']){  # 自己
                    unset($ContactList[$key]);
                }
            }
        }else{
            return false;
        }
        $this->ContactList = $ContactList;
        return true;
	}

	public function webwxbatchgetcontact(){
		$url = sprintf($this->base_uri .'/webwxbatchgetcontact?type=ex&r=%s&pass_ticket=%s' , time(), $this->pass_ticket);
        $List = [];
        foreach ($this->GroupList as $g) {
         	# code...
         	$List[] = ["UserName"=> $g['UserName'], "EncryChatRoomId"=>""];
        } 
        $params = [
            'BaseRequest'=> $this->BaseRequest,
            "Count"=> count($this->GroupList),
            "List"=> $List
        ];
        $dic = $this->_post($url, $params);

        # blabla ...
        $ContactList = $dic['ContactList'];
        $ContactCount = $dic['Count'];
        $this->GroupList = $ContactList;

        foreach($ContactList as $key=>$Contact){
            $MemberList = $Contact['MemberList'];
            foreach($MemberList as $member)
            	$this->GroupMemeberList[] = $member;
        }
            
        return true;
	}

    public function getNameById($id){
        $url = sprintf($this->base_uri .
            '/webwxbatchgetcontact?type=ex&r=%s&pass_ticket=%s' ,
                time(), $this->pass_ticket);
        $params = [
            'BaseRequest'=> $this->BaseRequest,
            "Count"=> 1,
            "List"=> [["UserName"=> $id, "EncryChatRoomId"=> ""]]
        ];
        $dic = $this->_post($url, $params);

        # blabla ...
        return $dic['ContactList'];
    }

    public function testsynccheck(){
        //TODO:
        $SyncHost = [
            'webpush.weixin.qq.com',
            'webpush2.weixin.qq.com',
            'webpush.wechat.com',
            'webpush1.wechat.com',
            'webpush2.wechat.com',
            'webpush1.wechatapp.com',
            'webpush.wechatapp.com'
        ];
        $SyncHost = ['webpush.wx.qq.com'];
        foreach($SyncHost as $host){
            $this->syncHost = $host;
            list($retcode, $selector) = $this->synccheck();
            if ($retcode == '0')
                return true;
        }
        return false;
    }
    public function synccheck(){
        $params = [
            'r'=> time(),
            'sid'=> $this->sid,
            'uin'=> $this->uin,
            'skey'=> $this->skey,
            'deviceid'=> $this->deviceId,
            'synckey'=> $this->synckey,
            '_'=> time(),
        ];
        $url = 'https://' . $this->syncHost .'/cgi-bin/mmwebwx-bin/synccheck?'.http_build_query($params);
        $data = $this->_get($url);
        if(preg_match('/window.synccheck={retcode:"(\d+)",selector:"(\d+)"}/', $data,$pm)){
            $retcode = $pm[1];
            $selector = $pm[2];
        }else{
            //var_dump($data);
            $retcode = -1;
            $selector = -1;
        }
        return [$retcode, $selector];
    }
    public function webwxsync(){
        $url = sprintf($this->base_uri .
            '/webwxsync?sid=%s&skey=%s&pass_ticket=%s' ,
                $this->sid, $this->skey, $this->pass_ticket);
        $params = [
            'BaseRequest'=> $this->BaseRequest,
            'SyncKey'=> $this->SyncKey,
            'rr'=> time()
        ];
        $dic = $this->_post($url, $params);
        if ($this->DEBUG)
            var_dump($dic);

        if ($dic['BaseResponse']['Ret'] == 0){
            $this->SyncKey = $dic['SyncKey'];
            $synckey = [];
            foreach($this->SyncKey['List'] as $keyVal)
                $synckey[] = "{$keyVal['Key']}_{$keyVal['Val']}";
            $this->synckey = implode('|', $synckey);
        }
        return $dic;
    }

    public function webwxsendmsg($word, $to='filehelper'){
        $url = sprintf($this->base_uri .
            '/webwxsendmsg?pass_ticket=%s' ,$this->pass_ticket);
        $clientMsgId = (time() * 1000) .substr(uniqid(), 0,5);
        $data = [
            'BaseRequest'=> $this->BaseRequest,
            'Msg'=> [
                "Type"=> 1,
                "Content"=> $this->_transcoding($word),
                "FromUserName"=> $this->User['UserName'],
                "ToUserName"=> $to,
                "LocalID"=> $clientMsgId,
                "ClientMsgId"=> $clientMsgId
            ]
        ];
        $dic = $this->_post($url, $data);
        return $dic['BaseResponse']['Ret'] == 0;
    }

    public function webwxuploadmedia($image_name){
        $url = 'https://file.wx.qq.com/cgi-bin/mmwebwx-bin/webwxuploadmedia?f=json';
        # 计数器
        $this->media_count = $this->media_count + 1;
        # 文件名
        $file_name = $image_name;
        # MIME格式
        # mime_type = application/pdf, image/jpeg, image/png, etc.
        $mime_type = mime_content_type($image_name);
        # 微信识别的文档格式，微信服务器应该只支持两种类型的格式。pic和doc
        # pic格式，直接显示。doc格式则显示为文件。
        $media_type =  explode('/', $mime_type)== 'image'?'pic':'doc';
        # 上一次修改日期
        $lastModifieDate = 'Thu Mar 17 2016 00:55:10 GMT+0800 (CST)';
        # 文件大小
        $file_size = filesize($file_name);
        # PassTicket
        $pass_ticket = $this->pass_ticket;
        # clientMediaId
        $client_media_id = (time() * 1000).mt_rand(10000,99999);
        # webwx_data_ticket
        $webwx_data_ticket = '';
        $fp = fopen('cookie.cookie', 'r');
        while ($line = fgets($fp)) {
            # code...
            if(strpos($line,'webwx_data_ticket')!==false){
                $arr=explode("\t", trim($line));
                //var_dump($arr);
                $webwx_data_ticket = $arr[6];
                break;
            }
        }
        fclose($fp);
                
        if ($webwx_data_ticket == '')
            return "None Fuck Cookie";

        $uploadmediarequest = self::json_encode([
            "BaseRequest"=> $this->BaseRequest,
            "ClientMediaId"=> $client_media_id,
            "TotalLen"=> $file_size,
            "StartPos"=> 0,
            "DataLen"=> $file_size,
            "MediaType"=> 4
        ]);

        $multipart_encoder = [
            'id'=> 'WU_FILE_' .$this->media_count,
            'name'=> $file_name,
            'type'=> $mime_type,
            'lastModifieDate'=> $lastModifieDate,
            'size'=> $file_size,
            'mediatype'=> $media_type,
            'uploadmediarequest'=> $uploadmediarequest,
            'webwx_data_ticket'=> $webwx_data_ticket,
            'pass_ticket'=> $pass_ticket,
            'filename'=> '@'.$file_name
        ];

        $response_json = json_decode($this->_post($url,$multipart_encoder,false,true),true);
        if ($response_json['BaseResponse']['Ret'] == 0)
            return $response_json;
        return null;
    }

    public function webwxsendmsgimg($user_id, $media_id){
        $url = sprintf($this->base_uri.'/webwxsendmsgimg?fun=async&f=json&pass_ticket=%s' , $this->pass_ticket);
        $clientMsgId = (time() * 1000) .substr(uniqid(), 0,5);
        $data = [
            "BaseRequest"=> $this->BaseRequest,
            "Msg"=> [
                "Type"=> 3,
                "MediaId"=> $media_id,
                "FromUserName"=> $this->User['UserName'],
                "ToUserName"=> $user_id,
                "LocalID"=> $clientMsgId,
                "ClientMsgId"=> $clientMsgId
            ]
        ];
        $dic = $this->_post($url, $data);
        if ($this->DEBUG)
            var_dump($dic);
        return $dic['BaseResponse']['Ret'] == 0;
    }

    public function webwxsendmsgemotion($user_id, $media_id){
        $url = sprintf($this->base_uri.'/webwxsendemoticon?fun=sys&f=json&pass_ticket=%s' , $this->pass_ticket);
        $clientMsgId = (time() * 1000) .substr(uniqid(), 0,5);
        $data = [
            "BaseRequest"=> $this->BaseRequest,
            "Msg"=> [
                "Type"=> 47,
                "EmojiFlag"=> 2,
                "MediaId"=> $media_id,
                "FromUserName"=> $this->User['UserName'],
                "ToUserName"=> $user_id,
                "LocalID"=> $clientMsgId,
                "ClientMsgId"=> $clientMsgId
            ]
        ];
        $dic = $this->_post($url, $data);
        if ($this->DEBUG)
           var_dump($dic);
        return $dic['BaseResponse']['Ret'] == 0;
    }

    public function _saveFile($filename, $data, $api=null){
        $fn = $filename;
        if (isset($this->saveSubFolders[$api])){
            $dirName = $this->saveFolder.$this->saveSubFolders[$api];
            umask(0);
            if(!is_dir($dirName)){
                mkdir($dirName,0777,true);
                chmod($dirName, 0777);
            }
            $fn = $dirName.'/'. $filename;
            $this->_echo(sprintf('Saved file: %s' , $fn));
            //file_put_contents($fn, $data);
            $f = fopen($fn, 'wb');
            if($f){
                fwrite($f,$data);
                fclose($f);
            }else{
                $this->_echo('[*] 保存失败 - '.$fn);
            }
        }
        return $fn;
    }

    public function webwxgeticon($id){
        $url = sprintf($this->base_uri .
            '/webwxgeticon?username=%s&skey=%s' , $id, $this->skey);
        $data = $this->_get($url);
        $fn = 'img_' . $id . '.jpg';
        return $this->_saveFile($fn, $data, 'webwxgeticon');
    }

    public function webwxgetheadimg(){
        $url = sprintf($this->base_uri .
            '/webwxgetheadimg?username=%s&skey=%s' , $id, $this->skey);
        $data = $this->_get($url);
        $fn = 'img_' . $id . '.jpg';
        return $this->_saveFile($fn, $data, 'webwxgetheadimg');
    }

    public function webwxgetmsgimg($msgid){
        $url = sprintf($this->base_uri .
            '/webwxgetmsgimg?MsgID=%s&skey=%s' , $msgid, $this->skey);
        $data = $this->_get($url);
        $fn = 'img_' . $msgid . '.jpg';
        return $this->_saveFile($fn, $data, 'webwxgetmsgimg');
    }
    public function webwxgetvideo($msgid){
        $url = sprintf($this->base_uri .
            '/webwxgetvideo?msgid=%s&skey=%s' , $msgid, $this->skey);
        $data = $this->_get($url, [],'webwxgetvideo');
        $fn = 'video_' . $msgid . '.mp4';
        return $this->_saveFile($fn, $data, 'webwxgetvideo');
    }
    public function webwxgetvoice($msgid){
        $url = sprintf($this->base_uri .
            '/webwxgetvoice?msgid=%s&skey=%s' , $msgid, $this->skey);
        $data = $this->_get($url,[],'webwxgetvoice');
        $fn = 'voice_' . $msgid . '.mp3';
        return $this->_saveFile($fn, $data, 'webwxgetvoice');
    }
    public function getGroupName($id){
        $name = '未知群';
        foreach($this->GroupList as $member){
            if ($member['UserName'] == $id){
                $name = $member['NickName'];
            }
        }
        if ($name == '未知群'){
            # 现有群里面查不到
            $GroupList = $this->getNameById($id);
            foreach($GroupList as $group){
                $this->GroupList[] = $group;
                if ($group['UserName'] == $id){
                    $name = $group['NickName'];
                    $MemberList = $group['MemberList'];
                    foreach($MemberList as $member)
                        $this->GroupMemeberList[] = $member;
                }
            }                
        }         
        return $name;
    }
    public function getUserRemarkName($id){
        $name = substr($id, 0,2) == '@@'?'未知群':'陌生人';
        if ($id == $this->User['UserName']){
            return $this->User['NickName'];  # 自己
        }
        if (substr($id, 0,2) == '@@'){
            # 群
            $name = $this->getGroupName($id);
        }else{
            # 特殊账号
            foreach($this->SpecialUsersList as  $member){
                if ($member['UserName'] == $id){
                    $name =  $member['RemarkName']?$member['RemarkName']:$member['NickName'];
                }
            }
            # 公众号或服务号
            foreach($this->PublicUsersList as $member){
                if ($member['UserName'] == $id){
                    $name =  $member['RemarkName']?$member['RemarkName']:$member['NickName'];
                }
            }
            # 直接联系人
            foreach($this->ContactList as $member){
                if ($member['UserName'] == $id){
                    $name =  $member['RemarkName']?$member['RemarkName']:$member['NickName'];
                }
            }
            # 群友
            foreach($this->GroupMemeberList as $member){
                if ($member['UserName'] == $id){
                    $name = $member['DisplayName'] ? $member['DisplayName'] : $member['NickName'];
                }
            }
        }
        if ($name == '未知群' || $name == '陌生人'){
            var_dump($id);
        }
        return $name;
    }
    public function getUSerID($name){
        foreach($this->MemberList as $member){
            if ($name == $member['RemarkName'] || $name == $member['NickName']){
                return $member['UserName'];
            }
        }
        return null;
    }
    public function _showMsg($message){
        $srcName = null;
        $dstName = null;
        $groupName = null;
        $content = null;

        $msg = $message;
        //$this->_echo($msg);

        if ($msg['raw_msg']){
            $srcName = $this->getUserRemarkName($msg['raw_msg']['FromUserName']);
            $dstName = $this->getUserRemarkName($msg['raw_msg']['ToUserName']);
            $content = $msg['raw_msg']['Content'];//str_replace(['&lt;','&gt;'], ['<','>'], $msg['raw_msg']['Content']);
            $message_id = $msg['raw_msg']['MsgId'];

            if (strpos($content, 'http://weixin.qq.com/cgi-bin/redirectforward?args=') !== false){
                # 地理位置消息  lbbniu 可能不对
                $data = iconv('gbk', 'utf-8', $this->_get($content));
                $pos = $this->_searchContent('title', $data, 'xml');
                $tree = simplexml_load_string($this->_get($content), 'SimpleXMLElement', LIBXML_NOCDATA);
                $url = $tree->xpath('//html/body/div/img')[0]->attributes('src');//TODO: 可能不对吧
                $query = parse_url($url)['query'];
                foreach (explode('&', $query) as $item){
                    if (explode('=',$item)[0] == 'center'){
                        $loc = explode('=', $item);
                    }
                }
                $content = sprintf('%s 发送了一个 位置消息 - 我在 [%s](%s) @ %s]' ,$srcName, $pos, $url, $loc);
            }

            if ($msg['raw_msg']['ToUserName'] == 'filehelper'){
                # 文件传输助手
                $dstName = '文件传输助手';
            }

            if (substr($msg['raw_msg']['FromUserName'],0,2) == '@@'){
                # 接收到来自群的消息
                if (stripos($content, ':'.PHP_EOL/*":<br/>"*/)!==false){
                    list($people, $content) = explode(':'.PHP_EOL/*":<br/>"*/, $content);
                    $groupName = $srcName;
                    $srcName = $this->getUserRemarkName($people);
                    $dstName = 'GROUP';
                }else{
                    $groupName = $srcName;
                    $srcName = 'SYSTEM';
                }
            }elseif( substr($msg['raw_msg']['ToUserName'],0,2) == '@@'){
                # 自己发给群的消息
                $groupName = $dstName;
                $dstName = 'GROUP';
            }

            # 收到了红包
            if ($content == '收到红包，请在手机上查看'){
                $msg['message'] = $content;
            }

            # 指定了消息内容
            if (isset($msg['message'])){
                $content = $msg['message'];
            }
        }
        if( !empty($groupName)){
            $this->_echo(sprintf('%s |%s| %s -> %s: %s' , $message_id, trim($groupName), trim($srcName), trim($dstName), str_replace("<br/>", "\n", $content)));
            //不自动回复的群
            if(in_array(trim($groupName), $this->NoReplyGroup)){
                return false;
            }else{
                return true;
            }
        }else{
            $this->_echo(sprintf('%s %s -> %s: %s' , $message_id, trim($srcName), trim($dstName), str_replace("<br/>", "\n", $content)));
            return true;
        }
    }
    public static function br2nl ( $string ){
        return preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $string);
    }
    public function handleMsg($r){
        foreach($r['AddMsgList'] as $msg){
            $this->_echo('[*] 你有新的消息，请注意查收');

            $msgType = $msg['MsgType'];
            $name = $this->getUserRemarkName($msg['FromUserName']);
            $content = $msg['Content']= self::br2nl(html_entity_decode($msg['Content']));//str_replace(['&lt;','&gt;'], ['<','>'], $msg['Content']);
            $msgid = $msg['MsgId'];
            if ($this->DEBUG||true){
                if(!is_dir('msg')){
                    umask(0);
                    mkdir('msg',0777,true);
                }
                $fn = 'msg/msg' .$msgid. '.json';
                $f = fopen($fn, 'w');
                fwrite($f,self::json_encode($msg));
                $this->_echo( '[*] 该消息已储存到文件: ' . $fn);
                fclose($f);
            }
            
            if ($msgType == 1){
                $raw_msg = ['raw_msg'=> $msg];
                $isReply = $this->_showMsg($raw_msg);
                //lbbniu 重要
                if ($this->autoReplyMode&&$isReply){
                    if(substr($msg['FromUserName'],0,2) == '@@' && stripos($content, ':'.PHP_EOL/*":<br/>"*/)!==false){
                        list($people, $content) = explode(':'.PHP_EOL/*":<br/>"*/, $content);
                        //continue;
                    }
                    //自己发的消息不自动回复
                    if($msg['FromUserName'] == $this->User['UserName']){
                        $this->_echo('[*] 自己发的消息不自动回复');
                        continue;
                    }

                    $ans = $this->robo365($content,$msg['FromUserName']);
                    //问答机器人
                    if(!$ans){
                        $ans = $this->_weida($content);
                    }
                    //青云客机器人
                    if(!$ans){
                        $ans = $this->qingyunke($content);
                    }
                    //小豆比机器人
                    if(!$ans){
                        $ans = $this->_xiaodoubi($content);
                    }
                    if(!$ans){
                        $ans = $content;
                    }
                    $ans .=  "\n[IT全才-LbbNiu]";
                    if ($this->webwxsendmsg($ans, $msg['FromUserName'])){
                        $this->_echo( '自动回复: ' . $ans);
                    }else{
                        $this->_echo( '自动回复失败');
                    }
                }
            }elseif ($msgType == 3){
                $image = $this->webwxgetmsgimg($msgid);
                $raw_msg = [
                    'raw_msg'=> $msg,
                    'message'=>sprintf('%s 发送了一张图片: %s' , $name, $image)
                ];
                $this->_showMsg($raw_msg);
                $this->_safe_open($image);
            }elseif ($msgType == 34){
                $voice = $this->webwxgetvoice($msgid);
                $raw_msg = ['raw_msg'=> $msg,
                           'message'=>sprintf('%s 发了一段语音: %s' , $name, $voice)];
                $this->_showMsg($raw_msg);
                $this->_safe_open($voice);
            }elseif ($msgType == 42){
                $info = $msg['RecommendInfo'];
                $this->_echo(sprintf('%s 发送了一张名片:' , $name));
                $this->_echo('=========================');
                $this->_echo(sprintf('= 昵称: %s' , $info['NickName']));
                $this->_echo(sprintf('= 微信号: %s' , $info['Alias']));
                $this->_echo(sprintf('= 地区: %s %s' , $info['Province'], $info['City']));
                $this->_echo(sprintf('= 性别: %s' , ['未知', '男', '女'][$info['Sex']]));
                $this->_echo('=========================');
                $raw_msg = ['raw_msg'=> $msg, 
                            'message'=> sprintf('%s 发送了一张名片: %s' , $name, self::json_encode($info))];
                $this->_showMsg($raw_msg);
            }elseif ($msgType == 47){
                $url = $this->_searchContent('cdnurl', $content);
                $raw_msg = ['raw_msg'=> $msg,
                           'message'=>sprintf('%s 发了一个动画表情，点击下面链接查看: %s' , $name, $url)];
                $this->_showMsg($raw_msg);
                $this->_safe_open($url);
            }elseif ($msgType == 49){
                $appMsgType = [5=> '链接', 3=> '音乐', 7=> '微博',17=>'位置共享'];
                $this->_echo(sprintf('%s 分享了一个%s:' , $name, $appMsgType[$msg['AppMsgType']]));
                $this->_echo('=========================');
                $this->_echo(sprintf('= 标题: %s' , $msg['FileName']));
                $this->_echo(sprintf('= 描述: %s' , $this->_searchContent('des', $content, 'xml')));
                $this->_echo(sprintf('= 链接: %s' , $msg['Url']));
                $this->_echo(sprintf('= 来自: %s' , $this->_searchContent('appname', $content, 'xml')));
                $this->_echo('=========================');
                $card = [
                    'title'=> $msg['FileName'],
                    'description'=> $this->_searchContent('des', $content, 'xml'),
                    'url'=> $msg['Url'],
                    'appname'=> $this->_searchContent('appname', $content, 'xml')
                ];
                $raw_msg = ['raw_msg'=> $msg, 'message'=>sprintf( '%s 分享了一个%s: %s' ,
                    $name, $appMsgType[$msg['AppMsgType']], self::json_encode($card))];
                $this->_showMsg($raw_msg);
            }elseif ($msgType == 51){
                $raw_msg = ['raw_msg'=> $msg, 'message'=> '[*] 成功获取联系人信息'];
                $this->_showMsg($raw_msg);
            }elseif ($msgType == 62){
                $video = $this->webwxgetvideo($msgid);
                $raw_msg = ['raw_msg'=> $msg,
                           'message'=> sprintf('%s 发了一段小视频: %s' , $name, $video)];
                $this->_showMsg($raw_msg);
                $this->_safe_open($video);
            }elseif ($msgType == 10002){//撤销消息
                $raw_msg = ['raw_msg'=> $msg, 'message'=> sprintf('%s 撤回了一条消息' , $name)];
                $this->_showMsg($raw_msg);
            }else{
                $raw_msg = [
                    'raw_msg'=> $msg, 
                    'message'=> sprintf('[*] 该消息类型为: %d，可能是表情，图片, 链接或红包' , $msg['MsgType'])
                ];
                var_dump($msg);
                $this->_showMsg($raw_msg);
            }
        }
    }
  
	public function listenMsgMode(){
		$this->_echo('[*] 进入消息监听模式 ... 成功');

		$this->_run('[*] 进行同步线路测试 ... ', 'testsynccheck');

		$playWeChat = 0;
        $redEnvelope = 0;

		while (true){
            $this->lastCheckTs = time();
            list($retcode, $selector) = $this->synccheck();
            if ($this->DEBUG){
                $this->_echo(sprintf('retcode: %s, selector: %s',$retcode, $selector));
            }
            //TODO:debug
            $this->_echo(sprintf('retcode: %s, selector: %s',$retcode, $selector));

            if ($retcode == '1100'){
            	$this->_echo('[*] 你在手机上登出了微信，债见');
                break;
            }
            if ($retcode == '1101'){
                $this->_echo('[*] 你在其他地方登录了 WEB 版微信，债见');
                break;
            }elseif($retcode == '0'){
                if ($selector == '2'){
                    $r = $this->webwxsync();
                    if ($r){
                    	$this->handleMsg($r);
                    }    
                }elseif($selector == '3'){
                    $r = $this->webwxsync();
                    if ($r){
                        $this->handleMsg($r);
                    }
                }elseif($selector == '4'){//朋友圈有动态
                    $r = $this->webwxsync();
                    if ($r){
                        $this->handleMsg($r);
                    }
                }elseif ($selector == '6'){//有消息返回结果
                    # TODO
                    $redEnvelope += 1;
                    $this->_echo(sprintf('[*] 收到疑似红包消息 %d 次' , $redEnvelope));
                    $r = $this->webwxsync();
                    if ($r){
                        $this->handleMsg($r);
                    }
                }elseif ($selector == '7'){
                    $playWeChat += 1;
                    $this->_echo(sprintf('[*] 你在手机上玩微信被我发现了 %d 次' , $playWeChat));
                    $r = $this->webwxsync();
                    if ($r){
                        $this->handleMsg($r);
                    }
                }elseif ($selector == '0'){
                    sleep(1);
                }
            }
            if ((time() - $this->lastCheckTs) <= 20){
                sleep(time() - $this->lastCheckTs);
            }
		}
	}
    public function sendMsg( $name, $word, $isfile=false){
        $id = $this->getUSerID($name);
        if ($id){
            if ($isfile){
                $f = fopen($word, 'r');
                while ($line = fgets($f)) {
                    # code...
                    $line = str_replace('\n', '', $line);
                    $this->_echo('-> ' . $name . ': ' . $line);
                    if ($this->webwxsendmsg($line, $id)){
                        $this->_echo(' [成功]');
                    }else{
                        $this->_echo(' [失败]');
                    }
                    sleep(1);
                }      
            }else{
                if ($this->webwxsendmsg($word, $id)){
                    $this->_echo('[*] 消息发送成功');
                }else{
                    $this->_echo('[*] 消息发送失败');
                }
            }
        }else{
            $this->_echo('[*] 此用户不存在');
        }
    }
    public function sendMsgToAll($word){
        foreach($this->ContactList as $contact){
            $name =  $contact['RemarkName']?$contact['RemarkName']:$contact['NickName'];
            $id = $contact['UserName'];
            echo('-> ' . $name . ': ' . $word);
            if ($this->webwxsendmsg($word, $id)){
                $this->_echo(' [成功]');
            }else{
                $this->_echo(' [失败]');
            }
            sleep(1);
        }
    }
    public function sendImg($name, $file_name){
        $response = $this->webwxuploadmedia($file_name);
        $media_id = "";
        if (!empty($response)){
            $media_id = $response['MediaId'];
        }
        $user_id = $this->getUSerID($name);
        $response = $this->webwxsendmsgimg($user_id, $media_id);
    }
    public function sendEmotion($name, $file_name){
        $response = $this->webwxuploadmedia($file_name);
        $media_id = "";
        if (!empty($response)){
            $media_id = $response['MediaId'];
        }
        $user_id = $this->getUSerID($name);
        $response = $this->webwxsendmsgemotion($user_id, $media_id);
    }
	//开始登录
	public function start(){
		$this->_echo('[*] 微信网页版 ... 开动');
        if(!$this->init()) {
            QRCODE:
            while (true) {
                $this->_run('[*] 正在获取 uuid ... ', 'getUUID');
                $this->_echo('[*] 正在获取二维码 ... 成功');
                $this->genQRCode();
                $this->_echo('[*] 请使用微信扫描二维码以登录 ... ');
                if (!$this->waitForLogin()) {
                    continue;
                    $this->_echo('[*] 请在手机上点击确认以登录 ... ');
                }
                if (!$this->waitForLogin(0)) {
                    continue;
                }
                break;
            }
            $this->_run('[*] 正在登录 ... ', 'login');
        }


		if(!$this->_run('[*] 微信初始化 ... ', 'webwxinit')){
            goto QRCODE;
        }
        $this->_run('[*] 开启状态通知 ... ', 'webwxstatusnotify');
        $this->_run('[*] 获取联系人 ... ', 'webwxgetcontact');
		$this->_echo(sprintf('[*] 应有 %s 个联系人，读取到联系人 %d 个' ,
                   $this->MemberCount, count($this->MemberList)));
		$this->_echo(sprintf('[*] 共有 %d 个群 | %d 个直接联系人 | %d 个特殊账号 ｜ %d 公众号或服务号',count($this->GroupList),
                             count($this->ContactList), count($this->SpecialUsersList), count($this->PublicUsersList)));
        $this->_run('[*] 获取群 ... ', 'webwxbatchgetcontact');
        $this->_echo('[*] 微信网页版 ... 开动');
        if ($this->DEBUG)
            echo($this);

        if ($this->interactive and raw_input('[*] 是否开启自动回复模式(y/n): ') == 'y'){
        	$this->autoReplyMode = true;
            $this->_echo('[*] 自动回复模式 ... 开启');
        } else{
            if($this->autoReplyMode)
                $this->_echo('[*] 自动回复模式 ... 开启');
            else
        	    $this->_echo('[*] 自动回复模式 ... 关闭');
        }
        if(extension_loaded("pcntl")){
            $pf = pcntl_fork();
            if ($pf){ //父进程负责监听消息
                $this->listenMsgMode();
                exit();
            }
        }elseif(extension_loaded("pthreads")){
            return true;
        }else{
            $this->_echo('[*] 缺少扩展，暂时只能获取监听消息，不能发送消息');
            $this->_echo('[*] 如果要发消息，请安装pcntl或者pthreads扩展');
            $this->listenMsgMode();
        }
    
        sleep(2);
        $this->readRun();
        return false;
	}

    public function readRun(){
        $this->help();
        while(true){
            $text = raw_input('');
            if($text == 'quit'){
                //listenProcess.terminate()
                $this->_echo('[*] 退出微信');
                exit();
            }elseif($text == 'help'){
                $this->help();
            }elseif($text == 'me'){
                $this->_echo($this->User);
            }elseif($text == 'friend'){
                foreach ($this->ContactList as $key => $value) {
                    # code...
                    $this->_echo("NickName:{$value['NickName']}----Alias:{$value['Alias']}----UserName:{$value['UserName']}");
                }
            }elseif($text == 'qun'){
                foreach ($this->GroupList as $key => $value) {
                    # code...
                    $this->_echo("NickName:{$value['NickName']}----MemberCount:{$value['UserName']}----UserName:{$value['UserName']}");
                }
            }elseif($text == 'qunyou'){
                foreach ($this->GroupMemeberList as $key => $value) {
                    # code...
                    $this->_echo("NickName:{$value['NickName']}----UserName:{$value['UserName']}");
                }
            }elseif($text == 'gzh'){
                foreach ($this->PublicUsersList as $key => $value) {
                    # code...
                    $this->_echo("NickName:{$value['NickName']}----Alias:{$value['Alias']}----UserName:{$value['UserName']}");
                }
            }elseif($text == 'tsh'){
                foreach ($this->SpecialUsersList as $key => $value) {
                    # code...
                    $this->_echo("NickName:{$value['NickName']}----UserName:{$value['UserName']}");
                }
            }elseif( substr($text, 0,2) == '->'){
                list($name, $word) = explode(':',substr($text,2));
                if ($name == 'all')
                    $this->sendMsgToAll($word);
                else
                    $this->sendMsg($name, $word);
            }elseif( substr($text, 0,3) == 'm->'){
                list($name, $file) = explode(':',substr($text,3));
                $this->sendMsg($name, $file, true);
            }elseif( substr($text, 0,3) == 'f->'){
                $this->_echo('发送文件');
            }elseif( substr($text, 0,3) == 'i->'){
                $this->_echo('发送图片');
                list($name, $file_name) = explode(':',substr($text,3));
                $this->sendImg($name, $file_name);
            }elseif( substr($text, 0,3) == 'e->'){
                $this->_echo('发送表情');
                list($name, $file_name) = explode(':',substr($text,3));
                $this->sendEmotion($name, $file_name);
            }
        }    
    }

    public function help(){
        $help = '
==============================================================
============================================================== 
->[昵称或ID]:[内容] 给好友发送消息
m->[昵称或ID]:[文件路径] 给好友发送文件中的内容
f->[昵称或ID]:[文件路径] 给好友发送文件
i->[昵称或ID]:[图片路径] 给好友发送图片
e->[昵称或ID]:[文件路径] 给好友发送表情(jpg/gif)
quit 退出程序
help 帮助
me 查看自己的信息
friend 好友列表
qun 群列表
qunyou 群友列表
gzh 公众号列表
tsh 特殊号列表
==============================================================
==============================================================
';
        $this->_echo($help);
    }

    public function init(){
        if(file_exists("key.key")){
            $array = json_decode(file_get_contents("key.key"),true);
            if($array){
                $this->skey = $array['skey'];
                $this->sid = $array['sid'];
                $this->uin = $array['uin'];
                $this->pass_ticket = $array['pass_ticket'];
                $this->deviceId = $array['deviceId'];

                $this->BaseRequest = [
                    'Uin'=> intval($this->uin),
                    'Sid'=> $this->sid,
                    'Skey'=> $this->skey,
                    'DeviceID'=> $this->deviceId
                ];
                return true;
            }
        }
        return false;
    }
    public function initSave(){
        file_put_contents("key.key",self::json_encode([
            'skey'=>$this->skey,
            'sid'=>$this->sid,
            'uin'=>$this->uin,
            'pass_ticket'=>$this->pass_ticket,
            'deviceId'=>$this->deviceId
        ]));
    }
    public function _safe_open($path){
        //lbbniu 有问题
        if ($this->autoOpen){
            if(PHP_OS == "Linux"){
                system(sprintf("xdg-open %s &" , $path));
            }elseif(PHP_OS == "Darwin"){
                system(sprintf('open %s &' , $path));
            }else{
                system($path);
            }
        }
    }
	public function _run($msg,$func){
		echo($msg);
		if($this->$func()){
			$this->_echo('成功');
            return true;
		}else{
            if($func == 'webwxinit'){
                $this->_echo("失败\n");
                return false;
            }
			$this->_echo("失败\n[*] 退出程序");
			exit();
		}
	}

	public function _echo($data){
        if(is_string($data)){
            echo $data."\n";
        }elseif(is_array($data)){
            print_r($data);
        }elseif(is_object($data)){
            var_dump($data);
        }else{
            echo $data;
        }
	}
    public function _printQR($mat){
        $black = "\033[40m  \033[0m";
        $white = "\033[47m  \033[0m";
        foreach ($mat as $v) {
            # code...
            for($i=0;$i<strlen($v);$i++){
                if($v[$i]){
                    print $black;
                }else{
                    print $white;
                }
            }
            print "\n";
        }
    }
    public function _str2qr($str){
        //$errorCorrectionLevel = 'L';//容错级别
        //$matrixPointSize = 190;//生成图片大小
        //QRcode::png($str, false, $errorCorrectionLevel, $matrixPointSize, 2);
        $mat=QRcode::text($str);
        $this->_printQR($mat);
    }

    //lbbniu 需要修改
    public function _transcoding($data){
        if  (!$data){
            return $data;
        }
        $result = null;
        if (gettype($data) == 'unicode'){
            $result = $data;
        }elseif(gettype($data) == 'string'){
            $result = $data;
        }
        return $result;
    }

    public static function json_encode($json){
        return json_encode($json,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

	/**
	 * GET 请求
	 * @param string $url
	 */
	private function _get($url,$params=[],$api = false){
		$oCurl = curl_init();
		if(stripos($url,"https://")!==FALSE){
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
		}
		$header = [
			'User-Agent: '.$this->user_agent,
			'Referer: https://wx.qq.com/'
		];
		if($api == 'webwxgetvoice')
			$header[]='Range: bytes=0-';
		if($api == 'webwxgetvideo')
			$header[]='Range: bytes=0-';
		curl_setopt($oCurl, CURLOPT_HTTPHEADER, $header);
        if(!empty($params)){
            if(strpos($url,'?')!==false){
                $url .="&".http_build_query($params);
            }else{
                $url .="?".http_build_query($params);
            }
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
		curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($oCurl, CURLOPT_TIMEOUT, 36);
		curl_setopt($oCurl, CURLOPT_COOKIEFILE, $this->cookie);
		curl_setopt($oCurl, CURLOPT_COOKIEJAR, $this->cookie);
		$sContent = curl_exec($oCurl);
		$aStatus = curl_getinfo($oCurl);
		curl_close($oCurl);
		if(intval($aStatus["http_code"])==200){
			return $sContent;
		}else{
			return false;
		}
	}

	/**
	 * POST 请求
	 * @param string $url
	 * @param array $param
	 * @param boolean $post_file 是否文件上传
	 * @return string content
	 */
	private function _post($url,$param,$jsonfmt=true,$post_file=false){
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
        if($jsonfmt){
        	$param = self::json_encode($param);
            //var_dump($param);
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
			'Content-Type: application/json; charset=UTF-8',
			'User-Agent: '.$this->user_agent
		];
		curl_setopt($oCurl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($oCurl, CURLOPT_URL, $url);
		curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($oCurl, CURLOPT_POST,true);
		curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
		curl_setopt($oCurl, CURLOPT_COOKIEFILE, $this->cookie);
		curl_setopt($oCurl, CURLOPT_COOKIEJAR, $this->cookie);
		$sContent = curl_exec($oCurl);
		$aStatus = curl_getinfo($oCurl);
		curl_close($oCurl);
		if(intval($aStatus["http_code"])==200){
			if($jsonfmt)
				return json_decode($sContent,true);
			return $sContent;
		}else{
			return false;
		}
	}
    public function _xiaodoubi($word){
        $url = 'http://www.xiaodoubi.com/bot/chat.php';
        try{
            $r = $this->_post($url, ['chat'=> $word],false);
            return $r;
        } catch(Exception $e){
            return "让我一个人静静 T_T...";
        }
    }

    public function _simsimi($word){
        $key = '';
        $url = sprintf('http://sandbox.api.simsimi.com/request.p?key=%s&lc=ch&ft=0.0&text=%s',$key, urlencode($word));
        $ans = json_decode(file_get_contents($url),true);
        if ($ans['result'] == '100'){
            return $ans['response'];
        }else{
            return '你在说什么，风太大听不清列';
        }
    }
    public function _searchContent($key, $content, $fmat='attr'){
        if($fmat == 'attr'){
            if (preg_match('/'.$key . '\s?=\s?"([^"<]+)"/', $content,$pm)){
                return $pm[1];
            }
        }elseif($fmat == 'xml'){
            if(!preg_match("/<{$key}>([^<]+)<\/{$key}>/",$content,$pm)){
                preg_match("/<{$key}><\!\[CDATA\[(.*?)\]\]><\/{$key}>/",$content,$pm);
            }
            if (isset($pm[1])){
                return $pm[1];
            }
        }
        return '未知';
    }
    public function  qingyunke($word){
        $url = "http://api.qingyunke.com/api.php?key=free&appid=0&msg=".urlencode($word);
        $ans = json_decode($this->juhecurl($url),true);
        if ($ans['result'] == 0){
            return $ans['content'];
        }else{
            return '你在说什么，风太大听不清列';
        }
    }
    public function robo365($word,$ToUserName){
        $robo365 = new Robot365(6);
        return $robo365->search($word,$ToUserName);
    }
    /**
     * 问答机器人
     * @param $word
     * @return string
     */
    public function _weida($word){
        //配置您申请的appkey
        $appkey = "1fca5eeb8e26b07baf8136981d4a6e1e";
        //************1.问答************
        $url = "http://op.juhe.cn/robot/index";
        $params = array(
            "key" => $appkey,//您申请到的本接口专用的APPKEY
            "info" => $word,//要发送给机器人的内容，不要超过30个字符
            "dtype" => "json",//返回的数据的格式，json或xml，默认为json
            "loc" => "",//地点，如北京中关村
            "lon" => "",//经度，东经116.234632（小数点后保留6位），需要写为116234632
            "lat" => "",//纬度，北纬40.234632（小数点后保留6位），需要写为40234632
            "userid" => "",//1~32位，此userid针对您自己的每一个用户，用于上下文的关联
        );
        $paramstring = http_build_query($params);
        $content = $this->juhecurl($url,$paramstring);
        $result = json_decode($content,true);
        if($result){
            if($result['error_code']=='0'){
                //print_r($result);
                return $result['result']['text'];
            }else{
                //echo $result['error_code'].":".$result['reason'];
                return '';
            }
        }else{
            //echo "请求失败";
            return '';
        }
    }
    /**
     * 请求接口返回内容
     * @param  string $url [请求的URL地址]
     * @param  string $params [请求的参数]
     * @param  int $ipost [是否采用POST形式]
     * @return  string
     */
    public function juhecurl($url,$params=false,$ispost=0){
        $httpInfo = array();
        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_HTTP_VERSION , CURL_HTTP_VERSION_1_1 );
        curl_setopt( $ch, CURLOPT_USERAGENT , $this->user_agent );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT , 60 );
        curl_setopt( $ch, CURLOPT_TIMEOUT , 60);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER , true );
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if( $ispost )
        {
            curl_setopt( $ch , CURLOPT_POST , true );
            curl_setopt( $ch , CURLOPT_POSTFIELDS , $params );
            curl_setopt( $ch , CURLOPT_URL , $url );
        }
        else
        {
            if($params){
                curl_setopt( $ch , CURLOPT_URL , $url.'?'.$params );
            }else{
                curl_setopt( $ch , CURLOPT_URL , $url);
            }
        }
        $response = curl_exec( $ch );
        if ($response === FALSE) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo( $ch , CURLINFO_HTTP_CODE );
        $httpInfo = array_merge( $httpInfo , curl_getinfo( $ch ) );
        curl_close( $ch );
        return $response;
    }
}
if(!extension_loaded('pthreads')){
    class Thread {
        public function start(){

        }
    }
}
class ListenMsg extends Thread {
    private $weixin;
    public function __construct(WebWeiXin $weixin){
        # code...
        $this->weixin = $weixin;
    }
    public function run(){
        if($this->weixin){
            $this->weixin->_echo("[*] 进入消息监听模式 ......ListenMsg...run");
            $this->weixin->listenMsgMode();
       }
    }
}
class ListenWrite extends Thread {
    public function __construct(WebWeiXin $weixin){
        $this->weixin = $weixin;
    }
    public function run(){
       if($this->weixin){
            if(!defined('STDIN'))  define('STDIN',  fopen('php://stdin',  'r'));
            if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
            if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
            $this->weixin->_echo("[*] 进入命令行等待输入模式 ......ListenWrite...run");
            $this->weixin->readRun();
       }
    }
}




$weixin = new WebWeiXin();
//var_dump($weixin);
$weixin->loadConfig([
    'interactive'=>true,
    //'autoReplyMode'=>true,
    //'DEBUG'=>true
]);
//var_dump($weixin->robo365("不错",'1222'));
//exit();
if($weixin->start()){
    $msg  = new ListenMsg($weixin);
    $write = new ListenWrite($weixin);
    $msg->start();
    sleep(2);
    $write->start();
}










