<?php

use think\Db;

function get_cash_user_id(){
    $sessionUserId = session('cashuser.id');
    if (empty($sessionUserId)) {
        return 0;
    }
    return $sessionUserId;
}
function get_cash_user_info($id){
    $info = Db::name('CashUser')->find($id);
    return $info;
}
function cash_check_username_exist($name,$rid=0){
    if($info = Db::name('CashUser')->where(['username'=>$name])->find()){
	if($rid > 0){
	    return $info['id'];
	}
	return true;
    }
    return false;
}
function cash_check_login($name,$pwd){
    $ret = Db::name('CashUser')->where(['username'=>$name,'password'=>md5($pwd)])->find();
    if($ret){
	session('cashuser',$ret);
    }
    return $ret;
}
function cash_get_active_users(){
    $ret[0] = '未分配';
    $list = Db::name('CashUser')->where(['status' => 1])->select();
    foreach($list as $v){
	$ret[$v['id']] = $v['username'];
    }
    return $ret;
}
function cash_get_child_list($id){
    $list = Db::name('CashUser')->where(['status' => 1,'pid' => $id])->select();
    return $list;
}
function cash_do_return($trade){
    $phone = $trade['user_phone'];
    $info = Db::name('CashUser')->where(['username'=>$phone])->find();
    if($info){
	$mymoney = round($trade['cash']*0.05,2);
	$data = [
	    'user_phone' => $phone,
	    'type'  => 'return',
	    'cash' => $mymoney,
	    'remark' => "流水号:".$trade['trade_id']."返现",
	    'time' => $trade['trade_time'],
	    'status' => 1
	];
	Db::name('CashLog')->insert($data);
	Db::name('CashUser')->where(['username'=>$phone])->setInc('cash',$mymoney);

	$pinfo = Db::name('CashUser')->find($info['pid']);
	if($pinfo){
	    $cmoney = round($trade['cash']*0.01,2);
		$pdata = [
		    'user_phone' => $pinfo['username'],
		    'type'  => 'preturn',
		    'cash' => $cmoney,
		    'remark' => "好友:".$info['username']."流水号:".$trade['trade_id']."返现",
		    'time' => $trade['trade_time'],
		    'status' => 1
		];
	    Db::name('CashLog')->insert($pdata);
	    Db::name('CashUser')->where(['username'=>$pinfo['username']])->setInc('cash',$cmoney);
	}

	Db::name('CashTrade')->where(['id'=>$trade['id']])->setField('status',1);
	return 0;
    }
    return 1;
}
