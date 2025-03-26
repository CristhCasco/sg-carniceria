<?php

namespace App\Http\Livewire;

use App\Models\Sale;
use App\Models\Product;
use Livewire\Component;
use App\Models\Customer;
use App\Models\SaleDetail;
use App\Models\Denomination;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Traits\JsonTrait;

class PosController extends Component
{

    use JsonTrait;


    public $posCart;
    public $totalCart = 0;
    public $totalItems = 0;
    public $total, $cash, $change, $customers = [], $customerId, $customerName, $status, $payment_type, $payment_method, $discount, $discount_total;

    //ESCUCHAS DE EVENTOS
    protected $listeners = [
        'scan-code' => 'addProduct',
        'removeItem' => 'removeItem',
        'clearCart' => 'clearCart',
        'saveSale' => 'saveSale',
        'refresh' => '$refresh',
        'scan-code-byid' => 'scanCodeById',
        'setCustomer',
        'print-json'
    ];

    public function mount()
    {
        $this->cash = 0;
        $this->change = 0;
        $this->customers = Customer::orderBy('name', 'ASC')->get();
        $this->status = 'PAGADO';
        $this->payment_type = 'CONTADO';
        $this->payment_method = 'EFECTIVO';
        $this->discount = 0;
        $this->discount_total = 0;
        $this->assignOccasionalCustomer();


        if (session()->has("posCart")) {
            $this->posCart = collect(session("posCart"));
        } else {
            $this->posCart = collect([]);
        }

        $this->total = $this->totalCart(); // Obtener el total del carrito
        $this->itemsQuantity = $this->totalItems(); // Obtener la cantidad total de ítems

    }
    

    public function render()
    {
        $this->itemsQuantity = $this->totalItems(); // Obtener la cantidad total de ítems
        $this->total = $this->totalCart(); // Obtener el total del carrito
        $this->posCart = $this->posCart->sortBy('name'); // Ordenar por nombre de forma ascendente

        return view('livewire.pos.component', [
            'denominations' => Denomination::get(),
            'posCart' => $this->posCart
        ])
        ->extends('layouts.theme.app')
        ->section('content');
    }

    public function assignOccasionalCustomer()
    {
        $occasionalCustomerId = 1; // Reemplaza con el ID real del cliente ocasional
        $occasionalCustomer = Customer::find($occasionalCustomerId);

        if ($occasionalCustomer) {
            $this->customerId = $occasionalCustomer->id;
            $this->customerName = $occasionalCustomer->name;
        } else {
            $this->customerId = null; // O asigna un valor predeterminado
            $this->customerName = "Cliente no definido";
            $this->emit('error', 'Cliente ocasional no encontrado en la base de datos');
        }
    }

        public function addProduct($barcode, $quantity = 1)
    {
        // Primero, verifica si el código de barras pertenece a un producto por peso
        if ($this->isWeightedProductBarcode($barcode)) {
            // Intenta procesarlo como un producto por peso
            $this->addWeightProduct($barcode);
        } else {
            // Si no, procesarlo como un producto por unidad
            $this->addUnitProduct($barcode, $quantity);
        }
    }

    //     private function isWeightedProductBarcode($barcode)
    // {
    //     return substr($barcode, 0, 1) == '2'; // Solo filtra los códigos que pueden ser por peso
    // }

    private function isWeightedProductBarcode($barcode)
    {
        // Verifica si el código empieza con "2"
        if (substr($barcode, 0, 1) == '2') {
            // Extrae el código del producto
            $productCode = ltrim(substr($barcode, 1, 6), '0');

            // Verifica si el producto es por peso
            $product = Product::where('barcode', $productCode)->first();

            return $product && $product->is_weighted;
        }

        return false; // Si no empieza con "2", no es por peso
    }




    //     public function addWeightProduct($barcode)
    // {
    //     $barcode = (string) $barcode;

    //     //EXTRAE EL CODIGO DEL PRODUCTO Y PESO
    //     $productCode = ltrim(substr($barcode, 1, 6), '0'); // Código del producto sin ceros a la izquierda
    //     $weight = (float) substr($barcode, 7, 6) / 10000; // Peso en kilogramos
    //     // dd('Código del producto: ' . $productCode, 'Peso: ' . $weight);


    //     //CONSULTA EN LA BASE DE DATOS
    //     $product = Product::where('barcode', $productCode)->first();


    //     if (!$product || !$product->is_weighted) {
    //         $this->emit('scan-notfound', 'El producto no es válido para ventas por peso.');
    //         return;
    //     }

