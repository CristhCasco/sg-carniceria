<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Denomination;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\SaleDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use App\Traits\CartTrait;
use App\Traits\JsonTrait;

use DB;

class PosController extends Component
{
    use CartTrait;
    use JsonTrait;
    
    public $total,$itemsQuantity, $efectivo, $change,$price,$customers =[],$customerId,$customerName,
    $status,$payment_type,$payment_method, $discount, $discount_total;

    
    public function mount()
    {   
        
        $this->efectivo = 0;
        $this->change = 0;
        $this->total = Cart::getTotal();
        $this->itemsQuantity = Cart::getTotalQuantity();
        $this->customers = Customer::orderBy('name', 'asc')->get(); 
        //$this->customerId = $this->customers->where('id', 3)->first()->id;  PARA SELECCIONAR UN CLIENTE POR DEFECTO
        $this->status = 'PAGADO';
        $this->payment_type = 'CONTADO';
        $this->payment_method = 'EFECTIVO';
        $this->discount = 0;
        $this->discount_total = 0;
        
    }
//         $venta = Sale::with('customer')
//         ->where('sales.id', 9)        
//         ->first(); // devuelve una colección [][][][]
//                 // first devuelve un array
// dd($venta->name);



    public function setCustomer($customerId, $customerName)
       {
        $this->customerName = $customerName;
        $this->customerId = $customerId;
        }


    public function render()
    {

        //dd($this->jsonData2(67));
        
        return view('livewire.pos.component', [
            'denominations' => Denomination::get(),
            'cart' => Cart::getContent()->sortBy('name')
        ])
        ->extends('layouts.theme.app')
        ->section('content');
    }


    public function ACash($value)
    {
        $this->efectivo +=  ($value == 0 ? $this->total : $value);
        $this->change = ($this->efectivo - $this->total);
    }


    //Escuchas realizadas desde el front-end
    protected $listeners = [
        'scan-code' => 'scanCode',
        'removeItem' => 'removeItem',
        'clearCart' => 'clearCart',
        'saveSale' => 'saveSale',
        'refresh' => '$refresh',
        //Escucha para aumentar la cantidad de un producto
        'scan-code-byid' => 'ScanCodeById',
        'setCustomer',
        'print-json',
        'stock-minimo' => 'mostrarAdvertenciaStockMinimo'
    ];

    public function ScanCodeById($productId)
    {
        $this->increaseQty($productId);
    }

    public function scanCode($barcode, $cant = 1)
    {
        //dd('a');
        $this->emit('show-scroll');
        $this->ScanearCode($barcode, $cant);
    }

    

    /*
    public function scanCode($barcode, $cant = 1)
    {
        $product = Product::where('barcode', $barcode)->first();

        if($product == null)
        {
            $this->emit('scan-notfound', 'Producto no esta registrado');
        }else{
            
            if($this->InCart($product->id))
            {
                $this->increaseQty($product->id);
                return;
            }

            if($product->stock < $cant)
            {
                $this->emit('no-stock', 'Stock insuficiente');
                return;
            }

            
            Cart::add($product->id, $product->name, $product->price, $cant, $product->image);
           
            $this->itemsQuantity = Cart::getTotalQuantity();
            $this->total = Cart::getTotal();

            $this->emit('scan-ok', 'Producto agregado');
        }
    }
    */


    public function InCart($productId)
    {
        $exist = Cart::get($productId);
        if($exist)
        
            return true;
        else
            return false;
    }



        public function increaseQty($productId, $cant = 1)
    {
        // Asegúrate de que el $productId sea válido
        if (!$productId) {
            $this->emit('no-stock', 'ID de producto no válido');
            return;
        }

        // Asegúrate de que el producto con el $productId exista
        $product = Product::find($productId);
        if (!$product) {
            $this->emit('no-stock', 'Producto no encontrado');
            return;
        }

        $title = '';
        $exist = Cart::get($productId);

        // Validación del stock
        if ($exist) {
            // Si el producto ya está en el carrito, verificamos la cantidad total deseada
            if ($product->stock < ($cant + $exist->quantity)) {
                $this->emit('no-stock', 'Stock insuficiente para "' . $product->name . '"');
                return;
            }
            $title = 'Cantidad actualizada';
        } else {
            // Si el producto no está en el carrito, verificamos si hay suficiente stock para la cantidad solicitada
            if ($product->stock < $cant) {
                $this->emit('no-stock', 'Stock insuficiente para "' . $product->name . '"');
                return;
            }
            $title = 'Producto agregado';
        }

        // Método add verifica si existe el producto en el carrito y actualiza la cantidad, en caso de que no existe lo inserta
        Cart::add($product->id, $product->name, $product->price, $cant, $product->imagen);

        $this->total = Cart::getTotal();
        $this->itemsQuantity = Cart::getTotalQuantity();

        $this->emit('scan-ok', $title);
    }


    

