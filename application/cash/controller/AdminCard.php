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

namespace app\cash\controller;

use controller\BasicAdmin;
use service\DataService;
use think\Db;

/**
 * 系统用户管理控制器
 * Class User
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 18:12
 */
class AdminCard extends BasicAdmin
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'CashCard';

    /**
     * 用户列表
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function index()
    {
        $this->title = '油卡列表';
        list($get, $db) = [$this->request->get(), Db::name($this->table)];
        foreach (['number'] as $key) {
            (isset($get[$key]) && $get[$key] !== '') && $db->whereLike($key, "%{$get[$key]}%");
        }
        if (isset($get['date']) && $get['date'] !== '') {
            list($start, $end) = explode(' - ', $get['date']);
            $db->whereBetween('login_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
	}
	$ulist = cash_get_active_users();
	$this->assign('ulist',$ulist);
        return parent::_list($db->where([]));
    }

    /**
     * 授权管理
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function auth()
    {
        return $this->_form($this->table, 'auth');
    }

    /**
     * 用户添加
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function add()
    {
        return $this->_form($this->table, 'form');
    }

    public function addmore(){
	if($this->request->isPost()){
	    $begin = input('param.begin','');
	    $number = input('param.num','');
	    $cash = input('param.cash','');
	    if(!is_numeric($begin)){
		$this->error('起始卡号不正确');
	    }
	    for($i=0;$i<$number;$i++){
		$arr = [
		    'number' => $begin+$i,
		    'cash' => $cash
		];
		if(!Db::name('CashCard')->where(['number'=>$arr['number']])->find()){
		    $data[] = $arr;
		}
	    }
	    Db::name('CashCard')->insertAll($data);
	    $this->success('添加成功');
	}
	else{
	    return $this->_form($this->table,'addmore');
	}
    }

    /**
     * 用户编辑
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function edit()
    {
        return $this->_form($this->table, 'form');
    }

    /**
     * 用户密码修改
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function pass()
    {
        if ($this->request->isGet()) {
            $this->assign('verify', false);
            return $this->_form($this->table, 'pass');
        }
        $post = $this->request->post();
        if ($post['password'] !== $post['repassword']) {
            $this->error('两次输入的密码不一致！');
        }
        $data = ['id' => $post['id'], 'password' => md5($post['password'])];
        if (DataService::save($this->table, $data, 'id')) {
            $this->success('密码修改成功，下次请使用新密码登录！', '');
        }
        $this->error('密码修改失败，请稍候再试！');
    }

    /**
     * 表单数据默认处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _form_filter(&$data)
    {
	if ($this->request->isPost()) {
	    if(isset($data['number'])){
		$data['number'] = trimall($data['number']);
		if (Db::name($this->table)->where(['number' => $data['number']])->count() > 0) {
		    $this->error('油卡号已经存在，请使用其它账号！');
		}
	    }
            if (isset($data['authorize']) && is_array($data['authorize'])) {
                $data['authorize'] = join(',', $data['authorize']);
            } else {
                $data['authorize'] = '';
            }
	}
	else {
	    $data['authorize'] = explode(',', isset($data['authorize']) ? $data['authorize'] : '');
	    $this->assign('authorizes', Db::name('SystemAuth')->where(['status' => '1'])->select());
	}
    }

    /**
     * 删除用户
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del()
    {
        if (in_array('10000', explode(',', $this->request->post('id')))) {
            $this->error('系统超级账号禁止删除！');
        }
        if (DataService::update($this->table)) {
            $this->success("用户删除成功！", '');
        }
        $this->error("用户删除失败，请稍候再试！");
    }

    public function assig(){
	if ($this->request->isGet()) {
	    $ulist = cash_get_active_users();
	    $this->assign('ulist', $ulist);
            return $this->_form($this->table, 'assig');
        }
	$post = $this->request->post();

        $data = ['id' => $post['id'], 'user_id' => $post['user_id']];
        if (DataService::save($this->table, $data, 'id')) {
            $this->success('分配成功！', '');
        }
        $this->error('失败，请稍候再试！');
    }

    /**
     * 用户禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (in_array('10000', explode(',', $this->request->post('id')))) {
            $this->error('系统超级账号禁止操作！');
        }
        if (DataService::update($this->table)) {
            $this->success("用户禁用成功！", '');
        }
        $this->error("用户禁用失败，请稍候再试！");
    }

    /**
     * 用户禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("用户启用成功！", '');
        }
        $this->error("用户启用失败，请稍候再试！");
    }

}
