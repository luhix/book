<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use EasyWeChat;

class OrdersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    //买家列表
	public function index(Request $request)
	{
	    $user = Auth::user();
	    $status = $request->status;
		$orders = $user->orders()
            ->when($status, function ($query) use ($status){
                return $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->with('seller')
            ->get();
		$statuses = config('custom.order.status');
		return view('orders.index', compact('orders', 'statuses'));
	}

	//卖家列表
	public function sellerIndex(Request $request){
        $user = Auth::user();
        $status = $request->status;
        $orders = $user->sellOrders()
            ->when($status, function ($query) use ($status){
                return $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->with('seller')
            ->get();
        $statuses = config('custom.order.status');
        return view('orders.seller_index', compact('orders', 'statuses'));
    }

    //买家详情
    public function show(Order $order)
    {
        $this->authorize('show', $order);
        $school = $order->school;
        $seller = $order->seller;
        $logs = $order->orderLogs;
        return view('orders.show', compact('order', 'school', 'seller', 'logs'));
    }

    //卖家详情
    public function sellerShow(Order $order){
        $this->authorize('seller_show', $order);
        $school = $order->school;
        $buyer = $order->user;
        $logs = $order->orderLogs;
        return view('orders.seller_show', compact('order', 'school', 'buyer', 'logs'));
    }

    //确认下单
	public function create(Order $order, Book $book)
	{
        $this->authorize('buy', $book);
        $school = $book->school;
        $seller = $book->user;
		return view('orders.create', compact('order', 'school', 'seller', 'book'));
	}

	//订单创建
	public function store(Request $request, Order $order)
	{
	    $this->validate($request, [
	        'book_id'=>'required|numeric',
            'message'=>'nullable|max:255',
        ]);
		$book_id = $request->book_id;
		$book = Book::findOrFail($book_id);
		$this->authorize('buy', $book);
        $order = $order->createOrder(Auth::user(), $book, $request->message);
        return redirect()->to($order->payLink());
	}

	//订单发起支付
	public function pay(Order $order){
        $this->authorize('pay', $order);
        $payment = EasyWeChat::payment();
        $orderData = [
            'body' => '腾讯充值中心-QQ会员充值',
            'out_trade_no' => '20150806125346',
            'total_fee' => 88,
            'notify_url' => 'https://pay.weixin.qq.com/wxpay/pay.action', // 支付结果通知网址
            'trade_type' => 'JSAPI',
            'openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o',
        ];
        dd($orderData);
        $result = $payment->order->unify($orderData);
        return view('orders.pay', compact('order'));
    }

    //模拟支付
    public function fakePay(Request $request){
        if(config('order_fake_pay') != 'on')return abort(404);
        $order = Order::where('sn' ,$request->sn)->firstOrFail();
        $this->authorize('pay', $order);
        $order->payed($order->createSn(), Carbon::now()->toDateTimeString(), $order->price);
        return redirect()->to($order->userLink())->with('message', '支付成功');
    }

    //卖家确认
    public function confirm(Order $order){
        $this->authorize('confirm', $order);
        $order->confirm();
        return redirect()->to($order->sellerLink())->with('message', '订单已确认！');
    }

    //卖家送达
    public function send(Order $order){
        $this->authorize('send', $order);
        $order->send();
        return redirect()->to($order->sellerLink())->with('message', '书本已确认送达！');
    }

    //买家收货+转账
    public function get(Order $order){
        $this->authorize('get', $order);
        $order->finish();

        $order->commission();

        return redirect()->to($order->userLink())->with('message', '确认收货成功！');
    }

    //买家取消
    public function cancel(Order $order){
        $this->authorize('cancel', $order);
        $order->cancel($order::OPERATOR_USER);
        return redirect()->to($order->userLink())->with('message', '订单已取消！');
    }




}