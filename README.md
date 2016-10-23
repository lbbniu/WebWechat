# WebWechat网页微信PHP登录的实现依赖扩展---* curl* pcntl 或者 pthreads* gd#微信网页版协议分析##微信网页版协议分析（1）-登录最近研究了微信网页版的实现原理，接下来会通过一系列的文章来总结这次研究的结果，最终会通过PHP代码实现一个简单的微信机器人程序。###1.获取uuid（get）https://login.wx.qq.com/jslogin?appid=wx782c26e4c19acffb&redirect_uri=https%3A%2F%2Fwx.qq.com%2Fcgi-bin%2Fmmwebwx-bin%2Fwebwxnewloginpage&fun=new&lang=zh_CN&_=1476606163580####参数：	appid：固定为wx782c26e4c19acffb	redirect_rui：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxnewloginpage经过url编码	fun：固定值new	lang：语言类型，中国zh_CN	_：当前的unix时间戳####返回数据格式：	window.QRLogin.code = 200; window.QRLogin.uuid = "gf5Gk61zEA==";	window.QRLogin.uuid的值就是我们需要的uuid###2.获取二维码（get）	https://login.weixin.qq.com/qrcode/gf5Gk61zEA==	固定格式：https://login.weixin.qq.com/qrcode/ 拼接上第一步的带的uuid####二维码中实际内容如下地址：	https://login.weixin.qq.com/l/gf5Gk61zEA==	即：https://login.weixin.qq.com/l/ 加上那个uuid###3.等待登录扫描（get轮询）：https://login.wx.qq.com/cgi-bin/mmwebwx-bin/login?loginicon=true&uuid=gf5Gk61zEA==&tip=0&r=862560455&_=1476606163582####参数：	loginicon：true	uuid：第一步得到的uuid	tip：0	r：862560455 非必须参数	_：同上为unix时间戳####返回数据格式：	window.code=408;//登录超时code为408	window.code=201;window.userAvatar = 'data:img/jpg;base64';//扫描成功 201，userAvatar为用户头像	window.code=200;//确认登录code 200， 还有下面的redirect_uri的获取cookie的连接	window.redirect_uri="https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxnewloginpage?ticket=AYfheMIH6tt9EmcZ0DxCKF4a@qrticket_0&uuid=YeGrrvqmHQ==&lang=zh_CN&scan=1476606728";###4.登录后获取cookie信息（get）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxnewloginpage?ticket=AYfheMIH6tt9EmcZ0DxCKF4a@qrticket_0&uuid=YeGrrvqmHQ==&lang=zh_CN&scan=1476606728&fun=new&version=v2&lang=zh_CN 此连接是上一步确认登录后获取的连接。####返回数据格式（xml，还有登录后设置的cookie数据）：	<error>		<ret>0</ret>		<message></message>		<skey>@crypt_14ae1b12_b73ba2673448154847d7007a2de3c53b</skey>		<wxsid>jSsRlGGPyY7U8det</wxsid>		<wxuin>566148615</wxuin>		<pass_ticket>kUY4PSgKNy4eOlWI%2FwIBMVULe3KHPVyvDqw1%2B4DVVu9McVvE2d5fL7LFOfa4iYnk</pass_ticket>		<isgrayscale>1</isgrayscale>	</error>	xml中skey、wxsid、wxuin、pass_ticket重要参数，	要在接来的请求中使用，需要记下来，还有返回的cookie信息，在接下来的请求中，都要去携带上才可以。##微信网页版协议分析（2）-获取信息###1.微信初始化请求（post）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxinit?r=862039733&lang=zh_CN&pass_ticket=kUY4PSgKNy4eOlWI%252FwIBMVULe3KHPVyvDqw1%252B4DVVu9McVvE2d5fL7LFOfa4iYnk这个请求会获取联系人信息、公众号信息、自己的账号信息####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：	{		"BaseRequest": {			"Uin": "566148615",			"Sid": "jSsRlGGPyY7U8det",			"Skey": "@crypt_14ae1b12_b73ba2673448154847d7007a2de3c53b",			"DeviceID": "e119795675188164"		}	}	Uin、Sid、Skey 登录后获取的xml中的数据	DeviceID 是e + 随机数####返回数据（json，用户的好友信息）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"Count": 11,		"ContactList": [...], //联系人信息、公众号信息、群		"SyncKey": {	        "Count": 4,	        "List": [	            {	                "Key": 1,	                "Val": 635705559	            },	            ... //同步key值，下次请求时要写到上	        ]	    },	    "User": {	        "Uin": xxx,	        "UserName": xxx,	        "NickName": xxx,	        "HeadImgUrl": xxx,	        "RemarkName": "",	        "PYInitial": "",	        "PYQuanPin": "",	        "RemarkPYInitial": "",	        "RemarkPYQuanPin": "",	        "HideInputBarFlag": 0,	        "StarFriend": 0,	        "Sex": 1,	        "Signature": "Apt-get install B",	        "AppAccountFlag": 0,	        "VerifyFlag": 0,	        "ContactFlag": 0,	        "WebWxPluginSwitch": 0,	        "HeadImgFlag": 1,	        "SnsFlag": 17	    },		"ChatSet": "xxx",		"SKey": "@crypt_14ae1b12_b73ba2673448154847d7007a2de3c53b",		"ClientVersion": 369302288,		"SystemTime": 1476608977,		"GrayScale": 1,		"InviteStartCount": 40,		"MPSubscribeMsgCount": 7,		"ClickReportInterval": 600000	}###2.webwxstatusnotify通知消息已读（post）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxstatusnotify?pass_ticket=ZDJfLCa0EAKrLn2CdD7MDl%252B54GwlW0IEiwYOsm6II%252F8W57y0pF1F8fqS%252B5z4INU5客户端读取消息后要发起请求，告诉服务器消息已经读取，从而通知手机客户端####参数	pass_ticket 登录请求返回的xml中的值####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：	{		"BaseRequest": {			"Uin": 566148615,			"Sid": "EKjoZCMRIvrY7NIP",			"Skey": "@crypt_14ae1b12_f59314a579c67b15f838d09feb79c17f",			"DeviceID": "e098855372553243"		},		"Code": 3,		"FromUserName": 自己ID, 	    "ToUserName": 自己ID, 		"ClientMsgId": 时间戳	}####返回数据（json）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"MsgID": "1525762281689643050"	}###3.获取联系人信息列表（get）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxgetcontact?pass_ticket=ZDJfLCa0EAKrLn2CdD7MDl%252B54GwlW0IEiwYOsm6II%252F8W57y0pF1F8fqS%252B5z4INU5&r=1476608979549&seq=0&skey=@crypt_14ae1b12_f59314a579c67b15f838d09feb79c17f####参数	pass_ticket 登录请求中返回xml中获取	seq=0 固定值即可	skey 初始化请求中获取	####返回数据（json）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"MemberCount": 637,		"MemberList": [			{				"Uin": 0,		        "UserName": xxx,		        "NickName": "LbbNiu",		        "HeadImgUrl": xxx,		        "ContactFlag": 3,		        "MemberCount": 0,		        "MemberList": [],		        "RemarkName": "",		        "HideInputBarFlag": 0,		        "Sex": 0,		        "Signature": "IT全才-LbbNiu",		        "VerifyFlag": 8,		        "OwnerUin": 0,		        "PYInitial": "LbbNiu",		        "PYQuanPin": "LbbNiu",		        "RemarkPYInitial": "",		        "RemarkPYQuanPin": "",		        "StarFriend": 0,		        "AppAccountFlag": 0,		        "Statues": 0,		        "AttrStatus": 0,		        "Province": "",		        "City": "",		        "Alias": "Urinxs",		        "SnsFlag": 0,		        "UniFriend": 0,		        "DisplayName": "",		        "ChatRoomId": 0,		        "KeyWord": "gh_",		        "EncryChatRoomId": ""			}			....//联系人列表		],		"Seq": 0	}###4.webwxbatchgetcontact获取聊天会话列表信息（post）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxbatchgetcontact?type=ex&r=1476608979648&pass_ticket=ZDJfLCa0EAKrLn2CdD7MDl%252B54GwlW0IEiwYOsm6II%252F8W57y0pF1F8fqS%252B5z4INU5####参数	type=ex 固定值即可	r 当前时间戳	pass_ticket 登录请求中返回xml中获取####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：	{		"BaseRequest": {			"Uin": 566148615,			"Sid": "EKjoZCMRIvrY7NIP",			"Skey": "@crypt_14ae1b12_f59314a579c67b15f838d09feb79c17f",			"DeviceID": "e316575061818116"		},		"Count": 7,		"List": [			{			"UserName": "@@e2da072e5beda58413f788fd2978b6f9fbde2ba337a71f02e1458958fcdb8371", //去要获取的群UserName			"ChatRoomId": ""			}…		]	}####返回数据（json）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"Count": 7,		"ContactList": [		]	}###5.同步刷新（get轮询）：https://webpush.wx.qq.com/cgi-bin/mmwebwx-bin/synccheck?r=1476611147442&skey=%40crypt_14ae1b12_f59314a579c67b15f838d09feb79c17f&sid=EKjoZCMRIvrY7NIP&uin=566148615&deviceid=e673682280871456&synckey=1_643606091%7C2_643606203%7C3_643606171%7C11_643605834%7C13_643590001%7C201_1476611120%7C1000_1476610779%7C1001_1476590971%7C1004_1475896795&_=1476611120940####参数	r 时间戳	skey 初始化信息中获取	sid 登录请求中返回xml中获取	uin 登录请求中返回xml中获取	synckey 初始化信息中获取	deviceid 设备id	_ 时间戳####返回数据：	window.synccheck={retcode:"0",selector:"2"}	retcode:	    0 正常	    1100 失败/登出微信	selector:	    0 正常		2 新的消息		4 通过时发现，删除好友		6 删除时发现和对方通过好友验证		7 进入/离开聊天界面 （可能没有了）###6.获取消息（post，cookie）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxsync?sid=2qui+CC4YTiiI2zk&skey=@crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0&lang=zh_CN&pass_ticket=tM909DkHH8fXLR7hhwIgn9MqmSuCxx6%252BcdaA9qE203zxP7fMT%252FtxPlY7opbnnVE2####参数	pass_ticket 登录请求中返回xml中获取	r 时间戳	skey 初始化信息中获取	sid 登录请求中返回xml中获取	lang=zh_CN 语言类型####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：	{		"BaseRequest":{			"Uin":566148615,			"Sid":"2qui+CC4YTiiI2zk",			"Skey":"@crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0",			"DeviceID":"e151332185497293"		},		"SyncKey":{		"Count":9,		"List":[			{"Key":1,"Val":643606091},			{"Key":2,"Val":643606236},			{"Key":3,"Val":643606171},			{"Key":11,"Val":643606232},			{"Key":13,"Val":643590001},			{"Key":203,"Val":1476612126},			{"Key":1000,"Val":1476610779},			{"Key":1001,"Val":1476590971},			{"Key":1004,"Val":1475896795}		]		},		"rr":856481324	}   ####返回数据（json）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"AddMsgCount": 1,		"AddMsgList": [			{				"MsgId": "8410419333108271670",				"FromUserName": "@595d9f44c64e2f480baa0d6430ca58ef053a768daa6d7b3fcc4bece244bcbce3",				"ToUserName": "@e5403f77c2193da671790c1a242d0b43ac6f570e5047993ef745d13d6376b57b",				"MsgType": 51,				"Content": "&lt;msg&gt;<br/>&lt;op id='5'&gt;<br/>&lt;username&gt;wxid_e602if1itlm821&lt;/username&gt;<br/>&lt;/op&gt;<br/>&lt;/msg&gt;",				"Status": 3,				"ImgStatus": 1,				"CreateTime": 1476612570,				"VoiceLength": 0,				"PlayLength": 0,				"FileName": "",				"FileSize": "",				"MediaId": "",				"Url": "",				"AppMsgType": 0,				"StatusNotifyCode": 5,				"StatusNotifyUserName": "@e5403f77c2193da671790c1a242d0b43ac6f570e5047993ef745d13d6376b57b",				"RecommendInfo": {					"UserName": "",					"NickName": "",					"QQNum": 0,					"Province": "",					"City": "",					"Content": "",					"Signature": "",					"Alias": "",					"Scene": 0,					"VerifyFlag": 0,					"AttrStatus": 0,					"Sex": 0,					"Ticket": "",					"OpCode": 0				},				"ForwardFlag": 0,				"AppInfo": {					"AppID": "",					"Type": 0				},				"HasProductId": 0,				"Ticket": "",				"ImgHeight": 0,				"ImgWidth": 0,				"SubMsgType": 0,				"NewMsgId": 8410419333108272000			}		],		"ModContactCount": 0,		"ModContactList": [],		"DelContactCount": 0,		"DelContactList": [],		"ModChatRoomMemberCount": 0,		"ModChatRoomMemberList": [],		"Profile": {			"BitFlag": 0,			"UserName": {				"Buff": ""			},			"NickName": {				"Buff": ""			},			"BindUin": 0,			"BindEmail": {				"Buff": ""			},			"BindMobile": {				"Buff": ""			},			"Status": 0,			"Sex": 0,			"PersonalCard": 0,			"Alias": "",			"HeadImgUpdateFlag": 0,			"HeadImgUrl": "",			"Signature": ""		},		"ContinueFlag": 0,		"SyncKey": {			"Count": 10,			"List": [				{					"Key": 1,					"Val": 643606091				}				..... //同步key			]		},		"SKey": "",		"SyncCheckKey": {			"Count": 10,			"List": [				{					"Key": 1,					"Val": 643606091				}				.....  //同步检测消息key			]		}	}###7.webwxstatreport(post):https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxstatreport?fun=new&lang=zh_CN登录前和登录后都有，可能是用来统计用的，不影响其他获取信息，暂时不用对次请求进行处理####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：##微信网页版协议分析（3）-消息接口###1.发文字消息（post）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxsendmsg?lang=zh_CN&pass_ticket=tM909DkHH8fXLR7hhwIgn9MqmSuCxx6%252BcdaA9qE203zxP7fMT%252FtxPlY7opbnnVE2####参数
	lang=zh_CN 语言
	pass_ticket 登录后返回信息中获取####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：	{		"BaseRequest": {			"Uin": 566148615,			"Sid": "2qui+CC4YTiiI2zk",			"Skey": "@crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0",			"DeviceID": "e784205590563506"		},		"Msg": {			"Type": 1,			"Content": "@李松林 啊啊啊啊啊啊", 			"FromUserName": "@595d9f44c64e2f480baa0d6430ca58ef053a768daa6d7b3fcc4bece244bcbce3",			"ToUserName": "@@9e1c0ab62b5ab222835786c00192fb1e10c75f8082c245d360ac6a6bd2efe2df",			"LocalID": "14766146756340722",			"ClientMsgId": "14766146756340722"		},		"Scene": 0	}	Type: 1 文字消息, 	Content: 要发送的消息, 	FromUserName: 自己ID, 	ToUserName: 好友ID, 	LocalID: 与clientMsgId相同, 	ClientMsgId: 时间戳左移4位随后补上4位随机数####返回数据（json）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"MsgID": "4527210051674039705",		"LocalID": "14766146756340722"	}###2.发送表情消息（post）:https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxsendemoticon?fun=sys&lang=zh_CN####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：	{		"BaseRequest": {			"Uin": 566148615,			"Sid": "2qui+CC4YTiiI2zk",			"Skey": "@crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0",			"DeviceID": "e961665475339031"		},		"Msg": {			"Type": 47,			"EmojiFlag": 2,			"EMoticonMd5": "44682e637b75a3f5d6747d61dbd23a15",			"FromUserName": "@595d9f44c64e2f480baa0d6430ca58ef053a768daa6d7b3fcc4bece244bcbce3",			"ToUserName": "@e5403f77c2193da671790c1a242d0b43ac6f570e5047993ef745d13d6376b57b",			"LocalID": "14766150190560991",			"ClientMsgId": "14766150190560991"		},		"Scene": 0	}	Type: 47 emoji消息, 	EmojiFlag: 2, 	MediaId: 表情上传后的媒体ID, 	FromUserName: 自己ID, 	ToUserName: 好友ID, 	LocalID: 与clientMsgId相同, 	ClientMsgId: 时间戳左移4位随后补上4位随机数####返回数据（json）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"MsgID": "1604548346807369725",		"LocalID": "14766150190560991"	}###3.发送图片消息：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxsendmsgimg?fun=async&f=json&lang=zh_CN####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：	{		"BaseRequest": {			"Uin": 566148615,			"Sid": "2qui+CC4YTiiI2zk",			"Skey": "@crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0",			"DeviceID": "e374531581999650"		},		"Msg": {			"Type": 3,			"MediaId": "@crypt_4d268bd0",			"FromUserName": "@595d9f44c64e2f480baa0d6430ca58ef053a768daa6d7b3fcc4bece244bcbce3",			"ToUserName": "@e5403f77c2193da671790c1a242d0b43ac6f570e5047993ef745d13d6376b57b",			"LocalID": "14766158484990338",			"ClientMsgId": "14766158484990338"		},		"Scene": 0	}	Type: 3 媒体消息, 	MediaId: 表情上传后的媒体ID, 	FromUserName: 自己ID, 	ToUserName: 好友ID, 	LocalID: 与clientMsgId相同, 	ClientMsgId: 时间戳左移4位随后补上4位随机数####返回数据（json）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"MsgID": "6722400141243782346",		"LocalID": "14766158484990338"	}###4.发送文件消息（post）:https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxsendappmsg?fun=async&f=json####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：	{		"BaseRequest": {			"Uin": 566148615,			"Sid": "NO0RlTCXvpBGaag7",			"Skey": "@crypt_14ae1b12_3bdec7023595f68f9900b5d9d7d30b75",			"DeviceID": "e984332143788024"		},		"Msg": {			"Type": 6,			"Content": "<appmsg appid='wxeb7ec651dd0aefa9' sdkver=''><title>top10.csv</title><des></des><action></action><type>6</type><content></content><url></url><lowurl></lowurl><appattach><totallen>1760</totallen><attachid>@crypt_f672a24d_9d9c7d6a3b5ec058db1e6eb3d7c66917ede2e615535e9659365322de110ac41877d9a3b878ed82c0959f9b4602ef6e6d784ae853ab4e242d201cb19b9ee255a337f884eab998c5c43e7d433c01f14c91</attachid><fileext>csv</fileext></appattach><extinfo></extinfo></appmsg>",			"FromUserName": "@257b5aa7fb7b1672afb889be97197478cf618d9bab2b470cf767a61ae9f7f321",			"ToUserName": "@@ab3e18c922f8ab162762f6da231236137ca52871419ec9cafc2421607e82e1ca",			"LocalID": "14767614604200494",			"ClientMsgId": "14767614604200494"		},		"Scene": 0	}####返回数据（json）：	{		"BaseResponse": {			"Ret": 0,			"ErrMsg": ""		},		"MsgID": "6923581840474342573",		"LocalID": "14767614604200494"	}###5.上传附件接口（post）：https://file.wx.qq.com/cgi-bin/mmwebwx-bin/webwxuploadmedia?f=json####请求头：Content-Type:multipart/form-data; boundary=----WebKitFormBoundaryoWmrkW7MIGyBhlOt####提交参数（表单信息）：
	 id 文件id
    name 文件名
    type 文件类型
    lastModifieDate 最后修改时间
    size 文件大小
    mediatype 文件媒体类型 doc pic
    uploadmediarequest（json字符串）		
    		  BaseRequest
    		  		Uin 登录中获得
            		Sid 登录中获得
            		Skey 登录中获得
            		DeviceID 设备id
            ClientMediaId 文件本地消息id
            TotalLen 文件总大小
            StartPos 开始位置
            DataLen 数据长度
            MediaType 媒体类型 4
    webwx_data_ticket cookie中信息
    pass_ticket 
    filename 文件流