    //     //VERIFICAR STOCK

    //     if ($product->stock < $weight) {
    //         $this->emit('no-stock', 'Stock insuficiente para "' . $product->name . '".');
    //         return;
    //     }

    //     //VERIFICAR SI YA EXISTE EN EL CARRITO
    //     $exist = $this->posCart->firstWhere('id', $product->id);

    //     if ($exist) {
    //         $this->posCart = $this->posCart->map(function ($item) use ($product, $weight) {
    //             if ($item['id'] === $product->id) {
    //                 $item['quantity'] += $weight;
    //                 $item['total'] = $item['quantity'] * $product->price_per_kg;
    //             }
    //             return $item;
    //         });
    //     } else {
    //         $this->posCart->push([
    //             'id' => $product->id,
    //             'barcode' => $product->barcode, // Asegúrate de incluir esta línea
    //             'name' => $product->name,
    //             'quantity' => $weight,
    //             'price' => $product->price_per_kg,
    //             // 'total' => $weight * $product->price_per_kg,
    //             'total' => round($weight * $product->price_per_kg, 2), // Redondear subtotal
    //         ]);

    //         dd([
    //             'peso' => $weight,
    //             'precio_por_kg' => $product->price_per_kg,
    //             'subtotal_calculado' => $weight * $product->price_per_kg,
    //             'subtotal_redondeado' => round($weight * $product->price_per_kg, 2)
    //         ]);
            
            
    //         // $total = $weight * $product->price_per_kg;
    //         // dd($weight, $product->price_per_kg, $total);
            
    //     }

    //     //dd($weight);
    //     //dd($product->price_per_kg); // Verifica el precio por kilogramo
    //     //dd($this->posCart->toArray());

    //     $this->updateCart();
    //     $this->emit('scan-ok', $exist ? 'Cantidad actualizada' : 'Producto agregado al carrito por peso.');
    // }

    public function addWeightProduct($barcode)
    {
        $barcode = (string) $barcode;

        // Extraer el código del producto y el peso
        $productCode = ltrim(substr($barcode, 1, 6), '0');
       //$weight = (float) substr($barcode, 7, 6) / 10000;
       //2 VECES SUBSTRIN PARA ELIMNAR EL ULTIMO DIGITO
        //$weight = (float)substr(substr($barcode, 7, 6), 0, -1) / 1000;
        //REDONDEO A 3 DECIMALES
        $weight = round((float)substr($barcode, 7, 6) / 10000, 3); // Redondeo a 3 decimales



        // Consultar el producto en la base de datos
        $product = Product::where('barcode', $productCode)->first();

        if (!$product || !$product->is_weighted) {
            $this->emit('scan-notfound', 'El producto no es válido para ventas por peso.');
            return;
        }

        if ($product->stock < $weight) {
            $this->emit('no-stock', 'Stock insuficiente para "' . $product->name . '".');
            return;
        }

        // Asegurar precisión en el precio
        $pricePerKg = round($product->price_per_kg, 2);
        $subtotal = round($weight * $pricePerKg, 2);

        // Verificar si ya existe en el carrito
        $exist = $this->posCart->firstWhere('id', $product->id);

        if ($exist) {
            $this->posCart = $this->posCart->map(function ($item) use ($product, $weight, $subtotal) {
                if ($item['id'] === $product->id) {
                    $item['quantity'] += $weight;
                    $item['total'] = round($item['quantity'] * $product->price_per_kg, 2);
                }
                return $item;
            });
        } else {
            $this->posCart->push([
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'quantity' => $weight,
                'price' => $pricePerKg,
                'total' => $subtotal,
                'w' => $product->is_weighted
            ]);
        }

        // dd([
        //     'peso' => $weight,
        //     'precio_por_kg' => $pricePerKg,
        //     'subtotal' => $subtotal,
        // ]);
        

        $this->updateCart();
        $this->emit('scan-ok', $exist ? 'Cantidad actualizada' : 'Producto agregado al carrito por peso.');
    }


    // private function truncateDecimal($value, $decimals)
    // {
    //     $factor = pow(10, $decimals);
    //     return floor($value * $factor) / $factor;
    // }




