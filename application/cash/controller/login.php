<?php


namespace app\cash\controller;

use think\Controller;
use think\Db;

class Login extends Controller
{
    public function index()
    {
	$this->assign('title','登录');
	return $this->fetch();
    }
    public function register()
    {
	$this->assign('title','注册');
	return $this->fetch();
    }
    public function verify(){
	$info = get_cash_user_info(session('cashuser.id'));
	$this->assign('info',$info);
	$this->assign('title','验证');
	return $this->fetch();
    }

    public function logout(){
	session('cashuser',null);
	$this->redirect('index/index');
    }
    public function dologin(){
	$username = input('param.username','');
	$password = input('param.password','');
	if(cash_check_login($username,$password)){
	    $this->redirect('index/index');
	}else{
	    $this->error('账号或密码错误');
	}
    }
    public function doregister(){
	$username = input('param.username','');
	$station = input('param.station','');
	$pname = input('param.pname','');

	if(cash_check_username_exist($username)){
	    $this->error('用户名重复');
	}
	if($pname !== '' && !cash_check_username_exist($pname)){
	    $this->error('推荐人不存在');
	}
	$data = [
	    'username' => $username,
	    'password' => md5(substr($username,-6,6)),
	    'station' => $station,
	    'pid' => cash_check_username_exist($pname,1),
	];
	if(Db::name('CashUser')->insert($data)){
	    $this->success('申请已经提交');
	}
    }
}
