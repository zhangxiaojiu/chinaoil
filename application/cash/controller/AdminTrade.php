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
use think\Validate;

/**
 * 系统用户管理控制器
 * Class User
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 18:12
 */
class AdminTrade extends BasicAdmin
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'CashTrade';

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
        $this->title = '交易列表';
        list($get, $db) = [$this->request->get(), Db::name($this->table)->order('status asc,id')];
        foreach (['card_number'] as $key) {
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

    public function import()
    {
	$file = request()->file('tradefile');
	if($file){
	    $info = $file->validate(['size'=>1567890,'ext'=>'csv,xls,xlsx'])->move('./static/upload');
	    // 输出 jpg
	    //echo $info->getExtension();
	    // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
	    //echo $info->getSaveName();
	    // 输出 42a79759f284b767dfcb2a0197904287.jpg
	    //echo $info->getFilename(); 
	    require_once('./vendor/phpoffice/phpexcel/Classes/PHPExcel.php');
	    $type = $info->getExtension();
	    $uploadfile = './static/upload/'.$info->getSaveName();
	    if($type=='xlsx'){
		$PHPReader = new \PHPExcel_Reader_Excel2007();
	    }else if($type == 'xls'){
		$PHPReader = new \PHPExcel_Reader_Excel5();
	    }else if( $type=='csv' ){
		$PHPReader = \PHPExcel_IOFactory::createReader('CSV'); 
	    }else{
		die('Not supported file types!');
	    }

	    $PHPExcel = $PHPReader->load($uploadfile); // 文档名称
	    $objWorksheet = $PHPExcel->getSheet(0);
	    $highestRow = $objWorksheet->getHighestRow(); // 取得总行数
	    $highestColumn = $objWorksheet->getHighestColumn(); // 取得总列数
	    //echo $highestRow.$highestColumn;
	    // 一次读取一列
	    $res = array();
	    $agent = [0=>'trade_id',1=>'card_number',5=>'cash',9=>'trade_time',10=>'trade_at',12=>'oil_type',13=>'capacity'];
	    for ($row = 2; $row <= $highestRow; $row++) {
		$f = 0;
		for ($column = 0; $column <=14; $column++) {
		    $val = $objWorksheet->getCellByColumnAndRow($column, $row)->getValue();
		    if($val == ''){
			$f++;
		    }
		    if(in_array($column,[0,1,5,9,10,12,13])){
			$res[$row-2][$agent[$column]] = htmlspecialchars($val);
		    }
		}
		//空数据
		if($f >2){
		    unset($res[$row-2]);
		}
		//重复流水号
		$tradeId = $res[$row-2]['trade_id'];
		if(Db::name('CashTrade')->where(['trade_id'=>$tradeId])->find()){
		    unset($res[$row-2]);
		}
	    }
	    if(!empty($res)){
		if(Db::name($this->table)->insertAll($res)){
		    $this->success('导入成功');
		}else{
		    $this->error('导入失败！');
		}
	    }else{
		$this->error('禁止重复导入！');
	    }
	}else{
	    return $this->_form($this->table, 'import');
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
     * 表单数据默认处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _form_filter(&$data)
    {
	if ($this->request->isPost()) {
            if (isset($data['authorize']) && is_array($data['authorize'])) {
                $data['authorize'] = join(',', $data['authorize']);
            } else {
                $data['authorize'] = '';
            }
	    if (isset($data['upload'])) {
		$file = request()->file('tradefile');
	    }
	} else {
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

    //结算返现
    public function do_return(){
	$undo = 0;
	$list = Db::name($this->table)->where(['status'=>0])->select();
	foreach($list as $v){
	    $res = cash_do_return($v);
	    $undo += $res;
	}
	$this->success('成功,其中'.$undo.'未结算');
    }

}