####返回数据（json）：
```json
{
	"BaseResponse": {
		"Ret": 0,
		"ErrMsg": ""
	}
	,
	"MediaId": "@crypt_6084141c_c73c046b750fd6e3e82d0aba4839b89b30851de7eace0446a4f73b524e3b43d41b24048e9c10cf7ba3387d04d63e6bd75bf8e3237acdddda5d4e3ede176617370573ebb2f820595076c55906a50a50a7",
	"StartPos": 25929,
	"CDNThumbImgHeight": 0,
	"CDNThumbImgWidth": 0
}
```###6.图片接口：####获取消息图片（get）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxgetmsgimg?&MsgID=6829659093985341608&skey=%40crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0&type=slave#####参数	MsgID: 消息ID 	type: slave 略缩图 or 为空时加载原图 	skey: xxx####获取联系人头像（get）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxgeticon?seq=637275253&username=@aa357b7fc1ccb2e77a2632a6251fb6de2c5dd1c0ec503f04b25cfd34c256956a&skey=@crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0
#####参数	seq: 数字，可为空 	username: ID 	skey: xxx####获取群头像（get）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxgetheadimg?seq=0&username=@@eca74ece82ad7947d262c59d53ab5d001e8776374141377cc025956b44bd1c03&skey=@crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0#####参数```seq: 数字，可为空 username: 群ID skey: xxx```###6.多媒体接口：####获取语音消息（get）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxgetvoice?msgid=6181125285738858128&skey=@crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0
#####参数```msgid: 消息ID skey: xxx```####获取视频消息信息（get）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxgetvideo?msgid=114757855447263581&skey=%40crypt_14ae1b12_1bb5e370393b8cd502919825fe9dbfc0
#####参数```msgid: 消息ID skey: xxx```####获取其他文件：https://file.wx.qq.com/cgi-bin/mmwebwx-bin/webwxgetmedia?sender=@@aa4d90eee1984452cfacf8b510ce6547af58aa4fe34fe567787c716fd262d42c&mediaid=@crypt_3d1bb9c7_7f07116cc9b86e9d8237ad938293eee8a820acfa35e7bdaa41a03fc2fb17b60a4c6da7f967714259dec587505a1b55b10e61e301f733ec718167d8d355f8809257d3fbf382d775e2872e552957e894d52060d8766133214a58ea017e6416216c9f6f1d6056e5b22fe6c78a50efcf384969a7e1b96f348f581853b41be070c16ea4d510b8d10d747a3fc5a3909e1ed2deea43aa6db558c6dd58d453e8d6235d9f6a7c7bc0eb752fb5aee59568ab8de8ff38e0064b827765ae847d6a3503fba9a970006d18f0092a12811ccaec57c071bd&filename=51CTO%E4%B8%8B%E8%BD%BD-MySQL%E6%80%A7%E8%83%BD%E8%B0%83%E4%BC%98%E4%B8%8E%E6%9E%B6%E6%9E%84%E8%AE%BE%E8%AE%A1.pdf&fromuser=1443149207&pass_ticket=td%252BZEx1ANBEB8wz%252BxjYhotl3fChIto%252FyC4w%252FNHMopoqOP8Eya9%252FivEs2lsPEDqEj&webwx_data_ticket=gSc8PoV98Y4y98pOsP6hmcpj
#####参数
	sender 发送者UserName
	mediaid 媒体id
	filename 文件名
	fromuser 接受者UserName
	pass_ticket 登录返回信息中
	webwx_data_ticket cookie中信息###7.账号类型####个人账号	以@开头，例如：@xxx####群聊	以@@开头，例如：@@xxx####公众号/服务号	```以@开头，但其VerifyFlag & 8 != 0 VerifyFlag:          一般公众号/服务号：8          微信自家的服务号：24          微信官方账号微信团队：56
