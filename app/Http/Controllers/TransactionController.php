<?php

namespace App\Http\Controllers;

use App\Transaction;
use App\Customer;
use App\Item;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TransactionController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin')->except(['index','show']);
    }

    public function index()
    {
        if (admin()) {
            $transactions = Transaction::paginate(20);
        }else {
            $current_customer_id = current_customer()->id ?? 0;
            $transactions = Transaction::where('customer_id', $current_customer_id)->paginate(20);
        }
        return view('transactions.index', compact('transactions'));
    }

    public function create()
    {
        $transaction = new Transaction;
        $customers = Customer::all();
        $step = request('cid') ? 2 : 1;
        $customer = Customer::find(request('cid'));
        if ($step == 2 && !$customer) {
            return redirect('transactions/create');
        }
        return view('transactions.create', compact('transaction', 'customers', 'customer', 'step'));
    }

    public function store(Request $request)
    {

        if ($request->step == 1) {

            if ($request->customer_id || $request->customer_code) {

                $customer = $request->customer_id ? Customer::find($request->customer_id) : Customer::where('code', $request->customer_code)->first();
                if ($customer) {
                    return redirect("transactions/create?cid=$customer->id");
                }else {
                    return back()->withErrors(["مشتری با این مشخصات یافت نشد"])->withInput();
                }

            }else {
                return back()->withErrors(["لفا یا مشتری مورد نظر را انتخاب کنید یا کد مشتری را تایپ کنید."])->withInput();
            }

        }elseif ($request->step == 2) {
            $inputs = $request->all();
            $customer = Customer::find($request->cid);
            $transaction = Transaction::make($inputs);
            $result = [];
            foreach ($inputs as $key => $array) {
                if(is_array($array) && count($array)){
                    foreach ($array as $i => $value) {
                        $result[$i][$key] = $value;
                        $result[$i]['created_at'] = Carbon::now();
                        $result[$i]['updated_at'] = Carbon::now();
                        $result[$i]['transaction_id'] = $transaction->id;
                    }
                }
            }
            Item::insert($result);
            if ($request->transaction_gift_amount > 0) {
                $customer->increase_credit($request->transaction_gift_amount);
            }
            if ($request->transaction_gift_spent > 0) {
                $customer->reduce_credit($request->transaction_gift_spent);
            }
            return redirect('transactions')->withMessage("تراکنش با موفقیت در سیستم ثبت شد.");
        }else {
            return back();
        }

    }

    public function show(Transaction $transaction)
    {
        customer_check($transaction);
        return view('transactions.show', compact('transaction'));
    }

    public function destroy(Transaction $transaction)
    {
        $customer = Customer::find($transaction->customer_id);
        if ($customer) {
            $customer->increase_credit($transaction->gift_spent);
            $customer->reduce_credit($transaction->gift_amount);
        }
        \DB::table('items')->where('transaction_id', $transaction->id)->delete();
        $transaction->delete();
        return back()->withMessage('تراکنش مورد نظر با موفقیت مرجوع شد.');
    }
}
