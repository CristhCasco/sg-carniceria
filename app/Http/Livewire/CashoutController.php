<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Customer;


class CashoutController extends Component
{

    public $fromDate, $toDate, $userId, $total, $items, $sales, $details;

   
    public function mount()
    {
        $this->fromDate = null;
        $this->toDate = null;
        $this->userId = 0;
        $this->total = 0;
        $this->items = 0;
        $this->sales = [];
        $this->details = [];
    }



    public function render()
    {
        return view('livewire.cashout.component', [
            'users' => User::orderBy('name', 'asc')->get(),])
            ->extends('layouts.theme.app')
            ->section('content');
    }

    public function Consult()
    {
        $fi = Carbon::parse($this->fromDate)->format('Y-m-d') . ' 00:00:00';
        $ff = Carbon::parse($this->toDate)->format('Y-m-d') . ' 23:59:59';

        $this->sales = Sale::whereBetween('created_at', [$fi, $ff] )            
            ->where('user_id', $this->userId)
            ->get();
            //->where('status', 'PAGADO')

        $this->total = $this->sales ? $this->sales->sum('total') : 0;
        $this->items = $this->sales ? $this->sales->sum('items') : 0;
    }


    public function viewDetails(Sale $sale)
    {
        $fi = Carbon::parse($this->fromDate)->format('Y-m-d') . ' 00:00:00';
        $ff = Carbon::parse($this->toDate)->format('Y-m-d') . ' 23:59:59';

        $this->details = Sale::Join('sale_details as d', 'd.sale_id', 'sales.id')
            ->Join('products as p', 'p.id', 'd.product_id')
            ->Join('customers as c', 'c.id', 'sales.customer_id') // Unir con la tabla customers
            ->select('d.sale_id', 'p.name as product', 'd.quantity', 'd.price', 'c.name as customer') // Obtener el nombre del cliente
            ->whereBetween('sales.created_at', [$fi, $ff])
            ->where('sales.status', 'PAGADO')
            ->where('sales.user_id', $this->userId)
            ->where('sales.id', $sale->id)
            ->get();

        $this->emit('show-modal', 'modal-details');
    }
/*
    public function viewDetails(Sale $sale)
    {
        $fi = Carbon::parse($this->fromDate)->format('Y-m-d') . ' 00:00:00';
        $ff = Carbon::parse($this->toDate)->format('Y-m-d') . ' 23:59:00';


        
        $this->details = Sale::Join('sale_details as d', 'd.sale_id', 'sales.id')
            ->Join('products as p', 'p.id', 'd.product_id')
            ->select('d.sale_id', 'p.name as product', 'd.quantity', 'd.price')
            ->whereBetween('sales.created_at', [$fi, $ff])
            ->where('sales.status', 'PAGADO')
            ->where('sales.user_id', $this->userId)
            ->where('sales.id', $sale->id)
            ->get();

        $this->emit('show-modal', 'modal-details');
    }
*/
    public function Print()
    {

    }
    
}