```####特殊账号	像文件传输助手之类的账号，有特殊的ID，目前已知的有：filehelper, newsapp, fmessage, weibo, qqmail, fmessage, tmessage, qmessage, qqsync, floatbottle, lbsapp, shakeapp, medianote, qqfriend, readerapp, blogapp, facebookapp, masssendapp, meishiapp, feedsapp, voip, blogappweixin, weixin, brandsessionholder, weixinreminder, officialaccounts, notification_messages, wxitil, userexperience_alarm, notification_messages####消息类型一般格式：	{	    "FromUserName": "",	    "ToUserName": "",	    "Content": "",	    "StatusNotifyUserName": "",	    "ImgWidth": 0,	    "PlayLength": 0,	    "RecommendInfo": {...},	    "StatusNotifyCode": 4,	    "NewMsgId": "",	    "Status": 3,	    "VoiceLength": 0,	    "ForwardFlag": 0,	    "AppMsgType": 0,	    "Ticket": "",	    "AppInfo": {...},	    "Url": "",	    "ImgStatus": 1,	    "MsgType": 1,	    "ImgHeight": 0,	    "MediaId": "",	    "MsgId": "",	    "FileName": "",	    "HasProductId": 0,	    "FileSize": "",	    "CreateTime": 1454602196,	    "SubMsgType": 0	}####MsgType说明：	1	文本消息	3	图片消息	34	语音消息	37	VERIFYMSG 好友验证消息	40	POSSIBLEFRIEND_MSG	42	共享名片	43	视频通话消息	47	动画表情	48	位置消息	49	分享链接	50	VOIPMSG	51	微信初始化消息	52	VOIPNOTIFY	53	VOIPINVITE	62	小视频	9999	SYSNOTICE	10000	系统消息	10002	撤回消息#####微信初始化消息```MsgType: 51FromUserName: 自己IDToUserName: 自己IDStatusNotifyUserName: 最近联系的联系人IDContent:    <msg>        <op id='4'>            <username>                // 最近联系的联系人                filehelper,xxx@chatroom,wxid_xxx,xxx,...            </username>            <unreadchatlist>                <chat>                    <username>                        // 朋友圈                        MomentsUnreadMsgStatus                    </username>                    <lastreadtime>                        1454502365                    </lastreadtime>                </chat>            </unreadchatlist>            <unreadfunctionlist>                // 未读的功能账号消息，群发助手，漂流瓶等            </unreadfunctionlist>        </op>    </msg>
```#####文本消息```MsgType: 1FromUserName: 发送方IDToUserName: 接收方IDContent: 消息内容图片消息MsgType: 3FromUserName: 发送方IDToUserName: 接收方IDMsgId: 用于获取图片Content:    <msg>        <img length="6503" hdlength="0" />        <commenturl></commenturl>    </msg>
```#####小视频消息```MsgType: 62FromUserName: 发送方IDToUserName: 接收方IDMsgId: 用于获取小视频Content:    <msg>        <img length="6503" hdlength="0" />        <commenturl></commenturl>    </msg>
```#####地理位置消息```MsgType: 1FromUserName: 发送方IDToUserName: 接收方IDContent: http://weixin.qq.com/cgi-bin/redirectforward?args=xxx// 属于文本消息，只不过内容是一个跳转到地图的链接
```#####名片消息```MsgType: 42FromUserName: 发送方IDToUserName: 接收方IDContent:    <?xml version="1.0"?>    <msg bigheadimgurl="" smallheadimgurl="" username="" nickname=""  shortpy="" alias="" imagestatus="3" scene="17" province="" city="" sign="" sex="1" certflag="0" certinfo="" brandIconUrl="" brandHomeUrl="" brandSubscriptConfigUrl="" brandFlags="0" regionCode="" />RecommendInfo:    {        "UserName": "xxx", // ID        "Province": "xxx",         "City": "xxx",         "Scene": 17,         "QQNum": 0,         "Content": "",         "Alias": "xxx", // 微信号        "OpCode": 0,         "Signature": "",         "Ticket": "",         "Sex": 0, // 1:男, 2:女        "NickName": "xxx", // 昵称        "AttrStatus": 4293221,         "VerifyFlag": 0    }
    ```#####语音消息```MsgType: 34FromUserName: 发送方IDToUserName: 接收方IDMsgId: 用于获取语音Content:    <msg>        <voicemsg endflag="1" cancelflag="0" forwardflag="0" voiceformat="4" voicelength="1580" length="2026" bufid="216825389722501519" clientmsgid="49efec63a9774a65a932a4e5fcd4e923filehelper174_1454602489" fromusername="" />    </msg>
```#####动画表情```MsgType: 47FromUserName: 发送方IDToUserName: 接收方IDContent:    <msg>        <emoji fromusername = "" tousername = "" type="2" 
        idbuffer="media:0_0" md5="e68363487d8f0519c4e1047de403b2e7" 
        len = "86235" 
        productid="com.tencent.xin.emoticon.bilibili"
        androidmd5="e68363487d8f0519c4e1047de403b2e7" 
        androidlen="86235" 
        s60v3md5 = "e68363487d8f0519c4e1047de403b2e7" 
        s60v3len="86235" 
        s60v5md5 = "e68363487d8f0519c4e1047de403b2e7" s60v5len="86235" 
        cdnurl = "http://emoji.qpic.cn/wx_emoji/eFygWtxcoMF8M0oCCsksMA0gplXAFQNpiaqsmOicbXl1OC4Tyx18SGsQ/" 
        designerid = "" 
        thumburl = "http://mmbiz.qpic.cn/mmemoticon/dx4Y70y9XctRJf6tKsy7FwWosxd4DAtItSfhKS0Czr56A70p8U5O8g/0" 
        encrypturl = "http://emoji.qpic.cn/wx_emoji/UyYVK8GMlq5VnJ56a4GkKHAiaC266Y0me0KtW6JN2FAZcXiaFKccRevA/" 
        aeskey= "a911cc2ec96ddb781b5ca85d24143642" >
        </emoji>         <gameext type="0" content="0" ></gameext>    </msg>
```#####普通链接或应用分享消息```MsgType: 49AppMsgType: 5FromUserName: 发送方IDToUserName: 接收方IDUrl: 链接地址FileName: 链接标题Content:    <msg>        <appmsg appid=""  sdkver="0">            <title></title>            <des></des>            <type>5</type>            <content></content>            <url></url>            <thumburl></thumburl>            ...        </appmsg>        <appinfo>            <version></version>            <appname></appname>        </appinfo>    </msg>
```#####音乐链接消息```MsgType: 49AppMsgType: 3FromUserName: 发送方IDToUserName: 接收方IDUrl: 链接地址FileName: 音乐名AppInfo: // 分享链接的应用    {        Type: 0,         AppID: wx485a97c844086dc9    }Content:    <msg>        <appmsg appid="wx485a97c844086dc9"  sdkver="0">            <title></title>            <des></des>            <action></action>            <type>3</type>            <showtype>0</showtype>            <mediatagname></mediatagname>            <messageext></messageext>            <messageaction></messageaction>            <content></content>            <contentattr>0</contentattr>            <url></url>            <lowurl></lowurl>            <dataurl>                http://ws.stream.qqmusic.qq.com/C100003i9hMt1bgui0.m4a?vkey=6867EF99F3684&amp;guid=ffffffffc104ea2964a111cf3ff3edaf&amp;fromtag=46            </dataurl>            <lowdataurl>                http://ws.stream.qqmusic.qq.com/C100003i9hMt1bgui0.m4a?vkey=6867EF99F3684&amp;guid=ffffffffc104ea2964a111cf3ff3edaf&amp;fromtag=46            </lowdataurl>            <appattach>                <totallen>0</totallen>                <attachid></attachid>                <emoticonmd5></emoticonmd5>                <fileext></fileext>            </appattach>            <extinfo></extinfo>            <sourceusername></sourceusername>            <sourcedisplayname></sourcedisplayname>            <commenturl></commenturl>            <thumburl>                http://imgcache.qq.com/music/photo/album/63/180_albumpic_143163_0.jpg            </thumburl>            <md5></md5>        </appmsg>        <fromusername></fromusername>        <scene>0</scene>        <appinfo>            <version>29</version>            <appname>摇一摇搜歌</appname>        </appinfo>        <commenturl></commenturl>    </msg>
```#####群消息```MsgType: 1FromUserName: @@xxxToUserName: @xxxContent:    @xxx:<br/>xxx
```#####红包消息	MsgType: 49	AppMsgType: 2001	FromUserName: 发送方ID	ToUserName: 接收方ID	Content: 未知	注：根据网页版的代码可以看到未来可能支持查看红包消息，但目前走的是系统消息，见下。#####系统消息	MsgType: 10000	FromUserName: 发送方ID	ToUserName: 自己ID	Content:    	"你已添加了 xxx ，现在可以开始聊天了。"    	"如果陌生人主动添加你为朋友，请谨慎核实对方身份。"    	"收到红包，请在手机上查看"##微信网页版协议分析（4）-好友操作	###1.加好友和通过好友验证接口（post）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxverifyuser?r=1476779339614&lang=zh_CN&pass_ticket=td%252BZEx1ANBEB8wz%252BxjYhotl3fChIto%252FyC4w%252FNHMopoqOP8Eya9%252FivEs2lsPEDqEj####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：```json
加好友提交的数据
{
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e799452821014375"
	},
	"Opcode": 2,固定
	"VerifyUserListSize": 1,
	"VerifyUserList": [
		{
		"Value": "@2cbb226c459cc5203aa991942f41e19820f5ef3ccceb2dece598412f36406d1f", #群中的好友id
		"VerifyUserTicket": ""
		}
	],
	"VerifyContent": "我是lbbniu.cn",#验证消息
	"SceneListCount": 1,
	"SceneList": [
		33
	],
	"skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c"
}
通过好友提交的数据，下面2中获取相关信息
{
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e606224692711308"
	},
	"Opcode": 3,固定
	"VerifyUserListSize": 1,
	"VerifyUserList": [
	{
		"Value": "@2cbb226c459cc5203aa991942f41e19820f5ef3ccceb2dece598412f36406d1f",对方UserName,验证消息得到
		"VerifyUserTicket": "v2_9d2e1b01ee6de582a237f6369a6746c84d3a072b48bf36f152c5c947505e871189cafa09748e05bab27caf812a8f3a12d51da3dceda3cab5f52a406ec124a4a9@stranger" 验证消息中得到
	}
	],
	"VerifyContent": "",
	"SceneListCount": 1,
	"SceneList": [
		33 固定
	],
	"skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c"
}```####返回数据（post）：```json
{	"BaseResponse": {		"Ret": 0,		"ErrMsg": ""	}}
```
###2.好友验证消息体
```
<msg fromusername="liubingbing889129" encryptusername="v1_18874657c313914eff3c00cfd89275a4fa5c0753c73a63d78b893dd29f814e0e2a34dabe550e3d4199b0c4d518d3019b@stranger" fromnickname="LbbNiu" content="验证消息内容" shortpy="LBBNIU" imagestatus="3" scene="14" country="CN" province="Beijing" city="Chaoyang" sign="" percard="1" sex="1" alias="lbbniu" weibo="http://t.qq.com/lbbniu" weibonickname="微博昵称" albumflag="3" albumstyle="0" albumbgimgid="" snsflag="17" snsbgimgid="http://mmsns.qpic.cn/mmsns/4376ae1e0cf0ccced233def9ad1560d0dec29d64941ab85a901c0129f89f98ce9b8524263f1c393a874098cc9f1e4961624ddae970ce1bc0/0" snsbgobjectid="12152681488304050449" mhash="c07f008cf5e3f34415e4baa355fb4f47" mfullhash="c07f008cf5e3f34415e4baa355fb4f47" bigheadimgurl="http://wx.qlogo.cn/mmhead/ver_1/qFZwXv2etMCTcs3RUKDOlmw9GQyMfAdYcbQyd5cH9SssCdTLFmjNdZyLkZ14DdpAUv9qwibl2icia1M6Mng30Adibw/0" smallheadimgurl="http://wx.qlogo.cn/mmhead/ver_1/qFZwXv2etMCTcs3RUKDOlmw9GQyMfAdYcbQyd5cH9SssCdTLFmjNdZyLkZ14DdpAUv9qwibl2icia1M6Mng30Adibw/132" ticket="v2_9d2e1b01ee6de582a237f6369a6746c84d3a072b48bf36f152c5c947505e871189cafa09748e05bab27caf812a8f3a12d51da3dceda3cab5f52a406ec124a4a9@stranger" opcode="2" googlecontact="" qrticket="" chatroomusername="1656114910@chatroom" sourceusername="" sourcenickname="">
  <brandlist count="0" ver="652639419"></brandlist>
