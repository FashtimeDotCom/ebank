<?php

namespace App\Jobs;

use App\Exceptions\ApiException;
use App\Models\FundMerchant;
use App\Models\FundNotifyJobs;
use App\Models\FundOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class OrderNotify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order_no = '';
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_no)
    {
        //
		$this->order_no = $order_no;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
		$order = FundOrder::where(['order_no'=>$this->order_no])->first();
		$merchant = FundMerchant::where(['id' => $order->merchant_id])->first(['appid','secret']);
		
		$notify_url = $order->notify_url;
		$param = [
			'appid'		=> $merchant->appid,
			'order_no'	=> $order->order_no,
			'pay_status'=> $order->pay_status
		];
		ksort($param);
		$param2 = $param;
		$param2['secret'] = $merchant->secret;
		$param_sign = strtolower(md5(http_build_query($param2)));
		// 添加签名
		$param['sign'] = $param_sign;
		$result = curl_post($notify_url,$param);
		// 商户输出 success 才表示成功
		if(strcmp('success',strtolower($result)) === 0){
			$order->notify_status = 1;
			$order->notify_time = time2date(time());
			$order->save();
		}else{
			abort_500('未返回 SUCCESS 字符串，标记为失败');
		}
    }
	
	
	public function failed(\Exception $exception){
		bug_email($this->order_no.'订单异步通知到商户失败，请通知商户及时处理程序错误',$exception->__toString());
	}
}