    public function addUnitProduct($barcode, $quantity = 1)
    {
        $product = Product::where('barcode', $barcode)->first();
    
        if (!$product) {
            $this->emit('scan-notfound', 'El producto no está registrado');
            return;
        }
    
        if ($product->stock < $quantity) {
            $this->emit('no-stock', 'Stock insuficiente');
            return;
        }
    
        $exist = $this->posCart->firstWhere('id', $product->id);
    
        if ($exist) {
            // Si ya existe, aumenta la cantidad
            $this->increaseQty($product->id, $quantity);
            return;
        }
        
    
        // Agrega un nuevo producto al carrito
        $this->posCart->push([
            'id' => $product->id,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'quantity' => $quantity,
            'price' => $product->price,
            'total' => $product->price * $quantity,
            'w' => $product->is_weighted
        ]);
    
        $this->updateCart();
        $this->emit('scan-ok', 'Producto agregado');
    }

    public function updateCart()
{
    // Guarda el carrito en sesión
    session(['posCart' => $this->posCart]);

    // Actualiza el total y la cantidad de ítems
    $this->total = $this->posCart->sum(function ($item) {
        return $item['price'] * $item['quantity'];
    });

    $this->itemsQuantity = $this->posCart->sum('quantity');
}

    public function inCart($product_id)
    {
        return $this->posCart->where('id', $product_id)->count() > 0;
    }

    public function updateQuantity($product_id, $quantity)
    {
        if (!is_numeric($quantity)) {
            $this->emit('noty', 'EL VALOR DE LA CANTIDAD ES INCORRECTO');
            return;
        }

        $item = $this->posCart->where('id', $product_id)->first();
        $product = Product::find($product_id);

        if ($product->stock <= 0) {
            $this->emit('no-stock', 'Stock insuficiente');
            return;
        }

        if ($item) {
            if ($quantity > $product->stock) {
                $this->emit('no-stock', 'Stock insuficiente');
                return;
            }

            if ($quantity <= 0) {
                $this->removeItem($product_id);
                return;
            }

            $item['quantity'] = $quantity;
            $item['total'] = $item['price'] * $quantity;

            $this->posCart = $this->posCart->map(function ($cartItem) use ($item) {
                return $cartItem['id'] === $item['id'] ? $item : $cartItem;
            });

            $this->save();
            $this->emit('scan-ok', 'Cantidad actualizada');
        }
    }

    public function removeItem($product_id)
    {
        $this->posCart = $this->posCart->reject(function ($item) use ($product_id) {
            return $item['id'] === $product_id;
        });

        $this->save();
        $this->emit('scan-ok', 'Producto eliminado');
    }


    public function clearCart()
    {
        $this->posCart = collect([]);
        $this->save();
        $this->emit('scan-ok', 'Carrito vaciado');
    }



    public function save()
    {
        session()->put("posCart", $this->posCart);
        session()->save();
        //Log::info('Carrito guardado en la sesión');
        //Log::info(json_encode($this->posCart));
    }