</msg>

"RecommendInfo": {
	"UserName": "@2cbb226c459cc5203aa991942f41e19820f5ef3ccceb2dece598412f36406d1f",
	"NickName": "LbbNiu",
	"QQNum": 0,
	"Province": "åŒ—äº¬",
	"City": "æœé˜³",
	"Content": "æˆ‘æ˜¯ç¾¤èŠ\"PHPå’Œæµ‹è¯•\"çš„LbbNiu",
	"Signature": "",
	"Alias": "lbbniu",
	"Scene": 14,
	"VerifyFlag": 0,
	"AttrStatus": 4305791,
	"Sex": 1,
	"Ticket": "v2_9d2e1b01ee6de582a237f6369a6746c84d3a072b48bf36f152c5c947505e871189cafa09748e05bab27caf812a8f3a12d51da3dceda3cab5f52a406ec124a4a9@stranger",
	"OpCode": 2
},
```###3.创建群聊（post）：https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxcreatechatroom?r=1476618448303&lang=zh_CN####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：
```{
	"MemberCount": 3,
	"MemberList": [
		{
		"UserName": "@fe20084fb8e3b488f49d3df2bf3ec2837daeb468abfe62a48ef39a6e407cab0e"
		},
		....
	],
	"Topic": "",
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e280173725621646"
	}
}
UserName 初始化接口中获取
```####返回数据（json）：```
{
	"BaseResponse": {
	"Ret": 0,
	"ErrMsg": "Everything is OK"
	},
	"Topic": "",
	"PYInitial": "",
	"QuanPin": "",
	"MemberCount": 3,
	"MemberList": [
		{
		"Uin": 0,
		"UserName": "@fe20084fb8e3b488f49d3df2bf3ec2837daeb468abfe62a48ef39a6e407cab0e",
		"NickName": "111111",
		"AttrStatus": 0,
		"PYInitial": "",
		"PYQuanPin": "",
		"RemarkPYInitial": "",
		"RemarkPYQuanPin": "",
		"MemberStatus": 0,
		"DisplayName": "",
		"KeyWord": ""
		},
		...
	],
	"ChatRoomName": "@@45340ba1e520285af196cef99d2f41a2bdb5cc568569efa178354f08eb863d00",
	"BlackList": ""
}```
###4.群中踢出好友接口
https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxupdatechatroom?fun=delmember
####请求头：Content-Type:application/json;charset=UTF-8####提交数据（json）：
```json
{
	"DelMemberList": "@2cbb226c459cc5203aa991942f41e19820f5ef3ccceb2dece598412f36406d1f",
	"ChatRoomName": "@@45340ba1e520285af196cef99d2f41a2bdb5cc568569efa178354f08eb863d00",
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e934789096108643"
	}
}
DelMemberList 好友id
ChatRoomName  群组id
```
####返回数据（json）：
```
{
	"BaseResponse": {
		"Ret": 0,
		"ErrMsg": ""
	}
	,
	"MemberCount": 0,
	"MemberList": []
}
```
###5.邀请好友加入群
https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxupdatechatroom?fun=addmember
####请求头
Content-Type:application/json;charset=UTF-8
####提交数据（json）：
```json
{
	"AddMemberList": "@2cbb226c459cc5203aa991942f41e19820f5ef3ccceb2dece598412f36406d1f",
	"ChatRoomName": "@@45340ba1e520285af196cef99d2f41a2bdb5cc568569efa178354f08eb863d00",
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e681854808526145"
	}
}
AddMemberList 好友id列表
ChatRoomName 群组id
```
####返回数据（json）：
```json
{
	"BaseResponse": {
		"Ret": 0,
		"ErrMsg": ""
	},
	"MemberCount": 1,
	"MemberList": [
		{
			"Uin": 0,
			"UserName": "@2cbb226c459cc5203aa991942f41e19820f5ef3ccceb2dece598412f36406d1f",
			"NickName": "LbbNiu",
			"AttrStatus": 0,
			"PYInitial": "",
			"PYQuanPin": "",
			"RemarkPYInitial": "",
			"RemarkPYQuanPin": "",
			"MemberStatus": 0,
			"DisplayName": "",
			"KeyWord": ""
		}
		......
	]
}
```
###6.修改群名称
https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxupdatechatroom?fun=modtopic
####请求头
Content-Type:application/json;charset=UTF-8
####提交数据（json）：
```
{
	"NewTopic": "lbbniu.com",
	"ChatRoomName": "@@45340ba1e520285af196cef99d2f41a2bdb5cc568569efa178354f08eb863d00",
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e326783138505913"
	}
}
NewTopic 新群名称
ChatRoomName 群组id
```
####返回数据（json）：
```
{
	"BaseResponse": {
		"Ret": 0,
		"ErrMsg": ""
	}
	,
	"MemberCount": 0,
	"MemberList": []
}
```
###6.置顶聊天会话
https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxoplog
####请求头
Content-Type:application/json;charset=UTF-8
####提交数据（json）：
```json
{
	"UserName": "@@45340ba1e520285af196cef99d2f41a2bdb5cc568569efa178354f08eb863d00",
	"CmdId": 3,
	"OP": 1,
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e736741369963778"
	}
}
```
####返回数据（json）：
```json
{
	"BaseResponse": {
		"Ret": 0,
		"ErrMsg": ""
	}
}
```
###7.取消置顶聊天会话
https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxoplog
####请求头
Content-Type:application/json;charset=UTF-8
####提交数据（json）：
```json
{
	"UserName": "@@45340ba1e520285af196cef99d2f41a2bdb5cc568569efa178354f08eb863d00",
	"CmdId": 3,
	"OP": 0,
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e736741369963778"
	}
}
```
####返回数据（json）：
```json
{
	"BaseResponse": {
		"Ret": 0,
		"ErrMsg": ""
	}
}
```
###8.给好友添加备注
https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxoplog
####请求头
Content-Type:application/json;charset=UTF-8
####提交数据（json）：
```json
{
	"UserName": "@2cbb226c459cc5203aa991942f41e19820f5ef3ccceb2dece598412f36406d1f",
	"CmdId": 2,
	"RemarkName": "lbbniu.com",
	"BaseRequest": {
		"Uin": 1443149207,
		"Sid": "HqU75NIXRJ6qqu8t",
		"Skey": "@crypt_d9da2d81_f3a62e80c16e61ad660dffd14e0ef72c",
		"DeviceID": "e573447900268361"
	}
}
```
####返回数据（json）：
```json
{
	"BaseResponse": {
		"Ret": 0,
		"ErrMsg": ""
	}
}
```












