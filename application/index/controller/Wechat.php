<?php

namespace app\index\controller;

use app\index\service\WxService;
use think\Db;
use think\Controller;

class Wechat extends Controller
{
    public function index()
    {
        if (!isset($_GET['echostr'])) {
            self::responseMsg();
        } else {
            $echoStr = $_GET['echostr'];
            $signature = $_GET['signature'];
            $timestamp = $_GET['timestamp'];
            $nonce = $_GET['nonce'];
            //配置参数
            $config = WxService::getConfig();
            $token = $config['token'];
            //获取签名
            $sign = WxService::getSign($token, $timestamp, $nonce);
            //签名正确 返回
            if ($sign == $signature && $echoStr) {
                echo $echoStr;
                exit;
            }
        }
    }
    private static function responseMsg(){
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $RX_TYPE = trim($postObj->MsgType);
        $clickEvent = cmf_get_option('click_event');
        if($RX_TYPE == "text"){
            $funcFlag = 0;
            //$contentStr = "你发送的内容为：".$postObj->Content;
            $contentStr = $clickEvent['msg_reply'];
            $resultStr = self::transmitText($postObj, $contentStr, $funcFlag);
            echo $resultStr;
        }
        if($RX_TYPE == "event") {
            $event = (string)$postObj->Event;
            //扫描带参数的二维码
            if ($event == "subscribe") {
                $fromUsername = (string)$postObj->FromUserName;
                $EventKey = trim((string)$postObj->EventKey);
                $keyArray = explode("_", $EventKey);
                if (count($keyArray) == 1) {//已关注者扫描
                    $pid = $EventKey;
                } else {//未关注者关注后推送事件
                    $pid = $keyArray[1];
                }
                $info = Db::name('third_party_user')->where(['openid' => $fromUsername])->find();
                if ($info) {
                    if (empty($info['user_id'])) {
                        $uData['pid'] = $pid;
                        $uData['create_time'] = time();
                        $uid = Db::name('user')->insertGetId($uData);
                        $data['user_id'] = $uid;
                        Db::name("third_party_user")->where(['openid' => $fromUsername])->update($data);
                    }
                } else {
                    $uData['pid'] = $pid;
                    $uData['create_time'] = time();
                    $uid = Db::name('user')->insertGetId($uData);
                    $data['openid'] = $fromUsername;
                    $data['user_id'] = $uid;
                    Db::name("third_party_user")->insert($data);
		}
		//score
		$scoreData = [
		    'user_id' => $pid,
		    'score' => 2,
		    'action' => 'invite',
		    'detail' => '邀请好友奖励积分2'
		];
		ScoreLogService::addScoreLog($scoreData);

                $wxInfo = Db::name('third_party_user')->where(['user_id' => $pid])->find();
                $title = "您好，您的下级绑定成功";
                $url = '';
                $remark = "您的下级绑定成功，下级完成任务您将获得奖励";
                $ret = WxService::sendWxtmp($wxInfo['openid'], $title, $url, '待完善', $remark, 'bind');
                $title = "绑定成功";
                $url = 'http://www.qianduoya.com/user/profile/center';
                $remark = "绑定成功，欢迎加入钱多呀,点击完善资料";
                $ret = WxService::sendWxtmp($fromUsername, $title, $url, '待完善', $remark, 'bind');
                $contentStr = $clickEvent['follow_reply'];
                $resultStr = self::transmitText($postObj, $contentStr);
                echo $resultStr;
            }
            //click事件
            if ($event == "CLICK") {
                switch ($postObj->EventKey)
                {
                    case "company":
                        $contentStr[] = array("Title" =>"公司简介",
                            "Description" =>"方倍工作室提供移动互联网相关的产品及服务",
                            "PicUrl" =>"http://discuz.comli.com/weixin/weather/icon/cartoon.jpg",
                            "Url" =>"weixin://addfriend/pondbaystudio");
                        break;
                    default:
                        $contentStr = "无点击事件，默认回复";
                        foreach ($clickEvent as $key => $val){
                            if($postObj->EventKey == $key){
                                $contentStr = $val;
                            }
                        }
                        break;
                }
                if (is_array($contentStr)){
                    $resultStr = self::transmitNews($postObj, $contentStr);
                }else{
                    $resultStr = self::transmitText($postObj, $contentStr);
                }
                echo $resultStr;
            }
        }
    }

    //微信用户签名
    public function auth()
    {
        $code = isset($_GET['code'])?$_GET['code']:false;
        if($code) {
            $state = isset($_GET['state'])?$_GET['state']:0;//传递参数用
            $ret = WxService::getAccessToken($code);
	    pr($ret,1);
            if (isset($ret['errcode'])) {
                p($ret, 0);
            } else {
                $data['access_token'] = $ret['access_token'];
                $data['expire_time'] = time() + $ret['expires_in'];

                $userRet = WxService::getUserInfo($data['access_token'],$ret['openid']);
                $data['openid'] = $userRet['openid'];
                $data['nickname'] = $userRet['nickname'];
                $data['union_id'] = isset($userRet['unionid'])?$userRet['unionid']:'';
                //本地用户数据
                $userData['sex'] = $userRet['sex'];
                $userData['avatar'] = $userRet['headimgurl'];
                $userData['user_nickname'] = $userRet['nickname'];
                $userData['last_login_time'] = time();

                $info = Db::name('third_party_user')->where(['openid' => $data['openid']])->find();

                if(empty(session('user')['id'])){
                    if(empty($info['user_id'])) {
                        $userData['pid'] = $state;
                        $userData['user_type'] = 2;
                        $userData['user_status'] = 1;
                        $userData['create_time'] = time();
                        $uid = Db::name('user')->insertGetId($userData);
                        $data['user_id'] = $uid;

                        $uInfo = Db::name('user')->find($uid);
                    }else{
                        $userData['id'] = $info['user_id'];
                        Db::name('user')->update($userData);
                        $uInfo = Db::name('user')->find($info['user_id']);
                    }
                    session('user',$uInfo);
                }else{
                    $data['user_id'] = session('user')['id'];
                    $userData['id'] = session('user')['id'];
                    Db::name('user')->update($userData);
                }


                if (empty($info)) {
                    Db::name('third_party_user')->insert($data);
                } else {
                    Db::name('third_party_user')->where(['openid' => $data['openid']])->update($data);
                }
                $this->redirect('/');
            }
        }else{
            $redirect_uri = request()->url(true);
            $pid = isset($_GET['pid'])?$_GET['pid']:0;
            $url = WxService::getAuthUrl($redirect_uri,$pid);
            $this->redirect($url);
        }
    }

    private static function transmitText($object, $content, $funcFlag = 0)
    {
        $textTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[%s]]></Content>
<FuncFlag>%d</FuncFlag>
</xml>";
        $resultStr = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content, $funcFlag);
        return $resultStr;
    }
    private static function transmitNews($object, $arr_item, $funcFlag = 0)
    {
        //首条标题28字，其他标题39字
        if(!is_array($arr_item))
            return;

        $itemTpl = "    <item>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
        <PicUrl><![CDATA[%s]]></PicUrl>
        <Url><![CDATA[%s]]></Url>
    </item>
";
        $item_str = "";
        foreach ($arr_item as $item)
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);

        $newsTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[news]]></MsgType>
<Content><![CDATA[]]></Content>
<ArticleCount>%s</ArticleCount>
<Articles>
$item_str</Articles>
<FuncFlag>%s</FuncFlag>
</xml>";

        $resultStr = sprintf($newsTpl, $object->FromUserName, $object->ToUserName, time(), count($arr_item), $funcFlag);
        return $resultStr;
    }
}