    public function totalCart()
    {
        if ($this->posCart === null) {
            $this->posCart = collect([]);
        }

        return $this->posCart->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });
    }

    public function totalItems()
    {
        if ($this->posCart === null) {
            $this->posCart = collect([]);
        }

        return $this->posCart->count('quantity');
        //return $this->posCart->sum('quantity');
    }

    //GUARDAR VENTAS
    public function saveSale()
    {

       if($this->total <=0)
       {
            $this->emit('sale-error', 'AGREGA PRODUCTOS A LA VENTA');
            return;
       }
       if($this->cash <=0)
       {
            $this->emit('sale-error', 'INGRESA EL EFECTIVO');
            return;
       }
       if($this->total > $this->cash)
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

            // Determinar el estado de la venta basado en el tipo de pago
            $saleStatus = $this->payment_type == 'CREDITO' ? 'PENDIENTE' : 'PAGADO';

        $sale = Sale::create([
            'total' => $this->total,
            'items' => $this->itemsQuantity,
            'cash' => $this->cash,
            'change' => $this->cash - $this->total,
            'user_id' => Auth::user()->id,
            'customer_id' => $this->customerId, //0
            'status' => $saleStatus, // Aplicando la lógica aquí
            'payment_type' => $this->payment_type,
            'payment_method' => $this->payment_method,
            'discount' => $this->discount,
            'discount_total' => $this->discount_total
        ]);

        if($sale)
        {
            $items = $this->posCart;
            foreach($items as $item)
            {
                SaleDetail::create([
                    'price' => $item['price'], // Acceder a los elementos del array utilizando la sintaxis de array
                    'quantity' => $item['quantity'],
                    'sub_total' => $item['price'] * $item['quantity'],
                    'product_id' => $item['id'],
                    'sale_id' => $sale->id
                ]);

                //Actualiza el stock
                $product = Product::find($item['id']);
                $product->stock -= $item['quantity'];
                $product->save();
            }
        }
        
        DB::commit();

        //Limpiar carrito
        // $this->clearCart();
        // $this->cash = 0;
        // $this->change = 0;
        // $this->payment_type = 'CONTADO'; // Restablecer a 'CONTADO'
        // $this->total = $this->totalCart();
        // $this->itemsQuantity = $this->totalItems();
        // $this->emit('sale-ok', 'Venta realizada');
        $this->completeSale();


        //$this->emit('print-ticket', $sale->id);

        //base64 /pinterapp
        $b64 = $this->jsonData2($sale->id);

       //  METODO 
       //$this->emit('print-json', $b64);
        
       
       //OTRO METODO
       $this->dispatchBrowserEvent('print-json', ['data' => $b64]);


        //$this->resetCustomer();


       } catch (Exepction $e) {
        DB::rollBack();
        $this->emit('sale-error', $e->getMessage());
       }

       $this->assignOccasionalCustomer();
    }

    public function completeSale()
    {
        // Limpiar carrito
        $this->clearCart();
        $this->cash = 0;
        $this->change = 0;
        $this->total = $this->totalCart();
        $this->itemsQuantity = $this->totalItems();
        $this->payment_type = 'CONTADO'; // Restablecer a 'CONTADO'
        $this->emit('sale-ok', 'Venta realizada');
    }

    public function setCustomer($customerId, $customerName)
    {
        $this->customerId = $customerId;
        $this->customerName = $customerName;
    }

    public function ACash($value)
    {
        $this->cash +=  ($value == 0 ? $this->total : $value);
        $this->change = ($this->cash - $this->total);
    }

    public function resetCustomer()
    {
        $this->customerId = null;
        $this->customerName = null;
    }

    public function scanCodeById($productId)
    {
        $this->increaseQty($productId);
    }



    public function increaseQty($productId, $cant = 1)
    {
        if (!$productId) {
            $this->emit('no-stock', 'ID de producto no válido');
            return;
        }
    
        $product = Product::find($productId);
        if (!$product) {
            $this->emit('no-stock', 'Producto no encontrado');
            return;
        }
    
        $exist = $this->posCart->firstWhere('id', $productId);
    
        if ($exist) {
            if ($product->stock < ($cant + $exist['quantity'])) {
                $this->emit('no-stock', 'Stock insuficiente para "' . $product->name . '"');
                return;
            }
    
            // Actualiza la cantidad en el carrito
            $exist['quantity'] += $cant;
            $exist['total'] = $exist['quantity'] * $exist['price'];
    
            $this->posCart = $this->posCart->map(function ($item) use ($exist) {
                return $item['id'] === $exist['id'] ? $exist : $item;
            });
        } else {
            if ($product->stock < $cant) {
                $this->emit('no-stock', 'Stock insuficiente para "' . $product->name . '"');
                return;
            }
    
            // Agrega el producto al carrito si no existe
            $this->posCart->push([
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'quantity' => $cant,
                'price' => $product->price,
                'total' => $product->price * $cant,
            ]);
        }
    
        $this->updateCart();
        $this->emit('scan-ok', $exist ? 'Cantidad actualizada' : 'Producto agregado al carrito');
    }




public function decreaseQty($productId)
{
    // Buscar el producto en el carrito
    $item = $this->posCart->firstWhere('id', $productId);

    if ($item) {
        // Eliminar el producto del carrito
        $this->posCart = $this->posCart->reject(function ($cartItem) use ($productId) {
            return $cartItem['id'] === $productId;
        });

        // Calcular la nueva cantidad
        $newQty = $item['quantity'] - 1;

        // Si la nueva cantidad es mayor que 0, agregar el producto de nuevo al carrito con la nueva cantidad
        if ($newQty > 0) {
            $item['quantity'] = $newQty;
            $item['total'] = $item['price'] * $newQty;
            $this->posCart->push($item);
        }

        // Guardar el carrito en la sesión
        $this->save();

        // Actualizar el total y la cantidad de ítems en el carrito
        $this->total = $this->totalCart();
        $this->itemsQuantity = $this->totalItems();

        // Emitir evento
        $this->emit('scan-ok', 'Cantidad actualizada');
    } else {
        $this->emit('scan-notfound', 'El producto no está en el carrito');
    }
}



}