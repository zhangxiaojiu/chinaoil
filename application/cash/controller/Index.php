<?php


namespace app\cash\controller;

use think\Controller;
use think\Db;

class Index extends Controller
{
    public function initialize()
    {
	$this->checkLogin();
    }

    public function checkLogin(){
	$userId = get_cash_user_id();
        if (empty($userId)) {
            if ($this->request->isAjax()) {
                $this->error("您尚未登录", url("login/index"));
            } else {
                $this->redirect(url("login/index"));
            }
        } 
	$userInfo = get_cash_user_info($userId);
	if($userInfo['status'] == 0){
	    $this->redirect('login/verify');
	}
    }

    public function index()
    {
	$info = get_cash_user_info(session('cashuser.id'));
	$this->assign('info',$info);

	$list = Db::name('CashLog')->where(['user_phone'=>session('cashuser.username')])->order('id desc')->paginate(5);
	$arr_card = cash_get_user_card($info['id']);
	$total['day_trade'] = Db::name('CashTrade')->where('card_number','in',$arr_card)->where('trade_time','like',date('Y-m-d',time()).'%')->sum('cash');
	$total['month'] = Db::name('CashLog')->where([
	    'user_phone'=>session('cashuser.username')])->where(
	    'time','like',date('Y-m',time()).'%'
	)->sum('cash');
	$total['day'] = Db::name('CashLog')->where([
	    'user_phone'=>session('cashuser.username')])->where(
	    'time','like',date('Y-m-d',time()-24*3600).'%'
	)->sum('cash');

	$this->assign('total',$total);
	$this->assign('list',$list);
	$this->assign('title','钱包');
	return $this->fetch();
    }
    public function user(){
	$info = get_cash_user_info(session('cashuser.id'));
	$clist = cash_get_child_list($info['id']);
	$this->assign('info',$info);
	$this->assign('clist',$clist);
	$this->assign('title','我的');
	return $this->fetch();
    }
    public function oil(){
	$this->assign('title','加油记录');
	$user_id= session('cashuser.id');
	$arr_card = cash_get_user_card($user_id);
	$list = Db::name('cash_trade')->where('card_number','in',$arr_card)->where('trade_time','like',date('Y-m').'%')->order('trade_time desc')->paginate(10);
	$page = $list->render();
	$this->assign('list',$list);
	$this->assign('page',$page);
	return $this->fetch();
    }
    public function card(){
	$list = Db::name('CashCard')->where(['user_id'=>session('cashuser.id')])->select();
	$this->assign('list',$list);
	$this->assign('title','油卡');
	return $this->fetch();
    }
    public function withdraw(){
	$info = get_cash_user_info(session('cashuser.id'));
	if(request()->isPost()){
	    $cash = input('param.cash','');
	    if($cash <= 0 || $cash > $info['cash']){
		$this->error('提现金额不正确');
	    }
	    $data = [
		'user_phone' => $phone = session('cashuser.username'),
		'cash' => $cash,
		'remark' => input('param.remark')
	    ];
	    Db::name('CashWithdraw')->insert($data);
	    Db::name('CashUser')->where(['username'=>$phone])->setDec('cash',$cash);
	    $this->success('申请提现成功，请等待审核');
	}else{
	    $this->assign('info',$info);
	    $list = Db::name('CashWithdraw')->where(['user_phone'=>session('cashuser.username')])->paginate(5);
	    $this->assign('list',$list);
	    $this->assign('title','提现');
	    return $this->fetch();
	}
    }
}