    //quitar un producto del carrito y la vuelve a poner en el stock
    public function updateQtyCart($productId, $cant = 1)
    {
       

        $title='';
        $product = Product::find($productId);
        $exist = Cart::get($productId);
        //dd($product);

        // para que no se pueda agregar mas de lo que hay en stock
        if($cant <= 0)
            $this->removeItem($productId);
        else
            $this->updateQuantity($product, $cant);
            //$this->updateQty($productId, $cant);


        if($exist)
            $title = 'Cantidad actualizada';
        else
            $title = 'Producto agregado';

        if($exist)
        {
            if($product->stock < $cant)
            {
                $this->emit('no-stock', 'Stock insuficiente');
                return;
            }
        }

        $this->removeItem($productId);

        if($cant > 0)
        {
            Cart::add($product->id, $product->name, $product->price, $cant, $product->imagen);


            $this->total = Cart::getTotal();
            $this->itemsQuantity = Cart::getTotalQuantity();
            $this->emit('scan-ok', $title);
        }
    }

    public function removeItem($productId)
    {
        Cart::remove($productId);


        $this->total = Cart::getTotal();
        $this->itemsQuantity = Cart::getTotalQuantity();
        $this->emit('scan-ok', 'Producto eliminado');
    }

    public function decreaseQty($productId)
    {
        $item = Cart::get($productId);
        Cart::remove($productId);

        $img = (count($item->attributes) > 0 ? $item->attributes : Product::find($productId)->imagen);

        $newQty = ($item->quantity) - 1;

        if($newQty > 0)
        
            Cart::add($item->id, $item->name, $item->price, $newQty, $img);
            
            $this->total = Cart::getTotal();
            $this->itemsQuantity = Cart::getTotalQuantity();
            $this->emit('scan-ok', 'Cantidad actualizada');
    }

    public function clearCart()
    {
        Cart::clear();

        $this->efectivo = 0;
        $this->change = 0;
        $this->total = Cart::getTotal();
        $this->itemsQuantity = Cart::getTotalQuantity();
        $this->emit('scan-ok', 'Carrito vacio');
        $this->resetCustomer();
    }
    //GUARDAR VENTAS
    public function saveSale()
    {
       if($this->total <=0)
       {
            $this->emit('sale-error', 'AGREGA PRODUCTOS A LA VENTA');
            return;
       }
       if($this->efectivo <=0)
       {
            $this->emit('sale-error', 'INGRESA EL EFECTIVO');
            return;
       }
       if($this->total > $this->efectivo)
       {
        $this->emit('sale-error', 'EL EFECTIVO ES MENOR AL TOTAL');
        return;
       }

       if($this->customerId == null)
       {
        $this->emit('sale-error', 'DEBES SELECCIONAR UN CLIENTE');
        return;
       }


       DB::beginTransaction();

       try {
        $sale = Sale::create([
            'total' => $this->total,
            'items' => $this->itemsQuantity,
            'cash' => $this->efectivo,
            'change' => $this->efectivo - $this->total,
            'user_id' => Auth::user()->id,
            'customer_id' => $this->customerId, //0
            'status' => $this->status,
            'payment_type' => $this->payment_type,
            'payment_method' => $this->payment_method,
            'discount' => $this->discount,
            'discount_total' => $this->discount_total
        ]);

        if($sale)
        {
            $items = Cart::getContent();
            foreach($items as $item)
            {
                    SaleDetail::create([
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'sub_total' => $item->price * $item->quantity,
                    'product_id' => $item->id,
                    'sale_id' => $sale->id

                    
                ]);

                //Actualiza el stock
                $product = Product::find($item->id);
                $product->stock -= $item->quantity;
                $product->save();
            }
        }
        
        DB::commit();

        //Limpiar carrito
        Cart::clear();
        $this->efectivo = 0;
        $this->change = 0;
        $this->total = Cart::getTotal();
        $this->itemsQuantity = Cart::getTotalQuantity();
        $this->emit('sale-ok', 'Venta realizada');


        //$this->emit('print-ticket', $sale->id);

        //base64 /pinterapp
        $b64 = $this->jsonData2($sale->id);

       //  METODO 
       //$this->emit('print-json', $b64);
        
       
       //OTRO METODO
       $this->dispatchBrowserEvent('print-json', ['data' => $b64]);


        $this->resetCustomer();


       } catch (Exepction $e) {
        DB::rollBack();
        $this->emit('sale-error', $e->getMessage());
       }
    }

    // public function printTicket($sale)
    // {
    //     return Redirect::to("print://$sale");
    // }

    public function resetCustomer()
    {
        $this->customerId = null;
        $this->customerName = null;
    }

    public function mostrarAdvertenciaStockMinimo($message)
{
    // Aquí puedes manejar la lógica para mostrar un mensaje en la interfaz
    $this->emit('stock-minimo-alerta', $message);
}
    

    
}