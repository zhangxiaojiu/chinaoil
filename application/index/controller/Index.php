<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\index\controller;

use think\Controller;
use think\Db;

/**
 * 应用入口控制器
 * @author Anyon <zoujingli@qq.com>
 */
class Index extends Controller
{
    public function initialize()
    {
	//$this->checkLogin();
    }

    public function checkLogin(){
	$userId = get_current_user_id();
        if (empty($userId)) {
            if ($this->request->isAjax()) {
                $this->error("您尚未登录", url("wechat/auth"));
            } else {
                $this->redirect(url("wechat/auth"));
            }
        } 
    }

    public function index()
    {
	$list = Db::name('StoreGoodsStation')->where(['is_deleted' => '0'])->limit(5)->select();
	$this->assign('list',$list);
	$this->assign('title', '首页');
	return $this->fetch();
    }
    public function map()
    {
	$list = Db::name('StoreGoodsStation')->where(['is_deleted' => '0'])->select();
	$this->assign('list',$list);
	$this->assign('title', '加油');
	return $this->fetch();
    }
    public function oil()
    {
	$id = input('param.0');
	$info = Db::name('StoreGoodsStation')->where(['id' => $id])->find();
	$this->assign('info',$info);
	$this->assign('title', '加油');
	return $this->fetch();
    }
    public function user()
    {
	$this->checkLogin();
	$uid = get_current_user_id();
	$info = Db::name('WechatFans')->find($uid);
	$this->assign('info',$info);
	$this->assign('title', '我的');
	return $this->fetch();
    }
}
